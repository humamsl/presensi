<?php
/*
 * ============================================================================
 *  API ADMS / PUSH SDK (ZKTeco) — penanganan bersama
 * ----------------------------------------------------------------------------
 *  Mesin absensi yang menghubungi server (bukan sebaliknya). Berkas ini dipakai
 *  oleh DUA pintu masuk, keduanya berlaku sama:
 *
 *    1) /iclock/...        jalur baku ZKTeco (dipakai firmware yang otomatis
 *                          menambahkan /iclock/cdata sendiri)
 *    2) /adms/index.php    jalur eksplisit (dipakai firmware yang alamat
 *                          servernya diisi lengkap sampai nama berkas)
 *
 *  Aksi ditentukan dari jalur (cdata / getrequest / devicecmd / ping). Bila
 *  mesin memanggil /adms/index.php tanpa jalur tambahan, aksinya disimpulkan
 *  dari metode & parameter — lihat admsDeteksiAksi().
 *
 *  Aksi yang dikenali:
 *    GET  ...?SN=..&options=all    -> handshake, server membalas konfigurasi
 *    POST ...?SN=..&table=ATTLOG   -> kiriman data absensi
 *    POST ...?SN=..&table=OPERLOG  -> log operasi / data user (diterima saja)
 *    GET  ...getrequest?SN=..      -> mesin meminta perintah dari server
 *    POST ...devicecmd?SN=..       -> mesin melaporkan hasil perintah
 *    GET  ...ping                  -> cek hidup
 *
 *  Balasan WAJIB text/plain. Untuk ATTLOG mesin menghapus data lokalnya setelah
 *  menerima "OK", jadi jangan balas OK bila data gagal disimpan.
 * ============================================================================
 */
define('TANPA_SESSION', true);           // mesin polling tiap beberapa detik
require_once __DIR__ . '/../config.php';

header('Content-Type: text/plain; charset=utf-8');

$sn     = trim($_GET['SN'] ?? $_GET['sn'] ?? '');
$ip     = $_SERVER['REMOTE_ADDR'] ?? null;
$metode = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$jalur  = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';

/**
 * Tentukan aksi dari jalur URL; bila tidak ada petunjuk di jalur, simpulkan
 * dari metode & parameter (kasus mesin yang menembak /adms/index.php langsung).
 */
function admsDeteksiAksi(string $jalur, string $metode, array $get): string {
    // Nama aksi bisa muncul di mana saja pada jalur, dengan atau tanpa ekstensi:
    //   /iclock/cdata          /iclock/cdata.aspx
    //   /adms/index.php/iclock/cdata      /adms/cdata
    if (preg_match('#(cdata|getrequest|devicecmd|ping|fdata|querydata)#i', $jalur, $m)) {
        return strtolower($m[1]);
    }
    // Tanpa petunjuk jalur: tebak dari bentuk permintaan.
    if ($metode === 'POST') {
        // Kiriman data selalu menyertakan nama tabel; selain itu laporan perintah.
        return isset($get['table']) ? 'cdata' : 'devicecmd';
    }
    // GET dengan options=all adalah handshake; GET polos adalah ambil perintah.
    return isset($get['options']) ? 'cdata' : 'getrequest';
}

$aksi = admsDeteksiAksi($jalur, $metode, $_GET);

/** Mesin terdaftar (dicocokkan lewat serial number). */
function cariMesin(PDO $pdo, string $sn): ?array {
    if ($sn === '') return null;
    $st = $pdo->prepare('SELECT * FROM mesin_absensi WHERE serial_number = ?');
    $st->execute([$sn]);
    return $st->fetch() ?: null;
}

function catatLog(PDO $pdo, array $d): void {
    $pdo->prepare('INSERT INTO adms_log (sn, endpoint, tabel, jumlah, disimpan, gagal, sn_dikenal, ip)
                   VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$d['sn'] ?: null, $d['endpoint'], $d['tabel'] ?? null, $d['jumlah'] ?? 0,
                   $d['disimpan'] ?? 0, $d['gagal'] ?? 0, $d['sn_dikenal'] ?? 1, $d['ip']]);
}

$mesin = cariMesin($pdo, $sn);
if ($mesin) {
    $pdo->prepare('UPDATE mesin_absensi SET last_online = NOW() WHERE id = ?')->execute([$mesin['id']]);
}
// SN asing: default tetap diterima & ditandai, supaya data absensi tidak hilang
// hanya karena serial number belum didaftarkan (lihat ADMS_SN_KETAT di config).
if (!$mesin && ADMS_SN_KETAT) {
    catatLog($pdo, ['sn'=>$sn, 'endpoint'=>$aksi, 'ip'=>$ip, 'sn_dikenal'=>0]);
    http_response_code(401);
    echo "Unauthorized device\n";
    exit;
}

switch ($aksi) {

    // ---- Handshake / kiriman data ------------------------------------------
    case 'cdata':
        if ($metode === 'GET') {
            catatLog($pdo, ['sn'=>$sn, 'endpoint'=>'cdata(handshake)', 'ip'=>$ip, 'sn_dikenal'=>$mesin?1:0]);
            $stamp = time();
            echo "GET OPTION FROM: $sn\r\n"
               . "Stamp=$stamp\r\n"
               . "OpStamp=$stamp\r\n"
               . "ErrorDelay=30\r\n"        // jeda coba lagi saat gagal (detik)
               . "Delay=10\r\n"             // jeda polling getrequest (detik)
               . "TransTimes=00:00;12:00\r\n"
               . "TransInterval=1\r\n"      // kirim tiap 1 menit bila ada data
               . "TransFlag=1111000000\r\n" // AttLog, OpLog, AttPhoto, EnrollUser
               . "TimeZone=7\r\n"           // WIB
               . "Realtime=1\r\n"           // kirim begitu ada scan
               . "Encrypt=0\r\n";
            exit;
        }

        $tabel = strtoupper(trim($_GET['table'] ?? ''));
        $isi   = file_get_contents('php://input') ?: '';
        $baris = preg_split('/\r\n|\n|\r/', trim($isi), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($tabel !== 'ATTLOG') {
            // OPERLOG dsb belum diproses — cukup diterima agar mesin tidak mengulang.
            catatLog($pdo, ['sn'=>$sn, 'endpoint'=>'cdata', 'tabel'=>$tabel ?: 'LAIN',
                            'jumlah'=>count($baris), 'ip'=>$ip, 'sn_dikenal'=>$mesin?1:0]);
            echo "OK\r\n";
            exit;
        }

        // ATTLOG: PIN \t waktu \t status \t verify \t workcode ...
        $simpanScan = $pdo->prepare(
            'INSERT INTO adms_scan (sn, pin, waktu, status_mesin, verify, tipe, nomor_induk, diproses)
             VALUES (?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE tipe=VALUES(tipe), nomor_induk=VALUES(nomor_induk), diproses=VALUES(diproses)');

        $disimpan = 0; $gagal = 0; $petaCache = [];
        foreach ($baris as $b) {
            $f = preg_split('/\t+/', trim($b));
            if (count($f) < 2) { $gagal++; continue; }

            $pin    = trim($f[0]);
            $waktu  = trim($f[1]);
            $status = isset($f[2]) && $f[2] !== '' ? (int)$f[2] : 0;
            $verify = isset($f[3]) && $f[3] !== '' ? (int)$f[3] : null;

            $ts = strtotime($waktu);
            if ($pin === '' || !$ts) { $gagal++; continue; }
            $waktu = date('Y-m-d H:i:s', $ts);

            // Satu baris bermasalah tidak boleh menggagalkan seluruh kiriman: bila
            // gagal, mesin tidak menerima "OK" dan akan mengulang tanpa henti.
            try {
                if (!array_key_exists($pin, $petaCache)) {
                    $petaCache[$pin] = admsPetakanPin($pdo, $dc, $pin);
                }
                $orang = $petaCache[$pin];

                $ditulis = false;
                if ($orang) {
                    $ditulis = admsTulisAbsensi($pdo, $orang['tipe'], $orang['nomor_induk'], $waktu, $status);
                } else {
                    $gagal++;   // PIN tidak dikenal: data mentah tetap disimpan
                }

                $simpanScan->execute([$sn ?: null, $pin, $waktu, $status, $verify,
                                      $orang['tipe'] ?? null, $orang['nomor_induk'] ?? null, $ditulis ? 1 : 0]);
                $disimpan++;
            } catch (Throwable $ex) {
                $gagal++;
                error_log('ADMS ATTLOG gagal (PIN ' . $pin . ' ' . $waktu . '): ' . $ex->getMessage());
            }
        }

        catatLog($pdo, ['sn'=>$sn, 'endpoint'=>'cdata', 'tabel'=>'ATTLOG', 'jumlah'=>count($baris),
                        'disimpan'=>$disimpan, 'gagal'=>$gagal, 'ip'=>$ip, 'sn_dikenal'=>$mesin?1:0]);
        echo "OK: $disimpan\r\n";
        exit;

    // ---- Mesin meminta perintah dari server ---------------------------------
    case 'getrequest':
        $st = $pdo->prepare("SELECT id, perintah FROM adms_perintah
                             WHERE sn = ? AND status = 'antre' ORDER BY id LIMIT 10");
        $st->execute([$sn]);
        $antre = $st->fetchAll();
        if (!$antre) { echo "OK\r\n"; exit; }

        $tandai = $pdo->prepare("UPDATE adms_perintah SET status='terkirim', dikirim=NOW() WHERE id=?");
        $keluar = '';
        foreach ($antre as $c) {
            $keluar .= 'C:' . $c['id'] . ':' . $c['perintah'] . "\r\n";
            $tandai->execute([$c['id']]);
        }
        catatLog($pdo, ['sn'=>$sn, 'endpoint'=>'getrequest', 'jumlah'=>count($antre),
                        'ip'=>$ip, 'sn_dikenal'=>$mesin?1:0]);
        echo $keluar;
        exit;

    // ---- Mesin melaporkan hasil perintah ------------------------------------
    case 'devicecmd':
        $isi = file_get_contents('php://input') ?: '';
        parse_str(str_replace(["\r\n", "\n"], '&', trim($isi)), $data);
        if (!empty($data['ID'])) {
            $pdo->prepare("UPDATE adms_perintah SET status='selesai', hasil=? WHERE id=?")
                ->execute([substr((string)($data['Return'] ?? ''), 0, 100), (int)$data['ID']]);
        }
        catatLog($pdo, ['sn'=>$sn, 'endpoint'=>'devicecmd', 'ip'=>$ip, 'sn_dikenal'=>$mesin?1:0]);
        echo "OK\r\n";
        exit;

    case 'ping':
        echo "OK\r\n";
        exit;

    default:
        // Aksi tak dikenal tetap dicatat supaya terlihat di Monitor ADMS —
        // sangat membantu bila firmware mesin memakai nama jalur yang berbeda.
        catatLog($pdo, ['sn'=>$sn, 'endpoint'=>substr('? ' . $jalur, 0, 30), 'ip'=>$ip, 'sn_dikenal'=>$mesin?1:0]);
        http_response_code(404);
        echo "Not found\r\n";
        exit;
}

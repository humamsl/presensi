<?php
// Pastikan session punya folder yang writable. Di sebagian hosting,
// session.save_path bawaan server tidak ada / tidak bisa ditulis sehingga
// login "berhasil" tapi session hilang. Cari folder pertama yang writable:
//   1) temp sistem (di luar web root, paling aman)
//   2) folder ./sessions di dalam aplikasi (fallback)
foreach ([sys_get_temp_dir() . '/presensi_sessions', __DIR__ . '/sessions'] as $sessionDir) {
    if (!is_dir($sessionDir)) { @mkdir($sessionDir, 0700, true); }
    if (is_dir($sessionDir) && is_writable($sessionDir)) {
        session_save_path($sessionDir);
        break;
    }
}
session_start();
date_default_timezone_set('Asia/Jakarta');

/*
 * ============================================================================
 *  KONSEP 2 KONEKSI DATABASE
 * ----------------------------------------------------------------------------
 *  Koneksi 1 ($pdo) -> absensi_sekolah : data ABSENSI & konfigurasi aplikasi
 *                                        (absensi_siswa, absensi_guru, jadwal,
 *                                        mesin, shift, hari libur, users).
 *  Koneksi 2 ($dc)  -> datacenter_v2   : data MASTER (dibaca langsung / live):
 *                                        - siswa   = tabel siswa
 *                                        - guru    = tabel guru
 *                                        - jabatan = kolom guru.jabatan
 *                                        - kelas   = tabel rombongan_belajar
 *
 *  Data master TIDAK disalin/di-sync ke database absensi. Semua halaman
 *  membaca siswa/guru/jabatan/kelas langsung dari datacenter melalui $dc.
 *  Tabel absensi menyimpan id datacenter:
 *    absensi_siswa.siswa_id      -> datacenter_v2.siswa.id
 *    absensi_guru.guru_id        -> datacenter_v2.guru.id
 *    jadwal_shift_guru.guru_id   -> datacenter_v2.guru.id
 * ============================================================================
 */

// ---- Koneksi 1: Database Absensi ----
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'presensi');
define('DB_USER', 'root');
define('DB_PASS', '');

// ---- Koneksi 2: Database Datacenter (sumber data master) ----
define('DC_HOST', '127.0.0.1');
define('DC_PORT', '3306');
define('DC_NAME', 'datacenter_v2');
define('DC_USER', 'root');
define('DC_PASS', '');

function connectPDO(string $host, string $port, string $name, string $user, string $pass, string $label): PDO {
    try {
        return new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        die("Koneksi database $label gagal: " . $e->getMessage());
    }
}

$pdo = connectPDO(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, 'presensi (' . DB_NAME . ')');
$dc  = connectPDO(DC_HOST, DC_PORT, DC_NAME, DC_USER, DC_PASS, 'datacenter_v2 (' . DC_NAME . ')');

// ============================================================================
//  Helper data master (dari datacenter, via $dc)
// ============================================================================

/** Tahun ajaran aktif di datacenter. Return ['id'=>, 'nama_tahun_ajaran'=>] atau null. */
function tahunAjaranAktif(PDO $dc): ?array {
    return $dc->query("SELECT id, nama_tahun_ajaran FROM tahun_ajaran WHERE is_aktif=1 LIMIT 1")->fetch() ?: null;
}

/** Daftar tingkat (kelas) unik pada tahun ajaran aktif, mis. [7,8,9]. */
function dcTingkatList(PDO $dc, int $taId): array {
    $st = $dc->prepare("SELECT DISTINCT tingkat FROM rombongan_belajar WHERE tahun_ajaran_id=? ORDER BY tingkat");
    $st->execute([$taId]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/** Daftar kelas (rombongan belajar) pada tahun ajaran aktif. */
function dcKelasList(PDO $dc, int $taId): array {
    $st = $dc->prepare("SELECT id, nama_rombel AS nama, tingkat
                        FROM rombongan_belajar WHERE tahun_ajaran_id=?
                        ORDER BY tingkat, nama_rombel");
    $st->execute([$taId]);
    return $st->fetchAll();
}

/** Satu kelas by id, dibatasi tahun ajaran aktif. */
function dcKelas(PDO $dc, int $taId, int $id): ?array {
    $st = $dc->prepare("SELECT id, nama_rombel AS nama, tingkat
                        FROM rombongan_belajar WHERE id=? AND tahun_ajaran_id=?");
    $st->execute([$id, $taId]);
    return $st->fetch() ?: null;
}

/** Roster siswa aktif pada tahun ajaran aktif (opsional filter kelas/rombel). */
function dcSiswaList(PDO $dc, int $taId, int $kelasId = 0): array {
    $sql = "SELECT s.id, COALESCE(NULLIF(s.nis,''), s.nisn) AS nis, s.nama_siswa AS nama,
                   s.jenis_kelamin AS jk, rb.id AS kelas_id, rb.nama_rombel AS kelas
            FROM siswa s
            JOIN siswa_rombel sr ON sr.siswa_id = s.id AND sr.tahun_ajaran_id = ?
            JOIN rombongan_belajar rb ON rb.id = sr.rombongan_belajar_id
            WHERE s.is_aktif = 1 AND s.status_siswa = 'Aktif'";
    $args = [$taId];
    if ($kelasId) { $sql .= " AND rb.id = ?"; $args[] = $kelasId; }
    $sql .= " ORDER BY rb.nama_rombel, s.nama_siswa";
    $st = $dc->prepare($sql);
    $st->execute($args);
    return $st->fetchAll();
}

/** Satu siswa by id, beserta kelas pada tahun ajaran aktif (kelas bisa null bila belum ditempatkan). */
function dcSiswa(PDO $dc, int $taId, int $id): ?array {
    $st = $dc->prepare("SELECT s.id, COALESCE(NULLIF(s.nis,''), s.nisn) AS nis, s.nama_siswa AS nama,
                               s.jenis_kelamin AS jk, rb.id AS kelas_id, rb.nama_rombel AS kelas
                        FROM siswa s
                        LEFT JOIN siswa_rombel sr ON sr.siswa_id = s.id AND sr.tahun_ajaran_id = ?
                        LEFT JOIN rombongan_belajar rb ON rb.id = sr.rombongan_belajar_id
                        WHERE s.id = ?");
    $st->execute([$taId, $id]);
    return $st->fetch() ?: null;
}

/** Daftar guru aktif (opsional filter berdasarkan nama jabatan). */
function dcGuruList(PDO $dc, string $jabatan = ''): array {
    $sql = "SELECT id, nip, nama_ptk AS nama, jenis_kelamin AS jk,
                   COALESCE(NULLIF(TRIM(jabatan),''),'Guru') AS jabatan
            FROM guru WHERE is_aktif = 1";
    $args = [];
    if ($jabatan !== '') { $sql .= " AND COALESCE(NULLIF(TRIM(jabatan),''),'Guru') = ?"; $args[] = $jabatan; }
    $sql .= " ORDER BY nama_ptk";
    $st = $dc->prepare($sql);
    $st->execute($args);
    return $st->fetchAll();
}

/** Satu guru by id. */
function dcGuru(PDO $dc, int $id): ?array {
    $st = $dc->prepare("SELECT id, nip, nama_ptk AS nama, jenis_kelamin AS jk,
                               COALESCE(NULLIF(TRIM(jabatan),''),'Guru') AS jabatan
                        FROM guru WHERE id = ?");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** Daftar nama jabatan unik dari guru aktif (jabatan = kolom teks di datacenter). */
function dcJabatanList(PDO $dc): array {
    return $dc->query("SELECT DISTINCT COALESCE(NULLIF(TRIM(jabatan),''),'Guru') AS nama
                       FROM guru WHERE is_aktif = 1 ORDER BY nama")->fetchAll(PDO::FETCH_COLUMN);
}

// ============================================================================
//  Helper agregasi absensi (dari database absensi, via $pdo)
//  $table & $col dikontrol kode (bukan input user) -> aman untuk interpolasi.
// ============================================================================

/** Rekap jumlah tiap status per orang dalam periode. Return [id => ['hadir'=>n, ...]]. */
function absensiRekap(PDO $pdo, string $table, string $col, array $ids, string $dari, string $sampai): array {
    $out = [];
    if (!$ids) return $out;
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT $col AS pid, status, COUNT(*) c
                         FROM $table WHERE $col IN ($in) AND tanggal BETWEEN ? AND ?
                         GROUP BY $col, status");
    $st->execute([...$ids, $dari, $sampai]);
    foreach ($st as $r) $out[$r['pid']][$r['status']] = (int)$r['c'];
    return $out;
}

/** Baris absensi per orang untuk satu tanggal. Return [id => row]. */
function absensiByDate(PDO $pdo, string $table, string $col, array $ids, string $tanggal): array {
    $out = [];
    if (!$ids) return $out;
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT * FROM $table WHERE $col IN ($in) AND tanggal = ?");
    $st->execute([...$ids, $tanggal]);
    foreach ($st as $r) $out[$r[$col]] = $r;
    return $out;
}

/**
 * Laporan absensi harian satu orang untuk SETIAP tanggal dalam rentang.
 * Status tiap tanggal ditentukan dari setting jadwal PER HARI (jadwal_absensi)
 * digabung catatan absensi & hari libur khusus (hari_libur):
 *   - izin / sakit                          -> sesuai catatan
 *   - ada jam masuk                         -> 'hadir' jika <= batas_terlambat hari itu, selain itu 'terlambat'
 *   - tanggal libur khusus (hari_libur)     -> 'libur'
 *   - hari terjadwal libur (mis. akhir pekan) -> 'libur' (tidak dihitung tidak hadir)
 *   - hari sekolah tanpa catatan             -> 'alpha' (Tidak Hadir)
 *
 * @return array{rows: array<int,array>, rekap: array<string,int>}
 */
function laporanHarian(PDO $pdo, string $table, string $col, int $id, string $dari, string $sampai): array {
    $rekap = ['hadir'=>0,'terlambat'=>0,'izin'=>0,'sakit'=>0,'alpha'=>0,'libur'=>0];
    if (strtotime($dari) > strtotime($sampai)) return ['rows'=>[], 'rekap'=>$rekap];

    // Setting jadwal PER HARI (1=Senin..7=Minggu) sesuai tipe
    $tipe = $table === 'absensi_guru' ? 'guru' : 'siswa';
    $jadwalHari = [];
    $js = $pdo->prepare('SELECT hari, jam_masuk, batas_terlambat, libur FROM jadwal_absensi WHERE tipe=?');
    $js->execute([$tipe]);
    foreach ($js as $jr) $jadwalHari[(int)$jr['hari']] = $jr;

    // Hari libur (rentang di-expand) yang beririsan dengan periode
    $libur = [];
    $hl = $pdo->prepare("SELECT tanggal, tanggal_selesai, keterangan FROM hari_libur
                         WHERE tanggal <= ? AND COALESCE(tanggal_selesai, tanggal) >= ?");
    $hl->execute([$sampai, $dari]);
    foreach ($hl as $h) {
        $end = $h['tanggal_selesai'] ?: $h['tanggal'];
        for ($d = strtotime($h['tanggal']); $d <= strtotime($end); $d = strtotime('+1 day', $d)) {
            $libur[date('Y-m-d', $d)] = $h['keterangan'];
        }
    }

    // Catatan absensi dalam periode
    $rec = [];
    $st = $pdo->prepare("SELECT * FROM $table WHERE $col=? AND tanggal BETWEEN ? AND ?");
    $st->execute([$id, $dari, $sampai]);
    foreach ($st as $r) $rec[$r['tanggal']] = $r;

    // Iterasi tiap tanggal dalam rentang
    $rows = [];
    for ($d = strtotime($dari); $d <= strtotime($sampai); $d = strtotime('+1 day', $d)) {
        $tgl = date('Y-m-d', $d);
        $r = $rec[$tgl] ?? null;
        $jh = $jadwalHari[(int)date('N', $d)] ?? null;   // jadwal hari ybs
        // Hari non-sekolah: ditandai libur di jadwal, atau tak punya jam masuk terjadwal
        $hariLibur = !$jh || (int)$jh['libur'] === 1 || empty($jh['jam_masuk']);
        $batas = ($jh && $jh['batas_terlambat']) ? $jh['batas_terlambat'] : '07:00:00';
        $isHoliday = isset($libur[$tgl]);          // tanggal libur khusus (hari_libur)
        $jamMasuk = $r['jam_masuk'] ?? null;
        $ket = $r['keterangan'] ?? null;

        if ($r && ($r['status'] === 'izin' || $r['status'] === 'sakit')) {
            $status = $r['status'];
        } elseif ($jamMasuk) {
            $status = ($jamMasuk <= $batas) ? 'hadir' : 'terlambat';
        } elseif ($r && $r['status'] !== 'alpha') {
            $status = $r['status'];                 // status manual tanpa jam (mis. hadir)
        } elseif ($isHoliday) {
            $status = 'libur';
            $ket = $ket ?: $libur[$tgl];            // nama hari libur khusus
        } elseif ($hariLibur) {
            $status = 'libur';                      // hari terjadwal tanpa jam sekolah
        } else {
            $status = 'alpha';                      // hari sekolah tanpa catatan
        }

        $rekap[$status] = ($rekap[$status] ?? 0) + 1;
        $rows[] = [
            'tanggal'   => $tgl,
            'jam_masuk' => $jamMasuk,
            'jam_pulang'=> $r['jam_pulang'] ?? null,
            'status'    => $status,
            'keterangan'=> $ket,
        ];
    }
    return ['rows'=>$rows, 'rekap'=>$rekap];
}

// ============================================================================

function requireLogin(): void {
    if (empty($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
}

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$HARI_ID = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
$STATUS_LIST = ['hadir' => 'Hadir', 'terlambat' => 'Terlambat', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpha' => 'Tidak Hadir'];
// Laporan harian (termasuk "Libur"). Warna dipakai sebagai kelas badge Bootstrap.
$STATUS_DETAIL = $STATUS_LIST + ['libur' => 'Libur'];
$STATUS_WARNA  = ['hadir'=>'success', 'terlambat'=>'warning text-dark', 'izin'=>'info', 'sakit'=>'primary', 'alpha'=>'danger', 'libur'=>'secondary'];

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
 *  Tabel absensi merujuk data datacenter tanpa FK lintas-database:
 *    absensi_siswa.nis           -> datacenter_v2.siswa.nis (fallback nisn)
 *    absensi_guru.nip            -> datacenter_v2.guru.nip
 *    jadwal_shift_guru.guru_id   -> datacenter_v2.guru.id
 * ============================================================================
 */

// ---- Koneksi 1: Database Absensi ----
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'absensi_sekolah');
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
//  KODE STATUS ABSENSI  (kolom status pada absensi_siswa & absensi_guru)
// ----------------------------------------------------------------------------
//  KEDUA tabel berbentuk LOG EVENT dengan struktur yang sama:
//    absensi_siswa : id, nis, tanggal, jam, status, keterangan
//    absensi_guru  : id, nip, tanggal, jam, status, keterangan
//
//      0 = masuk       -> kolom jam berisi jam scan masuk
//      1 = pulang      -> kolom jam berisi jam scan pulang
//      2 = sakit        3 = ijin        4 = alpha
//      5 = dinas luar   6 = cuti                    (kolom jam boleh NULL)
//
//  Satu orang pada satu tanggal bisa punya beberapa baris (mis. masuk + pulang).
//  Status laporan "hadir"/"terlambat" TIDAK disimpan — dihitung dari jam masuk
//  terhadap batas terlambat pada setting jadwal hari yang bersangkutan.
//  Kode 5 & 6 dipakai untuk guru; dropdown siswa hanya menawarkan kode 2-4.
// ============================================================================
const ABS_MASUK = 0, ABS_PULANG = 1, ABS_SAKIT = 2, ABS_IJIN = 3,
      ABS_ALPHA = 4, ABS_DINAS = 5, ABS_CUTI  = 6;

/** Kode ketidakhadiran yang bisa dipilih saat koreksi, per tipe. */
function kodeKetidakhadiran(string $tipe): array {
    return $tipe === 'guru'
        ? [ABS_SAKIT, ABS_IJIN, ABS_ALPHA, ABS_DINAS, ABS_CUTI]
        : [ABS_SAKIT, ABS_IJIN, ABS_ALPHA];
}

/** Label kode status untuk tampilan. */
function kodeLabel(int $kode): string {
    return [ABS_MASUK => 'Masuk', ABS_PULANG => 'Pulang', ABS_SAKIT => 'Sakit',
            ABS_IJIN  => 'Ijin',  ABS_ALPHA  => 'Alpha (Tidak Hadir)',
            ABS_DINAS => 'Dinas Luar', ABS_CUTI => 'Cuti'][$kode] ?? '-';
}

/** Kode ketidakhadiran (2-6) -> status laporan. Null untuk kode masuk/pulang. */
function kodeKeStatus(int $kode): ?string {
    return [ABS_SAKIT => 'sakit', ABS_IJIN  => 'izin',  ABS_ALPHA => 'alpha',
            ABS_DINAS => 'dinas', ABS_CUTI  => 'cuti'][$kode] ?? null;
}

/** Status laporan -> kode ketidakhadiran. Null bila bukan status ketidakhadiran. */
function statusKeKode(string $status): ?int {
    return ['sakit' => ABS_SAKIT, 'izin'  => ABS_IJIN,  'alpha' => ABS_ALPHA,
            'dinas' => ABS_DINAS, 'cuti'  => ABS_CUTI][$status] ?? null;
}

/** Rekap kosong (semua status laporan bernilai 0) — satu sumber kebenaran urutan status. */
function rekapKosong(): array {
    return ['hadir'=>0, 'terlambat'=>0, 'izin'=>0, 'sakit'=>0,
            'dinas'=>0, 'cuti'=>0, 'alpha'=>0, 'libur'=>0];
}

// ============================================================================
//  Helper catatan absensi (dari database absensi, via $pdo)
// ----------------------------------------------------------------------------
//  Kedua tabel diringkas ke bentuk seragam "catatan per hari":
//     [kunci orang => [tanggal => ['jam_masuk','jam_pulang','status','keterangan']]]
//  sehingga logika laporan/rekap di bawahnya sama untuk siswa maupun guru.
//  Kunci orang: NIS (siswa, string) atau guru_id (guru, int dari datacenter).
// ============================================================================

/** Nama tabel & kolom kunci absensi per tipe. */
function absTabel(string $tipe): array {
    return $tipe === 'guru' ? ['absensi_guru', 'nip'] : ['absensi_siswa', 'nis'];
}

/**
 * Baca log event absensi lalu ringkas jadi satu baris per (orang, tanggal).
 * Berlaku untuk siswa (kunci NIS) maupun guru (kunci NIP) karena strukturnya sama.
 *
 * @param array $keys Daftar NIS (siswa) atau NIP (guru).
 * @return array [kunci => [tanggal => ['jam_masuk','jam_pulang','status','keterangan']]]
 */
function recAbsensi(PDO $pdo, string $tipe, array $keys, string $dari, string $sampai): array {
    $out = [];
    if (!$keys) return $out;
    [$tabel, $kol] = absTabel($tipe);
    $in = implode(',', array_fill(0, count($keys), '?'));
    $st = $pdo->prepare("SELECT $kol AS orang, tanggal, jam, status, keterangan FROM $tabel
                         WHERE $kol IN ($in) AND tanggal BETWEEN ? AND ?
                         ORDER BY tanggal, status, jam");
    $st->execute([...$keys, $dari, $sampai]);
    foreach ($st as $r) {
        $k = $r['orang']; $tgl = $r['tanggal'];
        if (!isset($out[$k][$tgl])) {
            $out[$k][$tgl] = ['jam_masuk'=>null, 'jam_pulang'=>null, 'status'=>null, 'keterangan'=>null];
        }
        $kode = (int)$r['status'];
        if ($kode === ABS_MASUK)      $out[$k][$tgl]['jam_masuk']  = $r['jam'];
        elseif ($kode === ABS_PULANG) $out[$k][$tgl]['jam_pulang'] = $r['jam'];
        else                          $out[$k][$tgl]['status']     = kodeKeStatus($kode);
        if (($r['keterangan'] ?? '') !== '') $out[$k][$tgl]['keterangan'] = $r['keterangan'];
    }
    return $out;
}

/**
 * Tulis ulang seluruh catatan satu orang pada satu tanggal (dipakai halaman koreksi).
 * Kode ketidakhadiran (2-6) disimpan sebagai satu baris tanpa jam; selain itu
 * jam masuk/pulang disimpan sebagai event kode 0 dan 1.
 */
function simpanAbsensi(PDO $pdo, string $tipe, string $orang, string $tanggal,
                       ?int $kode, ?string $jamMasuk, ?string $jamPulang, ?string $ket): void {
    [$tabel, $kol] = absTabel($tipe);
    $pdo->prepare("DELETE FROM $tabel WHERE $kol=? AND tanggal=?")->execute([$orang, $tanggal]);
    $ins = $pdo->prepare("INSERT INTO $tabel ($kol, tanggal, jam, status, keterangan) VALUES (?,?,?,?,?)");
    if ($kode !== null) {
        $ins->execute([$orang, $tanggal, null, $kode, $ket]);
        return;
    }
    if ($jamMasuk)  $ins->execute([$orang, $tanggal, $jamMasuk,  ABS_MASUK,  $ket]);
    if ($jamPulang) $ins->execute([$orang, $tanggal, $jamPulang, ABS_PULANG, null]);
}

/**
 * Info tiap tanggal dalam periode: hari sekolah atau libur, dan batas terlambatnya.
 * Menggabungkan setting jadwal PER HARI (jadwal_absensi) dengan hari libur khusus
 * (hari_libur, rentang tanggal di-expand).
 *
 * @return array<string,array{libur:bool, batas:string, ket:?string}>
 */
function kalenderPeriode(PDO $pdo, string $tipe, string $dari, string $sampai): array {
    $jadwalHari = [];
    $js = $pdo->prepare('SELECT hari, jam_masuk, batas_terlambat, libur FROM jadwal_absensi WHERE tipe=?');
    $js->execute([$tipe]);
    foreach ($js as $jr) $jadwalHari[(int)$jr['hari']] = $jr;

    $liburKhusus = [];
    $hl = $pdo->prepare("SELECT tanggal, tanggal_selesai, keterangan FROM hari_libur
                         WHERE tanggal <= ? AND COALESCE(tanggal_selesai, tanggal) >= ?");
    $hl->execute([$sampai, $dari]);
    foreach ($hl as $h) {
        $end = $h['tanggal_selesai'] ?: $h['tanggal'];
        for ($d = strtotime($h['tanggal']); $d <= strtotime($end); $d = strtotime('+1 day', $d)) {
            $liburKhusus[date('Y-m-d', $d)] = $h['keterangan'];
        }
    }

    $kal = [];
    for ($d = strtotime($dari); $d <= strtotime($sampai); $d = strtotime('+1 day', $d)) {
        $tgl = date('Y-m-d', $d);
        $jh = $jadwalHari[(int)date('N', $d)] ?? null;
        $kal[$tgl] = [
            // Hari non-sekolah: ditandai libur di jadwal, tak punya jam masuk terjadwal, atau libur khusus
            'libur' => !$jh || (int)$jh['libur'] === 1 || empty($jh['jam_masuk']) || isset($liburKhusus[$tgl]),
            'batas' => ($jh && $jh['batas_terlambat']) ? $jh['batas_terlambat'] : '07:00:00',
            'ket'   => $liburKhusus[$tgl] ?? null,
        ];
    }
    return $kal;
}

/**
 * Tentukan status satu tanggal dari catatan absensi + info kalender:
 *   - sakit/ijin/dinas luar/cuti tercatat   -> sesuai catatan (kode 2,3,5,6)
 *   - ada jam masuk                         -> 'hadir' jika <= batas terlambat, selain itu 'terlambat'
 *   - alpha tercatat (kode 4)               -> 'alpha'
 *   - hari libur (jadwal / libur khusus)    -> 'libur' (tidak dihitung tidak hadir)
 *   - hari sekolah tanpa catatan            -> 'alpha' (Tidak Hadir)
 */
function statusTanggal(array $info, ?array $r): array {
    $jamMasuk = $r['jam_masuk'] ?? null;
    $ket      = $r['keterangan'] ?? null;
    $tercatat = $r['status'] ?? null;   // sakit|izin|dinas|cuti|alpha|null

    if ($tercatat !== null && $tercatat !== 'alpha') {
        $status = $tercatat;
    } elseif ($jamMasuk) {
        $status = ($jamMasuk <= $info['batas']) ? 'hadir' : 'terlambat';
    } elseif ($tercatat === 'alpha') {
        $status = 'alpha';                      // alpha dicatat eksplisit (kode 4)
    } elseif ($info['libur']) {
        $status = 'libur';
        $ket = $ket ?: $info['ket'];            // nama hari libur khusus
    } else {
        $status = 'alpha';                      // hari sekolah tanpa catatan
    }
    return ['status' => $status, 'keterangan' => $ket];
}

/**
 * Laporan absensi harian satu orang untuk SETIAP tanggal dalam rentang.
 *
 * @param array $rec Catatan orang tsb: [tanggal => ['jam_masuk','jam_pulang','status','keterangan']]
 * @return array{rows: array<int,array>, rekap: array<string,int>}
 */
function laporanHarian(PDO $pdo, string $tipe, array $rec, string $dari, string $sampai): array {
    $rekap = rekapKosong();
    if (strtotime($dari) > strtotime($sampai)) return ['rows'=>[], 'rekap'=>$rekap];

    $rows = [];
    foreach (kalenderPeriode($pdo, $tipe, $dari, $sampai) as $tgl => $info) {
        $r = $rec[$tgl] ?? null;
        ['status'=>$status, 'keterangan'=>$ket] = statusTanggal($info, $r);
        $rekap[$status]++;
        $rows[] = [
            'tanggal'   => $tgl,
            'jam_masuk' => $r['jam_masuk'] ?? null,
            'jam_pulang'=> $r['jam_pulang'] ?? null,
            'status'    => $status,
            'keterangan'=> $ket,
        ];
    }
    return ['rows'=>$rows, 'rekap'=>$rekap];
}

/**
 * Rekap periode untuk banyak orang sekaligus, memakai aturan yang sama dengan
 * laporanHarian() sehingga angka di halaman per kelas/jabatan konsisten dengan
 * halaman per siswa/guru.
 *
 * @param array $recAll [kunci orang => [tanggal => catatan]]
 * @return array [kunci orang => ['hadir'=>n,'terlambat'=>n,'izin'=>n,'sakit'=>n,'alpha'=>n,'libur'=>n]]
 */
function rekapPeriode(PDO $pdo, string $tipe, array $keys, array $recAll, string $dari, string $sampai): array {
    $kal = strtotime($dari) > strtotime($sampai) ? [] : kalenderPeriode($pdo, $tipe, $dari, $sampai);
    $out = [];
    foreach ($keys as $key) {
        $rekap = rekapKosong();
        foreach ($kal as $tgl => $info) {
            $rekap[statusTanggal($info, $recAll[$key][$tgl] ?? null)['status']]++;
        }
        $out[$key] = $rekap;
    }
    return $out;
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
$STATUS_LIST = ['hadir' => 'Hadir', 'terlambat' => 'Terlambat', 'izin' => 'Izin', 'sakit' => 'Sakit',
                'dinas' => 'Dinas Luar', 'cuti' => 'Cuti', 'alpha' => 'Tidak Hadir'];
// Laporan harian (termasuk "Libur"). Warna dipakai sebagai kelas badge Bootstrap.
$STATUS_DETAIL = $STATUS_LIST + ['libur' => 'Libur'];
$STATUS_WARNA  = ['hadir'=>'success', 'terlambat'=>'warning text-dark', 'izin'=>'info', 'sakit'=>'primary',
                  'dinas'=>'dark', 'cuti'=>'secondary', 'alpha'=>'danger', 'libur'=>'light text-dark border'];

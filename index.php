<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$today = date('Y-m-d');

/**
 * Jumlah orang per status pada satu tanggal (berlaku untuk siswa maupun guru —
 * kedua tabel absensi berbentuk log event dengan struktur sama).
 * Baris event diringkas dulu per orang, lalu statusnya dihitung dengan aturan
 * laporan yang sama. Hanya orang yang punya catatan yang dihitung — sisanya
 * masuk "tidak hadir" lewat pengurangan terhadap total siswa/guru.
 */
function statusCounts(PDO $pdo, string $tipe, string $tanggal, array $kal): array {
    $counts = rekapKosong();
    $info = $kal[$tanggal] ?? ['libur'=>false, 'batas'=>'07:00:00', 'ket'=>null];
    [$tabel, $kol] = absTabel($tipe);
    $st = $pdo->prepare("SELECT DISTINCT $kol AS orang FROM $tabel WHERE tanggal = ?");
    $st->execute([$tanggal]);
    $keys = $st->fetchAll(PDO::FETCH_COLUMN);
    $rec = recAbsensi($pdo, $tipe, $keys, $tanggal, $tanggal);
    foreach ($keys as $k) {
        $counts[statusTanggal($info, $rec[$k][$tanggal] ?? null)['status']]++;
    }
    return $counts;
}

// Total siswa/guru dibaca langsung dari datacenter (koneksi $dc)
$ta = tahunAjaranAktif($dc);
$totalSiswa = $ta
    ? (int)$dc->query("SELECT COUNT(*) FROM siswa s
                       JOIN siswa_rombel sr ON sr.siswa_id=s.id AND sr.tahun_ajaran_id={$ta['id']}
                       WHERE s.is_aktif=1 AND s.status_siswa='Aktif'")->fetchColumn()
    : 0;
$totalGuru  = (int)$dc->query('SELECT COUNT(*) FROM guru WHERE is_aktif=1')->fetchColumn();

// Kalender 7 hari terakhir (sekali ambil, dipakai kartu hari ini + grafik)
$mulai7 = date('Y-m-d', strtotime('-6 day'));
$kalSiswa = kalenderPeriode($pdo, 'siswa', $mulai7, $today);
$kalGuru  = kalenderPeriode($pdo, 'guru',  $mulai7, $today);

$cs = statusCounts($pdo, 'siswa', $today, $kalSiswa);
$cg = statusCounts($pdo, 'guru',  $today, $kalGuru);

$siswaHadir = $cs['hadir'] + $cs['terlambat'];
$siswaTidakHadir = $totalSiswa - $siswaHadir;
// Guru dinas luar tetap bertugas -> dihitung hadir; cuti tidak.
$guruHadir = $cg['hadir'] + $cg['terlambat'] + $cg['dinas'];
$guruTidakHadir = $totalGuru - $guruHadir;

// Grafik 7 hari terakhir (gabungan siswa + guru per status)
$labels = [];
$series = ['hadir'=>[],'terlambat'=>[],'izin'=>[],'sakit'=>[],'dinas'=>[],'cuti'=>[],'alpha'=>[]];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $labels[] = date('d/m', strtotime($d));
    $a = statusCounts($pdo, 'siswa', $d, $kalSiswa);
    $b = statusCounts($pdo, 'guru',  $d, $kalGuru);
    foreach ($series as $k => $_) $series[$k][] = $a[$k] + $b[$k];
}
?>
<?php if (!$ta): ?>
  <div class="alert alert-warning">Tidak ada <b>tahun ajaran aktif</b> di datacenter (<code><?= e(DC_NAME) ?></code>). Data siswa & kelas tidak dapat ditampilkan sampai ada tahun ajaran yang diaktifkan.</div>
<?php else: ?>
  <div class="text-muted small mb-3"><i class="bi bi-database me-1"></i>Tahun Ajaran aktif <b><?= e($ta['nama_tahun_ajaran']) ?></b></div>
<?php endif; ?>
<div class="row g-3">
  <?php
  $cards = [
    ['Total Siswa', $totalSiswa, 'bi-people', 'primary'],
    ['Siswa Hadir', $siswaHadir, 'bi-check-circle', 'success'],
    ['Siswa Tidak Hadir', $siswaTidakHadir, 'bi-x-circle', 'danger'],
    ['Siswa Terlambat', $cs['terlambat'], 'bi-clock-history', 'warning'],
    ['Total Guru', $totalGuru, 'bi-person-badge', 'primary'],
    ['Guru Hadir', $guruHadir, 'bi-check-circle', 'success'],
    ['Guru Tidak Hadir', $guruTidakHadir, 'bi-x-circle', 'danger'],
    ['Guru Terlambat', $cg['terlambat'], 'bi-clock-history', 'warning'],
  ];
  foreach ($cards as [$label, $val, $icon, $color]): ?>
  <div class="col-6 col-md-3">
    <div class="card card-stat">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div>
          <div class="text-muted small"><?= e($label) ?></div>
          <div class="fs-3 fw-bold"><?= $val ?></div>
        </div>
        <i class="bi <?= $icon ?> icon text-<?= $color ?>"></i>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="card card-stat mt-4">
  <div class="card-body">
    <h6 class="mb-3">Grafik Absensi 7 Hari Terakhir (Siswa + Guru) — Hari ini: <?= e(date('d-m-Y')) ?></h6>
    <canvas id="chartAbsensi" height="90"></canvas>
  </div>
</div>

<script>
new Chart(document.getElementById('chartAbsensi'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($labels) ?>,
    datasets: [
      { label: 'Hadir',       data: <?= json_encode($series['hadir']) ?>,     backgroundColor: '#22c55e' },
      { label: 'Terlambat',   data: <?= json_encode($series['terlambat']) ?>, backgroundColor: '#f59e0b' },
      { label: 'Izin',        data: <?= json_encode($series['izin']) ?>,      backgroundColor: '#3b82f6' },
      { label: 'Sakit',       data: <?= json_encode($series['sakit']) ?>,     backgroundColor: '#a855f7' },
      { label: 'Dinas Luar',  data: <?= json_encode($series['dinas']) ?>,     backgroundColor: '#0f172a' },
      { label: 'Cuti',        data: <?= json_encode($series['cuti']) ?>,      backgroundColor: '#64748b' },
      { label: 'Tidak Hadir', data: <?= json_encode($series['alpha']) ?>,     backgroundColor: '#ef4444' }
    ]
  },
  options: {
    responsive: true,
    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
  }
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

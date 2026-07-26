<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$today = date('Y-m-d');

function statusCounts(PDO $pdo, string $table, string $tanggal): array {
    $counts = ['hadir'=>0,'terlambat'=>0,'izin'=>0,'sakit'=>0,'alpha'=>0];
    $stmt = $pdo->prepare("SELECT status, COUNT(*) c FROM $table WHERE tanggal = ? GROUP BY status");
    $stmt->execute([$tanggal]);
    foreach ($stmt as $r) $counts[$r['status']] = (int)$r['c'];
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
$cs = statusCounts($pdo, 'absensi_siswa', $today);
$cg = statusCounts($pdo, 'absensi_guru', $today);

$siswaHadir = $cs['hadir'] + $cs['terlambat'];
$siswaTidakHadir = $totalSiswa - $siswaHadir;
$guruHadir = $cg['hadir'] + $cg['terlambat'];
$guruTidakHadir = $totalGuru - $guruHadir;

// Grafik 7 hari terakhir (gabungan siswa + guru per status)
$labels = []; $series = ['hadir'=>[],'terlambat'=>[],'izin'=>[],'sakit'=>[],'alpha'=>[]];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $labels[] = date('d/m', strtotime($d));
    $a = statusCounts($pdo, 'absensi_siswa', $d);
    $b = statusCounts($pdo, 'absensi_guru', $d);
    foreach ($series as $k => $_) $series[$k][] = $a[$k] + $b[$k];
}
?>
<?php if (!$ta): ?>
  <div class="alert alert-warning">Tidak ada <b>tahun ajaran aktif</b> di datacenter (<code><?= e(DC_NAME) ?></code>). Data siswa & kelas tidak dapat ditampilkan sampai ada tahun ajaran yang diaktifkan.</div>
<?php else: ?>
  <div class="text-muted small mb-3"><i class="bi bi-database me-1"></i>Sumber data master: <b>datacenter</b> (<?= e(DC_NAME) ?>) — Tahun Ajaran aktif <b><?= e($ta['nama_tahun_ajaran']) ?></b></div>
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

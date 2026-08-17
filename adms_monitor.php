<?php
$pageTitle = 'Monitor ADMS (Data Masuk dari Mesin)';
require_once __DIR__ . '/config.php';
requireLogin();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'proses_ulang') {
    // Petakan ulang scan yang PIN-nya dulu tidak dikenal (mis. setelah PIN
    // didaftarkan di mesin_pin atau siswa/guru ditambahkan di datacenter).
    $st = $pdo->query('SELECT * FROM adms_scan WHERE diproses = 0 ORDER BY waktu');
    $ok = 0; $sisa = 0; $cache = [];
    foreach ($st->fetchAll() as $s) {
        if (!array_key_exists($s['pin'], $cache)) $cache[$s['pin']] = admsPetakanPin($pdo, $dc, $s['pin']);
        $orang = $cache[$s['pin']];
        if (!$orang) { $sisa++; continue; }
        $ditulis = admsTulisAbsensi($pdo, $orang['tipe'], $orang['nomor_induk'], $s['waktu'], (int)$s['status_mesin']);
        $pdo->prepare('UPDATE adms_scan SET tipe=?, nomor_induk=?, diproses=? WHERE id=?')
            ->execute([$orang['tipe'], $orang['nomor_induk'], $ditulis ? 1 : 0, $s['id']]);
        if ($ditulis) $ok++; else $sisa++;
    }
    $msg = "Proses ulang selesai: $ok scan tercatat ke absensi, $sisa masih belum terpetakan.";
}

$tanggal = $_GET['tanggal'] ?? date('Y-m-d');

$mesinList = $pdo->query('SELECT * FROM mesin_absensi ORDER BY nama')->fetchAll();
$snTerdaftar = array_filter(array_column($mesinList, 'serial_number'));

// SN yang mengirim data tapi belum terdaftar di Setting Mesin
$snAsing = $pdo->query("SELECT sn, COUNT(*) c, MAX(diterima_pada) terakhir FROM adms_scan
                        WHERE sn IS NOT NULL GROUP BY sn")->fetchAll();
$snAsing = array_filter($snAsing, fn($r) => !in_array($r['sn'], $snTerdaftar, true));

$belumTerpetakan = (int)$pdo->query('SELECT COUNT(*) FROM adms_scan WHERE nomor_induk IS NULL')->fetchColumn();

$st = $pdo->prepare('SELECT * FROM adms_scan WHERE DATE(waktu) = ? ORDER BY waktu DESC LIMIT 300');
$st->execute([$tanggal]);
$scan = $st->fetchAll();

// Nama orang untuk scan yang sudah terpetakan
$nama = ['siswa' => [], 'guru' => []];
foreach (['siswa', 'guru'] as $tp) {
    $induk = array_values(array_unique(array_column(array_filter($scan, fn($r) => $r['tipe'] === $tp), 'nomor_induk')));
    if (!$induk) continue;
    $in = implode(',', array_fill(0, count($induk), '?'));
    $q = $tp === 'guru'
        ? $dc->prepare("SELECT nip AS k, nama_ptk AS n FROM guru WHERE nip IN ($in)")
        : $dc->prepare("SELECT COALESCE(NULLIF(nis,''), nisn) AS k, nama_siswa AS n FROM siswa
                        WHERE COALESCE(NULLIF(nis,''), nisn) IN ($in)");
    $q->execute($induk);
    foreach ($q as $r) $nama[$tp][$r['k']] = $r['n'];
}

$log = $pdo->query('SELECT * FROM adms_log ORDER BY id DESC LIMIT 15')->fetchAll();

$punch = [0=>'Check-In', 1=>'Check-Out', 2=>'Break-Out', 3=>'Break-In', 4=>'OT-In', 5=>'OT-Out',
          255=>'Tanpa status'];
// Cara verifikasi yang dilaporkan mesin
$verifikasi = [0=>'Password', 1=>'Sidik jari', 2=>'Nomor induk', 3=>'Kartu', 4=>'Wajah',
               15=>'Wajah', 9=>'Telapak tangan', 25=>'Telapak tangan'];

require_once __DIR__ . '/includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

<?php if ($snAsing): ?>
<div class="alert alert-warning">
  <b>Serial number belum terdaftar.</b> Mesin berikut mengirim data tapi SN-nya belum ada di Setting Mesin —
  datanya tetap disimpan. Daftarkan SN agar status online mesin ikut terpantau:
  <ul class="mb-0 mt-1">
    <?php foreach ($snAsing as $s): ?>
      <li><code><?= e($s['sn']) ?></code> — <?= (int)$s['c'] ?> scan, terakhir <?= e($s['terakhir']) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<?php if ($belumTerpetakan): ?>
<div class="alert alert-danger d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div>
    <b><?= $belumTerpetakan ?> scan</b> PIN-nya tidak cocok dengan NIS siswa maupun NIP guru, jadi belum masuk ke laporan absensi.
    Daftarkan pemetaan PIN di tabel <code>mesin_pin</code>, lalu proses ulang.
  </div>
  <form method="post"><input type="hidden" name="act" value="proses_ulang">
    <button class="btn btn-sm btn-danger"><i class="bi bi-arrow-repeat me-1"></i>Proses Ulang</button>
  </form>
</div>
<?php endif; ?>

<div class="card card-stat mb-4">
  <div class="card-header bg-white fw-semibold">Status Mesin</div>
  <div class="card-body table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>Nama</th><th>Serial Number</th><th>Lokasi</th><th>Terakhir Online</th><th>Status</th></tr></thead>
      <tbody>
      <?php if (!$mesinList): ?><tr><td colspan="5" class="text-muted">Belum ada mesin terdaftar.</td></tr><?php endif; ?>
      <?php foreach ($mesinList as $m):
        $onlineBaru = $m['last_online'] && (time() - strtotime($m['last_online']) < 300); ?>
        <tr>
          <td><?= e($m['nama']) ?></td>
          <td><code><?= e($m['serial_number'] ?: '—') ?></code></td>
          <td><?= e($m['lokasi'] ?: '—') ?></td>
          <td><?= e($m['last_online'] ?: '—') ?></td>
          <td><?= $onlineBaru ? '<span class="badge bg-success">Online</span>' : '<span class="badge bg-secondary">Tidak ada kabar</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div class="text-muted small mt-2">Mesin dianggap online bila menghubungi server dalam 5 menit terakhir.</div>
  </div>
</div>

<form class="card card-stat mb-4"><div class="card-body row g-2 align-items-end">
  <div class="col-md-3"><label class="form-label">Tanggal Scan</label><input type="date" class="form-control" name="tanggal" value="<?= e($tanggal) ?>"></div>
  <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Tampilkan</button></div>
</div></form>

<div class="card card-stat mb-4">
  <div class="card-header bg-white fw-semibold">Data Scan Masuk — <?= e(date('d-m-Y', strtotime($tanggal))) ?> (maks. 300 terbaru)</div>
  <div class="card-body table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead><tr><th>Jam</th><th>PIN</th><th>Nama</th><th>Tipe</th><th>Punch State</th><th>Verifikasi</th><th>SN Mesin</th><th>Ke Absensi</th></tr></thead>
      <tbody>
      <?php if (!$scan): ?><tr><td colspan="8" class="text-muted">Belum ada data scan pada tanggal ini.</td></tr><?php endif; ?>
      <?php foreach ($scan as $s): ?>
        <tr class="<?= $s['nomor_induk'] === null ? 'table-warning' : '' ?>">
          <td><?= e(substr($s['waktu'], 11, 5)) ?></td>
          <td><code><?= e($s['pin']) ?></code></td>
          <td><?= e($s['nomor_induk'] === null ? '— PIN tidak dikenal —' : ($nama[$s['tipe']][$s['nomor_induk']] ?? $s['nomor_induk'])) ?></td>
          <td><?= $s['tipe'] ? e(ucfirst($s['tipe'])) : '-' ?></td>
          <td><?= e($punch[(int)$s['status_mesin']] ?? $s['status_mesin']) ?></td>
          <td><?= e($s['verify'] === null ? '-' : ($verifikasi[(int)$s['verify']] ?? 'Kode ' . $s['verify'])) ?></td>
          <td class="small"><?= e($s['sn'] ?: '-') ?></td>
          <td><?= $s['diproses'] ? '<span class="badge bg-success">Tercatat</span>' : '<span class="badge bg-secondary">Tidak</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card card-stat">
  <div class="card-header bg-white fw-semibold">Riwayat Komunikasi Mesin (15 terakhir)</div>
  <div class="card-body table-responsive">
    <table class="table table-sm mb-0">
      <thead><tr><th>Waktu</th><th>SN</th><th>Endpoint</th><th>Tabel</th><th>Baris</th><th>Disimpan</th><th>Gagal</th><th>IP</th></tr></thead>
      <tbody>
      <?php if (!$log): ?><tr><td colspan="8" class="text-muted">Belum ada mesin yang menghubungi server.</td></tr><?php endif; ?>
      <?php foreach ($log as $l): ?>
        <tr>
          <td><?= e($l['waktu']) ?></td>
          <td class="small"><?= e($l['sn'] ?: '-') ?><?= $l['sn_dikenal'] ? '' : ' <span class="badge bg-warning text-dark">asing</span>' ?></td>
          <td><?= e($l['endpoint']) ?></td>
          <td><?= e($l['tabel'] ?: '-') ?></td>
          <td><?= (int)$l['jumlah'] ?></td>
          <td><?= (int)$l['disimpan'] ?></td>
          <td><?= (int)$l['gagal'] ?></td>
          <td class="small"><?= e($l['ip'] ?: '-') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

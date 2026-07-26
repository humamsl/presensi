<?php
$pageTitle = 'Info Absensi Per Guru (Periode Tanggal)';
require_once __DIR__ . '/includes/header.php';

// Daftar guru dibaca langsung dari datacenter ($dc); data absensi dari $pdo
$guruList = dcGuruList($dc);
$guruId = (int)($_GET['guru_id'] ?? 0);
$dari = $_GET['dari'] ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-d');
$rows = []; $rekap = ['hadir'=>0,'terlambat'=>0,'izin'=>0,'sakit'=>0,'alpha'=>0,'libur'=>0];
if ($guruId) {
    // Setiap tanggal dalam rentang, status dihitung dari setting jadwal + catatan absensi
    ['rows'=>$rows, 'rekap'=>$rekap] = laporanHarian($pdo, 'absensi_guru', 'guru_id', $guruId, $dari, $sampai);
}
?>
<form class="card card-stat mb-4"><div class="card-body row g-2 align-items-end">
  <div class="col-md-4">
    <label class="form-label">Guru</label>
    <select class="form-select" name="guru_id" required>
      <option value="">— Pilih Guru —</option>
      <?php foreach ($guruList as $g): ?>
        <option value="<?= $g['id'] ?>" <?= $g['id']==$guruId?'selected':'' ?>><?= e($g['nama']) ?> — <?= e($g['jabatan']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3"><label class="form-label">Dari Tanggal</label><input type="date" class="form-control" name="dari" value="<?= e($dari) ?>"></div>
  <div class="col-md-3"><label class="form-label">Sampai Tanggal</label><input type="date" class="form-control" name="sampai" value="<?= e($sampai) ?>"></div>
  <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Tampilkan</button></div>
</div></form>

<?php if ($guruId): $qs = http_build_query(['type'=>'guru','guru_id'=>$guruId,'dari'=>$dari,'sampai'=>$sampai]); ?>
<div class="mb-3">
  <a class="btn btn-success btn-sm" href="export.php?<?= $qs ?>&format=excel"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
  <a class="btn btn-danger btn-sm" href="export.php?<?= $qs ?>&format=pdf"><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</a>
</div>
<div class="row g-2 mb-3 row-cols-2 row-cols-sm-3 row-cols-md-6">
  <?php foreach ($STATUS_DETAIL as $k => $label): ?>
  <div class="col"><div class="card card-stat text-center"><div class="card-body py-2"><div class="small text-muted"><?= e($label) ?></div><div class="fs-4 fw-bold"><?= $rekap[$k] ?? 0 ?></div></div></div></div>
  <?php endforeach; ?>
</div>
<div class="card card-stat"><div class="card-body table-responsive">
  <table class="table table-hover table-sm align-middle">
    <thead><tr><th>Tanggal</th><th>Hari</th><th>Jam Masuk</th><th>Jam Pulang</th><th>Status</th><th>Keterangan</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="6" class="text-muted">Tidak ada data absensi pada periode ini.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e(date('d-m-Y', strtotime($r['tanggal']))) ?></td>
        <td><?= e($HARI_ID[date('N', strtotime($r['tanggal'])) - 1]) ?></td>
        <td><?= e($r['jam_masuk'] ? substr($r['jam_masuk'],0,5) : '-') ?></td>
        <td><?= e($r['jam_pulang'] ? substr($r['jam_pulang'],0,5) : '-') ?></td>
        <td><span class="badge bg-<?= $STATUS_WARNA[$r['status']] ?>"><?= e($STATUS_DETAIL[$r['status']]) ?></span></td>
        <td><?= e($r['keterangan'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div></div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

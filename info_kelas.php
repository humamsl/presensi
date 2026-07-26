<?php
$pageTitle = 'Info Absensi Per Kelas (Periode Tanggal)';
require_once __DIR__ . '/includes/header.php';

// Kelas (rombongan belajar) & siswa dibaca dari datacenter ($dc); rekap absensi dari $pdo.
$ta = tahunAjaranAktif($dc);
$kelasList = $ta ? dcKelasList($dc, (int)$ta['id']) : [];
$kelasId = (int)($_GET['kelas_id'] ?? 0);
$dari = $_GET['dari'] ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-d');
$rows = [];
if ($kelasId && $ta) {
    $siswa = dcSiswaList($dc, (int)$ta['id'], $kelasId);
    $ids = array_column($siswa, 'id');
    $rekap = absensiRekap($pdo, 'absensi_siswa', 'siswa_id', $ids, $dari, $sampai);
    foreach ($siswa as $s) {
        $c = $rekap[$s['id']] ?? [];
        $rows[] = [
            'nis' => $s['nis'], 'nama' => $s['nama'],
            'hadir' => $c['hadir'] ?? 0, 'terlambat' => $c['terlambat'] ?? 0,
            'izin' => $c['izin'] ?? 0, 'sakit' => $c['sakit'] ?? 0, 'alpha' => $c['alpha'] ?? 0,
            'total' => array_sum($c),
        ];
    }
}
?>
<form class="card card-stat mb-4"><div class="card-body row g-2 align-items-end">
  <div class="col-md-4">
    <label class="form-label">Kelas</label>
    <select class="form-select" name="kelas_id" required>
      <option value="">— Pilih Kelas —</option>
      <?php foreach ($kelasList as $k): ?>
        <option value="<?= $k['id'] ?>" <?= $k['id']==$kelasId?'selected':'' ?>><?= e($k['nama']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3"><label class="form-label">Dari Tanggal</label><input type="date" class="form-control" name="dari" value="<?= e($dari) ?>"></div>
  <div class="col-md-3"><label class="form-label">Sampai Tanggal</label><input type="date" class="form-control" name="sampai" value="<?= e($sampai) ?>"></div>
  <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Tampilkan</button></div>
</div></form>

<?php if ($kelasId): $qs = http_build_query(['type'=>'kelas','kelas_id'=>$kelasId,'dari'=>$dari,'sampai'=>$sampai]); ?>
<div class="mb-3">
  <a class="btn btn-success btn-sm" href="export.php?<?= $qs ?>&format=excel"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
  <a class="btn btn-danger btn-sm" href="export.php?<?= $qs ?>&format=pdf"><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</a>
</div>
<div class="card card-stat"><div class="card-body table-responsive">
  <table class="table table-hover table-sm align-middle">
    <thead><tr><th>NIS</th><th>Nama Siswa</th><th>Hadir</th><th>Terlambat</th><th>Izin</th><th>Sakit</th><th>Tidak Hadir</th><th>Total Hari</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="8" class="text-muted">Tidak ada siswa pada kelas ini.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['nis']) ?></td><td><?= e($r['nama']) ?></td>
        <td><span class="badge bg-success"><?= (int)$r['hadir'] ?></span></td>
        <td><span class="badge bg-warning text-dark"><?= (int)$r['terlambat'] ?></span></td>
        <td><span class="badge bg-info"><?= (int)$r['izin'] ?></span></td>
        <td><span class="badge bg-primary"><?= (int)$r['sakit'] ?></span></td>
        <td><span class="badge bg-danger"><?= (int)$r['alpha'] ?></span></td>
        <td><?= (int)$r['total'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div></div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

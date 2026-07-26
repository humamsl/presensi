<?php
$pageTitle = 'Setting Hari Libur';
require_once __DIR__ . '/config.php';
requireLogin();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'add') {
        $selesai = $_POST['tanggal_selesai'] ?: null;
        $pdo->prepare('INSERT INTO hari_libur (tanggal, tanggal_selesai, keterangan, jenis) VALUES (?,?,?,?)')
            ->execute([$_POST['tanggal'], $selesai, $_POST['keterangan'], $_POST['jenis']]);
        $msg = 'Hari libur berhasil ditambahkan.';
    } elseif ($act === 'delete') {
        $pdo->prepare('DELETE FROM hari_libur WHERE id=?')->execute([(int)$_POST['id']]);
        $msg = 'Hari libur dihapus.';
    }
}
$liburList = $pdo->query('SELECT * FROM hari_libur ORDER BY tanggal')->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<div class="row g-3">
  <div class="col-md-4">
    <div class="card card-stat">
      <div class="card-header bg-white fw-semibold">Tambah Hari Libur</div>
      <div class="card-body">
        <form method="post">
          <input type="hidden" name="act" value="add">
          <div class="mb-3"><label class="form-label">Tanggal Mulai</label><input type="date" class="form-control" name="tanggal" required></div>
          <div class="mb-3"><label class="form-label">Tanggal Selesai <span class="text-muted">(kosongkan jika 1 hari)</span></label><input type="date" class="form-control" name="tanggal_selesai"></div>
          <div class="mb-3"><label class="form-label">Keterangan</label><input class="form-control" name="keterangan" required></div>
          <div class="mb-3">
            <label class="form-label">Jenis Libur</label>
            <select class="form-select" name="jenis">
              <option value="sekolah">Libur Sekolah</option>
              <option value="nasional">Libur Nasional</option>
            </select>
          </div>
          <button class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i>Tambah</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <div class="card card-stat">
      <div class="card-header bg-white fw-semibold">Daftar Hari Libur</div>
      <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
          <thead><tr><th>Tanggal</th><th>Keterangan</th><th>Jenis</th><th style="width:70px"></th></tr></thead>
          <tbody>
          <?php foreach ($liburList as $l): ?>
            <tr>
              <td><?= e(date('d-m-Y', strtotime($l['tanggal']))) ?><?= $l['tanggal_selesai'] ? ' s/d ' . e(date('d-m-Y', strtotime($l['tanggal_selesai']))) : '' ?></td>
              <td><?= e($l['keterangan']) ?></td>
              <td><?= $l['jenis'] === 'nasional' ? '<span class="badge bg-danger">Nasional</span>' : '<span class="badge bg-info">Sekolah</span>' ?></td>
              <td>
                <form method="post" onsubmit="return confirm('Hapus hari libur ini?')">
                  <input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= $l['id'] ?>">
                  <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

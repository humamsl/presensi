<?php
$pageTitle = 'Setting Jadwal Shift Guru';
require_once __DIR__ . '/config.php';
requireLogin();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'shift_add') {
        $pdo->prepare('INSERT INTO shift (nama, jam_masuk, jam_pulang) VALUES (?,?,?)')
            ->execute([$_POST['nama'], $_POST['jam_masuk'], $_POST['jam_pulang']]);
        $msg = 'Shift berhasil ditambahkan.';
    } elseif ($act === 'shift_delete') {
        try {
            $pdo->prepare('DELETE FROM shift WHERE id=?')->execute([(int)$_POST['id']]);
            $msg = 'Shift dihapus.';
        } catch (PDOException $e) {
            $msg = 'Shift tidak bisa dihapus karena masih dipakai pada jadwal guru.';
        }
    } elseif ($act === 'jadwal_save') {
        $guruId = (int)$_POST['guru_id'];
        $del = $pdo->prepare('DELETE FROM jadwal_shift_guru WHERE guru_id=? AND hari=?');
        $ins = $pdo->prepare('INSERT INTO jadwal_shift_guru (guru_id, hari, shift_id) VALUES (?,?,?)');
        for ($h = 1; $h <= 7; $h++) {
            $del->execute([$guruId, $h]);
            $shiftId = (int)($_POST['hari'][$h] ?? 0);
            if ($shiftId) $ins->execute([$guruId, $h, $shiftId]);
        }
        $msg = 'Jadwal shift guru berhasil disimpan.';
    }
}

$shiftList = $pdo->query('SELECT * FROM shift ORDER BY jam_masuk')->fetchAll();
$guruList = dcGuruList($dc); // daftar guru dibaca langsung dari datacenter
$jadwal = [];
foreach ($pdo->query('SELECT * FROM jadwal_shift_guru') as $r) $jadwal[$r['guru_id']][$r['hari']] = $r['shift_id'];

$selGuru = (int)($_GET['guru_id'] ?? ($_POST['guru_id'] ?? ($guruList[0]['id'] ?? 0)));

require_once __DIR__ . '/includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<div class="row g-3">
  <div class="col-md-5">
    <div class="card card-stat mb-3">
      <div class="card-header bg-white fw-semibold">Daftar Shift</div>
      <div class="card-body">
        <table class="table table-sm align-middle">
          <thead><tr><th>Nama</th><th>Masuk</th><th>Pulang</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($shiftList as $s): ?>
            <tr>
              <td><?= e($s['nama']) ?></td><td><?= e(substr($s['jam_masuk'],0,5)) ?></td><td><?= e(substr($s['jam_pulang'],0,5)) ?></td>
              <td><form method="post" onsubmit="return confirm('Hapus shift?')"><input type="hidden" name="act" value="shift_delete"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <form method="post" class="row g-2">
          <input type="hidden" name="act" value="shift_add">
          <div class="col-4"><input class="form-control form-control-sm" name="nama" placeholder="Nama shift" required></div>
          <div class="col-3"><input type="time" class="form-control form-control-sm" name="jam_masuk" required></div>
          <div class="col-3"><input type="time" class="form-control form-control-sm" name="jam_pulang" required></div>
          <div class="col-2"><button class="btn btn-sm btn-primary w-100">+</button></div>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-7">
    <div class="card card-stat">
      <div class="card-header bg-white fw-semibold">Jadwal Shift per Guru (per Hari)</div>
      <div class="card-body">
        <form method="get" class="mb-3">
          <label class="form-label">Pilih Guru</label>
          <select class="form-select" name="guru_id" onchange="this.form.submit()">
            <?php foreach ($guruList as $g): ?>
              <option value="<?= $g['id'] ?>" <?= $g['id'] == $selGuru ? 'selected' : '' ?>><?= e($g['nama']) ?> — <?= e($g['jabatan']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <?php if ($selGuru): ?>
        <form method="post">
          <input type="hidden" name="act" value="jadwal_save">
          <input type="hidden" name="guru_id" value="<?= $selGuru ?>">
          <table class="table table-sm align-middle">
            <thead><tr><th>Hari</th><th>Shift</th></tr></thead>
            <tbody>
            <?php foreach ($HARI_ID as $i => $hari): $h = $i + 1; ?>
              <tr>
                <td><?= e($hari) ?></td>
                <td>
                  <select class="form-select form-select-sm" name="hari[<?= $h ?>]">
                    <option value="0">— Libur / Tidak ada shift —</option>
                    <?php foreach ($shiftList as $s): ?>
                      <option value="<?= $s['id'] ?>" <?= ($jadwal[$selGuru][$h] ?? 0) == $s['id'] ? 'selected' : '' ?>>
                        <?= e($s['nama']) ?> (<?= e(substr($s['jam_masuk'],0,5)) ?>–<?= e(substr($s['jam_pulang'],0,5)) ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan Jadwal Shift</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

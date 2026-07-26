<?php
$pageTitle = 'Koreksi Absensi Siswa';
require_once __DIR__ . '/config.php';
requireLogin();

$msg = '';
$tanggal = $_GET['tanggal'] ?? $_POST['tanggal'] ?? date('Y-m-d');
$kelasId = (int)($_GET['kelas_id'] ?? $_POST['kelas_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'save') {
    $siswaId = (int)$_POST['siswa_id'];
    $jamMasuk = $_POST['jam_masuk'] ?: null;
    $jamPulang = $_POST['jam_pulang'] ?: null;
    $stmt = $pdo->prepare('INSERT INTO absensi_siswa (siswa_id, tanggal, jam_masuk, jam_pulang, status, keterangan)
        VALUES (?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE jam_masuk=VALUES(jam_masuk), jam_pulang=VALUES(jam_pulang), status=VALUES(status), keterangan=VALUES(keterangan)');
    $stmt->execute([$siswaId, $tanggal, $jamMasuk, $jamPulang, $_POST['status'], $_POST['keterangan'] ?: null]);
    $msg = 'Koreksi absensi siswa berhasil disimpan.';
}

// Daftar siswa & kelas dari datacenter ($dc); baris absensi tanggal ini dari $pdo, digabung di PHP.
$ta = tahunAjaranAktif($dc);
$kelasList = $ta ? dcKelasList($dc, (int)$ta['id']) : [];
$siswa = $ta ? dcSiswaList($dc, (int)$ta['id'], $kelasId) : [];
$absen = absensiByDate($pdo, 'absensi_siswa', 'siswa_id', array_column($siswa, 'id'), $tanggal);
$rows = [];
foreach ($siswa as $s) {
    $a = $absen[$s['id']] ?? null;
    $rows[] = [
        'id' => $s['id'], 'nis' => $s['nis'], 'nama' => $s['nama'], 'kelas' => $s['kelas'],
        'jam_masuk' => $a['jam_masuk'] ?? null, 'jam_pulang' => $a['jam_pulang'] ?? null,
        'status' => $a['status'] ?? null, 'keterangan' => $a['keterangan'] ?? null,
    ];
}

require_once __DIR__ . '/includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<form class="card card-stat mb-4"><div class="card-body row g-2 align-items-end">
  <div class="col-md-3"><label class="form-label">Tanggal</label><input type="date" class="form-control" name="tanggal" value="<?= e($tanggal) ?>"></div>
  <div class="col-md-3">
    <label class="form-label">Kelas</label>
    <select class="form-select" name="kelas_id">
      <option value="0">— Semua Kelas —</option>
      <?php foreach ($kelasList as $k): ?><option value="<?= $k['id'] ?>" <?= $k['id']==$kelasId?'selected':'' ?>><?= e($k['nama']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Tampilkan</button></div>
</div></form>

<?php foreach ($rows as $r): ?>
<form method="post" id="fr<?= $r['id'] ?>">
  <input type="hidden" name="act" value="save">
  <input type="hidden" name="tanggal" value="<?= e($tanggal) ?>">
  <input type="hidden" name="kelas_id" value="<?= $kelasId ?>">
  <input type="hidden" name="siswa_id" value="<?= $r['id'] ?>">
</form>
<?php endforeach; ?>

<div class="card card-stat"><div class="card-body table-responsive">
  <table class="table table-sm align-middle">
    <thead><tr><th>NIS</th><th>Nama</th><th>Kelas</th><th>Jam Masuk</th><th>Jam Pulang</th><th>Status</th><th>Keterangan</th><th style="width:90px"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $f = 'fr' . $r['id']; ?>
      <tr class="<?= $r['status'] === null ? 'table-light' : '' ?>">
        <td><?= e($r['nis']) ?></td>
        <td><?= e($r['nama']) ?></td>
        <td><?= e($r['kelas']) ?></td>
        <td><input type="time" class="form-control form-control-sm" name="jam_masuk" form="<?= $f ?>" value="<?= e($r['jam_masuk'] ? substr($r['jam_masuk'],0,5) : '') ?>"></td>
        <td><input type="time" class="form-control form-control-sm" name="jam_pulang" form="<?= $f ?>" value="<?= e($r['jam_pulang'] ? substr($r['jam_pulang'],0,5) : '') ?>"></td>
        <td>
          <select class="form-select form-select-sm" name="status" form="<?= $f ?>">
            <?php foreach ($STATUS_LIST as $k2 => $label): ?>
              <option value="<?= $k2 ?>" <?= $r['status'] === $k2 || ($r['status'] === null && $k2 === 'alpha') ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </td>
        <td><input class="form-control form-control-sm" name="keterangan" form="<?= $f ?>" value="<?= e($r['keterangan'] ?? '') ?>" placeholder="mis. surat izin"></td>
        <td><button class="btn btn-sm btn-primary w-100" form="<?= $f ?>"><i class="bi bi-save"></i></button></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="text-muted small">Baris berwarna abu-abu = belum ada data absensi pada tanggal tersebut. Klik simpan untuk membuat/mengoreksi data.</div>
</div></div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

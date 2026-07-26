<?php
$pageTitle = 'Setting Mesin Absensi & Upload Data';
require_once __DIR__ . '/config.php';
requireLogin();

$msg = ''; $err = '';
$ta = tahunAjaranAktif($dc);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'add') {
        $stmt = $pdo->prepare('INSERT INTO mesin_absensi (nama, ip, port, serial_number, tipe, lokasi, aktif) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([$_POST['nama'], $_POST['ip'], (int)$_POST['port'], ($_POST['serial_number'] ?? '') ?: null, $_POST['tipe'], $_POST['lokasi'], isset($_POST['aktif']) ? 1 : 0]);
        $msg = 'Mesin absensi berhasil ditambahkan.';
    } elseif ($act === 'edit') {
        $stmt = $pdo->prepare('UPDATE mesin_absensi SET nama=?, ip=?, port=?, serial_number=?, tipe=?, lokasi=?, aktif=? WHERE id=?');
        $stmt->execute([$_POST['nama'], $_POST['ip'], (int)$_POST['port'], ($_POST['serial_number'] ?? '') ?: null, $_POST['tipe'], $_POST['lokasi'], isset($_POST['aktif']) ? 1 : 0, (int)$_POST['id']]);
        $msg = 'Mesin absensi berhasil diperbarui.';
    } elseif ($act === 'delete') {
        $pdo->prepare('DELETE FROM upload_log WHERE mesin_id=?')->execute([(int)$_POST['id']]);
        $pdo->prepare('DELETE FROM mesin_absensi WHERE id=?')->execute([(int)$_POST['id']]);
        $msg = 'Mesin absensi dihapus.';
    } elseif ($act === 'upload') {
        // Upload terpisah: pilih mesin + jenis data (per tingkat kelas / per rombel / per jabatan).
        // Jumlah data dihitung dari datacenter; PIN mesin memakai NIS/NIP (simulasi -> dicatat di riwayat).
        $mesinId = (int)($_POST['mesin_id'] ?? 0);
        $mesin = $pdo->prepare('SELECT * FROM mesin_absensi WHERE id=?');
        $mesin->execute([$mesinId]);
        $mesin = $mesin->fetch();
        $scope = $_POST['scope'] ?? '';
        if (!$mesin) {
            $err = 'Mesin tujuan tidak ditemukan.';
        } elseif (!$mesin['aktif']) {
            $err = 'Mesin tidak aktif — aktifkan dulu sebelum upload.';
        } else {
            $jg = 0; $js = 0; $ket = '';
            try {
                if ($scope === 'tingkat') {
                    if (!$ta) throw new RuntimeException('Tidak ada tahun ajaran aktif di datacenter.');
                    $tingkat = (int)($_POST['tingkat'] ?? 0);
                    if (!$tingkat) throw new RuntimeException('Tingkat kelas belum dipilih.');
                    $q = $dc->prepare("SELECT COUNT(*) FROM siswa s
                        JOIN siswa_rombel sr ON sr.siswa_id=s.id AND sr.tahun_ajaran_id=?
                        JOIN rombongan_belajar rb ON rb.id=sr.rombongan_belajar_id
                        WHERE rb.tingkat=? AND s.is_aktif=1 AND s.status_siswa='Aktif'");
                    $q->execute([$ta['id'], $tingkat]);
                    $js = (int)$q->fetchColumn();
                    $ket = "Siswa Per Tingkat Kelas: Tingkat $tingkat";
                } elseif ($scope === 'rombel') {
                    if (!$ta) throw new RuntimeException('Tidak ada tahun ajaran aktif di datacenter.');
                    $rid = (int)($_POST['rombel_id'] ?? 0);
                    $rb = $rid ? dcKelas($dc, (int)$ta['id'], $rid) : null;
                    if (!$rb) throw new RuntimeException('Rombel belum dipilih / tidak valid.');
                    $q = $dc->prepare("SELECT COUNT(*) FROM siswa s
                        JOIN siswa_rombel sr ON sr.siswa_id=s.id AND sr.tahun_ajaran_id=?
                        WHERE sr.rombongan_belajar_id=? AND s.is_aktif=1 AND s.status_siswa='Aktif'");
                    $q->execute([$ta['id'], $rid]);
                    $js = (int)$q->fetchColumn();
                    $ket = "Siswa Per Rombel: " . $rb['nama'];
                } elseif ($scope === 'jabatan') {
                    $jab = trim($_POST['jabatan'] ?? '');
                    if ($jab === '') throw new RuntimeException('Jabatan belum dipilih.');
                    $q = $dc->prepare("SELECT COUNT(*) FROM guru WHERE is_aktif=1 AND COALESCE(NULLIF(TRIM(jabatan),''),'Guru')=?");
                    $q->execute([$jab]);
                    $jg = (int)$q->fetchColumn();
                    $ket = "Guru Per Jabatan: $jab";
                } else {
                    throw new RuntimeException('Jenis data upload tidak valid.');
                }
                if ($jg + $js === 0) {
                    $err = "Tidak ada data untuk diupload ($ket).";
                } else {
                    $pdo->prepare('INSERT INTO upload_log (mesin_id, jumlah_guru, jumlah_siswa, keterangan) VALUES (?,?,?,?)')
                        ->execute([$mesinId, $jg, $js, $ket]);
                    $rincian = $scope === 'jabatan' ? "$jg guru" : "$js siswa";
                    $msg = "Berhasil upload $rincian ($ket) ke mesin \"{$mesin['nama']}\" ({$mesin['ip']}:{$mesin['port']}).";
                }
            } catch (Throwable $ex) {
                $err = 'Upload gagal: ' . $ex->getMessage();
            }
        }
    }
}

$mesinList   = $pdo->query('SELECT * FROM mesin_absensi ORDER BY id')->fetchAll();
$activeMesin = array_values(array_filter($mesinList, fn($m) => $m['aktif']));
$logs        = $pdo->query('SELECT l.*, m.nama FROM upload_log l JOIN mesin_absensi m ON m.id=l.mesin_id ORDER BY l.waktu DESC LIMIT 10')->fetchAll();
$tingkatList = $ta ? dcTingkatList($dc, (int)$ta['id']) : [];
$rombelList  = $ta ? dcKelasList($dc, (int)$ta['id']) : [];
$jabatanList = dcJabatanList($dc);

require_once __DIR__ . '/includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>

<!-- ============ Daftar Mesin ============ -->
<div class="card card-stat mb-4">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <span class="fw-semibold">Daftar Mesin Absensi</span>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalMesin" onclick="resetForm()"><i class="bi bi-plus-lg me-1"></i>Tambah Mesin</button>
  </div>
  <div class="card-body table-responsive">
    <table class="table table-hover align-middle">
      <thead><tr><th>Nama</th><th>IP:Port</th><th>Serial Number</th><th>Tipe</th><th>Lokasi</th><th>Status</th><th style="width:110px">Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($mesinList as $m): ?>
        <tr>
          <td><?= e($m['nama']) ?></td>
          <td><?= e($m['ip']) ?>:<?= e($m['port']) ?></td>
          <td><?= $m['serial_number'] ? '<code>' . e($m['serial_number']) . '</code>' : '<span class="text-muted">-</span>' ?></td>
          <td><?= e($m['tipe']) ?></td>
          <td><?= e($m['lokasi']) ?></td>
          <td><?= $m['aktif'] ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Nonaktif</span>' ?></td>
          <td>
            <button class="btn btn-sm btn-warning" onclick='editMesin(<?= json_encode($m, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
            <form method="post" class="d-inline" onsubmit="return confirm('Hapus mesin ini?')">
              <input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= $m['id'] ?>">
              <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$mesinList): ?><tr><td colspan="7" class="text-muted">Belum ada mesin absensi.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ============ Upload Data ke Mesin (terpisah) ============ -->
<div class="card card-stat mb-4">
  <div class="card-header bg-white fw-semibold"><i class="bi bi-upload me-1"></i>Upload Data ke Mesin</div>
  <div class="card-body">
    <?php if (!$activeMesin): ?>
      <div class="text-muted">Belum ada mesin <b>aktif</b>. Tambahkan atau aktifkan mesin terlebih dahulu.</div>
    <?php else: ?>
    <form method="post" class="row g-3" onsubmit="return confirm('Upload data terpilih ke mesin ini?')">
      <input type="hidden" name="act" value="upload">
      <div class="col-md-4">
        <label class="form-label">Mesin Tujuan</label>
        <select class="form-select" name="mesin_id" required>
          <?php foreach ($activeMesin as $m): ?>
            <option value="<?= $m['id'] ?>"><?= e($m['nama']) ?> — <?= e($m['ip']) ?><?= $m['serial_number'] ? ' (' . e($m['serial_number']) . ')' : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Jenis Data</label>
        <select class="form-select" id="up_scope" name="scope" onchange="toggleScope()">
          <option value="tingkat">Siswa — Per Tingkat Kelas</option>
          <option value="rombel">Siswa — Per Rombel</option>
          <option value="jabatan">Guru — Per Jabatan</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Pilihan</label>
        <select class="form-select scope-sel" id="sel_tingkat" name="tingkat" required>
          <?php foreach ($tingkatList as $t): ?><option value="<?= $t ?>">Tingkat <?= $t ?></option><?php endforeach; ?>
          <?php if (!$tingkatList): ?><option value="">(tidak ada kelas)</option><?php endif; ?>
        </select>
        <select class="form-select scope-sel" id="sel_rombel" name="rombel_id" required>
          <?php foreach ($rombelList as $rb): ?><option value="<?= $rb['id'] ?>"><?= e($rb['nama']) ?> — Tingkat <?= e($rb['tingkat']) ?></option><?php endforeach; ?>
          <?php if (!$rombelList): ?><option value="">(tidak ada rombel)</option><?php endif; ?>
        </select>
        <select class="form-select scope-sel" id="sel_jabatan" name="jabatan" required>
          <?php foreach ($jabatanList as $jb): ?><option value="<?= e($jb) ?>"><?= e($jb) ?></option><?php endforeach; ?>
          <?php if (!$jabatanList): ?><option value="">(tidak ada jabatan)</option><?php endif; ?>
        </select>
      </div>
      <div class="col-12 d-flex align-items-center flex-wrap gap-2">
        <button class="btn btn-success"><i class="bi bi-upload me-1"></i>Upload ke Mesin</button>
        <span class="text-muted small">PIN mesin memakai NIS/NIP. Sumber data: datacenter (TA <?= e($ta['nama_tahun_ajaran'] ?? '-') ?>). Simulasi — dicatat di riwayat.</span>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>

<!-- ============ Riwayat Upload ============ -->
<div class="card card-stat">
  <div class="card-header bg-white fw-semibold">Riwayat Upload Data ke Mesin</div>
  <div class="card-body table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>Waktu</th><th>Mesin</th><th>Keterangan</th><th class="text-end">Guru</th><th class="text-end">Siswa</th></tr></thead>
      <tbody>
      <?php if (!$logs): ?><tr><td colspan="5" class="text-muted">Belum ada riwayat upload.</td></tr><?php endif; ?>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td><?= e($l['waktu']) ?></td>
          <td><?= e($l['nama']) ?></td>
          <td><?= e($l['keterangan'] ?? '-') ?></td>
          <td class="text-end"><?= e($l['jumlah_guru']) ?></td>
          <td class="text-end"><?= e($l['jumlah_siswa']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal tambah/edit mesin -->
<div class="modal fade" id="modalMesin"><div class="modal-dialog"><div class="modal-content">
  <form method="post">
    <div class="modal-header"><h5 class="modal-title" id="modalTitle">Tambah Mesin</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" name="act" id="f_act" value="add">
      <input type="hidden" name="id" id="f_id">
      <div class="mb-3"><label class="form-label">Nama Mesin</label><input class="form-control" name="nama" id="f_nama" required></div>
      <div class="row">
        <div class="col-8 mb-3"><label class="form-label">IP Address</label><input class="form-control" name="ip" id="f_ip" required></div>
        <div class="col-4 mb-3"><label class="form-label">Port</label><input type="number" class="form-control" name="port" id="f_port" value="4370" required></div>
      </div>
      <div class="mb-3"><label class="form-label">Serial Number</label><input class="form-control" name="serial_number" id="f_serial" placeholder="mis. ZK-A1B2C3D4"></div>
      <div class="mb-3"><label class="form-label">Tipe</label><input class="form-control" name="tipe" id="f_tipe" value="Fingerprint"></div>
      <div class="mb-3"><label class="form-label">Lokasi</label><input class="form-control" name="lokasi" id="f_lokasi"></div>
      <div class="form-check"><input type="checkbox" class="form-check-input" name="aktif" id="f_aktif" checked><label class="form-check-label" for="f_aktif">Aktif</label></div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Simpan</button></div>
  </form>
</div></div></div>

<script>
function resetForm() {
  document.getElementById('modalTitle').textContent = 'Tambah Mesin';
  document.getElementById('f_act').value = 'add';
  ['f_id','f_nama','f_ip','f_serial','f_lokasi'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('f_port').value = 4370;
  document.getElementById('f_tipe').value = 'Fingerprint';
  document.getElementById('f_aktif').checked = true;
}
function editMesin(m) {
  document.getElementById('modalTitle').textContent = 'Edit Mesin';
  document.getElementById('f_act').value = 'edit';
  document.getElementById('f_id').value = m.id;
  document.getElementById('f_nama').value = m.nama;
  document.getElementById('f_ip').value = m.ip;
  document.getElementById('f_port').value = m.port;
  document.getElementById('f_serial').value = m.serial_number || '';
  document.getElementById('f_tipe').value = m.tipe;
  document.getElementById('f_lokasi').value = m.lokasi || '';
  document.getElementById('f_aktif').checked = m.aktif == 1;
  new bootstrap.Modal(document.getElementById('modalMesin')).show();
}
// Tampilkan hanya pilihan yang sesuai jenis data; yang lain dinonaktifkan agar tak ikut terkirim
function toggleScope() {
  const scope = document.getElementById('up_scope').value;
  const map = { tingkat: 'sel_tingkat', rombel: 'sel_rombel', jabatan: 'sel_jabatan' };
  for (const key in map) {
    const el = document.getElementById(map[key]);
    if (!el) continue;
    const on = (key === scope);
    el.style.display = on ? '' : 'none';
    el.disabled = !on;
  }
}
if (document.getElementById('up_scope')) toggleScope();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
$pageTitle = 'Data User';
require_once __DIR__ . '/config.php';
requireLogin();

$msg = ''; $err = '';
$meId = (int)($_SESSION['user']['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    try {
        if ($act === 'add') {
            if ($username === '' || $nama === '' || $password === '') {
                throw new RuntimeException('Username, nama, dan password wajib diisi.');
            }
            $st = $pdo->prepare('INSERT INTO users (username, nama, password) VALUES (?,?,?)');
            $st->execute([$username, $nama, password_hash($password, PASSWORD_DEFAULT)]);
            $msg = 'User berhasil ditambahkan.';

        } elseif ($act === 'edit') {
            $id = (int)$_POST['id'];
            if ($username === '' || $nama === '') {
                throw new RuntimeException('Username dan nama wajib diisi.');
            }
            if ($password !== '') {
                $st = $pdo->prepare('UPDATE users SET username=?, nama=?, password=? WHERE id=?');
                $st->execute([$username, $nama, password_hash($password, PASSWORD_DEFAULT), $id]);
            } else {
                $st = $pdo->prepare('UPDATE users SET username=?, nama=? WHERE id=?');
                $st->execute([$username, $nama, $id]);
            }
            if ($id === $meId) $_SESSION['user']['nama'] = $nama; // perbarui nama di sesi bila edit diri sendiri
            $msg = 'User berhasil diperbarui.';

        } elseif ($act === 'delete') {
            $id = (int)$_POST['id'];
            if ($id === $meId) {
                throw new RuntimeException('Tidak bisa menghapus akun yang sedang login.');
            }
            if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() <= 1) {
                throw new RuntimeException('Minimal harus tersisa 1 user.');
            }
            $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
            $msg = 'User dihapus.';
        }
    } catch (PDOException $e) {
        $err = $e->getCode() === '23000' ? 'Username sudah dipakai user lain.' : 'Gagal menyimpan: ' . $e->getMessage();
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$userList = $pdo->query('SELECT id, username, nama FROM users ORDER BY nama')->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>

<div class="card card-stat">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <span class="fw-semibold">Daftar User (Akun Login Aplikasi)</span>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalUser" onclick="resetForm()"><i class="bi bi-plus-lg me-1"></i>Tambah User</button>
  </div>
  <div class="card-body table-responsive">
    <table class="table table-hover align-middle">
      <thead><tr><th>Nama</th><th>Username</th><th style="width:110px">Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($userList as $u): ?>
        <tr>
          <td>
            <?= e($u['nama']) ?>
            <?php if ((int)$u['id'] === $meId): ?><span class="badge bg-info ms-1">Anda</span><?php endif; ?>
          </td>
          <td><code><?= e($u['username']) ?></code></td>
          <td>
            <button class="btn btn-sm btn-warning" onclick='editUser(<?= json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
            <?php if ((int)$u['id'] !== $meId): ?>
            <form method="post" class="d-inline" onsubmit="return confirm('Hapus user \'<?= e($u['username']) ?>\'?')">
              <input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
            </form>
            <?php else: ?>
            <button class="btn btn-sm btn-danger" disabled title="Tidak bisa menghapus akun sendiri"><i class="bi bi-trash"></i></button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$userList): ?><tr><td colspan="3" class="text-muted">Belum ada user.</td></tr><?php endif; ?>
      </tbody>
    </table>
    <div class="text-muted small">User di sini adalah akun untuk <b>login ke aplikasi absensi</b> (bukan data guru/siswa). Password disimpan ter-enkripsi.</div>
  </div>
</div>

<!-- Modal tambah/edit user -->
<div class="modal fade" id="modalUser"><div class="modal-dialog"><div class="modal-content">
  <form method="post">
    <div class="modal-header"><h5 class="modal-title" id="modalTitle">Tambah User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" name="act" id="f_act" value="add">
      <input type="hidden" name="id" id="f_id">
      <div class="mb-3"><label class="form-label">Nama Lengkap</label><input class="form-control" name="nama" id="f_nama" required></div>
      <div class="mb-3"><label class="form-label">Username</label><input class="form-control" name="username" id="f_username" autocomplete="off" required></div>
      <div class="mb-1">
        <label class="form-label">Password <span class="text-muted small" id="pwHint"></span></label>
        <div class="input-group">
          <input type="password" class="form-control" name="password" id="f_password" autocomplete="new-password">
          <button class="btn btn-outline-secondary" type="button" onclick="togglePw()"><i class="bi bi-eye" id="pwIcon"></i></button>
        </div>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button></div>
  </form>
</div></div></div>

<script>
function resetForm() {
  document.getElementById('modalTitle').textContent = 'Tambah User';
  document.getElementById('f_act').value = 'add';
  ['f_id','f_nama','f_username','f_password'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('f_password').required = true;
  document.getElementById('pwHint').textContent = '';
}
function editUser(u) {
  document.getElementById('modalTitle').textContent = 'Edit User';
  document.getElementById('f_act').value = 'edit';
  document.getElementById('f_id').value = u.id;
  document.getElementById('f_nama').value = u.nama;
  document.getElementById('f_username').value = u.username;
  document.getElementById('f_password').value = '';
  document.getElementById('f_password').required = false;
  document.getElementById('pwHint').textContent = '(kosongkan bila tidak diubah)';
  new bootstrap.Modal(document.getElementById('modalUser')).show();
}
function togglePw() {
  const f = document.getElementById('f_password'), i = document.getElementById('pwIcon');
  const show = f.type === 'password';
  f.type = show ? 'text' : 'password';
  i.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
require_once __DIR__ . '/config.php';
if (!empty($_SESSION['user'])) { header('Location: index.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([trim($_POST['username'] ?? '')]);
    $user = $stmt->fetch();
    if ($user && password_verify($_POST['password'] ?? '', $user['password'])) {
        $_SESSION['user'] = ['id' => $user['id'], 'nama' => $user['nama']];
        header('Location: index.php');
        exit;
    }
    $error = 'Username atau password salah.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login - Absensi Sekolah</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
@import url('https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800');
:root { --bs-primary:#14b8a6; --bs-primary-rgb:20,184,166; }
body{
  font-family:'Plus Jakarta Sans', system-ui, -apple-system, "Segoe UI", sans-serif;
  min-height:100vh;
  display:flex;
  align-items:center;
  /* Overlay navy (tema Datacenter/CBT) di atas gambar agar teks tetap terbaca. Ganti nama file sesuai gambar Anda. */
  background:
    linear-gradient(rgba(6,34,117,.78), rgba(6,34,117,.78)),
    url('assets/img/login-bg.jpg') center/cover no-repeat fixed;
  background-color:#062275; /* fallback jika gambar belum ada */
}
.card{ border:0; border-radius:1rem; box-shadow:0 20px 60px -15px rgba(2,10,40,.55); }
.card h4{ color:#062275; font-weight:700; }
.form-control:focus{ border-color:#14b8a6; box-shadow:0 0 0 .2rem rgba(20,184,166,.2); }
.btn-primary{
  --bs-btn-color:#fff; --bs-btn-hover-color:#fff; --bs-btn-active-color:#fff;
  border:0; background:linear-gradient(135deg,#14b8a6 0%,#059669 100%);
  box-shadow:0 4px 24px -8px rgba(13,148,136,.35);
}
.btn-primary:hover, .btn-primary:focus, .btn-primary:active{ background:linear-gradient(135deg,#0d9488 0%,#047857 100%); }
</style>
</head>
<body>
<div class="container" style="max-width:400px;max-height: 90vh; overflow-y: auto;">
  <div class="card shadow">
    <div class="card-body p-4">
      <h4 class="text-center mb-1"><i class="bi bi-fingerprint me-2"></i>Absensi Sekolah</h4>
      <p class="text-center text-muted mb-4">Silakan login untuk melanjutkan</p>
      <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
      <form method="post">
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input type="text" name="username" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <button class="btn btn-primary w-100">Login</button>
      </form>
    </div>
  </div>
</div>
</body>
</html>

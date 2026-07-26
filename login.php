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
<style>body{background:#1e293b;display:flex;align-items:center;min-height:100vh;}</style>
</head>
<body>
<div class="container" style="max-width:400px">
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
      <p class="text-muted small text-center mt-3 mb-0">Default: admin / admin123</p>
    </div>
  </div>
</div>
</body>
</html>

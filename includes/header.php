<?php
require_once __DIR__ . '/../config.php';
requireLogin();
$page = basename($_SERVER['PHP_SELF']);

// Link tunggal teratas
$dashboard = ['index.php', 'bi-speedometer2', 'Dashboard'];

// Grup sub-menu dropdown (collapse): [label, ikon grup, [ [file, ikon, label], ... ]]
$groups = [
    ['Setting Absensi', 'bi-gear', [
        ['setting_jadwal.php', 'bi-clock', 'Jadwal Jam Absensi'],
        ['setting_mesin.php', 'bi-cpu', 'Mesin Absensi & Upload'],
        ['setting_libur.php', 'bi-calendar-x', 'Hari Libur'],
        ['setting_shift.php', 'bi-arrow-repeat', 'Jadwal Shift Guru'],
    ]],
    ['Info Absensi Guru', 'bi-person-badge', [
        ['info_guru.php', 'bi-person-badge', 'Per Guru'],
        ['info_jabatan.php', 'bi-diagram-3', 'Per Jabatan'],
    ]],
    ['Info Absensi Siswa', 'bi-people', [
        ['info_siswa.php', 'bi-person', 'Per Siswa'],
        ['info_kelas.php', 'bi-people', 'Per Kelas'],
    ]],
    ['Koreksi Absensi', 'bi-pencil-square', [
        ['koreksi_siswa.php', 'bi-pencil-square', 'Koreksi Siswa'],
        ['koreksi_guru.php', 'bi-pencil', 'Koreksi Guru'],
    ]],
    ['Akun', 'bi-person-circle', [
        ['logout.php', 'bi-box-arrow-right', 'Logout (' . ($_SESSION['user']['nama'] ?? '') . ')'],
    ]],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#1e293b">
<title><?= e($pageTitle ?? 'Absensi Sekolah') ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<style>
:root { --sb-w: 260px; --sb-bg:#1e293b; }
body { background:#f4f6f9; }

/* ---- Sidebar (offcanvas di mobile, tetap di desktop) ---- */
.sidebar { background:var(--sb-bg); --bs-offcanvas-width:var(--sb-w); border:0; }
.sidebar .offcanvas-header { border-bottom:1px solid #334155; padding:1rem 1.25rem; }
.sidebar .brand-link { color:#fff; font-weight:700; font-size:1.1rem; text-decoration:none; }
.sidebar .offcanvas-body { padding:0; overflow-y:auto; }
.sidebar a.nav-link { color:#cbd5e1; padding:.7rem 1.25rem; font-size:.95rem; display:flex; align-items:center; }
.sidebar a.nav-link i { width:1.4rem; }
.sidebar a.nav-link:hover, .sidebar a.nav-link.active { color:#fff; background:#334155; }
.sidebar .group { color:#64748b; font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; padding:.9rem 1.25rem .3rem; }

/* ---- Dropdown sub-menu (collapse) ---- */
.sidebar .nav-group-toggle { cursor:pointer; }
.sidebar .nav-group-toggle .chev { margin-left:auto; font-size:.75rem; transition:transform .2s ease; }
.sidebar .nav-group-toggle[aria-expanded="true"] .chev { transform:rotate(180deg); }
.sidebar .nav-group-toggle[aria-expanded="true"] { color:#fff; background:#273349; }
.sidebar .submenu { background:rgba(0,0,0,.18); }
.sidebar .submenu a.nav-link { padding-left:2.85rem; font-size:.9rem; }
.sidebar .submenu a.nav-link i { width:1.2rem; font-size:.9rem; }

/* ---- Top bar mobile ---- */
.mobile-topbar { background:var(--sb-bg); color:#fff; padding:.4rem .6rem; position:sticky; top:0; z-index:1030; gap:.25rem; }
.mobile-topbar .btn { color:#fff; border:0; padding:.25rem .5rem; line-height:1; }
.mobile-topbar .brand-link { color:#fff; font-weight:700; text-decoration:none; font-size:1.05rem; }

/* ---- Konten ---- */
.main { padding:1rem; }
.main > h4.page-title { margin-bottom:1.25rem; font-size:1.35rem; }

.card-stat { border:0; border-radius:.75rem; box-shadow:0 1px 3px rgba(0,0,0,.08); }
.card-stat .icon { font-size:1.8rem; opacity:.85; }

/* Desktop: sidebar tetap terpampang, konten digeser */
@media (min-width:992px){
  .sidebar { position:fixed; top:0; left:0; height:100vh; width:var(--sb-w);
             transform:none !important; visibility:visible !important; z-index:1000;
             display:flex; flex-direction:column; }
  .sidebar .offcanvas-header .btn-close { display:none; }
  .main { margin-left:var(--sb-w); padding:1.5rem 1.75rem; }
  .main > h4.page-title { font-size:1.5rem; }
}
/* Layar sangat kecil: rapatkan padding & font tabel */
@media (max-width:575.98px){
  .main { padding:.75rem; }
  .table { font-size:.85rem; }
  .btn { --bs-btn-padding-x:.6rem; }
}
</style>
</head>
<body>
<!-- Top bar (hanya mobile) -->
<nav class="mobile-topbar d-lg-none d-flex align-items-center">
  <button class="btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Buka menu">
    <i class="bi bi-list fs-3"></i>
  </button>
  <a class="brand-link" href="index.php"><i class="bi bi-fingerprint me-1"></i>Absensi Sekolah</a>
</nav>

<!-- Sidebar / menu -->
<div class="sidebar offcanvas offcanvas-start" tabindex="-1" id="sidebar" aria-labelledby="sidebarBrand">
  <div class="offcanvas-header">
    <a class="brand-link" id="sidebarBrand" href="index.php"><i class="bi bi-fingerprint me-2"></i>Absensi Sekolah</a>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
  </div>
  <div class="offcanvas-body">
    <ul class="nav flex-column pb-3" id="sidebarNav">
      <!-- Link tunggal -->
      <li><a class="nav-link <?= $page === $dashboard[0] ? 'active' : '' ?>" href="<?= $dashboard[0] ?>"><i class="bi <?= $dashboard[1] ?> me-2"></i><?= e($dashboard[2]) ?></a></li>

      <!-- Grup sub-menu berupa dropdown -->
      <?php foreach ($groups as $gi => [$glabel, $gicon, $items]): ?>
        <?php $activeGroup = in_array($page, array_column($items, 0), true); $cid = 'grp' . $gi; ?>
        <li>
          <a class="nav-link nav-group-toggle <?= $activeGroup ? '' : 'collapsed' ?>" data-bs-toggle="collapse" href="#<?= $cid ?>" role="button" aria-expanded="<?= $activeGroup ? 'true' : 'false' ?>" aria-controls="<?= $cid ?>">
            <i class="bi <?= $gicon ?> me-2"></i><span><?= e($glabel) ?></span><i class="bi bi-chevron-down chev"></i>
          </a>
          <div class="collapse <?= $activeGroup ? 'show' : '' ?>" id="<?= $cid ?>" data-bs-parent="#sidebarNav">
            <ul class="nav flex-column submenu">
              <?php foreach ($items as [$file, $icon, $label]): ?>
                <li><a class="nav-link <?= $page === $file ? 'active' : '' ?>" href="<?= $file ?>"><i class="bi <?= $icon ?> me-2"></i><?= e($label) ?></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>

<div class="main">
<h4 class="page-title"><?= e($pageTitle ?? '') ?></h4>

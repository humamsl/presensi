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
        ['data_user.php', 'bi-people-fill', 'Data User'],
        ['logout.php', 'bi-box-arrow-right', 'Logout (' . ($_SESSION['user']['nama'] ?? '') . ')'],
    ]],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#062275">
<title><?= e($pageTitle ?? 'Absensi Sekolah') ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<style>
@import url('https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800');
:root {
  --sb-w:260px; --sb-bg:#062275;                 /* navy sidebar — tema Datacenter/CBT */
  --brand:#14b8a6; --brand-600:#0d9488; --brand-700:#0f766e;
  --bs-primary:#14b8a6; --bs-primary-rgb:20,184,166;
  --bs-link-color:#0d9488; --bs-link-color-rgb:13,148,136; --bs-link-hover-color:#0f766e;
}
body {
  font-family:'Plus Jakarta Sans', system-ui, -apple-system, "Segoe UI", sans-serif;
  color:#1e293b;
  background-color:#f4fbfa;
  background-image:
    radial-gradient(at 0% 0%, rgba(20,184,166,.07) 0, transparent 45%),
    radial-gradient(at 100% 0%, rgba(16,185,129,.06) 0, transparent 42%),
    radial-gradient(at 100% 100%, rgba(56,189,248,.05) 0, transparent 45%);
  background-attachment:fixed;
}

/* ---- Aksen teal (tombol & link) ---- */
.btn-primary {
  --bs-btn-color:#fff; --bs-btn-hover-color:#fff; --bs-btn-active-color:#fff;
  border:0; background:linear-gradient(135deg,#14b8a6 0%,#059669 100%);
  box-shadow:0 4px 24px -8px rgba(13,148,136,.35);
}
.btn-primary:hover, .btn-primary:focus, .btn-primary:active { background:linear-gradient(135deg,#0d9488 0%,#047857 100%); }
.btn-outline-primary { --bs-btn-color:#0d9488; --bs-btn-border-color:#14b8a6; --bs-btn-hover-bg:#14b8a6; --bs-btn-hover-border-color:#14b8a6; --bs-btn-active-bg:#0d9488; }
a { text-decoration:none; }

/* ---- Sidebar (offcanvas di mobile, tetap di desktop) ---- */
.sidebar { background:var(--sb-bg); --bs-offcanvas-width:var(--sb-w); border:0; }
.sidebar .offcanvas-header { border-bottom:1px solid rgba(255,255,255,.15); padding:1rem 1.25rem; }
.sidebar .brand-link { color:#fff; font-weight:700; font-size:1.1rem; text-decoration:none; }
.sidebar .offcanvas-body { padding:0; overflow-y:auto; }
.sidebar a.nav-link { color:rgba(255,255,255,.82); padding:.65rem 1rem; font-size:.95rem; display:flex; align-items:center; border-radius:.6rem; margin:.12rem .6rem; transition:background .15s, color .15s; }
.sidebar a.nav-link i { width:1.4rem; }
.sidebar a.nav-link:hover { color:#fff; background:rgba(255,255,255,.12); }
.sidebar a.nav-link.active { color:#fff; font-weight:600; background:rgba(255,255,255,.18); box-shadow:inset 3px 0 0 rgba(255,255,255,.9); }
.sidebar .group { color:rgba(255,255,255,.5); font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; padding:.9rem 1.25rem .3rem; }

/* ---- Dropdown sub-menu (collapse) ---- */
.sidebar .nav-group-toggle { cursor:pointer; }
.sidebar .nav-group-toggle .chev { margin-left:auto; font-size:.75rem; transition:transform .2s ease; }
.sidebar .nav-group-toggle[aria-expanded="true"] .chev { transform:rotate(180deg); }
.sidebar .nav-group-toggle[aria-expanded="true"] { color:#fff; background:rgba(255,255,255,.1); }
.sidebar .submenu { background:rgba(0,0,0,.15); border-radius:.6rem; margin:.15rem .6rem; }
.sidebar .submenu a.nav-link { padding-left:2.4rem; font-size:.9rem; margin:.1rem .3rem; }
.sidebar .submenu a.nav-link i { width:1.2rem; font-size:.9rem; }

/* ---- Top bar mobile ---- */
.mobile-topbar { background:var(--sb-bg); color:#fff; padding:.4rem .6rem; position:sticky; top:0; z-index:1030; gap:.25rem; }
.mobile-topbar .btn { color:#fff; border:0; padding:.25rem .5rem; line-height:1; }
.mobile-topbar .brand-link { color:#fff; font-weight:700; text-decoration:none; font-size:1.05rem; }

/* ---- Konten ---- */
.main { padding:1rem; }
.main > h4.page-title { margin-bottom:1.25rem; font-size:1.35rem; font-weight:700; color:#0f172a; }

.card { border:1px solid #eef2f7; border-radius:1rem; box-shadow:0 1px 2px rgba(15,23,42,.04),0 8px 24px -12px rgba(15,23,42,.10); }
.card-stat { transition:box-shadow .25s, transform .25s; }
.card-stat:hover { box-shadow:0 8px 30px -6px rgba(13,148,136,.25); transform:translateY(-2px); }
.card-stat .icon { font-size:1.8rem; opacity:.9; }

/* ---- Select2 tampil seperti input/select Bootstrap (border, tinggi, padding, radius) ---- */
.select2-container--bootstrap-5 .select2-selection {
  min-height: calc(1.5em + .75rem + 2px);
  padding: .375rem .75rem;
  border: 1px solid var(--bs-border-color, #dee2e6);
  border-radius: var(--bs-border-radius, .5rem);
  background-color: #fff;
  display: flex;
  align-items: center;
  font-size: 1rem;
  line-height: 1.5;
}
.select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
  padding: 0; line-height: 1.5; color: #1e293b;
}
.select2-container--bootstrap-5 .select2-selection--single .select2-selection__placeholder { color: #94a3b8; }
.select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow { height: 100%; top: 0; right: .5rem; }
.select2-container--bootstrap-5.select2-container--focus .select2-selection,
.select2-container--bootstrap-5.select2-container--open .select2-selection {
  border-color: var(--brand, #14b8a6);
  box-shadow: 0 0 0 .25rem rgba(20,184,166,.25);
  outline: 0;
}
.select2-container--bootstrap-5 .select2-results__option--highlighted { background-color: var(--brand, #14b8a6); }

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

<?php
require_once __DIR__ . '/../config/database.php';
requireLogin();

$currentPage = basename($_SERVER['PHP_SELF']);
$u = user();

$menu = [
['index.php',       'Dashboard',   'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10'],
['kasir.php',       'Kasir',       'M3 3h2l2 12h12l2-9H6M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z'],
['transaksi.php',   'Transaksi',   'M4 4h16v4H4zM4 12h16v8H4z'],
['hutang.php',      'Hutang',      'M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6'],
['pengeluaran.php', 'Pengeluaran', 'M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6'],
['produk.php',      'Produk',      'M21 16V8l-9-5-9 5v8l9 5 9-5zM3.3 7L12 12l8.7-5M12 22V12'],
['laporan.php',     'Laporan',     'M4 4h16v16H4zM8 12h8M8 8h8M8 16h5'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' · ' : '' ?>Kasir BMT</title>
<link rel="stylesheet" href="assets/style.css">
<link rel="stylesheet" href="assets/page-styles.css">
</head>
<body>

<div class="app">

<!-- ============================================================
     SIDEBAR
     ============================================================ -->
<aside class="sidebar" id="sidebar">
  <button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Tutup menu" type="button">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 6L6 18M6 6l12 12"/>
      </svg>
  </button>

  <div class="brand">
      <div class="brand-logo-wrap">
          <img src="storage/loge.png" alt="Logo Asfie Mart" class="brand-logo">
      </div>
      <div class="brand-text">
          <strong>Asvie Mart</strong>
          <span class="brand-sub">Baitul Maal wat Tamwil</span>
      </div>
  </div>

  <nav class="nav">
      <?php foreach ($menu as $m): ?>
          <a href="<?= $m[0] ?>" title="<?= e($m[1]) ?>"
              class="nav-item <?= $currentPage === $m[0] ? 'active' : '' ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                  <path d="<?= $m[2] ?>"/>
              </svg>
              <span><?= $m[1] ?></span>
          </a>
      <?php endforeach; ?>
  </nav>

  <div class="sidebar-foot">
      <div class="user-chip">
          <div class="avatar"><?= strtoupper(substr($u['nama'], 0, 1)) ?></div>
          <div>
              <strong><?= e($u['nama']) ?></strong>
              <small><?= e(ucfirst($u['role'])) ?></small>
          </div>
      </div>
      <a href="logout.php" class="btn-logout">Keluar</a>
  </div>
</aside>

<!-- ============================================================
     MAIN
     ============================================================ -->
<main class="main">
  <header class="topbar">
      <button class="sidebar-toggle" id="sidebarToggle"
              aria-label="Buka atau ciutkan menu navigasi"
              aria-expanded="false" type="button">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 6h18M3 12h18M3 18h18"/>
          </svg>
      </button>

      <div class="topbar-info">
          <h1><?= isset($pageTitle) ? e($pageTitle) : 'Dashboard' ?></h1>
          <p class="subtitle">
              <?= isset($pageSubtitle) ? e($pageSubtitle) : 'Sistem Informasi Kasir BMT' ?>
          </p>
      </div>

      <div class="topbar-right">
          <?php if ($currentPage === 'kasir.php'): ?>
              <button type="button" class="btn-cart-toggle" id="cartToggleBtn"
                      aria-label="Buka atau tutup keranjang" aria-expanded="false">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="9" cy="21" r="1"/>
                      <circle cx="20" cy="21" r="1"/>
                      <path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/>
                  </svg>
                  <span class="btn-cart-label">Kasir</span>
                  <span class="cart-badge" id="cartBadge">0</span>
              </button>
          <?php endif; ?>

          <span class="badge-date" id="liveClock">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    style="width:14px;height:14px;">
                  <circle cx="12" cy="12" r="10"/>
                  <path d="M12 6v6l4 2"/>
              </svg>
              <span id="liveClockText"><?= tanggalIndo(date('Y-m-d H:i:s')) ?></span>
          </span>
      </div>
  </header>

  <?php if ($msg = flash('success')): ?>
      <div class="alert alert-success"><?= e($msg) ?></div>
  <?php endif; ?>
  <?php if ($msg = flash('error')): ?>
      <div class="alert alert-error"><?= e($msg) ?></div>
  <?php endif; ?>

  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <div class="content">
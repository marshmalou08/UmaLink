<?php
// Usage: set $pageTitle and $active before including this file.
require_once __DIR__ . '/functions.php';
require_login();
$pageTitle = $pageTitle ?? 'Dashboard';
$active = $active ?? '';
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= e(url('assets/vendor/bootstrap.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<nav class="navbar app-navbar navbar-expand-md">
  <div class="container-fluid">
    <button class="navbar-toggler border-0 me-2 d-md-none" type="button" data-bs-toggle="collapse"
            data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle menu">
      <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
    </button>
    <a class="navbar-brand me-auto" href="<?= e(url('index.php')) ?>">
      <svg class="brand-mark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21V9"/><path d="M12 14c-4 0-6-2-6-6 4 0 6 2 6 6z"/><path d="M12 11c0-4 2-6 6-6 0 4-2 6-6 6z"/></svg>
      <span class="brand-name"><?= e(APP_NAME) ?></span> <small class="d-none d-sm-inline"><?= e(APP_SUBTITLE) ?></small>
    </a>
    <div class="d-flex align-items-center gap-2">
      <span class="user-chip d-none d-sm-inline">
        <?= e($user['full_name']) ?> <span class="badge"><?= e($user['role']) ?></span>
      </span>
      <a class="btn btn-sm btn-outline-light" href="<?= e(url('auth/logout.php')) ?>">Logout</a>
    </div>
  </div>
</nav>
<div class="container-fluid">
  <div class="row">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <main class="col-md-9 col-lg-10 app-content">
      <?php show_flash(); ?>

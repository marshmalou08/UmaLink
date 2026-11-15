<?php
$items = [
    'dashboard' => ['index.php', 'Dashboard'],
    'farmers'   => ['farmers/index.php', 'Farmers'],
    'farms'     => ['farms/index.php', 'Farms'],
    'crops'     => ['crops/index.php', 'Crops'],
    'planting'  => ['planting/index.php', 'Planting Records'],
    'harvest'   => ['harvest/index.php', 'Harvest Records'],
    'reports'   => ['reports/index.php', 'Reports'],
];
?>
<nav id="sidebarMenu" class="col-md-3 col-lg-2 app-sidebar collapse d-md-block">
  <ul class="nav flex-column">
    <li class="nav-heading">Main</li>
    <?php foreach ($items as $key => [$path, $label]): ?>
      <li class="nav-item">
        <a class="nav-link <?= $active === $key ? 'active' : '' ?>" href="<?= e(url($path)) ?>"><?= e($label) ?></a>
      </li>
    <?php endforeach; ?>
    <?php if (is_admin()): ?>
      <li class="nav-heading">Administration</li>
      <li class="nav-item">
        <a class="nav-link <?= $active === 'users' ? 'active' : '' ?>" href="<?= e(url('users/index.php')) ?>">User Accounts</a>
      </li>
    <?php endif; ?>
  </ul>
</nav>

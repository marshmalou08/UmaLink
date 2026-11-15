<?php
// Dashboard - main page after login. Every figure comes from the database.
require_once __DIR__ . '/includes/functions.php';
require_login();
$pageTitle = 'Dashboard';
$active = 'dashboard';
$pdo = db();

// Summary cards
$counts = $pdo->query(
    "SELECT (SELECT COUNT(*) FROM farmer)   AS farmers,
            (SELECT COUNT(*) FROM farm)     AS farms,
            (SELECT COUNT(*) FROM crop)     AS crops,
            (SELECT COUNT(*) FROM planting) AS plantings,
            (SELECT COUNT(*) FROM harvest)  AS harvests"
)->fetch();

// Harvest totals
$totals = $pdo->query(
    'SELECT COALESCE(SUM(quantity_kg), 0) AS total_kg,
            COALESCE(SUM(quantity_kg * selling_price_per_kg), 0) AS total_value
       FROM harvest'
)->fetch();

// Plantings grouped by status (every status is listed, even with 0 records)
$byStatus = $pdo->query(
    'SELECT s.status_description AS label, COUNT(p.planting_id) AS total
       FROM planting_status s
       LEFT JOIN planting p ON p.status_id = s.status_id
      GROUP BY s.status_id, s.status_description
      ORDER BY s.status_id'
)->fetchAll();

// Harvested quantity per crop (top 6) for the bar chart
$byCrop = $pdo->query(
    'SELECT c.crop_name AS label, SUM(h.quantity_kg) AS total
       FROM harvest h
       JOIN planting p ON p.planting_id = h.planting_id
       JOIN crop c     ON c.crop_id = p.crop_id
      GROUP BY c.crop_name
      ORDER BY total DESC
      LIMIT 6'
)->fetchAll();

// Recent records
$recentPlantings = $pdo->query(
    'SELECT p.planting_id, p.planting_date, f.farm_name, c.crop_name, c.variety, s.status_description, p.status_id
       FROM planting p
       JOIN farm f ON f.farm_id = p.farm_id
       JOIN crop c ON c.crop_id = p.crop_id
       JOIN planting_status s ON s.status_id = p.status_id
      ORDER BY p.planting_date DESC, p.planting_id DESC
      LIMIT 5'
)->fetchAll();

$recentHarvests = $pdo->query(
    'SELECT h.harvest_id, h.harvest_date, h.quantity_kg, f.farm_name, c.crop_name, r.remark_description
       FROM harvest h
       JOIN planting p ON p.planting_id = h.planting_id
       JOIN farm f ON f.farm_id = p.farm_id
       JOIN crop c ON c.crop_id = p.crop_id
       JOIN harvest_remark r ON r.remark_id = h.remark_id
      ORDER BY h.harvest_date DESC, h.harvest_id DESC
      LIMIT 5'
)->fetchAll();

$cards = [
    ['Farmers', $counts['farmers'], 'farmers/index.php', ''],
    ['Farms', $counts['farms'], 'farms/index.php', 'accent-tan'],
    ['Crops', $counts['crops'], 'crops/index.php', 'accent-rust'],
    ['Planting Records', $counts['plantings'], 'planting/index.php', 'accent-forest'],
    ['Harvest Records', $counts['harvests'], 'harvest/index.php', 'accent-tan'],
];

// Presentation helpers only: bar widths and percentage shares are derived from the values above.
$statusTotal = array_sum(array_map('intval', array_column($byStatus, 'total')));
$cropMax = $byCrop ? max(array_map('floatval', array_column($byCrop, 'total'))) : 0;

require __DIR__ . '/includes/header.php';
?>
<div class="masthead">
  <div>
    <h1 class="h3 mb-1">Dashboard</h1>
    <small class="text-muted">Welcome, <?= e($user['full_name']) ?>. Here are your records.</small>
  </div>
</div>

<div class="ledger" role="list" aria-label="Record totals">
  <?php foreach ($cards as [$label, $value, $link, $accent]): ?>
    <a href="<?= e(url($link)) ?>" class="ledger-item" role="listitem">
      <span class="ledger-label"><?= e($label) ?></span>
      <span class="ledger-value"><?= number_format((int) $value) ?></span>
      <span class="ledger-more">Open records &rarr;</span>
    </a>
  <?php endforeach; ?>
</div>

<div class="harvest-band">
  <div class="harvest-figure">
    <span class="harvest-label">Total Harvested Quantity</span>
    <span class="harvest-value"><?= number_format((float) $totals['total_kg'], 2) ?><small>kg</small></span>
  </div>
  <div class="harvest-figure">
    <span class="harvest-label">Estimated Harvest Value</span>
    <span class="harvest-value"><?= e(money($totals['total_value'])) ?></span>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header">Plantings by Status</div>
      <div class="card-body">
        <div class="status-bar" role="img" aria-label="Share of plantings by status">
          <?php foreach ($byStatus as $i => $s): ?>
            <?php if ($statusTotal > 0 && (int) $s['total'] > 0): ?>
              <span class="seg-<?= ($i % 6) + 1 ?>" style="width: <?= round(((int) $s['total'] / $statusTotal) * 100, 2) ?>%"></span>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
        <ul class="status-list">
          <?php foreach ($byStatus as $i => $s): ?>
            <li>
              <span class="swatch seg-<?= ($i % 6) + 1 ?>"></span>
              <span><?= e($s['label']) ?></span>
              <span class="count"><?= (int) $s['total'] ?></span>
              <span class="share"><?= $statusTotal > 0 ? round(((int) $s['total'] / $statusTotal) * 100) : 0 ?>%</span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header">Harvested Quantity by Crop <span class="text-muted fw-normal fs-6">(Top 6)</span></div>
      <div class="card-body">
        <ul class="crop-bars">
          <?php foreach ($byCrop as $c): ?>
            <li>
              <span class="name"><?= e($c['label']) ?></span>
              <span class="track"><span class="fill" style="width: <?= $cropMax > 0 ? round(((float) $c['total'] / $cropMax) * 100, 2) : 0 ?>%"></span></span>
              <span class="qty"><?= number_format((float) $c['total'], 2) ?> kg</span>
            </li>
          <?php endforeach; ?>
          <?php if (!$byCrop): ?><li class="text-muted">No harvest records yet.</li><?php endif; ?>
        </ul>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        Recent Planting Records
        <a href="<?= e(url('planting/index.php')) ?>" class="btn btn-sm btn-accent">View all</a>
      </div>
      <ol class="log">
        <?php foreach ($recentPlantings as $r): ?>
          <li>
            <span class="when"><?= e(fmt_date($r['planting_date'])) ?></span>
            <span class="what"><strong><?= e($r['farm_name']) ?></strong><span><?= e($r['crop_name']) ?> (<?= e($r['variety']) ?>)</span></span>
            <span class="tail"><span class="badge <?= (int) $r['status_id'] === 2 ? 'badge-status-harvested' : 'badge-status-growing' ?>"><?= e($r['status_description']) ?></span></span>
          </li>
        <?php endforeach; ?>
        <?php if (!$recentPlantings): ?><li class="empty">No planting records yet.</li><?php endif; ?>
      </ol>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        Recent Harvest Records
        <a href="<?= e(url('harvest/index.php')) ?>" class="btn btn-sm btn-accent">View all</a>
      </div>
      <ol class="log">
        <?php foreach ($recentHarvests as $r): ?>
          <li>
            <span class="when"><?= e(fmt_date($r['harvest_date'])) ?></span>
            <span class="what"><strong><?= e($r['farm_name']) ?></strong><span><?= e($r['crop_name']) ?> &middot; <?= e($r['remark_description']) ?></span></span>
            <span class="tail"><span class="qty"><?= number_format((float) $r['quantity_kg'], 2) ?></span>kg</span>
          </li>
        <?php endforeach; ?>
        <?php if (!$recentHarvests): ?><li class="empty">No harvest records yet.</li><?php endif; ?>
      </ol>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

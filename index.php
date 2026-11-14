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

$chartData = [
    'status' => ['labels' => array_column($byStatus, 'label'), 'values' => array_map('intval', array_column($byStatus, 'total'))],
    'crop'   => ['labels' => array_column($byCrop, 'label'), 'values' => array_map('floatval', array_column($byCrop, 'total'))],
];
$extraScripts = ['assets/vendor/chart.umd.js'];
$inlineScript = '<script>const chartData = ' . json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP) . ';
(function () {
  var colors = ["#606c38", "#dda15e", "#bc6c25", "#283618", "#8a9a5b", "#e9c496"];
  new Chart(document.getElementById("statusChart"), {
    type: "doughnut",
    data: { labels: chartData.status.labels, datasets: [{ data: chartData.status.values, backgroundColor: colors, borderColor: "#fefae0" }] },
    options: { maintainAspectRatio: false, plugins: { legend: { position: "bottom" } } }
  });
  new Chart(document.getElementById("cropChart"), {
    type: "bar",
    data: { labels: chartData.crop.labels, datasets: [{ label: "Kilograms harvested", data: chartData.crop.values, backgroundColor: "#606c38" }] },
    options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
  });
})();</script>';

require __DIR__ . '/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
  <div>
    <h1 class="h3 mb-0">Dashboard</h1>
    <small class="text-muted">Welcome, <?= e($user['full_name']) ?>. Here is the current state of your records.</small>
  </div>
</div>

<div class="row g-3 mb-3">
  <?php foreach ($cards as [$label, $value, $link, $accent]): ?>
    <div class="col-6 col-lg">
      <a href="<?= e(url($link)) ?>" class="text-decoration-none">
        <div class="card stat-card <?= e($accent) ?> h-100">
          <div class="card-body">
            <div class="stat-label"><?= e($label) ?></div>
            <div class="stat-value"><?= number_format((int) $value) ?></div>
          </div>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-6">
    <div class="card stat-card accent-forest h-100">
      <div class="card-body">
        <div class="stat-label">Total Harvested Quantity</div>
        <div class="stat-value"><?= number_format((float) $totals['total_kg'], 2) ?> kg</div>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card stat-card accent-rust h-100">
      <div class="card-body">
        <div class="stat-label">Estimated Harvest Value</div>
        <div class="stat-value"><?= e(money($totals['total_value'])) ?></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header">Plantings by Status</div>
      <div class="card-body">
        <div class="chart-box"><canvas id="statusChart" aria-label="Plantings by status chart" role="img"></canvas></div>
        <table class="table table-sm mt-3">
          <tbody>
          <?php foreach ($byStatus as $s): ?>
            <tr><td><?= e($s['label']) ?></td><td class="text-end fw-bold"><?= (int) $s['total'] ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header">Harvested Quantity by Crop (Top 6)</div>
      <div class="card-body">
        <div class="chart-box"><canvas id="cropChart" aria-label="Harvested quantity by crop chart" role="img"></canvas></div>
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
      <div class="table-responsive">
        <table class="table table-hover table-sm align-middle">
          <thead><tr><th>Date</th><th>Farm</th><th>Crop</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($recentPlantings as $r): ?>
            <tr>
              <td><?= e(fmt_date($r['planting_date'])) ?></td>
              <td><?= e($r['farm_name']) ?></td>
              <td><?= e($r['crop_name']) ?> <small class="text-muted">(<?= e($r['variety']) ?>)</small></td>
              <td><span class="badge <?= (int) $r['status_id'] === 2 ? 'badge-status-harvested' : 'badge-status-growing' ?>"><?= e($r['status_description']) ?></span></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$recentPlantings): ?><tr><td colspan="4" class="text-center text-muted">No planting records yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        Recent Harvest Records
        <a href="<?= e(url('harvest/index.php')) ?>" class="btn btn-sm btn-accent">View all</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover table-sm align-middle">
          <thead><tr><th>Date</th><th>Farm</th><th>Crop</th><th class="text-end">Qty (kg)</th><th>Remarks</th></tr></thead>
          <tbody>
          <?php foreach ($recentHarvests as $r): ?>
            <tr>
              <td><?= e(fmt_date($r['harvest_date'])) ?></td>
              <td><?= e($r['farm_name']) ?></td>
              <td><?= e($r['crop_name']) ?></td>
              <td class="text-end"><?= number_format((float) $r['quantity_kg'], 2) ?></td>
              <td><?= e($r['remark_description']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$recentHarvests): ?><tr><td colspan="5" class="text-center text-muted">No harvest records yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

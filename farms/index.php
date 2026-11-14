<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$pageTitle = 'Farms';
$active = 'farms';

$q = get('q');
$irrigation = get('irrigation');
$soil = get('soil');
$where = [];
$params = [];
if ($q !== '') {
    $where[] = "(fa.farm_name ILIKE :q OR fa.location ILIKE :q OR (fr.first_name || ' ' || fr.last_name) ILIKE :q)";
    $params[':q'] = '%' . $q . '%';
}
if ($irrigation !== '' && ctype_digit($irrigation)) {
    $where[] = 'fa.irrigation_type_id = :irr';
    $params[':irr'] = (int) $irrigation;
}
if ($soil !== '' && ctype_digit($soil)) {
    $where[] = 'fa.soil_type_id = :soil';
    $params[':soil'] = (int) $soil;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

[$sortKey, $sortDir, $orderBy] = sort_clause([
    'id' => 'fa.farm_id', 'farm' => 'fa.farm_name', 'farmer' => 'fr.last_name', 'location' => 'fa.location',
    'area' => 'fa.area_hectares', 'soil' => 's.soil_type_description', 'irrigation' => 'i.irrigation_type_description',
], 'id');

$from = 'FROM farm fa
         JOIN farmer fr ON fr.farmer_id = fa.farmer_id
         JOIN soil_type s ON s.soil_type_id = fa.soil_type_id
         JOIN irrigation_type i ON i.irrigation_type_id = fa.irrigation_type_id';

[$page, $pages, $total, $offset] = paginate("SELECT COUNT(*) $from $whereSql", $params);
$st = db()->prepare(
    "SELECT fa.farm_id, fa.farm_name, fa.location, fa.area_hectares, fr.first_name, fr.last_name,
            s.soil_type_description, i.irrigation_type_description
       $from $whereSql
      ORDER BY $orderBy, fa.farm_id
      LIMIT " . PER_PAGE . " OFFSET $offset"
);
$st->execute($params);
$rows = $st->fetchAll();
$irrOptions = lookup('irrigation_type', 'irrigation_type_id', 'irrigation_type_description');
$soilOptions = lookup('soil_type', 'soil_type_id', 'soil_type_description');

require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <h1 class="h3 mb-0">Farms</h1>
  <a href="<?= e(url('farms/add.php')) ?>" class="btn btn-primary">+ Add Farm</a>
</div>

<form method="get" class="filter-bar row g-2 mb-3">
  <div class="col-md-4">
    <label for="q" class="form-label mb-1">Search</label>
    <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Farm, location or farmer">
  </div>
  <div class="col-md-3">
    <label for="irrigation" class="form-label mb-1">Irrigation Type</label>
    <select class="form-select" id="irrigation" name="irrigation"><?= options($irrOptions, $irrigation, 'All irrigation types') ?></select>
  </div>
  <div class="col-md-3">
    <label for="soil" class="form-label mb-1">Soil Type</label>
    <select class="form-select" id="soil" name="soil"><?= options($soilOptions, $soil, 'All soil types') ?></select>
  </div>
  <div class="col-md-2 d-flex align-items-end gap-2">
    <button class="btn btn-primary" type="submit">Filter</button>
    <a class="btn btn-outline-secondary" href="<?= e(url('farms/index.php')) ?>">Reset</a>
  </div>
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <?= sort_th('ID', 'id', $sortKey, $sortDir) ?>
          <?= sort_th('Farm Name', 'farm', $sortKey, $sortDir) ?>
          <?= sort_th('Farmer', 'farmer', $sortKey, $sortDir) ?>
          <?= sort_th('Location', 'location', $sortKey, $sortDir) ?>
          <?= sort_th('Area (ha)', 'area', $sortKey, $sortDir) ?>
          <?= sort_th('Soil Type', 'soil', $sortKey, $sortDir) ?>
          <?= sort_th('Irrigation', 'irrigation', $sortKey, $sortDir) ?>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= (int) $r['farm_id'] ?></td>
          <td><?= e($r['farm_name']) ?></td>
          <td><?= e($r['last_name'] . ', ' . $r['first_name']) ?></td>
          <td><?= e($r['location']) ?></td>
          <td class="text-end"><?= number_format((float) $r['area_hectares'], 2) ?></td>
          <td><?= e($r['soil_type_description']) ?></td>
          <td><?= e($r['irrigation_type_description']) ?></td>
          <td class="text-end"><?= row_actions('farms', (int) $r['farm_id'], $r['farm_name']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">No farms found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?= pagination_html($page, $pages, $total) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

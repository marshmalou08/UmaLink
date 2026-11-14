<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$pageTitle = 'Harvest Records';
$active = 'harvest';

$q = get('q');
$grade = get('grade');
$remark = get('remark');
$from = get('date_from');
$to = get('date_to');
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(f.farm_name ILIKE :q OR c.crop_name ILIKE :q OR c.variety ILIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
if ($grade !== '' && ctype_digit($grade)) {
    $where[] = 'h.grade_id = :grade';
    $params[':grade'] = (int) $grade;
}
if ($remark !== '' && ctype_digit($remark)) {
    $where[] = 'h.remark_id = :remark';
    $params[':remark'] = (int) $remark;
}
if ($from !== '' && valid_date($from)) {
    $where[] = 'h.harvest_date >= :from';
    $params[':from'] = $from;
}
if ($to !== '' && valid_date($to)) {
    $where[] = 'h.harvest_date <= :to';
    $params[':to'] = $to;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

[$sortKey, $sortDir, $orderBy] = sort_clause([
    'id' => 'h.harvest_id', 'farm' => 'f.farm_name', 'crop' => 'c.crop_name', 'date' => 'h.harvest_date',
    'qty' => 'h.quantity_kg', 'price' => 'h.selling_price_per_kg', 'value' => 'est_value',
    'grade' => 'g.grade_description', 'remark' => 'rm.remark_description',
], 'date', 'desc');

$from_sql = 'FROM harvest h
             JOIN planting p ON p.planting_id = h.planting_id
             JOIN farm f ON f.farm_id = p.farm_id
             JOIN crop c ON c.crop_id = p.crop_id
             JOIN quality_grade g ON g.grade_id = h.grade_id
             JOIN harvest_remark rm ON rm.remark_id = h.remark_id';

[$page, $pages, $total, $offset] = paginate("SELECT COUNT(*) $from_sql $whereSql", $params);
$st = db()->prepare(
    "SELECT h.harvest_id, h.planting_id, h.harvest_date, h.quantity_kg, h.selling_price_per_kg,
            (h.quantity_kg * h.selling_price_per_kg) AS est_value,
            f.farm_name, c.crop_name, c.variety, g.grade_description, rm.remark_description
       $from_sql $whereSql ORDER BY $orderBy, h.harvest_id DESC LIMIT " . PER_PAGE . " OFFSET $offset"
);
$st->execute($params);
$rows = $st->fetchAll();

// Totals for everything that matches the filter (not only the current page)
$sum = db()->prepare("SELECT COALESCE(SUM(h.quantity_kg),0) AS qty, COALESCE(SUM(h.quantity_kg * h.selling_price_per_kg),0) AS val $from_sql $whereSql");
$sum->execute($params);
$totals = $sum->fetch();

$grades = lookup('quality_grade', 'grade_id', 'grade_description');
$remarks = lookup('harvest_remark', 'remark_id', 'remark_description');

require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <h1 class="h3 mb-0">Harvest Records</h1>
  <a href="<?= e(url('harvest/add.php')) ?>" class="btn btn-primary">+ Add Harvest Record</a>
</div>

<form method="get" class="filter-bar row g-2 mb-3">
  <div class="col-md-4">
    <label for="q" class="form-label mb-1">Search</label>
    <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Farm, crop or variety">
  </div>
  <div class="col-md-4">
    <label for="grade" class="form-label mb-1">Quality Grade</label>
    <select class="form-select" id="grade" name="grade"><?= options($grades, $grade, 'All grades') ?></select>
  </div>
  <div class="col-md-4">
    <label for="remark" class="form-label mb-1">Remarks</label>
    <select class="form-select" id="remark" name="remark"><?= options($remarks, $remark, 'All remarks') ?></select>
  </div>
  <div class="col-md-3">
    <label for="date_from" class="form-label mb-1">Harvested from</label>
    <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($from) ?>">
  </div>
  <div class="col-md-3">
    <label for="date_to" class="form-label mb-1">Harvested to</label>
    <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($to) ?>">
  </div>
  <div class="col-md-3 d-flex align-items-end gap-2">
    <button class="btn btn-primary" type="submit">Filter</button>
    <a class="btn btn-outline-secondary" href="<?= e(url('harvest/index.php')) ?>">Reset</a>
  </div>
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <?= sort_th('ID', 'id', $sortKey, $sortDir) ?>
          <?= sort_th('Farm', 'farm', $sortKey, $sortDir) ?>
          <?= sort_th('Crop', 'crop', $sortKey, $sortDir) ?>
          <?= sort_th('Harvest Date', 'date', $sortKey, $sortDir) ?>
          <?= sort_th('Qty (kg)', 'qty', $sortKey, $sortDir) ?>
          <?= sort_th('Price/kg', 'price', $sortKey, $sortDir) ?>
          <?= sort_th('Est. Value', 'value', $sortKey, $sortDir) ?>
          <?= sort_th('Grade', 'grade', $sortKey, $sortDir) ?>
          <?= sort_th('Remarks', 'remark', $sortKey, $sortDir) ?>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= (int) $r['harvest_id'] ?></td>
          <td><?= e($r['farm_name']) ?></td>
          <td><?= e($r['crop_name']) ?> <small class="text-muted">(<?= e($r['variety']) ?>)</small></td>
          <td><?= e(fmt_date($r['harvest_date'])) ?></td>
          <td class="text-end"><?= number_format((float) $r['quantity_kg'], 2) ?></td>
          <td class="text-end"><?= e(money($r['selling_price_per_kg'])) ?></td>
          <td class="text-end"><?= e(money($r['est_value'])) ?></td>
          <td><span class="badge badge-grade"><?= e($r['grade_description']) ?></span></td>
          <td><?= e($r['remark_description']) ?></td>
          <td class="text-end"><?= row_actions('harvest', (int) $r['harvest_id'], 'harvest record #' . $r['harvest_id'] . ' (' . $r['crop_name'] . ')') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="10" class="text-center text-muted py-4">No harvest records found.</td></tr>
      <?php endif; ?>
      </tbody>
      <?php if ($rows): ?>
      <tfoot>
        <tr>
          <th colspan="4" class="text-end">Totals for all matching records:</th>
          <th class="text-end"><?= number_format((float) $totals['qty'], 2) ?></th>
          <th></th>
          <th class="text-end"><?= e(money($totals['val'])) ?></th>
          <th colspan="3"></th>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>
<?= pagination_html($page, $pages, $total) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

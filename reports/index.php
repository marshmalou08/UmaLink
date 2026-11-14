<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$pageTitle = 'Reports';
$active = 'reports';
$pdo = db();

$reports = [
    'farmers_farms'   => 'Farmers and their Farms',
    'crops_per_farm'  => 'Crops Planted per Farm',
    'planting_status' => 'Planting Records by Status',
    'harvest_records' => 'Harvest Records',
    'total_quantity'  => 'Total Harvested Quantity',
    'harvest_value'   => 'Estimated Harvest Value',
];
$r = get('r');
if (!isset($reports[$r])) {
    $r = 'farmers_farms';
}

// ----- common filters
$from = valid_date(get('date_from')) ? get('date_from') : '';
$to = valid_date(get('date_to')) ? get('date_to') : '';
$dateError = ($from !== '' && $to !== '' && $to < $from) ? 'The "to" date cannot be earlier than the "from" date.' : '';
if ($dateError) {
    $to = '';
}
$where = [];
$params = [];
$dateLabel = 'Date';
$extra = ''; // extra filter controls (HTML)

function add_range(array &$where, array &$params, string $column, string $from, string $to): void
{
    if ($from !== '') {
        $where[] = "$column >= :from";
        $params[':from'] = $from;
    }
    if ($to !== '') {
        $where[] = "$column <= :to";
        $params[':to'] = $to;
    }
}

$cols = [];       // [label, key, type]  type: text|date|num|money|int
$sumKeys = [];    // keys to total in the footer
$summary = [];    // optional summary table rows [label, value]
$sql = '';
$subtitle = '';

switch ($r) {
    case 'farmers_farms':
        $dateLabel = 'Farmer registered';
        $q = get('q');
        if ($q !== '') {
            $where[] = "((fr.first_name || ' ' || fr.last_name) ILIKE :q OR fr.address ILIKE :q OR fa.farm_name ILIKE :q)";
            $params[':q'] = "%$q%";
        }
        add_range($where, $params, 'fr.date_registered', $from, $to);
        $extra = '<div class="col-md-4"><label for="q" class="form-label mb-1">Search</label>'
               . '<input type="search" class="form-control" id="q" name="q" value="' . e($q) . '" placeholder="Farmer, address or farm"></div>';
        $cols = [['Farmer', 'farmer', 'text'], ['Contact', 'contact_number', 'text'], ['Address', 'address', 'text'],
                 ['Farm', 'farm_name', 'text'], ['Location', 'location', 'text'], ['Area (ha)', 'area_hectares', 'num'],
                 ['Soil Type', 'soil', 'text'], ['Irrigation', 'irrigation', 'text']];
        $sumKeys = ['area_hectares'];
        $sql = "SELECT fr.last_name || ', ' || fr.first_name AS farmer, fr.contact_number, fr.address,
                       COALESCE(fa.farm_name, '(no farm)') AS farm_name, fa.location, fa.area_hectares,
                       s.soil_type_description AS soil, i.irrigation_type_description AS irrigation
                  FROM farmer fr
                  LEFT JOIN farm fa ON fa.farmer_id = fr.farmer_id
                  LEFT JOIN soil_type s ON s.soil_type_id = fa.soil_type_id
                  LEFT JOIN irrigation_type i ON i.irrigation_type_id = fa.irrigation_type_id
                  %WHERE% ORDER BY fr.last_name, fr.first_name, fa.farm_name";
        break;

    case 'crops_per_farm':
        $dateLabel = 'Planting date';
        $farm = get('farm');
        $cat = get('category');
        if (ctype_digit($farm) && $farm !== '') {
            $where[] = 'fa.farm_id = :farm';
            $params[':farm'] = (int) $farm;
        }
        if (ctype_digit($cat) && $cat !== '') {
            $where[] = 'c.category_id = :cat';
            $params[':cat'] = (int) $cat;
        }
        add_range($where, $params, 'p.planting_date', $from, $to);
        $farmRows = $pdo->query('SELECT farm_id AS id, farm_name AS label FROM farm ORDER BY farm_name')->fetchAll();
        $catRows = lookup('crop_category', 'category_id', 'category_description');
        $extra = '<div class="col-md-3"><label for="farm" class="form-label mb-1">Farm</label><select class="form-select" id="farm" name="farm">'
               . options($farmRows, $farm, 'All farms') . '</select></div>'
               . '<div class="col-md-3"><label for="category" class="form-label mb-1">Crop Category</label><select class="form-select" id="category" name="category">'
               . options($catRows, $cat, 'All categories') . '</select></div>';
        $cols = [['Farm', 'farm_name', 'text'], ['Farmer', 'farmer', 'text'], ['Crop', 'crop_name', 'text'], ['Variety', 'variety', 'text'],
                 ['Category', 'category', 'text'], ['Planting Date', 'planting_date', 'date'], ['Area Planted (ha)', 'area_planted', 'num'],
                 ['Status', 'status', 'text']];
        $sumKeys = ['area_planted'];
        $sql = "SELECT fa.farm_name, fr.last_name || ', ' || fr.first_name AS farmer, c.crop_name, c.variety,
                       cc.category_description AS category, p.planting_date, p.area_planted, ps.status_description AS status
                  FROM planting p
                  JOIN farm fa ON fa.farm_id = p.farm_id
                  JOIN farmer fr ON fr.farmer_id = fa.farmer_id
                  JOIN crop c ON c.crop_id = p.crop_id
                  JOIN crop_category cc ON cc.category_id = c.category_id
                  JOIN planting_status ps ON ps.status_id = p.status_id
                  %WHERE% ORDER BY fa.farm_name, p.planting_date";
        break;

    case 'planting_status':
        $dateLabel = 'Planting date';
        $status = get('status');
        if (ctype_digit($status) && $status !== '') {
            $where[] = 'p.status_id = :status';
            $params[':status'] = (int) $status;
        }
        add_range($where, $params, 'p.planting_date', $from, $to);
        $statusRows = lookup('planting_status', 'status_id', 'status_description');
        $extra = '<div class="col-md-3"><label for="status" class="form-label mb-1">Status</label><select class="form-select" id="status" name="status">'
               . options($statusRows, $status, 'All statuses') . '</select></div>';
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $st = $pdo->prepare("SELECT ps.status_description AS label, COUNT(*) AS total, SUM(p.area_planted) AS area
                               FROM planting p JOIN planting_status ps ON ps.status_id = p.status_id
                               $whereSql GROUP BY ps.status_id, ps.status_description ORDER BY ps.status_id");
        $st->execute($params);
        foreach ($st->fetchAll() as $s) {
            $summary[] = [$s['label'], (int) $s['total'] . ' record(s) — ' . number_format((float) $s['area'], 2) . ' ha planted'];
        }
        $cols = [['Planting ID', 'planting_id', 'int'], ['Status', 'status', 'text'], ['Farm', 'farm_name', 'text'], ['Crop', 'crop_name', 'text'],
                 ['Planting Date', 'planting_date', 'date'], ['Expected Harvest', 'expected_harvest_date', 'date'], ['Area (ha)', 'area_planted', 'num']];
        $sumKeys = ['area_planted'];
        $sql = "SELECT p.planting_id, ps.status_description AS status, fa.farm_name, c.crop_name || ' (' || c.variety || ')' AS crop_name,
                       p.planting_date, p.expected_harvest_date, p.area_planted
                  FROM planting p
                  JOIN farm fa ON fa.farm_id = p.farm_id
                  JOIN crop c ON c.crop_id = p.crop_id
                  JOIN planting_status ps ON ps.status_id = p.status_id
                  %WHERE% ORDER BY ps.status_id, p.planting_date";
        break;

    case 'harvest_records':
        $dateLabel = 'Harvest date';
        $grade = get('grade');
        if (ctype_digit($grade) && $grade !== '') {
            $where[] = 'h.grade_id = :grade';
            $params[':grade'] = (int) $grade;
        }
        add_range($where, $params, 'h.harvest_date', $from, $to);
        $gradeRows = lookup('quality_grade', 'grade_id', 'grade_description');
        $extra = '<div class="col-md-3"><label for="grade" class="form-label mb-1">Quality Grade</label><select class="form-select" id="grade" name="grade">'
               . options($gradeRows, $grade, 'All grades') . '</select></div>';
        $cols = [['Harvest ID', 'harvest_id', 'int'], ['Harvest Date', 'harvest_date', 'date'], ['Farm', 'farm_name', 'text'], ['Crop', 'crop_name', 'text'],
                 ['Qty (kg)', 'quantity_kg', 'num'], ['Price/kg', 'selling_price_per_kg', 'money'], ['Grade', 'grade', 'text'], ['Remarks', 'remark', 'text']];
        $sumKeys = ['quantity_kg'];
        $sql = "SELECT h.harvest_id, h.harvest_date, fa.farm_name, c.crop_name || ' (' || c.variety || ')' AS crop_name, h.quantity_kg,
                       h.selling_price_per_kg, g.grade_description AS grade, rm.remark_description AS remark
                  FROM harvest h
                  JOIN planting p ON p.planting_id = h.planting_id
                  JOIN farm fa ON fa.farm_id = p.farm_id
                  JOIN crop c ON c.crop_id = p.crop_id
                  JOIN quality_grade g ON g.grade_id = h.grade_id
                  JOIN harvest_remark rm ON rm.remark_id = h.remark_id
                  %WHERE% ORDER BY h.harvest_date DESC, h.harvest_id DESC";
        break;

    case 'total_quantity':
        $dateLabel = 'Harvest date';
        $cat = get('category');
        if (ctype_digit($cat) && $cat !== '') {
            $where[] = 'c.category_id = :cat';
            $params[':cat'] = (int) $cat;
        }
        add_range($where, $params, 'h.harvest_date', $from, $to);
        $catRows = lookup('crop_category', 'category_id', 'category_description');
        $extra = '<div class="col-md-3"><label for="category" class="form-label mb-1">Crop Category</label><select class="form-select" id="category" name="category">'
               . options($catRows, $cat, 'All categories') . '</select></div>';
        $cols = [['Crop', 'crop_name', 'text'], ['Category', 'category', 'text'], ['Harvest Records', 'records', 'int'], ['Total Quantity (kg)', 'total_kg', 'num']];
        $sumKeys = ['records', 'total_kg'];
        $sql = "SELECT c.crop_name, cc.category_description AS category, COUNT(h.harvest_id) AS records, SUM(h.quantity_kg) AS total_kg
                  FROM harvest h
                  JOIN planting p ON p.planting_id = h.planting_id
                  JOIN crop c ON c.crop_id = p.crop_id
                  JOIN crop_category cc ON cc.category_id = c.category_id
                  %WHERE% GROUP BY c.crop_name, cc.category_description ORDER BY total_kg DESC";
        break;

    case 'harvest_value':
        $dateLabel = 'Harvest date';
        $crop = get('crop');
        if (ctype_digit($crop) && $crop !== '') {
            $where[] = 'p.crop_id = :crop';
            $params[':crop'] = (int) $crop;
        }
        add_range($where, $params, 'h.harvest_date', $from, $to);
        $cropRows = $pdo->query("SELECT crop_id AS id, crop_name || ' (' || variety || ')' AS label FROM crop ORDER BY crop_name, variety")->fetchAll();
        $extra = '<div class="col-md-3"><label for="crop" class="form-label mb-1">Crop</label><select class="form-select" id="crop" name="crop">'
               . options($cropRows, $crop, 'All crops') . '</select></div>';
        $cols = [['Harvest ID', 'harvest_id', 'int'], ['Harvest Date', 'harvest_date', 'date'], ['Farm', 'farm_name', 'text'], ['Crop', 'crop_name', 'text'],
                 ['Qty (kg)', 'quantity_kg', 'num'], ['Price/kg', 'selling_price_per_kg', 'money'], ['Estimated Value', 'est_value', 'money']];
        $sumKeys = ['quantity_kg', 'est_value'];
        $subtitle = 'Estimated Value = Quantity Harvested × Selling Price per kg';
        $sql = "SELECT h.harvest_id, h.harvest_date, fa.farm_name, c.crop_name || ' (' || c.variety || ')' AS crop_name, h.quantity_kg,
                       h.selling_price_per_kg, (h.quantity_kg * h.selling_price_per_kg) AS est_value
                  FROM harvest h
                  JOIN planting p ON p.planting_id = h.planting_id
                  JOIN farm fa ON fa.farm_id = p.farm_id
                  JOIN crop c ON c.crop_id = p.crop_id
                  %WHERE% ORDER BY h.harvest_date DESC, h.harvest_id DESC";
        break;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$st = $pdo->prepare(str_replace('%WHERE%', $whereSql, $sql));
$st->execute($params);
$rows = $st->fetchAll();

$totals = array_fill_keys($sumKeys, 0.0);
foreach ($rows as $row) {
    foreach ($sumKeys as $k) {
        $totals[$k] += (float) $row[$k];
    }
}

function cell($value, string $type): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    switch ($type) {
        case 'date':
            return e(fmt_date($value));
        case 'num':
            return number_format((float) $value, 2);
        case 'int':
            return number_format((int) $value);
        case 'money':
            return e(money($value));
        default:
            return e($value);
    }
}

require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <h1 class="h3 mb-0">Reports</h1>
  <button type="button" class="btn btn-accent no-print" onclick="window.print()">Print Report</button>
</div>

<ul class="nav nav-pills report-tabs mb-3 flex-wrap gap-1">
  <?php foreach ($reports as $key => $label): ?>
    <li class="nav-item"><a class="nav-link <?= $key === $r ? 'active' : '' ?>" href="<?= e(url('reports/index.php?r=' . $key)) ?>"><?= e($label) ?></a></li>
  <?php endforeach; ?>
</ul>

<form method="get" class="filter-bar row g-2 mb-3">
  <input type="hidden" name="r" value="<?= e($r) ?>">
  <?= $extra ?>
  <div class="col-md-2">
    <label for="date_from" class="form-label mb-1"><?= e($dateLabel) ?> from</label>
    <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($from) ?>">
  </div>
  <div class="col-md-2">
    <label for="date_to" class="form-label mb-1"><?= e($dateLabel) ?> to</label>
    <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($to) ?>">
  </div>
  <div class="col-md-2 d-flex align-items-end gap-2">
    <button class="btn btn-primary" type="submit">Generate</button>
    <a class="btn btn-outline-secondary" href="<?= e(url('reports/index.php?r=' . $r)) ?>">Reset</a>
  </div>
</form>
<?php if ($dateError): ?><div class="alert alert-warning"><?= e($dateError) ?></div><?php endif; ?>

<div class="card">
  <div class="card-header">
    <?= e($reports[$r]) ?>
    <?php if ($from || $to): ?>
      <small class="text-muted fw-normal"> — <?= e($dateLabel) ?>: <?= $from ? e(fmt_date($from)) : 'start' ?> to <?= $to ? e(fmt_date($to)) : 'latest' ?></small>
    <?php endif; ?>
    <?php if ($subtitle): ?><div class="small text-muted fw-normal"><?= e($subtitle) ?></div><?php endif; ?>
  </div>
  <?php if ($summary): ?>
    <div class="card-body border-bottom">
      <div class="row g-2">
        <?php foreach ($summary as [$label, $value]): ?>
          <div class="col-md-6"><strong><?= e($label) ?>:</strong> <?= e($value) ?></div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <?php foreach ($cols as [$label, $key, $type]): ?>
            <th class="<?= in_array($type, ['num', 'money', 'int'], true) ? 'text-end' : '' ?>"><?= e($label) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <?php foreach ($cols as [$label, $key, $type]): ?>
            <td class="<?= in_array($type, ['num', 'money', 'int'], true) ? 'text-end' : '' ?>"><?= cell($row[$key], $type) ?></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="<?= count($cols) ?>" class="text-center text-muted py-4">No records match the selected filters.</td></tr>
      <?php endif; ?>
      </tbody>
      <?php if ($rows && $sumKeys): ?>
      <tfoot>
        <tr>
          <?php foreach ($cols as $i => [$label, $key, $type]): ?>
            <?php if ($i === 0): ?>
              <th>Total (<?= count($rows) ?> row<?= count($rows) === 1 ? '' : 's' ?>)</th>
            <?php elseif (isset($totals[$key])): ?>
              <th class="text-end"><?= cell($totals[$key], $type) ?></th>
            <?php else: ?>
              <th></th>
            <?php endif; ?>
          <?php endforeach; ?>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

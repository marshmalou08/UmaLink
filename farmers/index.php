<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$pageTitle = 'Farmers';
$active = 'farmers';

$q = get('q');
$address = get('address');
$where = [];
$params = [];
if ($q !== '') {
    $where[] = "(f.first_name ILIKE :q OR f.last_name ILIKE :q OR (f.first_name || ' ' || f.last_name) ILIKE :q
                 OR f.contact_number ILIKE :q OR f.address ILIKE :q)";
    $params[':q'] = '%' . $q . '%';
}
if ($address !== '') {
    $where[] = 'f.address = :address';
    $params[':address'] = $address;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

[$sortKey, $sortDir, $orderBy] = sort_clause([
    'id' => 'f.farmer_id', 'name' => 'f.last_name', 'contact' => 'f.contact_number',
    'address' => 'f.address', 'registered' => 'f.date_registered', 'farms' => 'farm_count',
], 'id');

[$page, $pages, $total, $offset] = paginate("SELECT COUNT(*) FROM farmer f $whereSql", $params);

$st = db()->prepare(
    "SELECT f.farmer_id, f.first_name, f.last_name, f.contact_number, f.address, f.date_registered,
            (SELECT COUNT(*) FROM farm fa WHERE fa.farmer_id = f.farmer_id) AS farm_count
       FROM farmer f $whereSql
      ORDER BY $orderBy, f.farmer_id
      LIMIT " . PER_PAGE . " OFFSET $offset"
);
$st->execute($params);
$rows = $st->fetchAll();
$addresses = db()->query('SELECT DISTINCT address FROM farmer ORDER BY address')->fetchAll(PDO::FETCH_COLUMN);

require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <h1 class="h3 mb-0">Farmers</h1>
  <a href="<?= e(url('farmers/add.php')) ?>" class="btn btn-primary">+ Add Farmer</a>
</div>

<form method="get" class="filter-bar row g-2 mb-3">
  <div class="col-md-5">
    <label for="q" class="form-label mb-1">Search</label>
    <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Name, contact number or address">
  </div>
  <div class="col-md-4">
    <label for="address" class="form-label mb-1">Address</label>
    <select class="form-select" id="address" name="address">
      <option value="">All addresses</option>
      <?php foreach ($addresses as $a): ?>
        <option value="<?= e($a) ?>" <?= $a === $address ? 'selected' : '' ?>><?= e($a) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3 d-flex align-items-end gap-2">
    <button class="btn btn-primary" type="submit">Filter</button>
    <a class="btn btn-outline-secondary" href="<?= e(url('farmers/index.php')) ?>">Reset</a>
  </div>
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <?= sort_th('ID', 'id', $sortKey, $sortDir) ?>
          <?= sort_th('Name', 'name', $sortKey, $sortDir) ?>
          <?= sort_th('Contact Number', 'contact', $sortKey, $sortDir) ?>
          <?= sort_th('Address', 'address', $sortKey, $sortDir) ?>
          <?= sort_th('Date Registered', 'registered', $sortKey, $sortDir) ?>
          <?= sort_th('Farms', 'farms', $sortKey, $sortDir) ?>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= (int) $r['farmer_id'] ?></td>
          <td><?= e($r['last_name'] . ', ' . $r['first_name']) ?></td>
          <td><?= e($r['contact_number']) ?></td>
          <td><?= e($r['address']) ?></td>
          <td><?= e(fmt_date($r['date_registered'])) ?></td>
          <td><?= (int) $r['farm_count'] ?></td>
          <td class="text-end"><?= row_actions('farmers', (int) $r['farmer_id'], $r['first_name'] . ' ' . $r['last_name']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No farmers found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?= pagination_html($page, $pages, $total) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$id = (int) ($_GET['id'] ?? 0);

$st = db()->prepare(
    'SELECT f.*, cu.full_name AS created_name, uu.full_name AS updated_name
       FROM farmer f
       LEFT JOIN users cu ON cu.user_id = f.created_by
       LEFT JOIN users uu ON uu.user_id = f.updated_by
      WHERE f.farmer_id = :id'
);
$st->execute([':id' => $id]);
$r = $st->fetch();
if (!$r) {
    flash('danger', 'Farmer record was not found.');
    redirect('farmers/index.php');
}

$fs = db()->prepare(
    'SELECT fa.farm_id, fa.farm_name, fa.location, fa.area_hectares, s.soil_type_description, i.irrigation_type_description
       FROM farm fa
       JOIN soil_type s ON s.soil_type_id = fa.soil_type_id
       JOIN irrigation_type i ON i.irrigation_type_id = fa.irrigation_type_id
      WHERE fa.farmer_id = :id ORDER BY fa.farm_name'
);
$fs->execute([':id' => $id]);
$farms = $fs->fetchAll();

$pageTitle = 'Farmer Details';
$active = 'farmers';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <h1 class="h3 mb-0">Farmer Details</h1>
  <div class="d-flex gap-2">
    <a href="<?= e(url('farmers/edit.php?id=' . $id)) ?>" class="btn btn-primary">Edit</a>
    <a href="<?= e(url('farmers/index.php')) ?>" class="btn btn-outline-secondary">Back to list</a>
  </div>
</div>
<div class="card mb-3">
  <div class="card-header"><?= e($r['first_name'] . ' ' . $r['last_name']) ?></div>
  <div class="card-body">
    <dl class="row detail-list mb-0">
      <dt class="col-sm-3">Farmer ID</dt><dd class="col-sm-9"><?= (int) $r['farmer_id'] ?></dd>
      <dt class="col-sm-3">Contact Number</dt><dd class="col-sm-9"><?= e($r['contact_number']) ?></dd>
      <dt class="col-sm-3">Address</dt><dd class="col-sm-9"><?= e($r['address']) ?></dd>
      <dt class="col-sm-3">Date Registered</dt><dd class="col-sm-9"><?= e(fmt_date($r['date_registered'])) ?></dd>
      <dt class="col-sm-3">Created</dt><dd class="col-sm-9"><?= e(fmt_datetime($r['created_at'])) ?> by <?= e($r['created_name'] ?? 'N/A') ?></dd>
      <dt class="col-sm-3">Last Updated</dt><dd class="col-sm-9"><?= e(fmt_datetime($r['updated_at'])) ?> by <?= e($r['updated_name'] ?? 'N/A') ?></dd>
    </dl>
  </div>
</div>
<div class="card">
  <div class="card-header">Farms of this Farmer (<?= count($farms) ?>)</div>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead><tr><th>Farm</th><th>Location</th><th class="text-end">Area (ha)</th><th>Soil Type</th><th>Irrigation</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($farms as $f): ?>
        <tr>
          <td><?= e($f['farm_name']) ?></td><td><?= e($f['location']) ?></td>
          <td class="text-end"><?= number_format((float) $f['area_hectares'], 2) ?></td>
          <td><?= e($f['soil_type_description']) ?></td><td><?= e($f['irrigation_type_description']) ?></td>
          <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('farms/view.php?id=' . $f['farm_id'])) ?>">View</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$farms): ?><tr><td colspan="6" class="text-center text-muted py-3">This farmer has no farms yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

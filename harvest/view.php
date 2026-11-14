<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$id = (int) ($_GET['id'] ?? 0);

$st = db()->prepare(
    'SELECT h.*, (h.quantity_kg * h.selling_price_per_kg) AS est_value, p.planting_date, p.farm_id, p.crop_id,
            f.farm_name, c.crop_name, c.variety, g.grade_description, rm.remark_description,
            cu.full_name AS created_name, uu.full_name AS updated_name
       FROM harvest h
       JOIN planting p ON p.planting_id = h.planting_id
       JOIN farm f ON f.farm_id = p.farm_id
       JOIN crop c ON c.crop_id = p.crop_id
       JOIN quality_grade g ON g.grade_id = h.grade_id
       JOIN harvest_remark rm ON rm.remark_id = h.remark_id
       LEFT JOIN users cu ON cu.user_id = h.created_by
       LEFT JOIN users uu ON uu.user_id = h.updated_by
      WHERE h.harvest_id = :id'
);
$st->execute([':id' => $id]);
$r = $st->fetch();
if (!$r) {
    flash('danger', 'Harvest record was not found.');
    redirect('harvest/index.php');
}

$pageTitle = 'Harvest Details';
$active = 'harvest';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <h1 class="h3 mb-0">Harvest Details</h1>
  <div class="d-flex gap-2">
    <a href="<?= e(url('harvest/edit.php?id=' . $id)) ?>" class="btn btn-primary">Edit</a>
    <a href="<?= e(url('harvest/index.php')) ?>" class="btn btn-outline-secondary">Back to list</a>
  </div>
</div>
<div class="card">
  <div class="card-header">Harvest #<?= (int) $r['harvest_id'] ?> — <?= e($r['crop_name']) ?> at <?= e($r['farm_name']) ?></div>
  <div class="card-body">
    <dl class="row detail-list mb-0">
      <dt class="col-sm-3">Planting Record</dt>
      <dd class="col-sm-9"><a href="<?= e(url('planting/view.php?id=' . $r['planting_id'])) ?>">#<?= (int) $r['planting_id'] ?></a> (planted <?= e(fmt_date($r['planting_date'])) ?>)</dd>
      <dt class="col-sm-3">Farm</dt><dd class="col-sm-9"><a href="<?= e(url('farms/view.php?id=' . $r['farm_id'])) ?>"><?= e($r['farm_name']) ?></a></dd>
      <dt class="col-sm-3">Crop</dt><dd class="col-sm-9"><a href="<?= e(url('crops/view.php?id=' . $r['crop_id'])) ?>"><?= e($r['crop_name']) ?></a> (<?= e($r['variety']) ?>)</dd>
      <dt class="col-sm-3">Harvest Date</dt><dd class="col-sm-9"><?= e(fmt_date($r['harvest_date'])) ?></dd>
      <dt class="col-sm-3">Quantity Harvested</dt><dd class="col-sm-9"><?= number_format((float) $r['quantity_kg'], 2) ?> kg</dd>
      <dt class="col-sm-3">Selling Price per kg</dt><dd class="col-sm-9"><?= e(money($r['selling_price_per_kg'])) ?></dd>
      <dt class="col-sm-3">Estimated Value</dt><dd class="col-sm-9"><strong><?= e(money($r['est_value'])) ?></strong></dd>
      <dt class="col-sm-3">Quality Grade</dt><dd class="col-sm-9"><span class="badge badge-grade"><?= e($r['grade_description']) ?></span></dd>
      <dt class="col-sm-3">Remarks</dt><dd class="col-sm-9"><?= e($r['remark_description']) ?></dd>
      <dt class="col-sm-3">Created</dt><dd class="col-sm-9"><?= e(fmt_datetime($r['created_at'])) ?> by <?= e($r['created_name'] ?? 'N/A') ?></dd>
      <dt class="col-sm-3">Last Updated</dt><dd class="col-sm-9"><?= e(fmt_datetime($r['updated_at'])) ?> by <?= e($r['updated_name'] ?? 'N/A') ?></dd>
    </dl>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

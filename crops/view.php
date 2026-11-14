<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$id = (int) ($_GET['id'] ?? 0);

$st = db()->prepare(
    'SELECT c.*, cc.category_description, se.season_description, cu.full_name AS created_name, uu.full_name AS updated_name
       FROM crop c
       JOIN crop_category cc ON cc.category_id = c.category_id
       JOIN season se ON se.season_id = c.season_id
       LEFT JOIN users cu ON cu.user_id = c.created_by
       LEFT JOIN users uu ON uu.user_id = c.updated_by
      WHERE c.crop_id = :id'
);
$st->execute([':id' => $id]);
$r = $st->fetch();
if (!$r) {
    flash('danger', 'Crop record was not found.');
    redirect('crops/index.php');
}

$ps = db()->prepare(
    'SELECT p.planting_id, p.planting_date, p.area_planted, f.farm_name, s.status_description, p.status_id
       FROM planting p
       JOIN farm f ON f.farm_id = p.farm_id
       JOIN planting_status s ON s.status_id = p.status_id
      WHERE p.crop_id = :id ORDER BY p.planting_date DESC'
);
$ps->execute([':id' => $id]);
$plantings = $ps->fetchAll();

$pageTitle = 'Crop Details';
$active = 'crops';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <h1 class="h3 mb-0">Crop Details</h1>
  <div class="d-flex gap-2">
    <a href="<?= e(url('crops/edit.php?id=' . $id)) ?>" class="btn btn-primary">Edit</a>
    <a href="<?= e(url('crops/index.php')) ?>" class="btn btn-outline-secondary">Back to list</a>
  </div>
</div>
<div class="card mb-3">
  <div class="card-header"><?= e($r['crop_name']) ?> <small class="text-muted">(<?= e($r['variety']) ?>)</small></div>
  <div class="card-body">
    <dl class="row detail-list mb-0">
      <dt class="col-sm-3">Crop ID</dt><dd class="col-sm-9"><?= (int) $r['crop_id'] ?></dd>
      <dt class="col-sm-3">Category</dt><dd class="col-sm-9"><?= e($r['category_description']) ?></dd>
      <dt class="col-sm-3">Growing Period</dt><dd class="col-sm-9"><?= (int) $r['growing_period_days'] ?> days</dd>
      <dt class="col-sm-3">Recommended Season</dt><dd class="col-sm-9"><?= e($r['season_description']) ?></dd>
      <dt class="col-sm-3">Created</dt><dd class="col-sm-9"><?= e(fmt_datetime($r['created_at'])) ?> by <?= e($r['created_name'] ?? 'N/A') ?></dd>
      <dt class="col-sm-3">Last Updated</dt><dd class="col-sm-9"><?= e(fmt_datetime($r['updated_at'])) ?> by <?= e($r['updated_name'] ?? 'N/A') ?></dd>
    </dl>
  </div>
</div>
<div class="card">
  <div class="card-header">Plantings of this Crop (<?= count($plantings) ?>)</div>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead><tr><th>Farm</th><th>Planting Date</th><th class="text-end">Area (ha)</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($plantings as $p): ?>
        <tr>
          <td><?= e($p['farm_name']) ?></td><td><?= e(fmt_date($p['planting_date'])) ?></td>
          <td class="text-end"><?= number_format((float) $p['area_planted'], 2) ?></td>
          <td><span class="badge <?= (int) $p['status_id'] === 2 ? 'badge-status-harvested' : 'badge-status-growing' ?>"><?= e($p['status_description']) ?></span></td>
          <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('planting/view.php?id=' . $p['planting_id'])) ?>">View</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$plantings): ?><tr><td colspan="5" class="text-center text-muted py-3">This crop has not been planted yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

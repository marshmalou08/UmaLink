<?php
// Shared by add.php and edit.php (not a page on its own).
require_once __DIR__ . '/../includes/functions.php';
require_login();
if (empty($formEntry)) {
    redirect('harvest/index.php');
}
$editing = $id !== null;
$errors = [];
$data = ['planting_id' => '', 'harvest_date' => date('Y-m-d'), 'quantity_kg' => '', 'selling_price_per_kg' => '',
         'grade_id' => '', 'remark_id' => ''];

if ($editing) {
    $st = db()->prepare('SELECT * FROM harvest WHERE harvest_id = :id');
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    if (!$row) {
        flash('danger', 'Harvest record was not found.');
        redirect('harvest/index.php');
    }
    $data = array_intersect_key($row, $data);
} elseif (ctype_digit(get('planting_id'))) {
    $data['planting_id'] = get('planting_id'); // preselect when coming from a planting page
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($data as $k => $_) {
        $data[$k] = post($k);
    }
    // ---- validation
    $plantDate = null;
    if (ctype_digit($data['planting_id'])) {
        $st = db()->prepare('SELECT planting_date FROM planting WHERE planting_id = :id');
        $st->execute([':id' => (int) $data['planting_id']]);
        $plantDate = $st->fetchColumn();
    }
    if ($plantDate === null || $plantDate === false) {
        $errors['planting_id'] = 'Select a valid planting record.';
    }
    if (!valid_date($data['harvest_date'])) {
        $errors['harvest_date'] = 'Enter a valid harvest date.';
    } elseif ($plantDate && $data['harvest_date'] < $plantDate) {
        $errors['harvest_date'] = 'Harvest date cannot be earlier than the planting date (' . fmt_date($plantDate) . ').';
    }
    $qty = valid_number($data['quantity_kg'], 0.01, 99999999.99);
    if ($qty === null) {
        $errors['quantity_kg'] = 'Quantity must be a number greater than 0.';
    }
    $price = valid_number($data['selling_price_per_kg'], 0.01, 100000);
    if ($price === null) {
        $errors['selling_price_per_kg'] = 'Selling price must be a number between 0.01 and 100,000.';
    }
    if (!ctype_digit($data['grade_id']) || !id_exists('quality_grade', 'grade_id', (int) $data['grade_id'])) {
        $errors['grade_id'] = 'Select a valid quality grade.';
    }
    if (!ctype_digit($data['remark_id']) || !id_exists('harvest_remark', 'remark_id', (int) $data['remark_id'])) {
        $errors['remark_id'] = 'Select a valid remark.';
    }

    // ---- save
    if (!$errors) {
        try {
            $uid = current_user()['user_id'];
            $p = [':pl' => (int) $data['planting_id'], ':hd' => $data['harvest_date'], ':q' => $qty, ':pr' => $price,
                  ':g' => (int) $data['grade_id'], ':r' => (int) $data['remark_id'], ':u' => $uid];
            if ($editing) {
                $st = db()->prepare(
                    'UPDATE harvest SET planting_id=:pl, harvest_date=:hd, quantity_kg=:q, selling_price_per_kg=:pr,
                            grade_id=:g, remark_id=:r, updated_by=:u WHERE harvest_id=:id'
                );
                $st->execute($p + [':id' => $id]);
                audit('UPDATE', 'harvest', $id, 'Updated harvest record #' . $id);
                flash('success', 'Harvest record updated successfully.');
            } else {
                $st = db()->prepare(
                    'INSERT INTO harvest (planting_id, harvest_date, quantity_kg, selling_price_per_kg, grade_id, remark_id, created_by, updated_by)
                     VALUES (:pl, :hd, :q, :pr, :g, :r, :u, :u) RETURNING harvest_id'
                );
                $st->execute($p);
                $newId = (int) $st->fetchColumn();
                audit('CREATE', 'harvest', $newId, 'Added harvest record #' . $newId);
                flash('success', 'Harvest record added successfully.');
            }
            redirect('harvest/index.php');
        } catch (PDOException $e) {
            $errors['general'] = friendly_db_error($e);
        }
    }
}

$plantings = db()->query(
    "SELECT p.planting_id AS id,
            '#' || p.planting_id || ' — ' || c.crop_name || ' at ' || f.farm_name || ' (planted ' || to_char(p.planting_date, 'Mon DD, YYYY') || ')' AS label
       FROM planting p JOIN farm f ON f.farm_id = p.farm_id JOIN crop c ON c.crop_id = p.crop_id
      ORDER BY p.planting_id"
)->fetchAll();
$grades = lookup('quality_grade', 'grade_id', 'grade_description');
$remarks = lookup('harvest_remark', 'remark_id', 'remark_description');

$pageTitle = $editing ? 'Edit Harvest Record' : 'Add Harvest Record';
$active = 'harvest';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="h3 mb-3"><?= e($pageTitle) ?></h1>
<?php if (isset($errors['general'])): ?><div class="alert alert-danger"><?= e($errors['general']) ?></div><?php endif; ?>
<?php if ($errors && !isset($errors['general'])): ?><div class="alert alert-danger">Please correct the highlighted fields.</div><?php endif; ?>

<div class="card">
  <div class="card-body">
    <form method="post" class="needs-validation" novalidate>
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-8">
          <label for="planting_id" class="form-label required">Planting Record</label>
          <select class="form-select <?= isset($errors['planting_id']) ? 'is-invalid' : '' ?>" id="planting_id" name="planting_id" required>
            <?= options($plantings, $data['planting_id'], '-- Select planting record --') ?>
          </select>
          <div class="invalid-feedback">Select a planting record.</div><?= field_error($errors, 'planting_id') ?>
        </div>
        <div class="col-md-4">
          <label for="harvest_date" class="form-label required">Harvest Date</label>
          <input type="date" class="form-control <?= isset($errors['harvest_date']) ? 'is-invalid' : '' ?>" id="harvest_date"
                 name="harvest_date" required value="<?= e($data['harvest_date']) ?>">
          <div class="invalid-feedback">Enter a valid date.</div><?= field_error($errors, 'harvest_date') ?>
        </div>
        <div class="col-md-4">
          <label for="quantity_kg" class="form-label required">Quantity Harvested (kg)</label>
          <input type="number" step="0.01" min="0.01" max="99999999.99" class="form-control <?= isset($errors['quantity_kg']) ? 'is-invalid' : '' ?>"
                 id="quantity_kg" name="quantity_kg" required value="<?= e($data['quantity_kg']) ?>">
          <div class="invalid-feedback">Enter a quantity greater than 0.</div><?= field_error($errors, 'quantity_kg') ?>
        </div>
        <div class="col-md-4">
          <label for="selling_price_per_kg" class="form-label required">Selling Price per kg (₱)</label>
          <input type="number" step="0.01" min="0.01" max="100000" class="form-control <?= isset($errors['selling_price_per_kg']) ? 'is-invalid' : '' ?>"
                 id="selling_price_per_kg" name="selling_price_per_kg" required value="<?= e($data['selling_price_per_kg']) ?>">
          <div class="invalid-feedback">Enter a price greater than 0.</div><?= field_error($errors, 'selling_price_per_kg') ?>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="est_value">Estimated Harvest Value</label>
          <input type="text" class="form-control" id="est_value" value="" readonly tabindex="-1" aria-describedby="est_help">
          <div id="est_help" class="form-text">Quantity × Selling Price per kg</div>
        </div>
        <div class="col-md-6">
          <label for="grade_id" class="form-label required">Quality Grade</label>
          <select class="form-select <?= isset($errors['grade_id']) ? 'is-invalid' : '' ?>" id="grade_id" name="grade_id" required>
            <?= options($grades, $data['grade_id'], '-- Select grade --') ?>
          </select>
          <div class="invalid-feedback">Select a quality grade.</div><?= field_error($errors, 'grade_id') ?>
        </div>
        <div class="col-md-6">
          <label for="remark_id" class="form-label required">Remarks</label>
          <select class="form-select <?= isset($errors['remark_id']) ? 'is-invalid' : '' ?>" id="remark_id" name="remark_id" required>
            <?= options($remarks, $data['remark_id'], '-- Select remark --') ?>
          </select>
          <div class="invalid-feedback">Select a remark.</div><?= field_error($errors, 'remark_id') ?>
        </div>
      </div>
      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= $editing ? 'Save Changes' : 'Add Harvest Record' ?></button>
        <a href="<?= e(url('harvest/index.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php
// Shared by add.php and edit.php (not a page on its own).
require_once __DIR__ . '/../includes/functions.php';
require_login();
if (empty($formEntry)) {
    redirect('planting/index.php');
}
$editing = $id !== null;
$errors = [];
$data = ['farm_id' => '', 'crop_id' => '', 'planting_date' => date('Y-m-d'), 'area_planted' => '', 'seed_quantity' => '',
         'expected_harvest_date' => '', 'status_id' => '1'];

if ($editing) {
    $st = db()->prepare('SELECT * FROM planting WHERE planting_id = :id');
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    if (!$row) {
        flash('danger', 'Planting record was not found.');
        redirect('planting/index.php');
    }
    $data = array_intersect_key($row, $data);
} else {
    foreach (['farm_id', 'crop_id'] as $k) {   // preselect when coming from another page
        if (ctype_digit(get($k))) {
            $data[$k] = get($k);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($data as $k => $_) {
        $data[$k] = post($k);
    }
    // ---- validation
    $farmArea = null;
    if (!ctype_digit($data['farm_id'])) {
        $errors['farm_id'] = 'Select a valid farm.';
    } else {
        $st = db()->prepare('SELECT area_hectares FROM farm WHERE farm_id = :id');
        $st->execute([':id' => (int) $data['farm_id']]);
        $farmArea = $st->fetchColumn();
        if ($farmArea === false) {
            $errors['farm_id'] = 'Select a valid farm.';
        }
    }
    if (!ctype_digit($data['crop_id']) || !id_exists('crop', 'crop_id', (int) $data['crop_id'])) {
        $errors['crop_id'] = 'Select a valid crop.';
    }
    if (!valid_date($data['planting_date'])) {
        $errors['planting_date'] = 'Enter a valid planting date.';
    }
    $area = valid_number($data['area_planted'], 0.01, 10000);
    if ($area === null) {
        $errors['area_planted'] = 'Area planted must be a number greater than 0 (maximum 10,000).';
    } elseif ($farmArea !== null && $farmArea !== false && $area > (float) $farmArea) {
        $errors['area_planted'] = 'Area planted (' . number_format($area, 2) . ' ha) cannot exceed the farm area (' . number_format((float) $farmArea, 2) . ' ha).';
    }
    $seed = valid_number($data['seed_quantity'], 0.01, 999999.99);
    if ($seed === null) {
        $errors['seed_quantity'] = 'Seed quantity must be a number greater than 0.';
    }
    if (!valid_date($data['expected_harvest_date'])) {
        $errors['expected_harvest_date'] = 'Enter a valid expected harvest date.';
    } elseif (!isset($errors['planting_date']) && $data['expected_harvest_date'] <= $data['planting_date']) {
        $errors['expected_harvest_date'] = 'Expected harvest date must be after the planting date.';
    }
    if (!ctype_digit($data['status_id']) || !id_exists('planting_status', 'status_id', (int) $data['status_id'])) {
        $errors['status_id'] = 'Select a valid status.';
    }

    // ---- save
    if (!$errors) {
        try {
            $uid = current_user()['user_id'];
            $p = [':fm' => (int) $data['farm_id'], ':cr' => (int) $data['crop_id'], ':pd' => $data['planting_date'],
                  ':ar' => $area, ':sq' => $seed, ':eh' => $data['expected_harvest_date'], ':st' => (int) $data['status_id'], ':u' => $uid];
            if ($editing) {
                $st = db()->prepare(
                    'UPDATE planting SET farm_id=:fm, crop_id=:cr, planting_date=:pd, area_planted=:ar, seed_quantity=:sq,
                            expected_harvest_date=:eh, status_id=:st, updated_by=:u WHERE planting_id=:id'
                );
                $st->execute($p + [':id' => $id]);
                audit('UPDATE', 'planting', $id, 'Updated planting record #' . $id);
                flash('success', 'Planting record updated successfully.');
            } else {
                $st = db()->prepare(
                    'INSERT INTO planting (farm_id, crop_id, planting_date, area_planted, seed_quantity, expected_harvest_date, status_id, created_by, updated_by)
                     VALUES (:fm, :cr, :pd, :ar, :sq, :eh, :st, :u, :u) RETURNING planting_id'
                );
                $st->execute($p);
                $newId = (int) $st->fetchColumn();
                audit('CREATE', 'planting', $newId, 'Added planting record #' . $newId);
                flash('success', 'Planting record added successfully.');
            }
            redirect('planting/index.php');
        } catch (PDOException $e) {
            $errors['general'] = friendly_db_error($e);
        }
    }
}

$farms = db()->query("SELECT f.farm_id AS id, f.farm_name || ' — ' || fr.last_name || ', ' || fr.first_name AS label
                        FROM farm f JOIN farmer fr ON fr.farmer_id = f.farmer_id ORDER BY f.farm_name")->fetchAll();
$crops = db()->query("SELECT crop_id AS id, crop_name || ' (' || variety || ')' AS label FROM crop ORDER BY crop_name, variety")->fetchAll();
$statuses = lookup('planting_status', 'status_id', 'status_description');

$pageTitle = $editing ? 'Edit Planting Record' : 'Add Planting Record';
$active = 'planting';
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
        <div class="col-md-6">
          <label for="farm_id" class="form-label required">Farm</label>
          <select class="form-select <?= isset($errors['farm_id']) ? 'is-invalid' : '' ?>" id="farm_id" name="farm_id" required>
            <?= options($farms, $data['farm_id'], '-- Select farm --') ?>
          </select>
          <div class="invalid-feedback">Select a farm.</div><?= field_error($errors, 'farm_id') ?>
        </div>
        <div class="col-md-6">
          <label for="crop_id" class="form-label required">Crop</label>
          <select class="form-select <?= isset($errors['crop_id']) ? 'is-invalid' : '' ?>" id="crop_id" name="crop_id" required>
            <?= options($crops, $data['crop_id'], '-- Select crop --') ?>
          </select>
          <div class="invalid-feedback">Select a crop.</div><?= field_error($errors, 'crop_id') ?>
        </div>
        <div class="col-md-4">
          <label for="planting_date" class="form-label required">Planting Date</label>
          <input type="date" class="form-control <?= isset($errors['planting_date']) ? 'is-invalid' : '' ?>" id="planting_date"
                 name="planting_date" required value="<?= e($data['planting_date']) ?>">
          <div class="invalid-feedback">Enter a valid date.</div><?= field_error($errors, 'planting_date') ?>
        </div>
        <div class="col-md-4">
          <label for="expected_harvest_date" class="form-label required">Expected Harvest Date</label>
          <input type="date" class="form-control <?= isset($errors['expected_harvest_date']) ? 'is-invalid' : '' ?>" id="expected_harvest_date"
                 name="expected_harvest_date" required value="<?= e($data['expected_harvest_date']) ?>">
          <div class="invalid-feedback">Enter a date after the planting date.</div><?= field_error($errors, 'expected_harvest_date') ?>
        </div>
        <div class="col-md-4">
          <label for="status_id" class="form-label required">Status</label>
          <select class="form-select <?= isset($errors['status_id']) ? 'is-invalid' : '' ?>" id="status_id" name="status_id" required>
            <?= options($statuses, $data['status_id'], '-- Select status --') ?>
          </select>
          <div class="invalid-feedback">Select a status.</div><?= field_error($errors, 'status_id') ?>
        </div>
        <div class="col-md-6">
          <label for="area_planted" class="form-label required">Area Planted (hectares)</label>
          <input type="number" step="0.01" min="0.01" max="10000" class="form-control <?= isset($errors['area_planted']) ? 'is-invalid' : '' ?>"
                 id="area_planted" name="area_planted" required value="<?= e($data['area_planted']) ?>">
          <div class="invalid-feedback">Enter an area greater than 0.</div><?= field_error($errors, 'area_planted') ?>
        </div>
        <div class="col-md-6">
          <label for="seed_quantity" class="form-label required">Seed Quantity</label>
          <input type="number" step="0.01" min="0.01" max="999999.99" class="form-control <?= isset($errors['seed_quantity']) ? 'is-invalid' : '' ?>"
                 id="seed_quantity" name="seed_quantity" required value="<?= e($data['seed_quantity']) ?>">
          <div class="invalid-feedback">Enter a quantity greater than 0.</div><?= field_error($errors, 'seed_quantity') ?>
        </div>
      </div>
      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= $editing ? 'Save Changes' : 'Add Planting Record' ?></button>
        <a href="<?= e(url('planting/index.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

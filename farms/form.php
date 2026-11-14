<?php
// Shared by add.php and edit.php (not a page on its own).
require_once __DIR__ . '/../includes/functions.php';
require_login();
if (empty($formEntry)) {
    redirect('farms/index.php');
}
$editing = $id !== null;
$errors = [];
$data = ['farmer_id' => '', 'farm_name' => '', 'location' => '', 'area_hectares' => '', 'soil_type_id' => '', 'irrigation_type_id' => ''];

if ($editing) {
    $st = db()->prepare('SELECT * FROM farm WHERE farm_id = :id');
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    if (!$row) {
        flash('danger', 'Farm record was not found.');
        redirect('farms/index.php');
    }
    $data = array_intersect_key($row, $data);
} elseif (ctype_digit(get('farmer_id'))) {
    $data['farmer_id'] = get('farmer_id'); // preselect when coming from a farmer page
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($data as $k => $_) {
        $data[$k] = post($k);
    }
    // ---- validation
    if (!ctype_digit($data['farmer_id']) || !id_exists('farmer', 'farmer_id', (int) $data['farmer_id'])) {
        $errors['farmer_id'] = 'Select a valid farmer.';
    }
    if ($data['farm_name'] === '' || mb_strlen($data['farm_name']) > 100) {
        $errors['farm_name'] = 'Farm name is required (maximum 100 characters).';
    }
    if ($data['location'] === '' || mb_strlen($data['location']) > 150) {
        $errors['location'] = 'Location is required (maximum 150 characters).';
    }
    $area = valid_number($data['area_hectares'], 0.01, 10000);
    if ($area === null) {
        $errors['area_hectares'] = 'Area must be a number between 0.01 and 10,000 hectares.';
    }
    if (!ctype_digit($data['soil_type_id']) || !id_exists('soil_type', 'soil_type_id', (int) $data['soil_type_id'])) {
        $errors['soil_type_id'] = 'Select a valid soil type.';
    }
    if (!ctype_digit($data['irrigation_type_id']) || !id_exists('irrigation_type', 'irrigation_type_id', (int) $data['irrigation_type_id'])) {
        $errors['irrigation_type_id'] = 'Select a valid irrigation type.';
    }
    if (!isset($errors['farmer_id']) && !isset($errors['farm_name'])) {
        $st = db()->prepare('SELECT 1 FROM farm WHERE farmer_id = :f AND LOWER(farm_name) = LOWER(:n) AND farm_id <> :id');
        $st->execute([':f' => (int) $data['farmer_id'], ':n' => $data['farm_name'], ':id' => $id ?? 0]);
        if ($st->fetchColumn()) {
            $errors['farm_name'] = 'This farmer already has a farm with that name.';
        }
    }

    // ---- save
    if (!$errors) {
        try {
            $uid = current_user()['user_id'];
            $p = [':fm' => (int) $data['farmer_id'], ':nm' => $data['farm_name'], ':lo' => $data['location'],
                  ':ar' => $area, ':so' => (int) $data['soil_type_id'], ':ir' => (int) $data['irrigation_type_id'], ':u' => $uid];
            if ($editing) {
                $st = db()->prepare(
                    'UPDATE farm SET farmer_id=:fm, farm_name=:nm, location=:lo, area_hectares=:ar,
                            soil_type_id=:so, irrigation_type_id=:ir, updated_by=:u WHERE farm_id=:id'
                );
                $st->execute($p + [':id' => $id]);
                audit('UPDATE', 'farm', $id, 'Updated farm ' . $data['farm_name']);
                flash('success', 'Farm updated successfully.');
            } else {
                $st = db()->prepare(
                    'INSERT INTO farm (farmer_id, farm_name, location, area_hectares, soil_type_id, irrigation_type_id, created_by, updated_by)
                     VALUES (:fm, :nm, :lo, :ar, :so, :ir, :u, :u) RETURNING farm_id'
                );
                $st->execute($p);
                audit('CREATE', 'farm', (int) $st->fetchColumn(), 'Added farm ' . $data['farm_name']);
                flash('success', 'Farm added successfully.');
            }
            redirect('farms/index.php');
        } catch (PDOException $e) {
            $errors['general'] = friendly_db_error($e);
        }
    }
}

$farmers = db()->query("SELECT farmer_id AS id, last_name || ', ' || first_name AS label FROM farmer ORDER BY last_name, first_name")->fetchAll();
$soils = lookup('soil_type', 'soil_type_id', 'soil_type_description');
$irrs = lookup('irrigation_type', 'irrigation_type_id', 'irrigation_type_description');

$pageTitle = $editing ? 'Edit Farm' : 'Add Farm';
$active = 'farms';
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
          <label for="farmer_id" class="form-label required">Farmer (Owner)</label>
          <select class="form-select <?= isset($errors['farmer_id']) ? 'is-invalid' : '' ?>" id="farmer_id" name="farmer_id" required>
            <?= options($farmers, $data['farmer_id'], '-- Select farmer --') ?>
          </select>
          <div class="invalid-feedback">Select a farmer.</div><?= field_error($errors, 'farmer_id') ?>
        </div>
        <div class="col-md-6">
          <label for="farm_name" class="form-label required">Farm Name</label>
          <input type="text" class="form-control <?= isset($errors['farm_name']) ? 'is-invalid' : '' ?>" id="farm_name"
                 name="farm_name" maxlength="100" required value="<?= e($data['farm_name']) ?>">
          <div class="invalid-feedback">Farm name is required.</div><?= field_error($errors, 'farm_name') ?>
        </div>
        <div class="col-md-8">
          <label for="location" class="form-label required">Location</label>
          <input type="text" class="form-control <?= isset($errors['location']) ? 'is-invalid' : '' ?>" id="location"
                 name="location" maxlength="150" required value="<?= e($data['location']) ?>">
          <div class="invalid-feedback">Location is required.</div><?= field_error($errors, 'location') ?>
        </div>
        <div class="col-md-4">
          <label for="area_hectares" class="form-label required">Area (hectares)</label>
          <input type="number" step="0.01" min="0.01" max="10000" class="form-control <?= isset($errors['area_hectares']) ? 'is-invalid' : '' ?>"
                 id="area_hectares" name="area_hectares" required value="<?= e($data['area_hectares']) ?>">
          <div class="invalid-feedback">Enter an area between 0.01 and 10,000.</div><?= field_error($errors, 'area_hectares') ?>
        </div>
        <div class="col-md-6">
          <label for="soil_type_id" class="form-label required">Soil Type</label>
          <select class="form-select <?= isset($errors['soil_type_id']) ? 'is-invalid' : '' ?>" id="soil_type_id" name="soil_type_id" required>
            <?= options($soils, $data['soil_type_id'], '-- Select soil type --') ?>
          </select>
          <div class="invalid-feedback">Select a soil type.</div><?= field_error($errors, 'soil_type_id') ?>
        </div>
        <div class="col-md-6">
          <label for="irrigation_type_id" class="form-label required">Irrigation Type</label>
          <select class="form-select <?= isset($errors['irrigation_type_id']) ? 'is-invalid' : '' ?>" id="irrigation_type_id" name="irrigation_type_id" required>
            <?= options($irrs, $data['irrigation_type_id'], '-- Select irrigation type --') ?>
          </select>
          <div class="invalid-feedback">Select an irrigation type.</div><?= field_error($errors, 'irrigation_type_id') ?>
        </div>
      </div>
      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= $editing ? 'Save Changes' : 'Add Farm' ?></button>
        <a href="<?= e(url('farms/index.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

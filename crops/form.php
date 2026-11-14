<?php
// Shared by add.php and edit.php (not a page on its own).
require_once __DIR__ . '/../includes/functions.php';
require_login();
if (empty($formEntry)) {
    redirect('crops/index.php');
}
$editing = $id !== null;
$errors = [];
$data = ['crop_name' => '', 'variety' => '', 'category_id' => '', 'growing_period_days' => '', 'season_id' => ''];

if ($editing) {
    $st = db()->prepare('SELECT * FROM crop WHERE crop_id = :id');
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    if (!$row) {
        flash('danger', 'Crop record was not found.');
        redirect('crops/index.php');
    }
    $data = array_intersect_key($row, $data);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($data as $k => $_) {
        $data[$k] = post($k);
    }
    // ---- validation
    if ($data['crop_name'] === '' || mb_strlen($data['crop_name']) > 80) {
        $errors['crop_name'] = 'Crop name is required (maximum 80 characters).';
    }
    if ($data['variety'] === '' || mb_strlen($data['variety']) > 80) {
        $errors['variety'] = 'Variety is required (maximum 80 characters).';
    }
    if (!ctype_digit($data['category_id']) || !id_exists('crop_category', 'category_id', (int) $data['category_id'])) {
        $errors['category_id'] = 'Select a valid crop category.';
    }
    $days = valid_number($data['growing_period_days'], 1, 1500);
    if ($days === null || floor($days) != $days) {
        $errors['growing_period_days'] = 'Growing period must be a whole number of days between 1 and 1,500.';
    }
    if (!ctype_digit($data['season_id']) || !id_exists('season', 'season_id', (int) $data['season_id'])) {
        $errors['season_id'] = 'Select a valid recommended season.';
    }
    if (!isset($errors['crop_name']) && !isset($errors['variety'])) {
        $st = db()->prepare('SELECT 1 FROM crop WHERE LOWER(crop_name) = LOWER(:n) AND LOWER(variety) = LOWER(:v) AND crop_id <> :id');
        $st->execute([':n' => $data['crop_name'], ':v' => $data['variety'], ':id' => $id ?? 0]);
        if ($st->fetchColumn()) {
            $errors['variety'] = 'This crop and variety combination already exists.';
        }
    }

    // ---- save
    if (!$errors) {
        try {
            $uid = current_user()['user_id'];
            $p = [':n' => $data['crop_name'], ':v' => $data['variety'], ':c' => (int) $data['category_id'],
                  ':d' => (int) $days, ':s' => (int) $data['season_id'], ':u' => $uid];
            if ($editing) {
                $st = db()->prepare(
                    'UPDATE crop SET crop_name=:n, variety=:v, category_id=:c, growing_period_days=:d,
                            season_id=:s, updated_by=:u WHERE crop_id=:id'
                );
                $st->execute($p + [':id' => $id]);
                audit('UPDATE', 'crop', $id, 'Updated crop ' . $data['crop_name']);
                flash('success', 'Crop updated successfully.');
            } else {
                $st = db()->prepare(
                    'INSERT INTO crop (crop_name, variety, category_id, growing_period_days, season_id, created_by, updated_by)
                     VALUES (:n, :v, :c, :d, :s, :u, :u) RETURNING crop_id'
                );
                $st->execute($p);
                audit('CREATE', 'crop', (int) $st->fetchColumn(), 'Added crop ' . $data['crop_name']);
                flash('success', 'Crop added successfully.');
            }
            redirect('crops/index.php');
        } catch (PDOException $e) {
            $errors['general'] = friendly_db_error($e);
        }
    }
}

$categories = lookup('crop_category', 'category_id', 'category_description');
$seasons = lookup('season', 'season_id', 'season_description');

$pageTitle = $editing ? 'Edit Crop' : 'Add Crop';
$active = 'crops';
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
          <label for="crop_name" class="form-label required">Crop Name</label>
          <input type="text" class="form-control <?= isset($errors['crop_name']) ? 'is-invalid' : '' ?>" id="crop_name"
                 name="crop_name" maxlength="80" required value="<?= e($data['crop_name']) ?>">
          <div class="invalid-feedback">Crop name is required.</div><?= field_error($errors, 'crop_name') ?>
        </div>
        <div class="col-md-6">
          <label for="variety" class="form-label required">Variety</label>
          <input type="text" class="form-control <?= isset($errors['variety']) ? 'is-invalid' : '' ?>" id="variety"
                 name="variety" maxlength="80" required value="<?= e($data['variety']) ?>">
          <div class="invalid-feedback">Variety is required.</div><?= field_error($errors, 'variety') ?>
        </div>
        <div class="col-md-4">
          <label for="category_id" class="form-label required">Crop Category</label>
          <select class="form-select <?= isset($errors['category_id']) ? 'is-invalid' : '' ?>" id="category_id" name="category_id" required>
            <?= options($categories, $data['category_id'], '-- Select category --') ?>
          </select>
          <div class="invalid-feedback">Select a category.</div><?= field_error($errors, 'category_id') ?>
        </div>
        <div class="col-md-4">
          <label for="growing_period_days" class="form-label required">Growing Period (days)</label>
          <input type="number" step="1" min="1" max="1500" class="form-control <?= isset($errors['growing_period_days']) ? 'is-invalid' : '' ?>"
                 id="growing_period_days" name="growing_period_days" required value="<?= e($data['growing_period_days']) ?>">
          <div class="invalid-feedback">Enter 1 to 1,500 days.</div><?= field_error($errors, 'growing_period_days') ?>
        </div>
        <div class="col-md-4">
          <label for="season_id" class="form-label required">Recommended Season</label>
          <select class="form-select <?= isset($errors['season_id']) ? 'is-invalid' : '' ?>" id="season_id" name="season_id" required>
            <?= options($seasons, $data['season_id'], '-- Select season --') ?>
          </select>
          <div class="invalid-feedback">Select a season.</div><?= field_error($errors, 'season_id') ?>
        </div>
      </div>
      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= $editing ? 'Save Changes' : 'Add Crop' ?></button>
        <a href="<?= e(url('crops/index.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

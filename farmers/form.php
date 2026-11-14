<?php
// Shared by add.php and edit.php (not a page on its own).
require_once __DIR__ . '/../includes/functions.php';
require_login();
if (empty($formEntry)) {
    redirect('farmers/index.php');
}
$editing = $id !== null;
$errors = [];
$data = ['first_name' => '', 'last_name' => '', 'contact_number' => '', 'address' => '', 'date_registered' => date('Y-m-d')];

if ($editing) {
    $st = db()->prepare('SELECT * FROM farmer WHERE farmer_id = :id');
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    if (!$row) {
        flash('danger', 'Farmer record was not found.');
        redirect('farmers/index.php');
    }
    $data = array_intersect_key($row, $data);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($data as $k => $_) {
        $data[$k] = post($k);
    }
    // ---- validation
    if ($data['first_name'] === '' || mb_strlen($data['first_name']) > 50) {
        $errors['first_name'] = 'First name is required (maximum 50 characters).';
    }
    if ($data['last_name'] === '' || mb_strlen($data['last_name']) > 50) {
        $errors['last_name'] = 'Last name is required (maximum 50 characters).';
    }
    if (!preg_match('/^[0-9+() -]{7,20}$/', $data['contact_number'])) {
        $errors['contact_number'] = 'Enter a valid contact number (7-20 digits; + ( ) - allowed).';
    } else {
        $st = db()->prepare('SELECT 1 FROM farmer WHERE contact_number = :c AND farmer_id <> :id');
        $st->execute([':c' => $data['contact_number'], ':id' => $id ?? 0]);
        if ($st->fetchColumn()) {
            $errors['contact_number'] = 'This contact number is already registered to another farmer.';
        }
    }
    if ($data['address'] === '' || mb_strlen($data['address']) > 150) {
        $errors['address'] = 'Address is required (maximum 150 characters).';
    }
    if (!valid_date($data['date_registered'])) {
        $errors['date_registered'] = 'Enter a valid registration date.';
    } elseif ($data['date_registered'] > date('Y-m-d')) {
        $errors['date_registered'] = 'Registration date cannot be in the future.';
    }

    // ---- save
    if (!$errors) {
        try {
            $uid = current_user()['user_id'];
            if ($editing) {
                $st = db()->prepare(
                    'UPDATE farmer SET first_name=:fn, last_name=:ln, contact_number=:cn, address=:ad,
                            date_registered=:dr, updated_by=:u WHERE farmer_id=:id'
                );
                $st->execute([':fn' => $data['first_name'], ':ln' => $data['last_name'], ':cn' => $data['contact_number'],
                              ':ad' => $data['address'], ':dr' => $data['date_registered'], ':u' => $uid, ':id' => $id]);
                audit('UPDATE', 'farmer', $id, 'Updated farmer ' . $data['first_name'] . ' ' . $data['last_name']);
                flash('success', 'Farmer updated successfully.');
            } else {
                $st = db()->prepare(
                    'INSERT INTO farmer (first_name, last_name, contact_number, address, date_registered, created_by, updated_by)
                     VALUES (:fn, :ln, :cn, :ad, :dr, :u, :u2) RETURNING farmer_id'
                );
                $st->execute([':fn' => $data['first_name'], ':ln' => $data['last_name'], ':cn' => $data['contact_number'],
                              ':ad' => $data['address'], ':dr' => $data['date_registered'], ':u' => $uid, ':u2' => $uid]);
                $newId = (int) $st->fetchColumn();
                audit('CREATE', 'farmer', $newId, 'Added farmer ' . $data['first_name'] . ' ' . $data['last_name']);
                flash('success', 'Farmer added successfully.');
            }
            redirect('farmers/index.php');
        } catch (PDOException $e) {
            $errors['general'] = friendly_db_error($e);
        }
    }
}

$pageTitle = $editing ? 'Edit Farmer' : 'Add Farmer';
$active = 'farmers';
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
          <label for="first_name" class="form-label required">First Name</label>
          <input type="text" class="form-control <?= isset($errors['first_name']) ? 'is-invalid' : '' ?>" id="first_name"
                 name="first_name" maxlength="50" required value="<?= e($data['first_name']) ?>">
          <div class="invalid-feedback">First name is required.</div><?= field_error($errors, 'first_name') ?>
        </div>
        <div class="col-md-6">
          <label for="last_name" class="form-label required">Last Name</label>
          <input type="text" class="form-control <?= isset($errors['last_name']) ? 'is-invalid' : '' ?>" id="last_name"
                 name="last_name" maxlength="50" required value="<?= e($data['last_name']) ?>">
          <div class="invalid-feedback">Last name is required.</div><?= field_error($errors, 'last_name') ?>
        </div>
        <div class="col-md-6">
          <label for="contact_number" class="form-label required">Contact Number</label>
          <input type="text" class="form-control <?= isset($errors['contact_number']) ? 'is-invalid' : '' ?>" id="contact_number"
                 name="contact_number" maxlength="20" required pattern="[0-9+() \-]{7,20}" placeholder="09171234567"
                 value="<?= e($data['contact_number']) ?>">
          <div class="invalid-feedback">Enter a valid contact number.</div><?= field_error($errors, 'contact_number') ?>
        </div>
        <div class="col-md-6">
          <label for="date_registered" class="form-label required">Date Registered</label>
          <input type="date" class="form-control <?= isset($errors['date_registered']) ? 'is-invalid' : '' ?>" id="date_registered"
                 name="date_registered" max="<?= e(date('Y-m-d')) ?>" required value="<?= e($data['date_registered']) ?>">
          <div class="invalid-feedback">Enter a valid date (not in the future).</div><?= field_error($errors, 'date_registered') ?>
        </div>
        <div class="col-12">
          <label for="address" class="form-label required">Address</label>
          <input type="text" class="form-control <?= isset($errors['address']) ? 'is-invalid' : '' ?>" id="address"
                 name="address" maxlength="150" required value="<?= e($data['address']) ?>">
          <div class="invalid-feedback">Address is required.</div><?= field_error($errors, 'address') ?>
        </div>
      </div>
      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= $editing ? 'Save Changes' : 'Add Farmer' ?></button>
        <a href="<?= e(url('farmers/index.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

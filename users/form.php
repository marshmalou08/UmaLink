<?php
// Shared by add.php and edit.php (not a page on its own). Administrators only.
require_once __DIR__ . '/../includes/functions.php';
require_admin();
if (empty($formEntry)) {
    redirect('users/index.php');
}
$editing = $id !== null;
$errors = [];
$data = ['username' => '', 'full_name' => '', 'role_id' => '2', 'is_active' => '1', 'password' => '', 'confirm' => ''];
$isSelf = $editing && $id === current_user()['user_id'];

if ($editing) {
    $st = db()->prepare('SELECT username, full_name, role_id, is_active FROM users WHERE user_id = :id');
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    if (!$row) {
        flash('danger', 'User account was not found.');
        redirect('users/index.php');
    }
    $data['username'] = $row['username'];
    $data['full_name'] = $row['full_name'];
    $data['role_id'] = (string) $row['role_id'];
    $data['is_active'] = $row['is_active'] ? '1' : '0';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $data['username'] = post('username');
    $data['full_name'] = post('full_name');
    $data['password'] = $_POST['password'] ?? '';
    $data['confirm'] = $_POST['confirm'] ?? '';
    if ($isSelf) {
        // An administrator cannot change their own role or deactivate themselves.
        $data['role_id'] = '1';
        $data['is_active'] = '1';
    } else {
        $data['role_id'] = post('role_id');
        $data['is_active'] = isset($_POST['is_active']) ? '1' : '0';
    }
    // ---- validation
    if (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $data['username'])) {
        $errors['username'] = 'Username must be 3-50 characters: letters, numbers, underscore or dot.';
    } else {
        $st = db()->prepare('SELECT 1 FROM users WHERE LOWER(username) = LOWER(:u) AND user_id <> :id');
        $st->execute([':u' => $data['username'], ':id' => $id ?? 0]);
        if ($st->fetchColumn()) {
            $errors['username'] = 'This username is already taken.';
        }
    }
    if ($data['full_name'] === '' || mb_strlen($data['full_name']) > 100) {
        $errors['full_name'] = 'Full name is required (maximum 100 characters).';
    }
    if (!ctype_digit($data['role_id']) || !id_exists('role', 'role_id', (int) $data['role_id'])) {
        $errors['role_id'] = 'Select a valid role.';
    }
    if (!$editing || $data['password'] !== '') {
        if (strlen($data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Za-z]/', $data['password']) || !preg_match('/[0-9]/', $data['password'])) {
            $errors['password'] = 'Password must contain at least one letter and one number.';
        } elseif ($data['password'] !== $data['confirm']) {
            $errors['confirm'] = 'Passwords do not match.';
        }
    }
    // Keep at least one active administrator in the system.
    if ($editing && !$isSelf && !$errors && ((int) $data['role_id'] !== 1 || $data['is_active'] !== '1')) {
        $st = db()->prepare('SELECT COUNT(*) FROM users WHERE role_id = 1 AND is_active AND user_id <> :id');
        $st->execute([':id' => $id]);
        if ((int) $st->fetchColumn() === 0) {
            $errors['role_id'] = 'At least one active administrator must remain.';
        }
    }

    // ---- save
    if (!$errors) {
        try {
            if ($editing) {
                $sql = 'UPDATE users SET username=:un, full_name=:fn, role_id=:r, is_active=:a'
                     . ($data['password'] !== '' ? ', password_hash=:ph' : '') . ' WHERE user_id=:id';
                $p = [':un' => $data['username'], ':fn' => $data['full_name'], ':r' => (int) $data['role_id'],
                      ':a' => $data['is_active'] === '1' ? 't' : 'f', ':id' => $id];
                if ($data['password'] !== '') {
                    $p[':ph'] = password_hash($data['password'], PASSWORD_DEFAULT);
                }
                db()->prepare($sql)->execute($p);
                audit('UPDATE', 'users', $id, 'Updated user ' . $data['username']);
                flash('success', 'User account updated successfully.');
            } else {
                $st = db()->prepare(
                    'INSERT INTO users (username, full_name, password_hash, role_id, is_active)
                     VALUES (:un, :fn, :ph, :r, :a) RETURNING user_id'
                );
                $st->execute([':un' => $data['username'], ':fn' => $data['full_name'],
                              ':ph' => password_hash($data['password'], PASSWORD_DEFAULT),
                              ':r' => (int) $data['role_id'], ':a' => $data['is_active'] === '1' ? 't' : 'f']);
                audit('CREATE', 'users', (int) $st->fetchColumn(), 'Added user ' . $data['username']);
                flash('success', 'User account added successfully.');
            }
            redirect('users/index.php');
        } catch (PDOException $e) {
            $errors['general'] = friendly_db_error($e);
        }
    }
}

$roles = lookup('role', 'role_id', 'role_description');
$pageTitle = $editing ? 'Edit User' : 'Add User';
$active = 'users';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="h3 mb-3"><?= e($pageTitle) ?></h1>
<?php if (isset($errors['general'])): ?><div class="alert alert-danger"><?= e($errors['general']) ?></div><?php endif; ?>
<?php if ($errors && !isset($errors['general'])): ?><div class="alert alert-danger">Please correct the highlighted fields.</div><?php endif; ?>

<div class="card">
  <div class="card-body">
    <form method="post" class="needs-validation" novalidate autocomplete="off">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-6">
          <label for="username" class="form-label required">Username</label>
          <input type="text" class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>" id="username" name="username"
                 maxlength="50" pattern="[A-Za-z0-9_.]{3,50}" required value="<?= e($data['username']) ?>">
          <div class="invalid-feedback">3-50 characters: letters, numbers, _ or .</div><?= field_error($errors, 'username') ?>
        </div>
        <div class="col-md-6">
          <label for="full_name" class="form-label required">Full Name</label>
          <input type="text" class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" id="full_name" name="full_name"
                 maxlength="100" required value="<?= e($data['full_name']) ?>">
          <div class="invalid-feedback">Full name is required.</div><?= field_error($errors, 'full_name') ?>
        </div>
        <div class="col-md-6">
          <label for="password" class="form-label <?= $editing ? '' : 'required' ?>">Password<?= $editing ? ' (leave blank to keep current)' : '' ?></label>
          <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" id="password" name="password"
                 minlength="8" <?= $editing ? '' : 'required' ?> autocomplete="new-password">
          <div class="invalid-feedback">At least 8 characters.</div><?= field_error($errors, 'password') ?>
        </div>
        <div class="col-md-6">
          <label for="confirm" class="form-label <?= $editing ? '' : 'required' ?>">Confirm Password</label>
          <input type="password" class="form-control <?= isset($errors['confirm']) ? 'is-invalid' : '' ?>" id="confirm" name="confirm"
                 <?= $editing ? '' : 'required' ?> autocomplete="new-password">
          <div class="invalid-feedback">Confirm the password.</div><?= field_error($errors, 'confirm') ?>
        </div>
        <div class="col-md-6">
          <label for="role_id" class="form-label required">Role</label>
          <select class="form-select <?= isset($errors['role_id']) ? 'is-invalid' : '' ?>" id="role_id" name="role_id" required <?= $isSelf ? 'disabled' : '' ?>>
            <?= options($roles, $data['role_id'], '-- Select role --') ?>
          </select>
          <?php if ($isSelf): ?><div class="form-text">You cannot change your own role.</div><?php endif; ?>
          <div class="invalid-feedback">Select a role.</div><?= field_error($errors, 'role_id') ?>
        </div>
        <div class="col-md-6 d-flex align-items-end">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                   <?= $data['is_active'] === '1' ? 'checked' : '' ?> <?= $isSelf ? 'disabled' : '' ?>>
            <label class="form-check-label" for="is_active">Account is active</label>
          </div>
        </div>
      </div>
      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= $editing ? 'Save Changes' : 'Add User' ?></button>
        <a href="<?= e(url('users/index.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

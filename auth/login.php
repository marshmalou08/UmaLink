<?php
require_once __DIR__ . '/../includes/functions.php';

if (current_user()) {
    redirect('index.php');
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = post('username');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $st = db()->prepare(
            'SELECT u.user_id, u.username, u.full_name, u.password_hash, u.role_id, u.is_active, r.role_description
               FROM users u JOIN role r ON r.role_id = u.role_id
              WHERE u.username = :u'
        );
        $st->execute([':u' => $username]);
        $u = $st->fetch();

        // Same message for "no such user", "wrong password" and "inactive account".
        if ($u && $u['is_active'] && password_verify($password, $u['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'user_id'   => (int) $u['user_id'],
                'username'  => $u['username'],
                'full_name' => $u['full_name'],
                'role_id'   => (int) $u['role_id'],
                'role'      => $u['role_description'],
            ];
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                $up = db()->prepare('UPDATE users SET password_hash = :h WHERE user_id = :id');
                $up->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $u['user_id']]);
            }
            audit('LOGIN', 'users', (int) $u['user_id'], 'User logged in');
            redirect('index.php');
        }
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login | <?= e(APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= e(url('assets/vendor/bootstrap.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div class="login-wrap">
  <div class="card login-card shadow">
    <div class="card-body p-4">
      <div class="text-center mb-4">
        <div class="brand"><?= e(APP_NAME) ?></div>
        <div class="text-muted"><?= e(APP_SUBTITLE) ?></div>
      </div>
      <?php if ($error): ?>
        <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
      <?php endif; ?>
      <?php show_flash(); ?>
      <form method="post" class="needs-validation" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
          <label for="username" class="form-label">Username</label>
          <input type="text" class="form-control" id="username" name="username" maxlength="50"
                 value="<?= e($username) ?>" required autofocus autocomplete="username">
          <div class="invalid-feedback">Username is required.</div>
        </div>
        <div class="mb-4">
          <label for="password" class="form-label">Password</label>
          <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
          <div class="invalid-feedback">Password is required.</div>
        </div>
        <button type="submit" class="btn btn-primary w-100">Log In</button>
      </form>
    </div>
  </div>
</div>
<script src="<?= e(url('assets/vendor/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>

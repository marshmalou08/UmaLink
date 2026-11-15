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
<div class="login-shell">
  <aside class="login-aside">
    <svg class="brand-mark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21V9"/><path d="M12 14c-4 0-6-2-6-6 4 0 6 2 6 6z"/><path d="M12 11c0-4 2-6 6-6 0 4-2 6-6 6z"/></svg>
    <div class="brand"><?= e(APP_NAME) ?></div>
    <div class="sub"><?= e(APP_SUBTITLE) ?></div>
    <p class="tagline">Simplifying farm management, one record at a time.</p>
    <svg class="rows" viewBox="0 0 400 120" preserveAspectRatio="none" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true">
      <path d="M0 20 C100 0 300 40 400 18"/><path d="M0 38 C100 18 300 58 400 36"/><path d="M0 56 C100 36 300 76 400 54"/>
      <path d="M0 74 C100 54 300 94 400 72"/><path d="M0 92 C100 72 300 112 400 90"/><path d="M0 110 C100 90 300 130 400 108"/>
    </svg>
  </aside>
  <main class="login-main">
    <div class="login-card">
      <h1 class="h3">Sign in</h1>
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
  </main>
</div>
<script src="<?= e(url('assets/vendor/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>

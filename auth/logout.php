<?php
require_once __DIR__ . '/../includes/functions.php';

if (current_user()) {
    audit('LOGOUT', 'users', current_user()['user_id'], 'User logged out');
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

// Start a fresh session only to carry the goodbye message.
session_start();
flash('success', 'You have been logged out.');
redirect('auth/login.php');

<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('users/index.php');
}
csrf_check();
$id = (int) ($_POST['id'] ?? 0);

if ($id === current_user()['user_id']) {
    flash('danger', 'You cannot delete your own account.');
    redirect('users/index.php');
}
$st = db()->prepare('SELECT username, role_id, is_active FROM users WHERE user_id = :id');
$st->execute([':id' => $id]);
$u = $st->fetch();
if (!$u) {
    flash('danger', 'User account was not found.');
    redirect('users/index.php');
}
if ((int) $u['role_id'] === 1 && $u['is_active']) {
    $st = db()->prepare('SELECT COUNT(*) FROM users WHERE role_id = 1 AND is_active AND user_id <> :id');
    $st->execute([':id' => $id]);
    if ((int) $st->fetchColumn() === 0) {
        flash('danger', 'At least one active administrator must remain.');
        redirect('users/index.php');
    }
}
try {
    // Records created by this user are kept (created_by / updated_by become empty).
    db()->prepare('DELETE FROM users WHERE user_id = :id')->execute([':id' => $id]);
    audit('DELETE', 'users', $id, 'Deleted user ' . $u['username']);
    flash('success', 'User account deleted successfully.');
} catch (PDOException $e) {
    flash('danger', friendly_db_error($e));
}
redirect('users/index.php');

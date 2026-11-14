<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$pageTitle = 'User Accounts';
$active = 'users';

$q = get('q');
$role = get('role');
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(u.username ILIKE :q OR u.full_name ILIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
if ($role !== '' && ctype_digit($role)) {
    $where[] = 'u.role_id = :role';
    $params[':role'] = (int) $role;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

[$sortKey, $sortDir, $orderBy] = sort_clause([
    'id' => 'u.user_id', 'username' => 'u.username', 'name' => 'u.full_name', 'role' => 'r.role_description', 'created' => 'u.created_at',
], 'id');

$from = 'FROM users u JOIN role r ON r.role_id = u.role_id';
[$page, $pages, $total, $offset] = paginate("SELECT COUNT(*) $from $whereSql", $params);
$st = db()->prepare(
    "SELECT u.user_id, u.username, u.full_name, u.is_active, u.created_at, r.role_description
       $from $whereSql ORDER BY $orderBy, u.user_id LIMIT " . PER_PAGE . " OFFSET $offset"
);
$st->execute($params);
$rows = $st->fetchAll();
$roles = lookup('role', 'role_id', 'role_description');

require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <h1 class="h3 mb-0">User Accounts</h1>
  <a href="<?= e(url('users/add.php')) ?>" class="btn btn-primary">+ Add User</a>
</div>

<form method="get" class="filter-bar row g-2 mb-3">
  <div class="col-md-5">
    <label for="q" class="form-label mb-1">Search</label>
    <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Username or full name">
  </div>
  <div class="col-md-4">
    <label for="role" class="form-label mb-1">Role</label>
    <select class="form-select" id="role" name="role"><?= options($roles, $role, 'All roles') ?></select>
  </div>
  <div class="col-md-3 d-flex align-items-end gap-2">
    <button class="btn btn-primary" type="submit">Filter</button>
    <a class="btn btn-outline-secondary" href="<?= e(url('users/index.php')) ?>">Reset</a>
  </div>
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <?= sort_th('ID', 'id', $sortKey, $sortDir) ?>
          <?= sort_th('Username', 'username', $sortKey, $sortDir) ?>
          <?= sort_th('Full Name', 'name', $sortKey, $sortDir) ?>
          <?= sort_th('Role', 'role', $sortKey, $sortDir) ?>
          <th>Status</th>
          <?= sort_th('Created', 'created', $sortKey, $sortDir) ?>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $u): ?>
        <tr>
          <td><?= (int) $u['user_id'] ?></td>
          <td><?= e($u['username']) ?></td>
          <td><?= e($u['full_name']) ?></td>
          <td><?= e($u['role_description']) ?></td>
          <td><span class="badge <?= $u['is_active'] ? 'badge-status-harvested' : 'badge-status-growing' ?>"><?= $u['is_active'] ? 'Active' : 'Inactive' ?></span></td>
          <td><?= e(fmt_datetime($u['created_at'])) ?></td>
          <td class="text-end">
            <div class="btn-group btn-group-sm">
              <a class="btn btn-outline-primary" href="<?= e(url('users/edit.php?id=' . $u['user_id'])) ?>">Edit</a>
              <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal"
                      data-action="<?= e(url('users/delete.php')) ?>" data-id="<?= (int) $u['user_id'] ?>"
                      data-label="user <?= e($u['username']) ?>">Delete</button>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No users found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?= pagination_html($page, $pages, $total) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * Shared helper functions used by every page.
 */
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/database.php';

load_env();
define('BASE_URL', rtrim((string) getenv('APP_BASE_URL'), '/'));

// ---------------------------------------------------------------- session
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

// ---------------------------------------------------------------- output
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function money($amount): string
{
    return '₱' . number_format((float) $amount, 2);
}

function fmt_date($date): string
{
    return $date ? date('M d, Y', strtotime($date)) : '—';
}

function fmt_datetime($ts): string
{
    return $ts ? date('M d, Y h:i A', strtotime($ts)) : '—';
}

// ---------------------------------------------------------------- flash messages
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function show_flash(): void
{
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $class = $f['type'] === 'error' ? 'danger' : $f['type'];
        echo '<div class="alert alert-' . e($class) . ' alert-dismissible fade show" role="alert">'
           . e($f['message'])
           . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
    unset($_SESSION['flash']);
}

// ---------------------------------------------------------------- CSRF
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Stops the request when the form token is missing or wrong. */
function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(400);
        exit('Invalid request. Please go back, refresh the page and try again.');
    }
}

// ---------------------------------------------------------------- authentication
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool
{
    return (current_user()['role_id'] ?? 0) === 1;
}

function require_login(): void
{
    if (!current_user()) {
        redirect('auth/login.php');
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        flash('danger', 'Access denied. This function is for administrators only.');
        redirect('index.php');
    }
}

// ---------------------------------------------------------------- audit log
function audit(string $action, string $table, ?int $recordId = null, ?string $details = null): void
{
    try {
        $st = db()->prepare(
            'INSERT INTO audit_log (user_id, action, table_name, record_id, details)
             VALUES (:u, :a, :t, :r, :d)'
        );
        $st->execute([
            ':u' => current_user()['user_id'] ?? null,
            ':a' => $action,
            ':t' => $table,
            ':r' => $recordId,
            ':d' => $details,
        ]);
    } catch (PDOException $e) {
        error_log('UmaLink audit failed: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------- database errors
/** Converts a database exception into a message that is safe to show. */
function friendly_db_error(PDOException $e): string
{
    error_log('UmaLink DB error: ' . $e->getMessage());
    switch ($e->getCode()) {
        case '23503':
            return 'This record is linked to other records, so the operation was blocked to protect data integrity.';
        case '23505':
            return 'A record with the same unique value already exists.';
        case '23502':
            return 'A required value is missing.';
        case '23514':
            // Our own trigger raises readable messages for the harvest date rule.
            if (strpos($e->getMessage(), 'Harvest date') !== false) {
                return 'The harvest date cannot be earlier than the planting date.';
            }
            return 'One of the values is outside the allowed range.';
        default:
            return 'The operation could not be completed. Please try again.';
    }
}

// ---------------------------------------------------------------- input and validation
/** Trimmed string from POST (or empty string). */
function post(string $key): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

/** Trimmed string from GET (or empty string). */
function get(string $key): string
{
    $v = $_GET[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

function valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/** Positive/ranged number check. Returns the float or null. */
function valid_number(string $value, float $min, float $max): ?float
{
    if ($value === '' || !is_numeric($value)) {
        return null;
    }
    $n = (float) $value;
    return ($n >= $min && $n <= $max) ? $n : null;
}

/** True when an ID exists in a table (verifies foreign key selections). */
function id_exists(string $table, string $idColumn, $id): bool
{
    // $table and $idColumn are always hard-coded by the callers, never user input.
    $st = db()->prepare("SELECT 1 FROM $table WHERE $idColumn = :id");
    $st->execute([':id' => $id]);
    return (bool) $st->fetchColumn();
}

// ---------------------------------------------------------------- lookups and dropdowns
function lookup(string $table, string $idCol, string $descCol): array
{
    return db()->query("SELECT $idCol AS id, $descCol AS label FROM $table ORDER BY $idCol")->fetchAll();
}

function options(array $rows, $selected = null, string $placeholder = '-- Select --'): string
{
    $html = '<option value="">' . e($placeholder) . '</option>';
    foreach ($rows as $r) {
        $sel = ((string) $selected !== '' && (string) $selected === (string) $r['id']) ? ' selected' : '';
        $html .= '<option value="' . e($r['id']) . '"' . $sel . '>' . e($r['label']) . '</option>';
    }
    return $html;
}

// ---------------------------------------------------------------- sorting and pagination
/**
 * Picks a safe ORDER BY from a whitelist.
 * $allowed = ['key' => 'sql_column']; returns [key, dir, "sql_column DIR"].
 */
function sort_clause(array $allowed, string $defaultKey, string $defaultDir = 'asc'): array
{
    $key = get('sort');
    if (!isset($allowed[$key])) {
        $key = $defaultKey;
    }
    $dir = strtolower(get('dir')) === 'desc' ? 'desc' : (strtolower(get('dir')) === 'asc' ? 'asc' : $defaultDir);
    return [$key, $dir, $allowed[$key] . ' ' . strtoupper($dir)];
}

/** Current query string with some values replaced. */
function qs(array $changes = []): string
{
    $q = array_merge($_GET, $changes);
    foreach ($q as $k => $v) {
        if ($v === '' || $v === null) {
            unset($q[$k]);
        }
    }
    return $q ? '?' . http_build_query($q) : '?';
}

/** Sortable column heading. */
function sort_th(string $label, string $key, string $curKey, string $curDir): string
{
    $dir = ($curKey === $key && $curDir === 'asc') ? 'desc' : 'asc';
    $arrow = $curKey === $key ? ($curDir === 'asc' ? ' ▲' : ' ▼') : '';
    return '<th><a class="sort-link" href="' . e(qs(['sort' => $key, 'dir' => $dir, 'page' => 1])) . '">'
         . e($label) . $arrow . '</a></th>';
}

/** Returns [page, pages, total, offset] for a COUNT query. */
function paginate(string $countSql, array $params): array
{
    $st = db()->prepare($countSql);
    $st->execute($params);
    $total = (int) $st->fetchColumn();
    $pages = max(1, (int) ceil($total / PER_PAGE));
    $page = min(max(1, (int) ($_GET['page'] ?? 1)), $pages);
    return [$page, $pages, $total, ($page - 1) * PER_PAGE];
}

function pagination_html(int $page, int $pages, int $total): string
{
    $html = '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">'
          . '<small class="text-muted">' . number_format($total) . ' record(s) found</small>';
    if ($pages > 1) {
        $html .= '<nav aria-label="Pagination"><ul class="pagination pagination-sm mb-0">';
        $html .= '<li class="page-item' . ($page <= 1 ? ' disabled' : '') . '"><a class="page-link" href="'
               . e(qs(['page' => $page - 1])) . '">Previous</a></li>';
        $start = max(1, $page - 2);
        $end = min($pages, $page + 2);
        for ($i = $start; $i <= $end; $i++) {
            $html .= '<li class="page-item' . ($i === $page ? ' active' : '') . '"><a class="page-link" href="'
                   . e(qs(['page' => $i])) . '">' . $i . '</a></li>';
        }
        $html .= '<li class="page-item' . ($page >= $pages ? ' disabled' : '') . '"><a class="page-link" href="'
               . e(qs(['page' => $page + 1])) . '">Next</a></li>';
        $html .= '</ul></nav>';
    }
    return $html . '</div>';
}

/** Row action buttons: View, Edit and (administrators only) Delete. */
function row_actions(string $module, int $id, string $label): string
{
    $html = '<div class="btn-group btn-group-sm" role="group">'
          . '<a class="btn btn-outline-secondary" href="' . e(url("$module/view.php?id=$id")) . '">View</a>'
          . '<a class="btn btn-outline-primary" href="' . e(url("$module/edit.php?id=$id")) . '">Edit</a>';
    if (is_admin()) {
        $html .= '<button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" '
               . 'data-bs-target="#deleteModal" data-action="' . e(url("$module/delete.php")) . '" '
               . 'data-id="' . $id . '" data-label="' . e($label) . '">Delete</button>';
    }
    return $html . '</div>';
}

/** Shared handler for delete.php files (POST only, admin only, CSRF-checked). */
function handle_delete(string $module, string $table, string $idCol, string $title, string $blockSql = ''): void
{
    require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect("$module/index.php");
    }
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0 || !id_exists($table, $idCol, $id)) {
        flash('danger', "$title record was not found.");
        redirect("$module/index.php");
    }
    // Friendly pre-check for dependent records (the foreign key still protects the data).
    if ($blockSql !== '') {
        $st = db()->prepare($blockSql);
        $st->execute([':id' => $id]);
        $n = (int) $st->fetchColumn();
        if ($n > 0) {
            flash('danger', "Cannot delete this $title: it is used by $n related record(s). Remove or reassign them first.");
            redirect("$module/index.php");
        }
    }
    try {
        $st = db()->prepare("DELETE FROM $table WHERE $idCol = :id");
        $st->execute([':id' => $id]);
        audit('DELETE', $table, $id, "Deleted $title #$id");
        flash('success', "$title deleted successfully.");
    } catch (PDOException $e) {
        flash('danger', friendly_db_error($e));
    }
    redirect("$module/index.php");
}

/** Server-side validation message shown under a form field. */
function field_error(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<div class="invalid-feedback d-block">' . e($errors[$key]) . '</div>' : '';
}

<?php
declare(strict_types=1);

/* ---------- Output / URL helpers ---------- */

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}

/**
 * Asset URL with automatic cache-busting (?v=last-modified-timestamp).
 * Ensures browsers pick up CSS/JS changes without manual hard-refresh.
 */
function asset(string $path): string
{
    $full = APP_ROOT . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    $ver  = @filemtime($full) ?: time();
    return url($path) . '?v=' . $ver;
}

function redirect(string $path): never
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)));
    exit;
}

/* ---------- Flash messages ---------- */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid CSRF token. Please go back and try again.');
    }
}

/* ---------- Auth ---------- */

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        if (empty($_SESSION['user_id'])) {
            $user = null;
        } else {
            $stmt = db()->prepare(
                'SELECT u.*, d.name AS department_name, d.code AS department_code
                 FROM users u LEFT JOIN departments d ON d.id = u.department_id
                 WHERE u.id = ? AND u.active = 1'
            );
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;
        }
    }
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('warning', 'Please log in to continue.');
        redirect('auth/login.php');
    }
    return $user;
}

function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        flash('danger', 'You are not authorized to access that page.');
        redirect(role_home($user));
    }
    return $user;
}

function role_home(array $user): string
{
    return match ($user['role']) {
        'admin'     => 'admin/users.php',
        'authority' => 'authority/dashboard.php',
        default     => 'citizen/dashboard.php',
    };
}

/* ---------- Domain helpers ---------- */

const STATUSES = ['pending', 'acknowledged', 'in_progress', 'resolved', 'rejected'];

function status_label(string $status): string
{
    return match ($status) {
        'in_progress'  => 'In Progress',
        default        => ucfirst($status),
    };
}

function status_badge(string $status): string
{
    $color = match ($status) {
        'pending'      => 'secondary',
        'acknowledged' => 'info',
        'in_progress'  => 'primary',
        'resolved'     => 'success',
        'rejected'     => 'danger',
        default        => 'dark',
    };
    return '<span class="badge text-bg-' . $color . '">' . e(status_label($status)) . '</span>';
}

function severity_badge(string $severity): string
{
    $color = match ($severity) {
        'low'      => 'success',
        'medium'   => 'warning',
        'high'     => 'orange',
        'critical' => 'danger',
        default    => 'dark',
    };
    return '<span class="badge badge-sev-' . e($severity) . '">' . e(ucfirst($severity)) . '</span>';
}

function time_ago(string $datetime): string
{
    $ts   = strtotime($datetime);
    $diff = time() - $ts;
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . ' min ago';
    if ($diff < 86400)  return floor($diff / 3600) . ' hr ago';
    if ($diff < 2592000) return floor($diff / 86400) . ' days ago';
    return date('M j, Y', $ts);
}

function next_ref_no(PDO $pdo): string
{
    $year = date('Y');
    $stmt = $pdo->query("SELECT COUNT(*) FROM incidents");
    $seq  = (int)$stmt->fetchColumn() + 1;
    return sprintf('INC-%s-%04d', $year, $seq + 1000);
}

/**
 * Notify every active authority user of a department (+ optionally admins).
 */
function notify_department(int $departmentId, int $incidentId, string $message): void
{
    $stmt = db()->prepare(
        'SELECT id FROM users WHERE role = "authority" AND department_id = ? AND active = 1'
    );
    $stmt->execute([$departmentId]);
    $ins = db()->prepare('INSERT INTO notifications (user_id, incident_id, message) VALUES (?, ?, ?)');
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
        $ins->execute([(int)$uid, $incidentId, $message]);
    }
}

function notify_user(int $userId, int $incidentId, string $message): void
{
    db()->prepare('INSERT INTO notifications (user_id, incident_id, message) VALUES (?, ?, ?)')
        ->execute([$userId, $incidentId, $message]);
}

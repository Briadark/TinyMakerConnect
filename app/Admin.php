<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function admin_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name('tinymaker_admin');
        session_start();
    }
}

function csrf_token(): string
{
    admin_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = token(16);
    }
    return (string)$_SESSION['csrf'];
}

function verify_csrf(): void
{
    admin_session();
    $posted = (string)($_POST['csrf'] ?? '');
    if ($posted === '' || !hash_equals((string)($_SESSION['csrf'] ?? ''), $posted)) {
        throw new RuntimeException('Invalid session token.');
    }
}

function current_admin(): ?array
{
    admin_session();
    $id = $_SESSION['admin_id'] ?? null;
    if (!$id) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, username, role, is_super, created_at, last_login FROM admins WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$id]);
    $admin = $stmt->fetch();
    return $admin ?: null;
}

function require_admin(): array
{
    $admin = current_admin();
    if (!$admin) {
        redirect_to('/admin.php');
    }
    return $admin;
}

function admin_login(string $username, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        return false;
    }

    admin_session();
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$admin['id'];
    $_SESSION['csrf'] = token(16);
    $update = db()->prepare('UPDATE admins SET last_login = NOW() WHERE id = ?');
    $update->execute([$admin['id']]);
    return true;
}

function admin_logout(): void
{
    admin_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
}

function admin_stats(): array
{
    return [
        'published' => (int)db()->query('SELECT COUNT(*) FROM models WHERE status = "published"')->fetchColumn(),
        'hidden' => (int)db()->query('SELECT COUNT(*) FROM models WHERE status = "hidden"')->fetchColumn(),
        'removed' => (int)db()->query('SELECT COUNT(*) FROM models WHERE status = "removed"')->fetchColumn(),
        'printers' => (int)db()->query('SELECT COUNT(*) FROM printers')->fetchColumn(),
        'blocked' => (int)db()->query('SELECT COUNT(*) FROM printers WHERE blocked = 1')->fetchColumn(),
        'downloads' => (int)db()->query('SELECT COALESCE(SUM(download_count), 0) FROM models')->fetchColumn(),
        'ratings' => (int)db()->query('SELECT COUNT(*) FROM model_ratings')->fetchColumn(),
        'bookmarks' => (int)db()->query('SELECT COUNT(*) FROM model_bookmarks')->fetchColumn(),
    ];
}

function admin_models(): array
{
    $stmt = db()->query(
        'SELECT m.*, p.public_id AS printer_public_id, p.printer_name, p.blocked AS printer_blocked
         FROM models m
         LEFT JOIN printers p ON p.id = m.printer_id
         ORDER BY m.created_at DESC
         LIMIT 100'
    );
    return $stmt->fetchAll();
}

function admin_printers(): array
{
    $stmt = db()->query(
        'SELECT p.*,
                COUNT(m.id) AS model_count,
                SUM(CASE WHEN m.status = "published" THEN 1 ELSE 0 END) AS published_model_count,
                SUM(CASE WHEN m.status = "hidden" THEN 1 ELSE 0 END) AS hidden_model_count
         FROM printers p
         LEFT JOIN models m ON m.printer_id = p.id AND m.status != "removed"
         GROUP BY p.id
         ORDER BY p.last_seen DESC
         LIMIT 100'
    );
    return $stmt->fetchAll();
}

function admin_admins(): array
{
    return db()->query('SELECT id, username, role, is_super, created_at, last_login FROM admins ORDER BY is_super DESC, username ASC')->fetchAll();
}

function admin_leaderboard(): array
{
    $stmt = db()->query(
        'SELECT p.public_id, p.printer_name,
          (SELECT COUNT(*) FROM models m WHERE m.printer_id = p.id AND m.status != "removed") AS uploads,
          (SELECT COUNT(*) FROM model_downloads d WHERE d.printer_id = p.id) AS downloads,
          (SELECT COUNT(*) FROM model_ratings r WHERE r.printer_id = p.id) AS ratings,
          (SELECT COUNT(*) FROM model_bookmarks b WHERE b.printer_id = p.id) AS bookmarks,
          (SELECT COALESCE(SUM(m2.layers), 0) FROM models m2 WHERE m2.printer_id = p.id AND m2.status != "removed") AS uploaded_layers
         FROM printers p
         ORDER BY uploads DESC, downloads DESC, ratings DESC
         LIMIT 50'
    );
    return $stmt->fetchAll();
}

function admin_handle_post(): ?string
{
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $admin = require_admin();

    if ($action === 'admin_add') {
        if ((int)$admin['is_super'] !== 1) {
            throw new RuntimeException('Only the super admin can add admins.');
        }
        $username = clean_string((string)($_POST['username'] ?? ''), 80);
        $password = (string)($_POST['password'] ?? '');
        if ($username === '' || strlen($password) < 10) {
            throw new RuntimeException('Admin username is required and password must be at least 10 characters.');
        }
        $stmt = db()->prepare('INSERT INTO admins (username, password_hash, role, is_super) VALUES (?, ?, \'admin\', 0)');
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
        return 'Admin added.';
    }

    if ($action === 'admin_delete') {
        if ((int)$admin['is_super'] !== 1) {
            throw new RuntimeException('Only the super admin can delete admins.');
        }
        $id = (int)($_POST['admin_id'] ?? 0);
        if ($id === (int)$admin['id']) {
            throw new RuntimeException('You cannot delete your own account.');
        }
        $stmt = db()->prepare('DELETE FROM admins WHERE id = ? AND is_super = 0');
        $stmt->execute([$id]);
        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('Super admin cannot be deleted.');
        }
        return 'Admin deleted.';
    }

    if ($action === 'model_update') {
        $id = (int)($_POST['model_id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        if (!in_array($status, ['published', 'hidden', 'removed'], true)) {
            throw new RuntimeException('Invalid model status.');
        }
        $name = clean_string((string)($_POST['model_name'] ?? ''), 120);
        $credits = clean_string((string)($_POST['original_credits'] ?? ''), 255);
        if ($name === '') {
            throw new RuntimeException('Model name is required.');
        }
        $stmt = db()->prepare('UPDATE models SET model_name = ?, original_credits = ?, status = ? WHERE id = ?');
        $stmt->execute([$name, $credits, $status, $id]);
        return 'Model updated.';
    }

    if ($action === 'printer_block') {
        $id = (int)($_POST['printer_id'] ?? 0);
        $blocked = (int)($_POST['blocked'] ?? 0) === 1 ? 1 : 0;
        $reason = clean_string((string)($_POST['block_reason'] ?? ''), 255);
        $stmt = db()->prepare('UPDATE printers SET blocked = ?, block_reason = ? WHERE id = ?');
        $stmt->execute([$blocked, $blocked ? $reason : null, $id]);
        return $blocked ? 'Printer blocked.' : 'Printer unblocked.';
    }

    if ($action === 'printer_delete_unused') {
        $id = (int)($_POST['printer_id'] ?? 0);
        $count = db()->prepare('SELECT COUNT(*) FROM models WHERE printer_id = ?');
        $count->execute([$id]);
        if ((int)$count->fetchColumn() > 0) {
            throw new RuntimeException('Only printers without uploaded models can be deleted.');
        }
        $stmt = db()->prepare('DELETE FROM printers WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0 ? 'Unused printer deleted.' : 'Printer not found.';
    }

    if ($action === 'hide_printer_models') {
        $id = (int)($_POST['printer_id'] ?? 0);
        $stmt = db()->prepare('UPDATE models SET status = "hidden" WHERE printer_id = ? AND status = "published"');
        $stmt->execute([$id]);
        return 'Published models from printer hidden.';
    }

    if ($action === 'unhide_printer_models') {
        $id = (int)($_POST['printer_id'] ?? 0);
        $stmt = db()->prepare('UPDATE models SET status = "published" WHERE printer_id = ? AND status = "hidden"');
        $stmt->execute([$id]);
        return 'Hidden models from printer published.';
    }

    throw new RuntimeException('Unknown action.');
}

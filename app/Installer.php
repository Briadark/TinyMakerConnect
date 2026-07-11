<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function install_page(string $title, string $body): void
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . '</title><style>';
    echo ':root{color-scheme:dark;--bg:#111214;--panel:#1b1d20;--text:#f2f2f2;--muted:#a5a7ad;--line:#33363d;--accent:#e8720c;--bad:#d95c5c}';
    echo '*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,Segoe UI,sans-serif}main{width:min(760px,100%);margin:0 auto;padding:28px}';
    echo '.card{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:18px}h1{margin:0 0 6px;font-size:28px}.muted{color:var(--muted)}label{display:block;margin:14px 0 6px}';
    echo 'input{width:100%;border:1px solid var(--line);border-radius:7px;background:#101113;color:var(--text);padding:11px;font:inherit}button,.button{display:inline-block;border:0;border-radius:8px;background:var(--accent);color:white;padding:11px 14px;font-weight:700;text-decoration:none;cursor:pointer}';
    echo '.row{display:grid;grid-template-columns:1fr 120px;gap:10px}.err{border:1px solid var(--bad);background:#321b1b;color:#ffd2d2;padding:10px;border-radius:8px}.ok{border:1px solid #357a45;background:#18331e;color:#cbffd6;padding:10px;border-radius:8px}pre{overflow:auto;background:#0b0c0d;border:1px solid var(--line);border-radius:8px;padding:12px}';
    echo '</style></head><body><main><h1>Welcome to TinyMaker Connect</h1><p class="muted">First-run setup for the TinyMaker web service.</p><div class="card">' . $body . '</div></main></body></html>';
    exit;
}

function run_schema(PDO $pdo): void
{
    migrate_database($pdo);
}

function build_config(array $input): array
{
    $host = clean_string((string)($input['db_host'] ?? 'localhost'), 120);
    $port = (int)($input['db_port'] ?? 3306);
    $name = clean_string((string)($input['db_name'] ?? ''), 120);
    $user = clean_string((string)($input['db_user'] ?? ''), 120);
    $pass = (string)($input['db_pass'] ?? '');
    $storageBase = rtrim(clean_string((string)($input['storage_base'] ?? ''), 255), '/\\');

    if ($host === '' || $name === '' || $user === '' || $storageBase === '') {
        throw new RuntimeException('Database host, database name, database user and storage path are required.');
    }
    if ($port < 1 || $port > 65535) {
        throw new RuntimeException('Database port is invalid.');
    }

    return [
        'db' => [
            'dsn' => 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4',
            'user' => $user,
            'pass' => $pass,
        ],
        'storage' => [
            'models' => $storageBase . DIRECTORY_SEPARATOR . 'models',
            'previews' => $storageBase . DIRECTORY_SEPARATOR . 'previews',
            'tmp' => $storageBase . DIRECTORY_SEPARATOR . 'tmp',
        ],
        'limits' => [
            'max_archive_bytes' => 120 * 1024 * 1024,
            'max_preview_bytes' => 2 * 1024 * 1024,
        ],
        'security' => [
            'server_salt' => bin2hex(random_bytes(32)),
        ],
    ];
}

function config_php(array $config): string
{
    return "<?php\nreturn " . var_export($config, true) . ";\n";
}

function write_config(array $config): void
{
    $php = config_php($config);
    if (@file_put_contents(config_path(), $php, LOCK_EX) === false) {
        install_page('Configuration file needed',
            '<div class="err">The installer could not write <code>app/config.php</code>. Create it manually with this content, then reload this page.</div><pre>' .
            h($php) . '</pre>'
        );
    }
}

function form_value(string $key, string $default = ''): string
{
    return h((string)($_POST[$key] ?? $default));
}

function mysql_form(?string $error = null): void
{
    $defaultStorage = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage';
    $body = '';
    if ($error) {
        $body .= '<div class="err">' . h($error) . '</div>';
    }
    $body .= '<h2>Configure MySQL</h2><form method="post"><input type="hidden" name="step" value="mysql">';
    $body .= '<label>MySQL host</label><input name="db_host" required value="' . form_value('db_host', 'localhost') . '">';
    $body .= '<div class="row"><div><label>Database name</label><input name="db_name" required value="' . form_value('db_name') . '"></div><div><label>Port</label><input name="db_port" type="number" min="1" max="65535" value="' . form_value('db_port', '3306') . '"></div></div>';
    $body .= '<label>Database user</label><input name="db_user" required value="' . form_value('db_user') . '">';
    $body .= '<label>Database password</label><input name="db_pass" type="password" value="' . form_value('db_pass') . '">';
    $body .= '<label>Storage base path</label><input name="storage_base" required value="' . form_value('storage_base', $defaultStorage) . '">';
    $body .= '<p class="muted">The installer will create <code>models</code>, <code>previews</code>, and <code>tmp</code> folders inside this path.</p>';
    $body .= '<button type="submit">Save and continue</button></form>';
    install_page('Configure MySQL', $body);
}

function admin_form(?string $error = null): void
{
    $body = '';
    if ($error) {
        $body .= '<div class="err">' . h($error) . '</div>';
    }
    $body .= '<h2>Configure the admin account</h2><form method="post"><input type="hidden" name="step" value="admin">';
    $body .= '<label>Admin username</label><input name="username" required maxlength="80" value="' . form_value('username', 'admin') . '">';
    $body .= '<label>Password</label><input name="password" type="password" required minlength="10">';
    $body .= '<label>Repeat password</label><input name="password_confirm" type="password" required minlength="10">';
    $body .= '<p class="muted">Use at least 10 characters. This account controls moderation and printer blocking.</p>';
    $body .= '<button type="submit">Create admin</button></form>';
    install_page('Create admin', $body);
}

function handle_mysql_step(): void
{
    try {
        $config = build_config($_POST);
        $pdo = pdo_from_config($config);
        foreach ($config['storage'] as $path) {
            if (!is_dir($path) && !mkdir($path, 0775, true)) {
                throw new RuntimeException('Could not create storage folder: ' . $path);
            }
            if (!is_writable($path)) {
                throw new RuntimeException('Storage folder is not writable by PHP: ' . $path);
            }
        }
        run_schema($pdo);
        write_config($config);
        redirect_to('/install.php?step=admin');
    } catch (Throwable $e) {
        mysql_form($e->getMessage());
    }
}

function handle_admin_step(): void
{
    try {
        run_schema(db());
        if (admin_count() > 0) {
            redirect_to('/admin.php');
        }
        $username = clean_string((string)($_POST['username'] ?? ''), 80);
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');
        if ($username === '' || strlen($password) < 10) {
            throw new RuntimeException('Username is required and password must be at least 10 characters.');
        }
        if ($password !== $confirm) {
            throw new RuntimeException('Passwords do not match.');
        }

        $stmt = db()->prepare('INSERT INTO admins (username, password_hash, role, is_super) VALUES (?, ?, \'super_admin\', 1)');
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
        redirect_to('/admin.php');
    } catch (Throwable $e) {
        admin_form($e->getMessage());
    }
}

function installer_main(): void
{
    if (request_method() === 'POST' && ($_POST['step'] ?? '') === 'mysql') {
        handle_mysql_step();
    }

    if (!config_is_installed()) {
        mysql_form();
    }

    try {
        run_schema(db());
    } catch (Throwable $e) {
        mysql_form('Existing configuration could not connect or migrate: ' . $e->getMessage());
    }

    if (admin_count() < 1) {
        if (request_method() === 'POST' && ($_POST['step'] ?? '') === 'admin') {
            handle_admin_step();
        }
        admin_form();
    }

    redirect_to('/admin.php');
}

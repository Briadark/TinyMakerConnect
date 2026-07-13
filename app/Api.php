<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function printer_hardware_hash(string $hardware): string
{
    return hash('sha256', config()['security']['server_salt'] . '|printer|' . $hardware);
}

function request_truthy(string $key): bool
{
    return isset($_POST[$key]) && $_POST[$key] !== '' && $_POST[$key] !== '0' && $_POST[$key] !== 'false';
}

function printer_register_response(array $printer, bool $leaderboard, int $status = 200): void
{
    json_response([
        'ok' => true,
        'printer_public_id' => $printer['public_id'],
        'publish_token' => $printer['publish_token'],
        'leaderboard_opt_in' => $leaderboard,
        'blocked' => (bool)$printer['blocked'],
    ], $status);
}

function create_printer_for_hardware(string $hash, string $firmware, string $name, int $leaderboard): array
{
    $publicId = public_id();
    $publishToken = token();
    $insert = db()->prepare('INSERT INTO printers (public_id, hardware_hash, publish_token, firmware_version, printer_name, leaderboard_opt_in) VALUES (?, ?, ?, ?, ?, ?)');
    $insert->execute([$publicId, $hash, $publishToken, $firmware ?: null, $name ?: null, $leaderboard]);
    $stmt = db()->prepare('SELECT * FROM printers WHERE public_id = ? LIMIT 1');
    $stmt->execute([$publicId]);
    return $stmt->fetch();
}

function api_register_printer(): void
{
    $hardware = clean_string((string)($_POST['hardware_id'] ?? ''), 128);
    if ($hardware === '') {
        error_response('hardware_id required', 400);
    }

    $firmware = clean_string((string)($_POST['firmware_version'] ?? ''), 32);
    $name = clean_string((string)($_POST['printer_name'] ?? ''), 80);
    $leaderboard = request_truthy('leaderboard_opt_in') ? 1 : 0;
    $incomingToken = clean_string((string)($_POST['publish_token'] ?? ''), 80);
    $hash = printer_hardware_hash($hardware);

    $stmt = db()->prepare('SELECT * FROM printers WHERE hardware_hash = ? LIMIT 1');
    $stmt->execute([$hash]);
    $existing = $stmt->fetch();
    if ($existing) {
        if ($incomingToken !== '' && hash_equals((string)$existing['publish_token'], $incomingToken)) {
            $update = db()->prepare('UPDATE printers SET firmware_version = ?, printer_name = COALESCE(NULLIF(?, ""), printer_name), leaderboard_opt_in = ?, last_seen = NOW() WHERE id = ?');
            $update->execute([$firmware ?: null, $name, $leaderboard, $existing['id']]);
            $existing['firmware_version'] = $firmware ?: $existing['firmware_version'];
            $existing['printer_name'] = $name ?: $existing['printer_name'];
            $existing['leaderboard_opt_in'] = $leaderboard;
            printer_register_response($existing, (bool)$leaderboard);
        }
        if (request_truthy('new_profile')) {
            $archiveHash = hash('sha256', $existing['hardware_hash'] . '|archived|' . $existing['public_id'] . '|' . time());
            $archive = db()->prepare('UPDATE printers SET hardware_hash = ? WHERE id = ?');
            $archive->execute([$archiveHash, $existing['id']]);
            printer_register_response(create_printer_for_hardware($hash, $firmware, $name, $leaderboard), (bool)$leaderboard, 201);
        }
        json_response([
            'ok' => false,
            'error' => 'reclaim required',
            'reclaim_required' => true,
            'printer_public_id' => $existing['public_id'],
        ], 409);
    }

    printer_register_response(create_printer_for_hardware($hash, $firmware, $name, $leaderboard), (bool)$leaderboard, 201);
}

function api_lookup_printer(): void
{
    $hardware = clean_string((string)($_POST['hardware_id'] ?? ''), 128);
    if ($hardware === '') {
        error_response('hardware_id required', 400);
    }
    $hash = printer_hardware_hash($hardware);
    $stmt = db()->prepare('SELECT public_id FROM printers WHERE hardware_hash = ? LIMIT 1');
    $stmt->execute([$hash]);
    $existing = $stmt->fetch();
    json_response([
        'ok' => true,
        'known' => (bool)$existing,
        'printer_public_id' => $existing ? $existing['public_id'] : null,
    ]);
}

function api_reclaim_printer(): void
{
    $hardware = clean_string((string)($_POST['hardware_id'] ?? ''), 128);
    $recovery = clean_string((string)($_POST['recovery_code'] ?? ($_POST['publish_token'] ?? '')), 80);
    if ($hardware === '' || $recovery === '') {
        error_response('hardware_id and recovery_code required', 400);
    }

    $firmware = clean_string((string)($_POST['firmware_version'] ?? ''), 32);
    $name = clean_string((string)($_POST['printer_name'] ?? ''), 80);
    $leaderboard = request_truthy('leaderboard_opt_in') ? 1 : 0;
    $hash = printer_hardware_hash($hardware);

    $stmt = db()->prepare('SELECT * FROM printers WHERE hardware_hash = ? LIMIT 1');
    $stmt->execute([$hash]);
    $printer = $stmt->fetch();
    if (!$printer || !hash_equals((string)$printer['publish_token'], $recovery)) {
        error_response('invalid recovery code', 401);
    }
    $update = db()->prepare('UPDATE printers SET firmware_version = ?, printer_name = COALESCE(NULLIF(?, ""), printer_name), leaderboard_opt_in = ?, last_seen = NOW() WHERE id = ?');
    $update->execute([$firmware ?: null, $name, $leaderboard, $printer['id']]);
    $printer['firmware_version'] = $firmware ?: $printer['firmware_version'];
    $printer['printer_name'] = $name ?: $printer['printer_name'];
    $printer['leaderboard_opt_in'] = $leaderboard;
    printer_register_response($printer, (bool)$leaderboard);
}

function api_store_printer_backup(): void
{
    $printer = require_printer();
    $raw = file_get_contents('php://input') ?: '';
    if ($raw === '' && isset($_POST['backup'])) {
        $raw = (string)$_POST['backup'];
    }
    if (strlen($raw) < 10 || strlen($raw) > 655350) {
        error_response('invalid backup', 400);
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || (int)($decoded['backupVersion'] ?? 0) < 1) {
        error_response('not a TinyMaker backup', 400);
    }

    $stmt = db()->prepare(
        'INSERT INTO printer_backups (printer_id, backup_json, updated_at)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE backup_json = VALUES(backup_json), updated_at = NOW()'
    );
    $stmt->execute([$printer['id'], $raw]);
    $ts = db()->prepare('SELECT UNIX_TIMESTAMP(updated_at) AS updated_epoch FROM printer_backups WHERE printer_id = ?');
    $ts->execute([$printer['id']]);
    json_response(['ok' => true, 'updated_epoch' => (int)$ts->fetchColumn()]);
}

function api_get_printer_backup(): void
{
    $printer = require_printer();
    $stmt = db()->prepare('SELECT backup_json, UNIX_TIMESTAMP(updated_at) AS updated_epoch FROM printer_backups WHERE printer_id = ? LIMIT 1');
    $stmt->execute([$printer['id']]);
    $backup = $stmt->fetch();
    if (!$backup) {
        error_response('backup not found', 404);
    }
    json_response([
        'ok' => true,
        'updated_epoch' => (int)$backup['updated_epoch'],
        'backup' => json_decode((string)$backup['backup_json'], true),
    ]);
}

function api_list_models(bool $mine = false): void
{
    if ($mine) {
        $printer = require_printer();
        $stmt = db()->prepare('SELECT * FROM models WHERE printer_id = ? AND status != "removed" ORDER BY created_at DESC LIMIT 100');
        $stmt->execute([$printer['id']]);
    } else {
        $stmt = db()->query('SELECT * FROM models WHERE status = "published" ORDER BY created_at DESC LIMIT 100');
    }

    $items = array_map('model_to_api', $stmt->fetchAll());
    json_response(['ok' => true, 'items' => $items]);
}

function api_list_bookmarks(): void
{
    $printer = require_printer();
    $stmt = db()->prepare(
        'SELECT m.*
         FROM model_bookmarks b
         JOIN models m ON m.id = b.model_id
         WHERE b.printer_id = ? AND m.status = "published"
         ORDER BY b.created_at DESC
         LIMIT 100'
    );
    $stmt->execute([$printer['id']]);
    json_response(['ok' => true, 'items' => array_map('model_to_api', $stmt->fetchAll())]);
}

function api_leaderboard(): void
{
    $stmt = db()->query(
        'SELECT p.public_id, p.printer_name,
          (SELECT COUNT(*) FROM models m WHERE m.printer_id = p.id AND m.status != "removed") AS uploads,
          (SELECT COUNT(*) FROM model_downloads d WHERE d.printer_id = p.id) AS downloads,
          (SELECT COUNT(*) FROM model_ratings r WHERE r.printer_id = p.id) AS ratings,
          (SELECT COUNT(*) FROM model_bookmarks b WHERE b.printer_id = p.id) AS bookmarks,
          (SELECT COALESCE(SUM(m2.layers), 0) FROM models m2 WHERE m2.printer_id = p.id AND m2.status != "removed") AS uploaded_layers
         FROM printers p
         WHERE p.blocked = 0 AND p.leaderboard_opt_in = 1
         ORDER BY uploads DESC, downloads DESC, ratings DESC
         LIMIT 50'
    );
    json_response(['ok' => true, 'items' => $stmt->fetchAll()]);
}

function api_get_model(string $publicId): void
{
    $publicId = clean_public_id($publicId);
    $stmt = db()->prepare('SELECT * FROM models WHERE public_id = ? AND status = "published" LIMIT 1');
    $stmt->execute([$publicId]);
    $model = $stmt->fetch();
    if (!$model) {
        error_response('model not found', 404);
    }
    json_response(['ok' => true, 'model' => model_to_api($model)]);
}

function validate_upload(array $file, int $maxBytes, array $allowedExt): void
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        error_response('upload failed', 400);
    }
    if ((int)$file['size'] <= 0 || (int)$file['size'] > $maxBytes) {
        error_response('file too large', 413);
    }
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        error_response('unsupported file type', 400);
    }
}

function store_optional_model_preview(string $publicId, string $field, string $suffix): ?string
{
    if (!isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $limits = config()['limits'];
    validate_upload($_FILES[$field], (int)$limits['max_preview_bytes'], ['png', 'jpg', 'jpeg']);
    $previewExt = strtolower(pathinfo((string)$_FILES[$field]['name'], PATHINFO_EXTENSION));
    $previewName = $publicId . $suffix . '.' . $previewExt;
    $previewFullPath = rtrim(config()['storage']['previews'], '/\\') . DIRECTORY_SEPARATOR . $previewName;
    if (!move_uploaded_file((string)$_FILES[$field]['tmp_name'], $previewFullPath)) {
        error_response('could not store preview', 500);
    }
    return $previewName;
}

function api_publish_model(): void
{
    ensure_storage();
    $printer = require_printer();
    $limits = config()['limits'];

    $name = clean_string((string)($_POST['model_name'] ?? ''), 120);
    if ($name === '') {
        error_response('model_name required', 400);
    }
    $credits = clean_string((string)($_POST['original_credits'] ?? ''), 255);
    $license = clean_string((string)($_POST['license'] ?? 'CC-BY-NC'), 32);
    $layers = (int)($_POST['layers'] ?? 0);
    $height = (float)($_POST['height_mm'] ?? 0);
    $resin = isset($_POST['resin_ml']) && $_POST['resin_ml'] !== '' ? (float)$_POST['resin_ml'] : null;

    if ($layers <= 0 || $height <= 0) {
        error_response('layers and height_mm must be positive', 400);
    }
    if (!isset($_FILES['archive'])) {
        error_response('archive required', 400);
    }

    validate_upload($_FILES['archive'], (int)$limits['max_archive_bytes'], ['zip', 'sl1']);
    $publicId = public_id();
    $ext = strtolower(pathinfo((string)$_FILES['archive']['name'], PATHINFO_EXTENSION));
    $archiveName = $publicId . '.' . $ext;
    $archivePath = rtrim(config()['storage']['models'], '/\\') . DIRECTORY_SEPARATOR . $archiveName;

    if (!move_uploaded_file((string)$_FILES['archive']['tmp_name'], $archivePath)) {
        error_response('could not store archive', 500);
    }

    $preview05Path = store_optional_model_preview($publicId, 'preview05', '-05');
    $preview1Path = store_optional_model_preview($publicId, 'preview1', '-1');

    $checksum = hash_file('sha256', $archivePath);
    $size = filesize($archivePath);

    $stmt = db()->prepare(
        'INSERT INTO models (public_id, printer_id, model_name, original_credits, license, layers, height_mm, resin_ml, file_size, checksum_sha256, preview_05_path, preview_1_path, download_path)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $publicId,
        $printer['id'],
        $name,
        $credits,
        $license ?: 'CC-BY-NC',
        $layers,
        $height,
        $resin,
        $size,
        $checksum,
        $preview05Path,
        $preview1Path,
        $archiveName,
    ]);

    $stmt = db()->prepare('SELECT * FROM models WHERE public_id = ?');
    $stmt->execute([$publicId]);
    json_response(['ok' => true, 'model' => model_to_api($stmt->fetch())], 201);
}

function api_list_boot_animations(bool $mine = false): void
{
    if ($mine) {
        $printer = require_printer();
        $stmt = db()->prepare('SELECT * FROM boot_animations WHERE printer_id = ? AND status != "removed" ORDER BY created_at DESC LIMIT 100');
        $stmt->execute([$printer['id']]);
    } else {
        $stmt = db()->query('SELECT * FROM boot_animations WHERE status = "published" ORDER BY created_at DESC LIMIT 100');
    }

    json_response(['ok' => true, 'items' => array_map('boot_animation_to_api', $stmt->fetchAll())]);
}

function api_publish_boot_animation(): void
{
    ensure_storage();
    $printer = require_printer();
    $limits = config()['limits'];

    $name = clean_string((string)($_POST['animation_name'] ?? ''), 120);
    if ($name === '') {
        error_response('animation_name required', 400);
    }
    $credits = clean_string((string)($_POST['original_credits'] ?? ''), 255);
    $description = clean_string((string)($_POST['description'] ?? ''), 255);
    $license = clean_string((string)($_POST['license'] ?? 'CC-BY-NC'), 32);
    $version = clean_string((string)($_POST['version'] ?? '1.0.0'), 32);
    $installName = clean_install_name((string)($_POST['install_name'] ?? $name));
    if (boot_animation_install_name_reserved($installName)) {
        error_response('reserved install name', 400);
    }

    if (!isset($_FILES['animation'])) {
        error_response('animation required', 400);
    }
    validate_upload($_FILES['animation'], (int)$limits['max_boot_animation_bytes'], ['tmb']);

    $tmpPath = (string)$_FILES['animation']['tmp_name'];
    $fh = fopen($tmpPath, 'rb');
    $magic = $fh ? fread($fh, 4) : false;
    if ($fh) {
        fclose($fh);
    }
    if ($magic !== 'TMB1') {
        error_response('not a TMB1 animation', 422);
    }

    $publicId = public_id();
    $fileName = $publicId . '.tmb';
    $filePath = rtrim(config()['storage']['boot_animations'], '/\\') . DIRECTORY_SEPARATOR . $fileName;
    if (!move_uploaded_file($tmpPath, $filePath)) {
        error_response('could not store animation', 500);
    }

    $checksum = hash_file('sha256', $filePath);
    $size = filesize($filePath);

    $stmt = db()->prepare(
        'INSERT INTO boot_animations (public_id, printer_id, animation_name, install_name, version, original_credits, description, license, file_size, checksum_sha256, download_path)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $publicId,
        $printer['id'],
        $name,
        $installName,
        $version ?: '1.0.0',
        $credits,
        $description,
        $license ?: 'CC-BY-NC',
        $size,
        $checksum,
        $fileName,
    ]);

    $stmt = db()->prepare('SELECT * FROM boot_animations WHERE public_id = ?');
    $stmt->execute([$publicId]);
    json_response(['ok' => true, 'animation' => boot_animation_to_api($stmt->fetch())], 201);
}

function api_update_boot_animation(string $publicId): void
{
    $publicId = clean_public_id($publicId);
    $printer = require_printer();
    parse_str(file_get_contents('php://input') ?: '', $body);
    $data = array_merge($_POST, $body);

    $stmt = db()->prepare('SELECT * FROM boot_animations WHERE public_id = ? AND printer_id = ? LIMIT 1');
    $stmt->execute([$publicId, $printer['id']]);
    $animation = $stmt->fetch();
    if (!$animation) {
        error_response('animation not found', 404);
    }

    $status = $data['status'] ?? null;
    if ($status !== null && !in_array($status, ['published', 'hidden'], true)) {
        error_response('invalid status', 400);
    }

    $name = array_key_exists('animation_name', $data) ? clean_string((string)$data['animation_name'], 120) : $animation['animation_name'];
    $credits = array_key_exists('original_credits', $data) ? clean_string((string)$data['original_credits'], 255) : $animation['original_credits'];
    $description = array_key_exists('description', $data) ? clean_string((string)$data['description'], 255) : $animation['description'];
    $installName = array_key_exists('install_name', $data) ? clean_install_name((string)$data['install_name']) : $animation['install_name'];
    if (boot_animation_install_name_reserved($installName)) {
        error_response('reserved install name', 400);
    }
    $version = array_key_exists('version', $data) ? clean_string((string)$data['version'], 32) : ($animation['version'] ?? '1.0.0');
    $license = array_key_exists('license', $data) ? clean_string((string)$data['license'], 32) : ($animation['license'] ?? 'CC-BY-NC');

    $update = db()->prepare('UPDATE boot_animations SET animation_name = ?, install_name = ?, version = ?, original_credits = ?, description = ?, license = ?, status = COALESCE(?, status) WHERE id = ?');
    $update->execute([$name, $installName, $version ?: '1.0.0', $credits, $description, $license ?: 'CC-BY-NC', $status, $animation['id']]);

    $stmt = db()->prepare('SELECT * FROM boot_animations WHERE id = ?');
    $stmt->execute([$animation['id']]);
    json_response(['ok' => true, 'animation' => boot_animation_to_api($stmt->fetch())]);
}

function api_remove_boot_animation(string $publicId): void
{
    $publicId = clean_public_id($publicId);
    $printer = require_printer();
    $stmt = db()->prepare('UPDATE boot_animations SET status = "removed" WHERE public_id = ? AND printer_id = ?');
    $stmt->execute([$publicId, $printer['id']]);
    if ($stmt->rowCount() < 1) {
        error_response('animation not found', 404);
    }
    json_response(['ok' => true, 'removed' => true]);
}

function api_download_boot_animation(string $publicId): void
{
    $publicId = clean_public_id($publicId);
    $stmt = db()->prepare('SELECT * FROM boot_animations WHERE public_id = ? AND status = "published" LIMIT 1');
    $stmt->execute([$publicId]);
    $animation = $stmt->fetch();
    if (!$animation) {
        error_response('animation not found', 404);
    }

    $path = rtrim(config()['storage']['boot_animations'], '/\\') . DIRECTORY_SEPARATOR . $animation['download_path'];
    if (!is_file($path)) {
        error_response('file missing', 404);
    }

    $printer = optional_printer();
    if ($printer) {
        $log = db()->prepare('INSERT IGNORE INTO boot_animation_downloads (animation_id, printer_id, ip_hash) VALUES (?, ?, ?)');
        $log->execute([$animation['id'], $printer['id'], ip_hash()]);
        if ($log->rowCount() > 0) {
            $count = db()->prepare('UPDATE boot_animations SET download_count = download_count + 1 WHERE id = ?');
            $count->execute([$animation['id']]);
        }
    } else {
        $log = db()->prepare('INSERT INTO boot_animation_downloads (animation_id, ip_hash) VALUES (?, ?)');
        $log->execute([$animation['id'], ip_hash()]);
    }
    $downloadName = preg_replace('/[^A-Za-z0-9_.-]/', '_', $animation['install_name']) . '.tmb';

    cors_headers();
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    readfile($path);
    exit;
}

function api_preview_boot_animation(string $publicId): void
{
    $publicId = clean_public_id($publicId);
    $stmt = db()->prepare('SELECT * FROM boot_animations WHERE public_id = ? AND status = "published" LIMIT 1');
    $stmt->execute([$publicId]);
    $animation = $stmt->fetch();
    if (!$animation) {
        error_response('animation not found', 404);
    }

    $path = rtrim(config()['storage']['boot_animations'], '/\\') . DIRECTORY_SEPARATOR . $animation['download_path'];
    if (!is_file($path)) {
        error_response('file missing', 404);
    }

    cors_headers();
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: public, max-age=86400');
    readfile($path);
    exit;
}

function api_update_model(string $publicId): void
{
    $publicId = clean_public_id($publicId);
    $printer = require_printer();
    parse_str(file_get_contents('php://input') ?: '', $body);
    $data = array_merge($_POST, $body);

    $stmt = db()->prepare('SELECT * FROM models WHERE public_id = ? AND printer_id = ? LIMIT 1');
    $stmt->execute([$publicId, $printer['id']]);
    $model = $stmt->fetch();
    if (!$model) {
        error_response('model not found', 404);
    }

    $status = $data['status'] ?? null;
    if ($status !== null && !in_array($status, ['published', 'hidden'], true)) {
        error_response('invalid status', 400);
    }

    $name = array_key_exists('model_name', $data) ? clean_string((string)$data['model_name'], 120) : $model['model_name'];
    $credits = array_key_exists('original_credits', $data) ? clean_string((string)$data['original_credits'], 255) : $model['original_credits'];
    $license = array_key_exists('license', $data) ? clean_string((string)$data['license'], 32) : ($model['license'] ?? 'CC-BY-NC');

    $update = db()->prepare('UPDATE models SET model_name = ?, original_credits = ?, license = ?, status = COALESCE(?, status) WHERE id = ?');
    $update->execute([$name, $credits, $license ?: 'CC-BY-NC', $status, $model['id']]);

    $stmt = db()->prepare('SELECT * FROM models WHERE id = ?');
    $stmt->execute([$model['id']]);
    json_response(['ok' => true, 'model' => model_to_api($stmt->fetch())]);
}

function api_remove_model(string $publicId): void
{
    $publicId = clean_public_id($publicId);
    $printer = require_printer();
    $stmt = db()->prepare('UPDATE models SET status = "removed" WHERE public_id = ? AND printer_id = ?');
    $stmt->execute([$publicId, $printer['id']]);
    if ($stmt->rowCount() < 1) {
        error_response('model not found', 404);
    }
    json_response(['ok' => true, 'removed' => true]);
}

function api_download_model(string $publicId): void
{
    $publicId = clean_public_id($publicId);
    $stmt = db()->prepare('SELECT * FROM models WHERE public_id = ? AND status = "published" LIMIT 1');
    $stmt->execute([$publicId]);
    $model = $stmt->fetch();
    if (!$model) {
        error_response('model not found', 404);
    }

    $path = rtrim(config()['storage']['models'], '/\\') . DIRECTORY_SEPARATOR . $model['download_path'];
    if (!is_file($path)) {
        error_response('file missing', 404);
    }

    $printer = optional_printer();
    if ($printer) {
        $log = db()->prepare('INSERT IGNORE INTO model_downloads (model_id, printer_id, ip_hash) VALUES (?, ?, ?)');
        $log->execute([$model['id'], $printer['id'], ip_hash()]);
        if ($log->rowCount() > 0) {
            $count = db()->prepare('UPDATE models SET download_count = download_count + 1 WHERE id = ?');
            $count->execute([$model['id']]);
        }
    } else {
        $log = db()->prepare('INSERT INTO model_downloads (model_id, ip_hash) VALUES (?, ?)');
        $log->execute([$model['id'], ip_hash()]);
    }

    $ext = strtolower(pathinfo((string)$model['download_path'], PATHINFO_EXTENSION));
    $downloadName = preg_replace('/[^A-Za-z0-9_.-]/', '_', $model['model_name']) . '.' . ($ext ?: 'zip');

    cors_headers();
    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    readfile($path);
    exit;
}

function api_rate_model(string $publicId): void
{
    $publicId = clean_public_id($publicId);
    $printer = require_printer();
    $rating = (int)($_POST['rating'] ?? 0);
    if ($rating < 1 || $rating > 5) {
        error_response('rating must be between 1 and 5', 400);
    }

    $model = find_published_model($publicId);
    $stmt = db()->prepare('SELECT rating FROM model_ratings WHERE model_id = ? AND printer_id = ? LIMIT 1');
    $stmt->execute([$model['id'], $printer['id']]);
    $old = $stmt->fetchColumn();

    if ($old === false) {
        $insert = db()->prepare('INSERT INTO model_ratings (model_id, printer_id, rating) VALUES (?, ?, ?)');
        $insert->execute([$model['id'], $printer['id'], $rating]);
        $update = db()->prepare('UPDATE models SET rating_count = rating_count + 1, rating_sum = rating_sum + ? WHERE id = ?');
        $update->execute([$rating, $model['id']]);
    } else {
        $updateRating = db()->prepare('UPDATE model_ratings SET rating = ? WHERE model_id = ? AND printer_id = ?');
        $updateRating->execute([$rating, $model['id'], $printer['id']]);
        $update = db()->prepare('UPDATE models SET rating_sum = rating_sum - ? + ? WHERE id = ?');
        $update->execute([(int)$old, $rating, $model['id']]);
    }

    $stmt = db()->prepare('SELECT * FROM models WHERE id = ?');
    $stmt->execute([$model['id']]);
    json_response(['ok' => true, 'model' => model_to_api($stmt->fetch())]);
}

function api_bookmark_model(string $publicId, bool $bookmark): void
{
    $publicId = clean_public_id($publicId);
    $printer = require_printer();
    $model = find_published_model($publicId);

    if ($bookmark) {
        $stmt = db()->prepare('INSERT IGNORE INTO model_bookmarks (model_id, printer_id) VALUES (?, ?)');
        $stmt->execute([$model['id'], $printer['id']]);
        if ($stmt->rowCount() > 0) {
            $update = db()->prepare('UPDATE models SET bookmark_count = bookmark_count + 1 WHERE id = ?');
            $update->execute([$model['id']]);
        }
    } else {
        $stmt = db()->prepare('DELETE FROM model_bookmarks WHERE model_id = ? AND printer_id = ?');
        $stmt->execute([$model['id'], $printer['id']]);
        if ($stmt->rowCount() > 0) {
            $update = db()->prepare('UPDATE models SET bookmark_count = GREATEST(bookmark_count - 1, 0) WHERE id = ?');
            $update->execute([$model['id']]);
        }
    }

    $stmt = db()->prepare('SELECT * FROM models WHERE id = ?');
    $stmt->execute([$model['id']]);
    json_response(['ok' => true, 'model' => model_to_api($stmt->fetch()), 'bookmarked' => $bookmark]);
}

function find_published_model(string $publicId): array
{
    $publicId = clean_public_id($publicId);
    $stmt = db()->prepare('SELECT * FROM models WHERE public_id = ? AND status = "published" LIMIT 1');
    $stmt->execute([$publicId]);
    $model = $stmt->fetch();
    if (!$model) {
        error_response('model not found', 404);
    }
    return $model;
}

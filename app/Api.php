<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function api_register_printer(): void
{
    $hardware = clean_string((string)($_POST['hardware_id'] ?? ''), 128);
    if ($hardware === '') {
        error_response('hardware_id required', 400);
    }

    $firmware = clean_string((string)($_POST['firmware_version'] ?? ''), 32);
    $name = clean_string((string)($_POST['printer_name'] ?? ''), 80);
    $leaderboard = !empty($_POST['leaderboard_opt_in']) && $_POST['leaderboard_opt_in'] !== '0' && $_POST['leaderboard_opt_in'] !== 'false' ? 1 : 0;
    $hash = hash('sha256', config()['security']['server_salt'] . '|printer|' . $hardware);

    $stmt = db()->prepare('SELECT * FROM printers WHERE hardware_hash = ? LIMIT 1');
    $stmt->execute([$hash]);
    $existing = $stmt->fetch();
    if ($existing) {
        $update = db()->prepare('UPDATE printers SET firmware_version = ?, printer_name = COALESCE(NULLIF(?, ""), printer_name), leaderboard_opt_in = ?, last_seen = NOW() WHERE id = ?');
        $update->execute([$firmware ?: null, $name, $leaderboard, $existing['id']]);
        json_response([
            'ok' => true,
            'printer_public_id' => $existing['public_id'],
            'publish_token' => $existing['publish_token'],
            'leaderboard_opt_in' => (bool)$leaderboard,
            'blocked' => (bool)$existing['blocked'],
        ]);
    }

    $publicId = public_id();
    $publishToken = token();
    $insert = db()->prepare('INSERT INTO printers (public_id, hardware_hash, publish_token, firmware_version, printer_name, leaderboard_opt_in) VALUES (?, ?, ?, ?, ?, ?)');
    $insert->execute([$publicId, $hash, $publishToken, $firmware ?: null, $name ?: null, $leaderboard]);

    json_response([
        'ok' => true,
        'printer_public_id' => $publicId,
        'publish_token' => $publishToken,
        'leaderboard_opt_in' => (bool)$leaderboard,
        'blocked' => false,
    ], 201);
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

    $previewPath = null;
    if (isset($_FILES['preview']) && ($_FILES['preview']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        validate_upload($_FILES['preview'], (int)$limits['max_preview_bytes'], ['png', 'jpg', 'jpeg']);
        $previewExt = strtolower(pathinfo((string)$_FILES['preview']['name'], PATHINFO_EXTENSION));
        $previewName = $publicId . '.' . $previewExt;
        $previewFullPath = rtrim(config()['storage']['previews'], '/\\') . DIRECTORY_SEPARATOR . $previewName;
        if (!move_uploaded_file((string)$_FILES['preview']['tmp_name'], $previewFullPath)) {
            error_response('could not store preview', 500);
        }
        $previewPath = $previewName;
    }

    $checksum = hash_file('sha256', $archivePath);
    $size = filesize($archivePath);

    $stmt = db()->prepare(
        'INSERT INTO models (public_id, printer_id, model_name, original_credits, layers, height_mm, resin_ml, file_size, checksum_sha256, preview_path, download_path)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $publicId,
        $printer['id'],
        $name,
        $credits,
        $layers,
        $height,
        $resin,
        $size,
        $checksum,
        $previewPath,
        $archiveName,
    ]);

    $stmt = db()->prepare('SELECT * FROM models WHERE public_id = ?');
    $stmt->execute([$publicId]);
    json_response(['ok' => true, 'model' => model_to_api($stmt->fetch())], 201);
}

function api_update_model(string $publicId): void
{
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

    $update = db()->prepare('UPDATE models SET model_name = ?, original_credits = ?, status = COALESCE(?, status) WHERE id = ?');
    $update->execute([$name, $credits, $status, $model['id']]);

    $stmt = db()->prepare('SELECT * FROM models WHERE id = ?');
    $stmt->execute([$model['id']]);
    json_response(['ok' => true, 'model' => model_to_api($stmt->fetch())]);
}

function api_remove_model(string $publicId): void
{
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
    $stmt = db()->prepare('SELECT * FROM models WHERE public_id = ? AND status = "published" LIMIT 1');
    $stmt->execute([$publicId]);
    $model = $stmt->fetch();
    if (!$model) {
        error_response('model not found', 404);
    }
    return $model;
}

<?php
declare(strict_types=1);

function updater_repo(): string
{
    return (string)(config()['updates']['github_repo'] ?? 'Briadark/TinyMakerConnect');
}

function updater_current_version(): string
{
    return TINYMAKER_CONNECT_VERSION;
}

function updater_http_get(string $url, int $timeout = 8): string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'TinyMakerConnect/' . updater_current_version(),
            CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json'],
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false || $code < 200 || $code >= 300) {
            throw new RuntimeException($err ?: 'GitHub returned HTTP ' . $code);
        }
        return (string)$body;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeout,
            'header' => "User-Agent: TinyMakerConnect/" . updater_current_version() . "\r\nAccept: application/vnd.github+json\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        throw new RuntimeException('Could not fetch update information.');
    }
    return $body;
}

function updater_normalize_version(string $version): string
{
    $version = trim($version);
    return substr($version, 0, 1) === 'v' ? substr($version, 1) : $version;
}

function updater_compare_versions(string $a, string $b): int
{
    return version_compare(updater_normalize_version($a), updater_normalize_version($b));
}

function updater_latest_release(bool $force = false): array
{
    admin_session();
    $cache = $_SESSION['update_status'] ?? null;
    if (!$force && is_array($cache) && (time() - (int)($cache['checked_at'] ?? 0)) < 600) {
        return $cache;
    }

    $repo = updater_repo();
    $repoParts = explode('/', $repo, 2);
    if (count($repoParts) !== 2) {
        throw new RuntimeException('Invalid GitHub repository setting.');
    }
    $json = updater_http_get('https://api.github.com/repos/' . rawurlencode($repoParts[0]) . '/' . rawurlencode($repoParts[1]) . '/releases/latest', 5);
    $release = json_decode($json, true);
    if (!is_array($release) || empty($release['tag_name'])) {
        throw new RuntimeException('Latest release response was invalid.');
    }

    $tag = (string)$release['tag_name'];
    $status = [
        'checked_at' => time(),
        'repo' => $repo,
        'current' => updater_current_version(),
        'latest' => $tag,
        'available' => updater_compare_versions($tag, updater_current_version()) > 0,
        'zipball_url' => (string)($release['zipball_url'] ?? ''),
        'html_url' => (string)($release['html_url'] ?? ''),
        'name' => (string)($release['name'] ?? $tag),
    ];
    $_SESSION['update_status'] = $status;
    return $status;
}

function updater_download_file(string $url, string $target): void
{
    $body = updater_http_get($url, 60);
    if (file_put_contents($target, $body) === false) {
        throw new RuntimeException('Could not write update package.');
    }
}

function updater_is_skipped_path(string $relative): bool
{
    $relative = str_replace('\\', '/', ltrim($relative, '/'));
    if ($relative === '' || substr($relative, -1) === '/') return true;
    if (strpos($relative, '../') !== false || strpos($relative, '/..') !== false || $relative === '..') return true;
    if ($relative === 'app/config.php') return true;
    if ($relative === 'storage' || strpos($relative, 'storage/') === 0) return true;
    if ($relative === '.git' || strpos($relative, '.git/') === 0) return true;
    return false;
}

function updater_write_file(string $relative, string $contents): void
{
    $targetRoot = realpath(dirname(__DIR__));
    if ($targetRoot === false) {
        throw new RuntimeException('Could not resolve app root.');
    }
    $target = $targetRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $dir = dirname($target);
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException('Could not create update directory: ' . $relative);
    }
    $resolvedDir = realpath($dir);
    if ($resolvedDir === false || strpos($resolvedDir . DIRECTORY_SEPARATOR, $targetRoot . DIRECTORY_SEPARATOR) !== 0) {
        throw new RuntimeException('Update file escapes the app root: ' . $relative);
    }
    if (file_put_contents($target, $contents) === false) {
        throw new RuntimeException('Could not update file: ' . $relative);
    }
}

function updater_install_latest(): array
{
    $release = updater_latest_release(true);
    if (empty($release['available'])) {
        return ['updated' => false, 'message' => 'TinyMaker Connect is already up to date.'];
    }
    if (empty($release['zipball_url'])) {
        throw new RuntimeException('Release does not provide a ZIP package.');
    }
    if (strpos((string)$release['zipball_url'], 'https://api.github.com/') !== 0) {
        throw new RuntimeException('Release ZIP URL is not a GitHub API URL.');
    }
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('PHP ZipArchive is required for server updates.');
    }

    ensure_storage();
    $tmpDir = (string)(config()['storage']['tmp'] ?? sys_get_temp_dir());
    if (!is_dir($tmpDir) && !mkdir($tmpDir, 0775, true)) {
        throw new RuntimeException('Could not create temporary update directory.');
    }

    $zipPath = $tmpDir . DIRECTORY_SEPARATOR . 'tinymaker-connect-update-' . time() . '.zip';
    updater_download_file((string)$release['zipball_url'], $zipPath);

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        @unlink($zipPath);
        throw new RuntimeException('Could not open update ZIP.');
    }

    // Stage the full update in memory first so a broken or truncated ZIP
    // never leaves a half-written installation behind.
    $maxTotalBytes = 256 * 1024 * 1024;
    $totalBytes = 0;
    $staged = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string)$zip->getNameIndex($i);
        $parts = explode('/', str_replace('\\', '/', $name), 2);
        if (count($parts) < 2) continue;
        $relative = $parts[1];
        if (updater_is_skipped_path($relative)) continue;
        $contents = $zip->getFromIndex($i);
        if ($contents === false) {
            $zip->close();
            @unlink($zipPath);
            throw new RuntimeException('Could not read update file: ' . $relative);
        }
        $totalBytes += strlen($contents);
        if ($totalBytes > $maxTotalBytes) {
            $zip->close();
            @unlink($zipPath);
            throw new RuntimeException('Update package is unexpectedly large; aborting.');
        }
        $staged[$relative] = $contents;
    }
    $zip->close();
    @unlink($zipPath);

    if ($staged === []) {
        throw new RuntimeException('Update package contained no installable files.');
    }

    $updated = 0;
    foreach ($staged as $relative => $contents) {
        updater_write_file($relative, $contents);
        $updated++;
    }

    unset($_SESSION['update_status']);
    return [
        'updated' => true,
        'message' => 'Updated TinyMaker Connect to ' . $release['latest'] . ' (' . $updated . ' files).',
    ];
}

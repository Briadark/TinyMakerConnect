<?php
declare(strict_types=1);

require_once __DIR__ . '/app_loader.php';
tinymaker_connect_require_app('Admin.php');

if (!config_is_installed() || admin_count() < 1) {
    redirect_to('/install.php');
}

$error = null;
$notice = null;

if (isset($_GET['logout'])) {
    admin_logout();
    redirect_to('/admin.php');
}

if (request_method() === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $username = clean_string((string)($_POST['username'] ?? ''), 80);
    $password = (string)($_POST['password'] ?? '');
    if (admin_login($username, $password)) {
        redirect_to('/admin.php');
    }
    $error = 'Invalid username or password.';
}

$admin = current_admin();
if ($admin && request_method() === 'POST' && ($_POST['action'] ?? '') !== 'login') {
    try {
        $notice = admin_handle_post();
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$admin = current_admin();
if (!$admin):
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>TinyMaker Connect Admin Login</title>
  <style>
    :root{color-scheme:dark;--bg:#111214;--panel:#1b1d20;--text:#f2f2f2;--muted:#a5a7ad;--line:#33363d;--accent:#e8720c;--bad:#d95c5c}
    *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,Segoe UI,sans-serif}main{width:min(440px,100%);margin:0 auto;padding:40px 20px}
    .card{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:18px}h1{margin:0 0 6px}.muted{color:var(--muted)}label{display:block;margin:14px 0 6px}input{width:100%;border:1px solid var(--line);border-radius:7px;background:#101113;color:var(--text);padding:11px;font:inherit}button{width:100%;border:0;border-radius:8px;background:var(--accent);color:white;padding:12px 14px;font-weight:700;margin-top:16px}.err{border:1px solid var(--bad);background:#321b1b;color:#ffd2d2;padding:10px;border-radius:8px;margin-bottom:12px}
  </style>
</head>
<body><main><h1>TinyMaker Connect Admin</h1><p class="muted">Login to manage the model library.</p><div class="card">
<?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
<form method="post">
  <input type="hidden" name="action" value="login">
  <label>Username</label><input name="username" required autofocus>
  <label>Password</label><input name="password" type="password" required>
  <button type="submit">Login</button>
</form>
</div></main></body></html>
<?php
exit;
endif;

$stats = admin_stats();
$models = admin_models();
$printers = admin_printers();
$admins = admin_admins();
$leaderboard = admin_leaderboard();
$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>TinyMaker Connect Admin</title>
  <style>
    :root{color-scheme:dark;--bg:#111214;--panel:#1b1d20;--text:#f2f2f2;--muted:#a5a7ad;--line:#33363d;--accent:#e8720c;--bad:#d95c5c;--ok:#3d9b55}
    *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,Segoe UI,sans-serif}main{width:min(1180px,100%);margin:0 auto;padding:22px}
    a{color:inherit}.top{display:flex;justify-content:space-between;gap:14px;align-items:flex-end;margin-bottom:18px}h1{margin:0;font-size:28px}h2{font-size:20px;margin:22px 0 10px}.muted{color:var(--muted)}
    .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px}.stat,.panel{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:12px}.label{font-size:12px;color:var(--muted)}.value{font-size:24px;font-weight:750}
    table{width:100%;border-collapse:collapse;background:var(--panel);border:1px solid var(--line);border-radius:8px;overflow:hidden}th,td{border-bottom:1px solid var(--line);padding:9px;text-align:left;vertical-align:top}th{font-size:12px;color:var(--muted);font-weight:650}tr:last-child td{border-bottom:0}
    input,select{width:100%;border:1px solid var(--line);border-radius:6px;background:#101113;color:var(--text);padding:8px;font:inherit}button,.button{border:0;border-radius:7px;background:var(--accent);color:white;padding:8px 10px;font-weight:700;text-decoration:none;cursor:pointer}.secondary{background:#3a3d44}.danger{background:#7b2f2f}
    .inline{display:flex;gap:6px;align-items:center}.msg{padding:10px;border-radius:8px;margin-bottom:12px}.err{border:1px solid var(--bad);background:#321b1b;color:#ffd2d2}.ok{border:1px solid var(--ok);background:#18331e;color:#cbffd6}.pill{display:inline-block;border:1px solid var(--line);border-radius:999px;padding:3px 8px;color:var(--muted)}
    .moderation{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:6px;align-items:end}.moderation form{margin:0}.moderation .block-form{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:6px;align-items:end}
    @media(max-width:760px){.top{display:block}table,thead,tbody,tr,th,td{display:block}th{display:none}td{border-bottom:0}tr{border-bottom:1px solid var(--line);padding:8px}.moderation,.moderation .block-form{grid-template-columns:1fr}}
  </style>
</head>
<body>
<main>
  <div class="top">
    <div>
      <h1>TinyMaker Connect Admin</h1>
      <div class="muted">Logged in as <?= h($admin['username']) ?></div>
    </div>
    <div class="inline"><a class="button secondary" href="/">Public site</a><a class="button danger" href="/admin.php?logout=1">Logout</a></div>
  </div>

  <?php if ($notice): ?><div class="msg ok"><?= h($notice) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="msg err"><?= h($error) ?></div><?php endif; ?>

  <section class="stats">
    <div class="stat"><div class="label">Published</div><div class="value"><?= (int)$stats['published'] ?></div></div>
    <div class="stat"><div class="label">Hidden</div><div class="value"><?= (int)$stats['hidden'] ?></div></div>
    <div class="stat"><div class="label">Removed</div><div class="value"><?= (int)$stats['removed'] ?></div></div>
    <div class="stat"><div class="label">Printers</div><div class="value"><?= (int)$stats['printers'] ?></div></div>
    <div class="stat"><div class="label">Blocked</div><div class="value"><?= (int)$stats['blocked'] ?></div></div>
    <div class="stat"><div class="label">Downloads</div><div class="value"><?= (int)$stats['downloads'] ?></div></div>
    <div class="stat"><div class="label">Ratings</div><div class="value"><?= (int)$stats['ratings'] ?></div></div>
    <div class="stat"><div class="label">Bookmarks</div><div class="value"><?= (int)$stats['bookmarks'] ?></div></div>
  </section>

  <h2>Models</h2>
  <table>
    <thead><tr><th>Model</th><th>Stats</th><th>Printer</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($models as $model): ?>
      <tr>
        <td>
          <form method="post" id="model-<?= (int)$model['id'] ?>">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="model_update">
            <input type="hidden" name="model_id" value="<?= (int)$model['id'] ?>">
            <input name="model_name" value="<?= h($model['model_name']) ?>">
            <div style="height:6px"></div>
            <input name="original_credits" value="<?= h($model['original_credits']) ?>" placeholder="Original credits">
          </form>
        </td>
        <td>
          <span class="pill"><?= (int)$model['layers'] ?> layers</span><br>
          <span class="pill"><?= h((string)$model['height_mm']) ?> mm</span><br>
          <span class="pill"><?= $model['resin_ml'] === null ? '-' : h((string)$model['resin_ml']) . ' ml' ?></span><br>
          <span class="pill"><?= (int)$model['download_count'] ?> downloads</span><br>
          <span class="pill"><?= (int)$model['rating_count'] ?> ratings</span><br>
          <span class="pill"><?= (int)$model['bookmark_count'] ?> bookmarks</span>
        </td>
        <td>
          <?= h($model['printer_name'] ?: $model['printer_public_id'] ?: '-') ?><br>
          <?php if ((int)$model['printer_blocked'] === 1): ?><span class="pill">blocked</span><?php endif; ?>
        </td>
        <td>
          <select name="status" form="model-<?= (int)$model['id'] ?>">
            <?php foreach (['published', 'hidden', 'removed'] as $status): ?>
              <option value="<?= h($status) ?>" <?= $model['status'] === $status ? 'selected' : '' ?>><?= h($status) ?></option>
            <?php endforeach; ?>
          </select>
        </td>
        <td>
          <div class="inline">
            <?php if ($model['status'] === 'published'): ?><a class="button secondary" href="/model/<?= h($model['public_id']) ?>">View</a><?php endif; ?>
            <button type="submit" form="model-<?= (int)$model['id'] ?>">Save</button>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <h2>Printers</h2>
  <table>
    <thead><tr><th>Printer</th><th>Firmware</th><th>Models</th><th>Seen</th><th>Moderation</th></tr></thead>
    <tbody>
    <?php foreach ($printers as $printer): ?>
      <tr>
        <td><?= h($printer['printer_name'] ?: $printer['public_id']) ?><br><span class="muted"><?= h($printer['public_id']) ?></span></td>
        <td><?= h($printer['firmware_version'] ?: '-') ?></td>
        <td>
          <?= (int)$printer['model_count'] ?><br>
          <span class="muted"><?= (int)$printer['published_model_count'] ?> published</span><br>
          <span class="muted"><?= (int)$printer['hidden_model_count'] ?> hidden</span>
        </td>
        <td><span class="muted">First</span> <?= h($printer['first_seen']) ?><br><span class="muted">Last</span> <?= h($printer['last_seen']) ?></td>
        <td>
          <div class="moderation">
            <form method="post" class="block-form">
              <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
              <input type="hidden" name="action" value="printer_block">
              <input type="hidden" name="printer_id" value="<?= (int)$printer['id'] ?>">
              <input type="hidden" name="blocked" value="<?= (int)$printer['blocked'] === 1 ? '0' : '1' ?>">
              <input name="block_reason" value="<?= h($printer['block_reason']) ?>" placeholder="Block reason">
              <button class="<?= (int)$printer['blocked'] === 1 ? 'secondary' : 'danger' ?>" type="submit"><?= (int)$printer['blocked'] === 1 ? 'Unblock' : 'Block' ?></button>
            </form>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
              <input type="hidden" name="action" value="hide_printer_models">
              <input type="hidden" name="printer_id" value="<?= (int)$printer['id'] ?>">
              <button class="secondary" type="submit" <?= (int)$printer['published_model_count'] < 1 ? 'disabled' : '' ?>>Hide models</button>
            </form>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
              <input type="hidden" name="action" value="unhide_printer_models">
              <input type="hidden" name="printer_id" value="<?= (int)$printer['id'] ?>">
              <button class="secondary" type="submit" <?= (int)$printer['hidden_model_count'] < 1 ? 'disabled' : '' ?>>Unhide models</button>
            </form>
            <?php if ((int)$printer['model_count'] === 0): ?>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
              <input type="hidden" name="action" value="printer_delete_unused">
              <input type="hidden" name="printer_id" value="<?= (int)$printer['id'] ?>">
              <button class="danger" type="submit">Delete unused</button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <h2>Admins</h2>
  <?php if ((int)$admin['is_super'] === 1): ?>
  <div class="panel" style="margin-bottom:10px">
    <form method="post" class="moderation">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="action" value="admin_add">
      <input name="username" placeholder="New admin username" required>
      <input name="password" type="password" placeholder="Temporary password" required minlength="10">
      <button type="submit">Add admin</button>
    </form>
  </div>
  <?php endif; ?>
  <table>
    <thead><tr><th>Username</th><th>Role</th><th>Created</th><th>Last login</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($admins as $row): ?>
      <tr>
        <td><?= h($row['username']) ?></td>
        <td><?= (int)$row['is_super'] === 1 ? 'Super admin' : 'Admin' ?></td>
        <td><?= h($row['created_at']) ?></td>
        <td><?= h($row['last_login'] ?: '-') ?></td>
        <td>
          <?php if ((int)$admin['is_super'] === 1 && (int)$row['is_super'] !== 1 && (int)$row['id'] !== (int)$admin['id']): ?>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="admin_delete">
            <input type="hidden" name="admin_id" value="<?= (int)$row['id'] ?>">
            <button class="danger" type="submit">Delete</button>
          </form>
          <?php else: ?>
            <span class="muted">Locked</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <h2>Leaderboard</h2>
  <table>
    <thead><tr><th>Printer</th><th>Uploads</th><th>Downloads</th><th>Ratings</th><th>Bookmarks</th><th>Layers uploaded</th></tr></thead>
    <tbody>
    <?php foreach ($leaderboard as $row): ?>
      <tr>
        <td><?= h($row['printer_name'] ?: $row['public_id']) ?><br><span class="muted"><?= h($row['public_id']) ?></span></td>
        <td><?= (int)$row['uploads'] ?></td>
        <td><?= (int)$row['downloads'] ?></td>
        <td><?= (int)$row['ratings'] ?></td>
        <td><?= (int)$row['bookmarks'] ?></td>
        <td><?= (int)$row['uploaded_layers'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</main>
</body>
</html>

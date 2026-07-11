<?php
declare(strict_types=1);

require_once __DIR__ . '/app_loader.php';
tinymaker_connect_require_app('bootstrap.php');

web_require_installed();
if (admin_count() < 1) {
    redirect_to('/install.php');
}

$path = route_path();
$parts = array_values(array_filter(explode('/', trim($path, '/'))));
$model = null;

if (count($parts) === 2 && $parts[0] === 'model') {
    $stmt = db()->prepare('SELECT * FROM models WHERE public_id = ? AND status = "published" LIMIT 1');
    $stmt->execute([$parts[1]]);
    $model = $stmt->fetch();
    if (!$model) {
        http_response_code(404);
    }
} else {
    $stmt = db()->query('SELECT * FROM models WHERE status = "published" ORDER BY created_at DESC LIMIT 100');
    $models = $stmt->fetchAll();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>TinyMaker Connect</title>
  <style>
    :root{color-scheme:dark;--bg:#111214;--panel:#1b1d20;--text:#f2f2f2;--muted:#a5a7ad;--line:#33363d;--accent:#e8720c}
    *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,Segoe UI,sans-serif}
    main{width:min(980px,100%);margin:0 auto;padding:24px}a{color:inherit}.top{display:flex;justify-content:space-between;gap:16px;align-items:flex-end;margin-bottom:24px}
    h1{font-size:28px;margin:0}h2{font-size:20px;margin:0 0 12px}.muted{color:var(--muted)}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px}
    .card{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:14px;text-decoration:none}.card:hover{border-color:var(--accent)}
    .preview{aspect-ratio:4/3;background:#090a0b;border:1px solid var(--line);border-radius:6px;display:flex;align-items:center;justify-content:center;overflow:hidden;margin-bottom:12px}
    .preview img{width:100%;height:100%;object-fit:contain}.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:12px}.stat{border-top:1px solid var(--line);padding-top:8px}
    .social{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}.pill{border:1px solid var(--line);border-radius:999px;padding:3px 8px;color:var(--muted);font-size:12px}
    .label{font-size:12px;color:var(--muted)}.value{font-size:16px;font-weight:650}.button{display:inline-block;background:var(--accent);color:white;padding:11px 14px;border-radius:8px;text-decoration:none;font-weight:700}
    .detail{display:grid;grid-template-columns:minmax(0,1fr) 280px;gap:20px}.side{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:14px}
    @media(max-width:720px){.top,.detail{display:block}.side{margin-top:16px}}
  </style>
</head>
<body>
<main>
<?php if ($model): ?>
  <div class="top">
    <div>
      <a class="muted" href="/">Back to models</a>
      <h1><?= h($model['model_name']) ?></h1>
      <div class="muted"><?= h($model['original_credits']) ?></div>
    </div>
    <a class="button" href="/api/models/<?= h($model['public_id']) ?>/download">Download</a>
  </div>
  <div class="detail">
    <div class="preview">
      <?php if ($model['preview_path']): ?>
        <img src="/preview.php?id=<?= h($model['public_id']) ?>" alt="">
      <?php else: ?>
        <span class="muted">No preview</span>
      <?php endif; ?>
    </div>
    <div class="side">
      <h2>Print data</h2>
      <div class="stats">
        <div class="stat"><div class="label">Layers</div><div class="value"><?= (int)$model['layers'] ?></div></div>
        <div class="stat"><div class="label">Height</div><div class="value"><?= h((string)$model['height_mm']) ?> mm</div></div>
        <div class="stat"><div class="label">Resin</div><div class="value"><?= $model['resin_ml'] === null ? '-' : h((string)$model['resin_ml']) . ' ml' ?></div></div>
      </div>
      <p class="muted">Published <?= h($model['created_at']) ?></p>
      <div class="social">
        <span class="pill"><?= (int)$model['download_count'] ?> downloads</span>
        <span class="pill"><?= (int)$model['bookmark_count'] ?> bookmarks</span>
        <span class="pill"><?= (int)$model['rating_count'] > 0 ? round((int)$model['rating_sum'] / (int)$model['rating_count'], 1) . '/5' : 'No ratings' ?></span>
      </div>
      <p class="muted">SHA256<br><?= h($model['checksum_sha256']) ?></p>
    </div>
  </div>
<?php elseif (isset($models)): ?>
  <div class="top">
    <div>
      <h1>TinyMaker Connect</h1>
      <div class="muted">Download ready-to-print TinyMaker model archives.</div>
    </div>
  </div>
  <div class="grid">
    <?php foreach ($models as $item): ?>
      <a class="card" href="/model/<?= h($item['public_id']) ?>">
        <div class="preview">
          <?php if ($item['preview_path']): ?>
            <img src="/preview.php?id=<?= h($item['public_id']) ?>" alt="">
          <?php else: ?>
            <span class="muted">No preview</span>
          <?php endif; ?>
        </div>
        <h2><?= h($item['model_name']) ?></h2>
        <div class="muted"><?= h($item['original_credits']) ?></div>
        <div class="stats">
          <div class="stat"><div class="label">Layers</div><div class="value"><?= (int)$item['layers'] ?></div></div>
          <div class="stat"><div class="label">Height</div><div class="value"><?= h((string)$item['height_mm']) ?> mm</div></div>
          <div class="stat"><div class="label">Resin</div><div class="value"><?= $item['resin_ml'] === null ? '-' : h((string)$item['resin_ml']) . ' ml' ?></div></div>
        </div>
        <div class="social">
          <span class="pill"><?= (int)$item['download_count'] ?> downloads</span>
          <span class="pill"><?= (int)$item['rating_count'] > 0 ? round((int)$item['rating_sum'] / (int)$item['rating_count'], 1) . '/5' : 'No ratings' ?></span>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <h1>Model not found</h1>
  <p><a href="/">Back to models</a></p>
<?php endif; ?>
</main>
</body>
</html>

<?php
declare(strict_types=1);

require_once __DIR__ . '/app_loader.php';
tinymaker_connect_require_app('bootstrap.php');
tinymaker_connect_require_app('Footer.php');

web_require_installed();
if (admin_count() < 1) {
    redirect_to('/install.php');
}

function public_model_preview_url(array $model, string $type): string
{
    $has = $type === '1' ? !empty($model['preview_1_path']) : !empty($model['preview_05_path']);
    if (!$has) {
        return '';
    }
    return '/preview.php?id=' . rawurlencode((string)$model['public_id']) . '&type=' . ($type === '1' ? '1' : '05');
}

function public_format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return rtrim(rtrim(number_format($bytes / 1048576, 1), '0'), '.') . ' MB';
    }
    if ($bytes >= 1024) {
        return rtrim(rtrim(number_format($bytes / 1024, 1), '0'), '.') . ' KB';
    }
    return $bytes . ' B';
}

function public_format_duration(int $seconds): string
{
    $hours = intdiv(max(0, $seconds), 3600);
    $minutes = intdiv(max(0, $seconds) % 3600, 60);
    return $hours > 0 ? ($hours . 'h ' . $minutes . 'm') : ($minutes . 'm');
}

$path = route_path();
$parts = array_values(array_filter(explode('/', trim($path, '/'))));
$model = null;
$animation = null;
$models = [];
$bootAnimations = [];
$leaderboard = [];
$isHome = false;

if (count($parts) === 2 && $parts[0] === 'model') {
    $stmt = db()->prepare('SELECT * FROM models WHERE public_id = ? AND status = "published" LIMIT 1');
    $stmt->execute([$parts[1]]);
    $model = $stmt->fetch();
    if (!$model) {
        http_response_code(404);
    }
} elseif (count($parts) === 2 && $parts[0] === 'boot-animation') {
    $stmt = db()->prepare('SELECT * FROM boot_animations WHERE public_id = ? AND status = "published" LIMIT 1');
    $stmt->execute([$parts[1]]);
    $animation = $stmt->fetch();
    if (!$animation) {
        http_response_code(404);
    }
} else {
    $isHome = true;
    $stmt = db()->query('SELECT * FROM models WHERE status = "published" ORDER BY created_at DESC LIMIT 100');
    $models = $stmt->fetchAll();
    $stmt = db()->query('SELECT * FROM boot_animations WHERE status = "published" ORDER BY created_at DESC LIMIT 100');
    $bootAnimations = $stmt->fetchAll();
    $stmt = db()->query(
        'SELECT p.public_id, p.printer_name, p.firmware_version, p.lifetime_print_secs,
          (SELECT COUNT(*) FROM models m WHERE m.printer_id = p.id AND m.status != "removed") AS uploads,
          (SELECT COUNT(*) FROM model_downloads d WHERE d.printer_id = p.id) AS downloads,
          (SELECT COUNT(*) FROM model_likes l WHERE l.printer_id = p.id) AS likes,
          (SELECT COUNT(*) FROM model_bookmarks b WHERE b.printer_id = p.id) AS bookmarks,
          (SELECT COALESCE(SUM(m2.layers), 0) FROM models m2 WHERE m2.printer_id = p.id AND m2.status != "removed") AS uploaded_layers
         FROM printers p
         WHERE p.blocked = 0 AND p.leaderboard_opt_in = 1
         ORDER BY uploads DESC, downloads DESC, likes DESC
         LIMIT 100'
    );
    $leaderboard = $stmt->fetchAll();
}
?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>TinyMaker Connect</title>
  <link rel="icon" type="image/svg+xml" href="/connect-favicon.svg">
  <style>
    :root{color-scheme:dark;--bg:#111214;--panel:#1b1d20;--panel2:#24262b;--text:#f2f2f2;--muted:#a5a7ad;--line:#343740;--accent:#e8720c;--accentText:#fff;--soft:#2f2419;--input:#141518;--shadow:0 14px 40px rgba(0,0,0,.28)}
    :root[data-theme="light"]{color-scheme:light;--bg:#f6f7f9;--panel:#fff;--panel2:#e6e9ee;--text:#202329;--muted:#68707d;--line:#d6dae2;--accent:#e8720c;--accentText:#fff;--soft:#fff0df;--input:#fff;--shadow:0 14px 34px rgba(30,35,45,.12)}
    *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,Segoe UI,sans-serif;font-size:16px;line-height:1.45}a{color:inherit}main{width:min(1180px,100%);margin:0 auto;padding:24px}.top{display:flex;justify-content:space-between;gap:16px;align-items:flex-end;margin-bottom:20px}.brand{display:flex;gap:12px;align-items:center}.eyebrow{color:var(--accent);font-weight:800;text-transform:uppercase;font-size:12px;letter-spacing:.08em}h1{font-size:32px;margin:0}h2{font-size:22px;margin:0 0 12px}h3{font-size:18px;margin:0 0 8px}.muted{color:var(--muted)}.section{margin-top:24px}.toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.tabs{display:flex;gap:8px;flex-wrap:wrap;margin:16px 0}.tab,.button,button{border:0;border-radius:8px;padding:11px 14px;background:var(--panel2);color:var(--text);font:inherit;font-weight:800;cursor:pointer;text-decoration:none}.tab.active,.button.primary,button.primary{background:var(--accent);color:var(--accentText)}.button.secondary,button.secondary{border:1px solid var(--line)}input[type="search"]{width:min(420px,100%);border:1px solid var(--line);border-radius:8px;background:var(--input);color:var(--text);padding:12px 14px;font:inherit}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:14px}.card{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:14px;text-decoration:none;box-shadow:var(--shadow)}.card:hover{border-color:var(--accent)}.preview{aspect-ratio:4/3;background:#090a0b;border:1px solid var(--line);border-radius:6px;display:flex;align-items:center;justify-content:center;overflow:hidden;margin-bottom:12px}.preview img{width:100%;height:100%;object-fit:contain}.bootPreview{aspect-ratio:2/1;background:#050506;border:1px solid var(--line);border-radius:6px;display:flex;align-items:center;justify-content:center;overflow:hidden;margin-bottom:12px}.bootPreview canvas{width:100%;height:100%;image-rendering:pixelated}.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:12px}.stat{border-top:1px solid var(--line);padding-top:8px}.social,.pills{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}.pill{border:1px solid var(--line);border-radius:999px;padding:3px 8px;color:var(--muted);font-size:12px}.label{font-size:12px;color:var(--muted)}.value{font-size:16px;font-weight:750}.detail{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:20px}.side{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:14px;box-shadow:var(--shadow)}.hash{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:12px;overflow-wrap:anywhere;word-break:break-word;line-height:1.5}.leaderRows{display:grid;gap:8px}.leaderRow{display:grid;grid-template-columns:44px minmax(0,1fr) repeat(4,auto);gap:10px;align-items:center;background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:12px}.empty{border:1px dashed var(--line);border-radius:8px;padding:18px;color:var(--muted);background:var(--panel)}.hidden{display:none!important}.notice{background:var(--soft);border:1px solid var(--accent);border-radius:8px;padding:12px 14px;margin:12px 0 18px}.siteNav{display:flex;gap:8px;flex-wrap:wrap;margin-top:18px}.siteNav .tab{flex:1;min-width:170px;margin-top:0}.logo{width:52px;height:52px;border-radius:0;background:transparent;display:block;flex:0 0 52px}.logo img{display:block;width:52px;height:52px}<?= tinymaker_connect_footer_css() ?>.sectionHead{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;margin-bottom:12px}.sectionHead p{margin:0}@media(max-width:820px){main{padding:16px}.top,.detail,.sectionHead{display:block}.toolbar{margin-top:12px}.side{margin-top:16px}.leaderRow{grid-template-columns:36px minmax(0,1fr)}.leaderRow .pill{width:max-content}.stats{grid-template-columns:repeat(2,1fr)}}
  </style>
  <script>
    (function(){
      var saved = localStorage.getItem('tmcTheme') || 'dark';
      document.documentElement.dataset.theme = saved === 'light' ? 'light' : 'dark';
    })();
  </script>
</head>
<body>
<main>
<?php if ($model): ?>
  <?php $preview05 = public_model_preview_url($model, '05'); $preview1 = public_model_preview_url($model, '1'); ?>
  <div class="top">
    <div>
      <a class="muted" href="/">Back to Connect</a>
      <h1><?= h($model['model_name']) ?></h1>
      <div class="muted"><?= h($model['original_credits']) ?></div>
      <div class="pills"><span class="pill"><?= h($model['license'] ?? 'CC-BY-NC') ?></span><span class="pill"><?= public_format_bytes((int)$model['file_size']) ?></span></div>
    </div>
    <div class="toolbar">
      <button id="themeToggle" type="button" class="secondary">Theme</button>
      <a class="button primary" href="/api/models/<?= h($model['public_id']) ?>/download">Download</a>
    </div>
  </div>
  <div class="tabs" data-preview-tabs>
    <button type="button" class="tab active" data-preview-mode="05">Show 0.05 mm</button>
    <button type="button" class="tab" data-preview-mode="1">Show 0.10 mm</button>
  </div>
  <div class="detail">
    <div class="preview">
      <?php if ($preview05 || $preview1): ?>
        <img class="modelPreviewImg" data-preview-05="<?= h($preview05 ?: $preview1) ?>" data-preview-1="<?= h($preview1 ?: $preview05) ?>" src="<?= h($preview05 ?: $preview1) ?>" alt="<?= h($model['model_name']) ?> preview">
      <?php else: ?>
        <span class="muted">No preview</span>
      <?php endif; ?>
    </div>
    <div class="side modelStats" data-layers="<?= (int)$model['layers'] ?>">
      <h2>Print data</h2>
      <div class="stats">
        <div class="stat"><div class="label">Source layers</div><div class="value"><?= (int)$model['layers'] ?></div></div>
        <div class="stat"><div class="label">Print layers</div><div class="value" data-print-layers><?= (int)$model['layers'] ?></div></div>
        <div class="stat"><div class="label">Height</div><div class="value"><?= h((string)$model['height_mm']) ?> mm</div></div>
        <div class="stat"><div class="label">Resin</div><div class="value"><?= $model['resin_ml'] === null ? '-' : h((string)$model['resin_ml']) . ' ml' ?></div></div>
        <div class="stat"><div class="label">Downloads</div><div class="value"><?= (int)$model['download_count'] ?></div></div>
        <div class="stat"><div class="label">Likes</div><div class="value">&#9829; <?= (int)($model['like_count'] ?? 0) ?></div></div>
      </div>
      <p class="muted">Published <?= h($model['created_at']) ?></p>
      <p class="muted">SHA256</p>
      <div class="hash"><?= h($model['checksum_sha256']) ?></div>
    </div>
  </div>
<?php elseif ($animation): ?>
  <div class="top">
    <div>
      <a class="muted" href="/">Back to Connect</a>
      <h1><?= h($animation['animation_name']) ?></h1>
      <div class="muted"><?= h($animation['description'] ?: $animation['original_credits']) ?></div>
      <div class="pills"><span class="pill"><?= h($animation['license'] ?? 'CC-BY-NC') ?></span><span class="pill">v<?= h($animation['version'] ?? '1.0.0') ?></span><span class="pill"><?= public_format_bytes((int)$animation['file_size']) ?></span></div>
    </div>
    <div class="toolbar">
      <button id="themeToggle" type="button" class="secondary">Theme</button>
      <a class="button primary" href="/api/boot-animations/<?= h($animation['public_id']) ?>/download">Download</a>
    </div>
  </div>
  <div class="detail">
    <div class="bootPreview"><canvas data-tmb-url="/api/boot-animations/<?= h($animation['public_id']) ?>/preview"></canvas></div>
    <div class="side">
      <h2>Animation data</h2>
      <div class="stats">
        <div class="stat"><div class="label">Install name</div><div class="value"><?= h($animation['install_name']) ?></div></div>
        <div class="stat"><div class="label">Installs</div><div class="value"><?= (int)$animation['download_count'] ?></div></div>
        <div class="stat"><div class="label">Likes</div><div class="value">&#9829; <?= (int)($animation['like_count'] ?? 0) ?></div></div>
        <div class="stat"><div class="label">Version</div><div class="value"><?= h($animation['version'] ?? '1.0.0') ?></div></div>
      </div>
      <p class="muted">Credits: <?= h($animation['original_credits']) ?></p>
      <p class="muted">SHA256</p>
      <div class="hash"><?= h($animation['checksum_sha256']) ?></div>
    </div>
  </div>
<?php elseif ($isHome): ?>
  <div class="top">
    <div class="brand">
      <div class="logo" aria-hidden="true"><img src="/connect-favicon.svg" alt=""></div>
      <div>
        <div class="eyebrow">TinyMaker Connect</div>
        <h1>Ready-to-print models and boot animations</h1>
        <div class="muted">Browse shared TinyMaker content for compatible TinyMakerWifi firmware builds.</div>
      </div>
    </div>
    <div class="toolbar">
      <a class="button secondary" href="/flash.php">First-time USB flash</a>
      <button id="themeToggle" type="button" class="secondary">Theme</button>
    </div>
  </div>
  <div class="notice">The hosted service is built for printers running TinyMakerWifi firmware with Connect support. Stock printers cannot use these downloads directly from the dashboard.</div>
  <nav class="siteNav" aria-label="Connect sections">
    <button class="tab active" type="button" data-site-tab="models">Models</button>
    <button class="tab" type="button" data-site-tab="boot-animations">Boot animations</button>
    <button class="tab" type="button" data-site-tab="leaderboard">Leaderboards</button>
  </nav>

  <section id="models" class="section" data-site-panel="models">
    <div class="sectionHead">
      <div>
        <h2>Models</h2>
        <p class="muted" id="modelModeLabel">Showing models in 0.05 mm layer height.</p>
      </div>
      <div class="toolbar">
        <input id="modelSearch" type="search" placeholder="Search models, credits or license">
        <button type="button" class="tab active" data-preview-mode="05">0.05 mm</button>
        <button type="button" class="tab" data-preview-mode="1">0.10 mm</button>
      </div>
    </div>
    <div id="modelGrid" class="grid">
      <?php foreach ($models as $item): ?>
        <?php $preview05 = public_model_preview_url($item, '05'); $preview1 = public_model_preview_url($item, '1'); ?>
        <a class="card modelCard modelStats" href="/model/<?= h($item['public_id']) ?>" data-search="<?= h(strtolower($item['model_name'] . ' ' . $item['original_credits'] . ' ' . ($item['license'] ?? ''))) ?>" data-layers="<?= (int)$item['layers'] ?>">
          <div class="preview">
            <?php if ($preview05 || $preview1): ?>
              <img class="modelPreviewImg" data-preview-05="<?= h($preview05 ?: $preview1) ?>" data-preview-1="<?= h($preview1 ?: $preview05) ?>" src="<?= h($preview05 ?: $preview1) ?>" alt="<?= h($item['model_name']) ?> preview">
            <?php else: ?>
              <span class="muted">No preview</span>
            <?php endif; ?>
          </div>
          <h3><?= h($item['model_name']) ?></h3>
          <div class="muted"><?= h($item['original_credits']) ?></div>
          <div class="pills"><span class="pill"><?= h($item['license'] ?? 'CC-BY-NC') ?></span><span class="pill"><?= public_format_bytes((int)$item['file_size']) ?></span></div>
          <div class="stats">
            <div class="stat"><div class="label">Source</div><div class="value"><?= (int)$item['layers'] ?></div></div>
            <div class="stat"><div class="label">Print</div><div class="value" data-print-layers><?= (int)$item['layers'] ?></div></div>
            <div class="stat"><div class="label">Height</div><div class="value"><?= h((string)$item['height_mm']) ?> mm</div></div>
            <div class="stat"><div class="label">Resin</div><div class="value"><?= $item['resin_ml'] === null ? '-' : h((string)$item['resin_ml']) . ' ml' ?></div></div>
            <div class="stat"><div class="label">Downloads</div><div class="value"><?= (int)$item['download_count'] ?></div></div>
            <div class="stat"><div class="label">Likes</div><div class="value">&#9829; <?= (int)($item['like_count'] ?? 0) ?></div></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <div id="modelEmpty" class="empty hidden">No models match your search.</div>
  </section>

  <section id="boot-animations" class="section hidden" data-site-panel="boot-animations">
    <div class="sectionHead">
      <div>
        <h2>Boot animations</h2>
        <p class="muted">Community power-on animations for TinyMakerWifi firmware with boot-animation support.</p>
      </div>
    </div>
    <?php if ($bootAnimations): ?>
      <div class="grid">
        <?php foreach ($bootAnimations as $anim): ?>
          <a class="card" href="/boot-animation/<?= h($anim['public_id']) ?>">
            <div class="bootPreview"><canvas data-tmb-url="/api/boot-animations/<?= h($anim['public_id']) ?>/preview"></canvas></div>
            <h3><?= h($anim['animation_name']) ?></h3>
            <div class="muted"><?= h($anim['description'] ?: $anim['original_credits']) ?></div>
            <div class="pills">
              <span class="pill">v<?= h($anim['version'] ?? '1.0.0') ?></span>
              <span class="pill"><?= h($anim['install_name']) ?></span>
              <span class="pill"><?= public_format_bytes((int)$anim['file_size']) ?></span>
              <span class="pill"><?= (int)$anim['download_count'] ?> installs</span>
              <span class="pill">&#9829; <?= (int)($anim['like_count'] ?? 0) ?></span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty">No boot animations published yet.</div>
    <?php endif; ?>
  </section>

  <section id="leaderboard" class="section hidden" data-site-panel="leaderboard">
    <div class="sectionHead">
      <div>
        <h2>Leaderboards</h2>
        <p class="muted">Printers that opted in to public leaderboard stats.</p>
      </div>
      <div class="toolbar" id="leaderTabs">
        <button type="button" class="tab active" data-leader-sort="uploads">Uploads</button>
        <button type="button" class="tab" data-leader-sort="downloads">Downloads</button>
        <button type="button" class="tab" data-leader-sort="likes">Likes</button>
        <button type="button" class="tab" data-leader-sort="bookmarks">Bookmarks</button>
        <button type="button" class="tab" data-leader-sort="lifetime">Print time</button>
        <button type="button" class="tab" data-leader-sort="layers">Layers</button>
      </div>
    </div>
    <?php if ($leaderboard): ?>
      <div id="leaderRows" class="leaderRows">
        <?php foreach ($leaderboard as $i => $row): ?>
          <div class="leaderRow" data-uploads="<?= (int)$row['uploads'] ?>" data-downloads="<?= (int)$row['downloads'] ?>" data-likes="<?= (int)$row['likes'] ?>" data-bookmarks="<?= (int)$row['bookmarks'] ?>" data-lifetime="<?= (int)$row['lifetime_print_secs'] ?>" data-layers="<?= (int)$row['uploaded_layers'] ?>">
            <div class="value" data-rank>#<?= $i + 1 ?></div>
            <div><div class="value"><?= h($row['printer_name'] ?: 'TinyMaker') ?></div><div class="muted hash"><?= h($row['public_id']) ?></div></div>
            <span class="pill">FW <?= h($row['firmware_version'] ?: '-') ?></span>
            <span class="pill"><?= public_format_duration((int)$row['lifetime_print_secs']) ?> print time</span>
            <span class="pill"><?= (int)$row['uploads'] ?> uploads</span>
            <span class="pill"><?= (int)$row['downloads'] ?> downloads</span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty">No printers opted in yet.</div>
    <?php endif; ?>
  </section>
<?php else: ?>
  <h1>Not found</h1>
  <p><a href="/">Back to Connect</a></p>
<?php endif; ?>
<?= tinymaker_connect_footer() ?></main>
<script>
(function(){
  const $ = s => document.querySelector(s);
  const $$ = s => Array.from(document.querySelectorAll(s));
  const setTheme = theme => {
    theme = theme === 'light' ? 'light' : 'dark';
    document.documentElement.dataset.theme = theme;
    localStorage.setItem('tmcTheme', theme);
    $$('#themeToggle').forEach(b => b.textContent = theme === 'light' ? 'Dark theme' : 'Light theme');
  };
  setTheme(localStorage.getItem('tmcTheme') || 'dark');
  $$('#themeToggle').forEach(b => b.addEventListener('click', () => setTheme(document.documentElement.dataset.theme === 'light' ? 'dark' : 'light')));

  let previewMode = localStorage.getItem('tmcPreviewMode') || '05';
  const updatePreviewMode = mode => {
    previewMode = mode === '1' ? '1' : '05';
    localStorage.setItem('tmcPreviewMode', previewMode);
    $$('[data-preview-mode]').forEach(b => b.classList.toggle('active', b.dataset.previewMode === previewMode));
    $$('.modelPreviewImg').forEach(img => {
      const next = previewMode === '1' ? img.dataset.preview1 : img.dataset.preview05;
      if (next) img.src = next;
    });
    $$('.modelStats').forEach(el => {
      const source = parseInt(el.dataset.layers || '0', 10) || 0;
      const print = previewMode === '1' ? Math.max(1, Math.floor(source / 2)) : source;
      el.querySelectorAll('[data-print-layers]').forEach(v => v.textContent = String(print));
    });
    const label = $('#modelModeLabel');
    if (label) label.textContent = 'Showing models in ' + (previewMode === '1' ? '0.10' : '0.05') + ' mm layer height.';
  };
  $$('[data-preview-mode]').forEach(b => b.addEventListener('click', () => updatePreviewMode(b.dataset.previewMode)));
  updatePreviewMode(previewMode);

  const setSiteTab = tab => {
    tab = ['models','boot-animations','leaderboard'].includes(tab) ? tab : 'models';
    localStorage.setItem('tmcSiteTab', tab);
    $$('[data-site-tab]').forEach(b => b.classList.toggle('active', b.dataset.siteTab === tab));
    $$('[data-site-panel]').forEach(p => p.classList.toggle('hidden', p.dataset.sitePanel !== tab));
  };
  $$('[data-site-tab]').forEach(b => b.addEventListener('click', () => setSiteTab(b.dataset.siteTab)));
  setSiteTab(localStorage.getItem('tmcSiteTab') || 'models');
  const search = $('#modelSearch');
  if (search) {
    const apply = () => {
      const q = search.value.trim().toLowerCase();
      let shown = 0;
      $$('.modelCard').forEach(card => {
        const ok = !q || (card.dataset.search || '').includes(q);
        card.classList.toggle('hidden', !ok);
        if (ok) shown++;
      });
      const empty = $('#modelEmpty');
      if (empty) empty.classList.toggle('hidden', shown !== 0);
    };
    search.addEventListener('input', apply);
    apply();
  }

  const leaderRows = $('#leaderRows');
  const leaderSort = key => {
    if (!leaderRows) return;
    const rows = Array.from(leaderRows.children);
    rows.sort((a,b) => (parseInt(b.dataset[key] || '0', 10) || 0) - (parseInt(a.dataset[key] || '0', 10) || 0));
    rows.forEach((row, i) => { row.querySelector('[data-rank]').textContent = '#' + (i + 1); leaderRows.appendChild(row); });
    $$('#leaderTabs [data-leader-sort]').forEach(b => b.classList.toggle('active', b.dataset.leaderSort === key));
  };
  $$('#leaderTabs [data-leader-sort]').forEach(b => b.addEventListener('click', () => leaderSort(b.dataset.leaderSort)));

  const rgb565 = (lo, hi) => {
    const v = lo | (hi << 8);
    return [(((v >> 11) & 31) * 255 / 31) | 0, (((v >> 5) & 63) * 255 / 63) | 0, ((v & 31) * 255 / 31) | 0, 255];
  };
  const timers = [];
  const renderTmbCanvas = async canvas => {
    const r = await fetch(canvas.dataset.tmbUrl, { cache: 'no-store' });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const u = new Uint8Array(await r.arrayBuffer());
    if (u.length < 12 || u[0] !== 84 || u[1] !== 77 || u[2] !== 66 || u[3] !== 49) throw new Error('invalid TMB1');
    const w = u[4] | (u[5] << 8), h = u[6] | (u[7] << 8), frames = u[8] | (u[9] << 8), fps = u[10] | (u[11] << 8);
    const frameBytes = w * h * 2, total = Math.min(frames, Math.floor((u.length - 12) / frameBytes), 120);
    if (!w || !h || w > 160 || h > 80 || !total) throw new Error('invalid TMB size');
    canvas.width = w; canvas.height = h;
    const ctx = canvas.getContext('2d'), img = ctx.createImageData(w, h);
    const draw = i => {
      let p = 12 + i * frameBytes;
      for (let px = 0; px < w * h; px++) {
        const c = rgb565(u[p], u[p + 1]), o = px * 4; p += 2;
        img.data[o] = c[0]; img.data[o + 1] = c[1]; img.data[o + 2] = c[2]; img.data[o + 3] = 255;
      }
      ctx.putImageData(img, 0, 0);
    };
    draw(0);
    if (total > 1) {
      let i = 0, delay = Math.max(40, Math.min(300, fps ? Math.round(1000 / fps) : 80));
      timers.push(setInterval(() => { i = (i + 1) % total; draw(i); }, delay));
    }
  };
  $$('canvas[data-tmb-url]').forEach(cv => renderTmbCanvas(cv).catch(() => {
    const p = cv.parentElement;
    if (p) p.innerHTML = '<span class="muted">Preview unavailable</span>';
  }));
  window.addEventListener('pagehide', () => timers.forEach(clearInterval));
})();
</script>
</body>
</html>

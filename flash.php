<?php
declare(strict_types=1);

require_once __DIR__ . '/app_loader.php';
tinymaker_connect_require_app('bootstrap.php');
tinymaker_connect_require_app('Footer.php');

web_require_installed();
if (admin_count() < 1) {
    redirect_to('/install.php');
}
?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Flash TinyMakerWifi - TinyMaker Connect</title>
  <link rel="icon" type="image/svg+xml" href="/flash-favicon.svg">
  <style>
    :root{color-scheme:dark;--bg:#111214;--panel:#1b1d20;--panel2:#24262b;--text:#f2f2f2;--muted:#a5a7ad;--line:#343740;--accent:#e8720c;--accentText:#fff;--soft:#2f2419;--input:#141518;--shadow:0 14px 40px rgba(0,0,0,.28)}
    :root[data-theme="light"]{color-scheme:light;--bg:#f6f7f9;--panel:#fff;--panel2:#e6e9ee;--text:#202329;--muted:#68707d;--line:#d6dae2;--accent:#e8720c;--accentText:#fff;--soft:#fff0df;--input:#fff;--shadow:0 14px 34px rgba(30,35,45,.12)}
    *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,Segoe UI,sans-serif;font-size:16px;line-height:1.45}a{color:inherit}main{width:min(1100px,100%);margin:0 auto;padding:24px}.top{display:flex;justify-content:space-between;gap:16px;align-items:flex-end;margin-bottom:20px}.brand{display:flex;gap:12px;align-items:center}.logo{width:52px;height:52px;display:block;flex:0 0 52px}.logo img{display:block;width:52px;height:52px}.eyebrow{color:var(--accent);font-weight:800;text-transform:uppercase;font-size:12px;letter-spacing:.08em}h1{font-size:32px;margin:0}h2{font-size:22px;margin:0 0 12px}.muted{color:var(--muted)}.toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.button,button{border:0;border-radius:8px;padding:11px 14px;background:var(--panel2);color:var(--text);font:inherit;font-weight:800;cursor:pointer;text-decoration:none}.button.primary,button.primary{background:var(--accent);color:var(--accentText)}.button.secondary,button.secondary{border:1px solid var(--line)}button:disabled{opacity:.55;cursor:not-allowed}input[type="url"],input[type="file"],select{width:100%;border:1px solid var(--line);border-radius:8px;background:var(--input);color:var(--text);padding:12px 14px;font:inherit}.notice{background:var(--soft);border:1px solid var(--accent);border-radius:8px;padding:12px 14px;margin:12px 0 18px}.flashGrid{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:16px}.panel{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:16px;box-shadow:var(--shadow)}.steps{margin:0;padding-left:20px}.steps li{margin:8px 0}.field{display:grid;gap:6px;margin:12px 0}.field small{color:var(--muted)}.log{height:250px;overflow:auto;border:1px solid var(--line);border-radius:8px;background:#070809;color:#d8dde6;padding:12px;font:12px/1.45 ui-monospace,SFMono-Regular,Consolas,monospace;white-space:pre-wrap}.progress{height:12px;border:1px solid var(--line);border-radius:999px;overflow:hidden;background:var(--input);margin:12px 0}.progress>span{display:block;height:100%;width:0;background:var(--accent);transition:width .15s ease}.hidden{display:none!important}.advanced{border-top:1px solid var(--line);margin-top:16px;padding-top:12px}.advanced summary{cursor:pointer;font-weight:800}.credits{margin-top:16px}.credits p{margin:8px 0}.credits a{color:var(--accent);text-decoration:none}.credits a:hover{text-decoration:underline}<?= tinymaker_connect_footer_css() ?>@media(max-width:820px){main{padding:16px}.top,.flashGrid{display:block}.brand{align-items:flex-start}.toolbar{margin-top:12px}.panel{margin-top:14px}}
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
  <div class="top">
    <div class="brand">
      <a class="logo" href="/" aria-label="TinyMaker Connect home"><img src="/flash-favicon.svg" alt=""></a>
      <div>
        <a class="muted" href="/">Back to Connect</a>
        <div class="eyebrow">First-time setup</div>
        <h1>Flash TinyMakerWifi over USB</h1>
        <div class="muted">Install the full TinyMakerWifi firmware from a browser using Web Serial.</div>
      </div>
    </div>
    <div class="toolbar">
      <button id="themeToggle" type="button" class="secondary">Theme</button>
    </div>
  </div>

  <div id="flashUnsupported" class="notice hidden">
    Web Serial is not available in this browser. Use Chrome or Edge on desktop over HTTPS.
  </div>

  <div class="notice">
    Use this only for the first USB install or recovery. It writes <b>firmware-full.bin</b> at address <b>0x0</b>. After TinyMakerWifi is installed, normal updates can be done from the printer dashboard over WiFi.
  </div>

  <div class="flashGrid">
    <section class="panel">
      <h2>Flash tool</h2>
      <p class="muted">Default: latest TinyMakerWifi <b>firmware-full.bin</b> from GitHub Releases.</p>
      <div class="toolbar">
        <button id="flashConnectButton" type="button" class="secondary">Connect printer</button>
        <button id="flashLatestButton" type="button" class="primary">Flash latest</button>
      </div>
      <details class="advanced">
        <summary>Advanced: custom firmware</summary>
        <div class="field">
          <label for="flashBaud">Baud rate</label>
          <select id="flashBaud">
            <option value="921600" selected>921600</option>
            <option value="460800">460800</option>
            <option value="230400">230400</option>
            <option value="115200">115200</option>
          </select>
          <small>Leave this at 921600 unless flashing fails, then try a lower speed.</small>
        </div>
        <div class="field">
          <label for="flashLocalFile">Local firmware-full.bin</label>
          <input id="flashLocalFile" type="file" accept=".bin,application/octet-stream">
          <small>Only use a trusted firmware-full.bin. The first-time USB image must be flashed at 0x0.</small>
        </div>
        <button id="flashLocalButton" type="button" class="secondary">Flash selected file</button>
      </details>
      <div class="progress" aria-label="Flash progress"><span id="flashProgressBar"></span></div>
      <div class="muted" id="flashProgressLabel">0%</div>
      <pre id="flashLog" class="log"></pre>
    </section>

    <aside class="panel">
      <h2>Steps</h2>
      <ol class="steps">
        <li>Connect the printer to USB.</li>
        <li>Turn the printer on.</li>
        <li>Press <b>Connect printer</b> and pick the USB serial port.</li>
        <li>Press <b>Flash latest</b>.</li>
        <li>Do not disconnect power or USB until the tool says flashing is complete.</li>
      </ol>
      <p class="muted">If you see multiple serial ports, unplug the printer, open the port picker again, then plug it back in and select the port that appears.</p>
      <p class="muted">If flashing fails at high speed, select a lower baud rate and retry.</p>
    </aside>
  </div>

  <section class="panel credits">
    <h2>Credits</h2>
    <p>USB flashing is powered by <a href="https://github.com/espressif/esptool-js" target="_blank" rel="noopener">Espressif esptool-js</a>, the browser version of Espressif's serial flashing tool.</p>
    <p>This page uses the browser <a href="https://developer.mozilla.org/en-US/docs/Web/API/Web_Serial_API" target="_blank" rel="noopener">Web Serial API</a>, so flashing happens locally between your browser and the printer.</p>
    <p class="muted">esptool-js is licensed under Apache-2.0 by Espressif Systems.</p>
  </section>

  <?= tinymaker_connect_footer() ?>
</main>
<script>
(function(){
  const setTheme = theme => {
    theme = theme === 'light' ? 'light' : 'dark';
    document.documentElement.dataset.theme = theme;
    localStorage.setItem('tmcTheme', theme);
    document.querySelectorAll('#themeToggle').forEach(b => b.textContent = theme === 'light' ? 'Dark theme' : 'Light theme');
  };
  setTheme(localStorage.getItem('tmcTheme') || 'dark');
  document.querySelectorAll('#themeToggle').forEach(b => b.addEventListener('click', () => setTheme(document.documentElement.dataset.theme === 'light' ? 'dark' : 'light')));
})();
</script>
<script type="module" src="/assets/flash-tool.js"></script>
</body>
</html>

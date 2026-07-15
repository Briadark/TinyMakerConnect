(function () {
  let rendered = false;
  const byId = id => document.getElementById(id);
  const bind = (id, ev, fn) => {
    const el = byId(id);
    if (el) el.addEventListener(ev, fn);
  };
  const injectCss = () => {
    if (byId('tinymakerConnectHostedStyle')) return;
    const style = document.createElement('style');
    style.id = 'tinymakerConnectHostedStyle';
    style.textContent =
      '.connectHosted .hint{color:var(--muted)}.connectHosted .hint.warn{color:var(--warn,var(--accent))}.connectNoTop{margin-top:0}.connectMt10{margin-top:10px}.connectMt12{margin-top:12px}.connectSection,.connectSubhead{margin-top:14px}' +
      '.connectTiles{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px}' +
      '.connectTile{background:var(--tile);border:1px solid var(--line);border-radius:8px;padding:12px;text-decoration:none;color:var(--text)}.connectTile>a{display:block;color:inherit;text-decoration:none}' +
      '.connectTile:hover{border-color:var(--accent);text-decoration:none}.connectPreview{aspect-ratio:4/3;background:var(--pv);border:1px solid var(--line);border-radius:6px;display:flex;align-items:center;justify-content:center;overflow:hidden;margin-bottom:10px}' +
      '.connectPreview img{width:100%;height:100%;object-fit:contain}.connectStats{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:10px}' +
      '.connectStat{border-top:1px solid var(--line);padding-top:7px}.pills{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}.pill{border:1px solid var(--line);border-radius:999px;padding:3px 8px;color:var(--muted);font-size:12px}' +
      '.connectActions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}' +
      '.connectFilters{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.connectFilters button{background:var(--card);border:1px solid var(--line);border-radius:8px;color:var(--text);font-weight:700;padding:9px 12px}.connectFilters button.active{background:var(--accent);border-color:var(--accent);color:var(--accentText,#fff)}' +
      '.connectShareCanvas{width:100%;border:1px solid var(--line);border-radius:8px;background:var(--pv);margin-top:10px}' +
      '.leaderRows{display:grid;gap:8px}.leaderRow{display:grid;grid-template-columns:36px minmax(0,1fr) repeat(6,auto);gap:8px;align-items:center;border-top:1px solid var(--line);padding-top:10px}' +
      '.bootAnimPreview{aspect-ratio:2/1;background:var(--pv);border:1px solid var(--line);border-radius:6px;display:flex;align-items:center;justify-content:center;overflow:hidden;margin-bottom:10px}' +
      '.bootAnimPreview canvas{width:100%;height:100%;image-rendering:pixelated}' +
      '@media(max-width:520px){.leaderRow{grid-template-columns:32px minmax(0,1fr);gap:4px}.leaderRow .pill{width:max-content}}';
    document.head.appendChild(style);
  };

  const decodeMaybe = value => {
    try { return decodeURIComponent(value || ''); }
    catch (e) { return value || ''; }
  };
  const connectApiUrl = path => connectBase() + path;
  const connectAuthHeaders = () => connectConfig && connectConfig.connectPublishToken ? { 'X-TinyMaker-Token': connectConfig.connectPublishToken } : {};
  const connectFetchJson = async (path, auth) => {
    const r = await fetch(connectApiUrl(path), { cache: 'no-store', headers: auth ? connectAuthHeaders() : {} });
    let j = {};
    try { j = await r.json(); } catch (e) {}
    if (!r.ok || j.ok === false) throw new Error(j.error || ('HTTP ' + r.status));
    return j;
  };
  const connectPostForm = (path, fd, progressCb) => new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', connectApiUrl(path));
    if (connectConfig && connectConfig.connectPublishToken) xhr.setRequestHeader('X-TinyMaker-Token', connectConfig.connectPublishToken);
    xhr.upload.onprogress = e => { if (progressCb && e.lengthComputable) progressCb(e.loaded, e.total); };
    xhr.onload = () => {
      let j = {};
      try { j = JSON.parse(xhr.responseText || '{}'); } catch (e) {}
      if (xhr.status >= 200 && xhr.status < 300 && j.ok !== false) resolve(j);
      else reject(new Error(j.error || ('HTTP ' + xhr.status)));
    };
    xhr.onerror = () => reject(new Error('upload failed'));
    xhr.send(fd);
  });
  let connectPreviewMode = localStorage.getItem('tmConnectPreviewMode') || '05';
  const setConnectPreviewMode = mode => {
    connectPreviewMode = mode === '1' ? '1' : '05';
    localStorage.setItem('tmConnectPreviewMode', connectPreviewMode);
    if (byId('connectPreview05Button')) byId('connectPreview05Button').classList.toggle('active', connectPreviewMode === '05');
    if (byId('connectPreview1Button')) byId('connectPreview1Button').classList.toggle('active', connectPreviewMode === '1');
    if (connectIsReady()) loadConnectTab();
  };
  const connectInstallBootAnim = async (downloadUrl, installName, publicId, animationName, version, checksum, credits, licenseName) => {
    downloadUrl = decodeMaybe(downloadUrl); installName = decodeMaybe(installName) || 'downloaded';
    publicId = decodeMaybe(publicId); animationName = decodeMaybe(animationName); version = decodeMaybe(version); checksum = decodeMaybe(checksum); credits = decodeMaybe(credits); licenseName = decodeMaybe(licenseName);
    if (statusData && statusData.busy) { msg('Printer is busy. Install when idle.', true); return; }
    if (statusData && statusData.sdReady === false) { msg('Insert an SD card before installing boot animations.', true); return; }
    if (!downloadUrl) { msg('Animation download URL missing.', true); return; }
    if (!await uiConfirm('Install this boot animation on the printer?')) return;
    const url = downloadUrl.indexOf('http') === 0 ? downloadUrl : connectBase() + downloadUrl;
    const fd = new URLSearchParams(); fd.append('url', url); fd.append('name', installName);
    if (publicId) { fd.append('public_id', publicId); fd.append('connect_url', connectBase() + '/api/boot-animations/' + enc(publicId) + '/download'); }
    if (animationName) fd.append('animation_name', animationName);
    if (version) fd.append('version', version);
    if (checksum) fd.append('checksum_sha256', checksum);
    if (credits) fd.append('original_credits', credits);
    if (licenseName) fd.append('license', licenseName);
    try { msg('Installing boot animation...'); await api('/api/boot-anim/install', { method: 'POST', body: fd }, 30000); msg('Boot animation installed.'); loadBootAnims(); loadConnectTab(); }
    catch (e) { msg(e.message, true); }
  };
  const connectActivateBootAnim = async nameEnc => {
    const name = decodeMaybe(nameEnc);
    if (statusData && statusData.busy) { msg('Printer is busy. Change boot animation when idle.', true); return; }
    try {
      await api('/api/boot-anim/select', { method: 'POST', body: new URLSearchParams({ name }) });
      msg(name ? 'Boot animation activated. Reboot to see it.' : 'Default boot animation activated.');
      loadBootAnims(); loadConnectTab();
    } catch (e) { msg(e.message, true); }
  };
  const connectModelAction = async (id, action) => {
    id = decodeMaybe(id);
    if (action === 'removed' && !await uiConfirm('Remove this shared model from TinyMaker Connect?', { danger: true })) return;
    const fd = new FormData();
    if (action === 'removed') fd.append('_method', 'DELETE');
    else { fd.append('_method', 'PATCH'); fd.append('status', action); }
    try { await connectPostForm('/api/models/' + enc(id), fd, null); loadConnectTab(); }
    catch (e) { msg(e.message, true); }
  };
  const connectImportModel = async (id, name, downloadUrl, credits, licenseName, preview05Url, preview1Url, resinMl, serverLayers) => {
    id = decodeMaybe(id); name = decodeMaybe(name); downloadUrl = decodeMaybe(downloadUrl);
    credits = decodeMaybe(credits); licenseName = decodeMaybe(licenseName); preview05Url = decodeMaybe(preview05Url); preview1Url = decodeMaybe(preview1Url); resinMl = decodeMaybe(resinMl);
    serverLayers = parseInt(decodeMaybe(serverLayers)) || 0;
    if (statusData && statusData.busy) { msg('Printer is busy. Import when idle.', true); return; }
    if (statusData && statusData.sdReady === false) { msg('Insert an SD card before importing models.', true); return; }
    if (!await uiConfirm('Import ' + name + ' to the printer SD card?')) return;
    const url = connectBase() + downloadUrl;
    try {
      msg('Importing ' + name + ' to SD...');
      const r = await fetch(url, { cache: 'no-store', headers: connectAuthHeaders() });
      if (!r.ok) throw new Error('download failed (HTTP ' + r.status + ')');
      const blob = await r.blob();
      if (!checkUploadFits(blob.size, byId('statusMsg'))) return;
      uploadBusy = true;
      msg('Importing ' + name + ' to SD...');
      const safeName = name.replace(/[^A-Za-z0-9_.-]/g, '_') + '.zip';
      const meta = { source: 'connect', connect_public_id: id, connect_url: connectBase() + '/model/' + enc(id), original_credits: credits, license: licenseName };
      if (resinMl) meta.resin_ml = resinMl;
      const done = await uploadModelPayload(blob, safeName, byId('statusMsg'), meta);
      const finalName = done && done.name ? done.name : name;
      let previewError = '';
      if (preview05Url) {
        const previewFull = preview05Url.indexOf('http') === 0 ? preview05Url : connectBase() + preview05Url;
        try { await uploadPreviewFromUrl(finalName, previewFull, '05'); } catch (e) { previewError = e.message; }
      }
      if (preview1Url) {
        const previewFull = preview1Url.indexOf('http') === 0 ? preview1Url : connectBase() + preview1Url;
        try { await uploadPreviewFromUrl(finalName, previewFull, '1'); } catch (e) { previewError = previewError || e.message; }
      }
      const sourceLayers = Number(done && done.layers) || Number(done && done.sourceLayers) || 0, printLayers = Number(done && done.printLayers) || 0;
      let note = '';
      if (sourceLayers && printLayers && sourceLayers !== printLayers) note += ' Source: ' + sourceLayers + ' layers; current print setting uses ' + printLayers + ' layers.';
      if (serverLayers && sourceLayers && serverLayers !== sourceLayers) note += ' Server listed ' + serverLayers + ' layers.';
      msg(previewError ? ('Imported ' + finalName + ' to SD, but preview was not saved: ' + previewError + note) : ('Imported ' + finalName + ' to SD.' + note), !!previewError);
      uploadBusy = false;
      connectLocalModels[id] = finalName;
      loadConnectTab();
      refreshStatus(); await loadFiles(); loadConnectTab();
    } catch (e) { uploadBusy = false; msg(e.message, true); }
  };
  let shareState = null;
  const shareSet = (text, pct) => {
    byId('shareSteps').textContent = text;
    show('shareProgress', pct !== null && pct !== undefined);
    if (pct !== null && pct !== undefined) byId('shareProgressFill').style.width = Math.max(0, Math.min(100, pct)) + '%';
  };
  const makeSharePreview = async (name, type, layers, modelH, pct) => {
    const mode = type === '05' ? 'source05' : 'print1';
    shareSet('2. Making ' + (type === '05' ? '0.05' : '0.10') + ' mm preview...', pct);
    await fetchSlices(name, layers, modelH, null, mode);
    show('connectPreviewCanvas', true); drawIso(byId('connectPreviewCanvas'), 1);
    const blob = await canvasBlob(byId('connectPreviewCanvas'));
    await uploadModelPreview(name, blob, type);
    return blob;
  };
  let crcTable = null;
  const crc32 = buf => {
    if (!crcTable) {
      crcTable = [];
      for (let n = 0; n < 256; n++) {
        let c = n;
        for (let k = 0; k < 8; k++) c = (c & 1) ? (0xedb88320 ^ (c >>> 1)) : (c >>> 1);
        crcTable[n] = c >>> 0;
      }
    }
    let c = 0xffffffff;
    const b = new Uint8Array(buf);
    for (let i = 0; i < b.length; i++) c = crcTable[(c ^ b[i]) & 255] ^ (c >>> 8);
    return (c ^ 0xffffffff) >>> 0;
  };
  const zipU16 = (a, v) => { a.push(v & 255, (v >>> 8) & 255); };
  const zipU32 = (a, v) => { a.push(v & 255, (v >>> 8) & 255, (v >>> 16) & 255, (v >>> 24) & 255); };
  const zipHeader = (sig, fn, crc, size, offset, central) => {
    const a = [];
    zipU32(a, sig);
    if (central) { zipU16(a, 20); zipU16(a, 20); } else zipU16(a, 20);
    zipU16(a, 0); zipU16(a, 0); zipU16(a, 0); zipU16(a, 0); zipU32(a, crc); zipU32(a, size); zipU32(a, size); zipU16(a, fn.length); zipU16(a, 0);
    if (central) { zipU16(a, 0); zipU16(a, 0); zipU16(a, 0); zipU32(a, 0); zipU32(a, offset); }
    return new Uint8Array(a);
  };
  const zipModelLayers = async (name, layers, progressCb, mode) => {
    mode = mode || 'current';
    const encText = new TextEncoder(), parts = [], central = [];
    let offset = 0, centralSize = 0;
    for (let i = 1; i <= layers; i++) {
      let url = '/api/files/layer?name=' + enc(name) + '&i=' + i;
      if (mode === 'source05') url += '&source=1';
      else if (mode === 'print1') url += '&layer_height=0.10';
      const r = await fetch(url, { cache: 'no-store' });
      if (!r.ok) throw new Error('layer ' + i + ' failed');
      const data = await r.arrayBuffer(), fn = encText.encode(i + '.png'), crc = crc32(data), size = data.byteLength;
      const local = zipHeader(0x04034b50, fn, crc, size, offset, false);
      parts.push(local, fn, data); offset += local.length + fn.length + size;
      const cd = zipHeader(0x02014b50, fn, crc, size, offset - local.length - fn.length - size, true);
      central.push(cd, fn); centralSize += cd.length + fn.length;
      if (progressCb) progressCb(i, layers);
    }
    const centralStart = offset;
    central.forEach(p => parts.push(p));
    const end = [];
    zipU32(end, 0x06054b50); zipU16(end, 0); zipU16(end, 0); zipU16(end, layers); zipU16(end, layers); zipU32(end, centralSize); zipU32(end, centralStart); zipU16(end, 0);
    parts.push(new Uint8Array(end));
    return new Blob(parts, { type: 'application/zip' });
  };
  const shareModel = async name => {
    if (!connectIsReady()) { msg('Configure TinyMaker Connect first.', true); openView('connect'); return; }
    setConnectTab('models');
    openView('connect'); show('connectPublishBox', true); byId('shareUploadButton').classList.add('hidden'); byId('shareUploadButton').disabled = true;
    byId('shareModelName').value = name; byId('shareCredits').value = ''; byId('shareLicense').value = 'CC-BY-NC';
    shareState = { sdName: name, details: null, archive: null, preview05: null, preview1: null };
    try {
      shareSet('1. Checking model details...', 8);
      let d = await api('/api/files/model?name=' + enc(name), null, 30000);
      if (!d.resinEstimated) {
        shareSet('1. Calculating ml...', 10);
        d = await api('/api/files/model?name=' + enc(name) + '&estimate=1', null, 120000);
      } else {
        shareSet('1. Using saved ml estimate...', 14);
      }
      shareState.details = d;
      const sourceLayers = Number(d.sourceLayers) || Number(d.layers) || 0;
      if (!sourceLayers) throw new Error('model has no source layers');
      const printLayers1 = Math.max(1, Math.floor(sourceLayers / 2));
      const modelH05 = sourceLayers * 0.05;
      if (d.preview05) {
        shareSet('2. Using saved 0.05 mm preview...', 20);
        shareState.preview05 = await localPreviewBlob(name, '05');
      } else {
        shareState.preview05 = await makeSharePreview(name, '05', sourceLayers, modelH05, 20);
      }
      if (d.preview1) {
        shareSet('2. Using saved 0.10 mm preview...', 28);
        shareState.preview1 = await localPreviewBlob(name, '1');
      } else {
        shareState.preview1 = await makeSharePreview(name, '1', printLayers1, printLayers1 * 0.1, 28);
      }
      shareSet('3. Preparing model for upload...', 35);
      shareState.archive = await zipModelLayers(name, sourceLayers, (i, n) => shareSet('3. Preparing model for upload... ' + i + ' / ' + n, 35 + Math.round(55 * i / n)), 'source05');
      shareSet('4. Done. Review the details, then upload.', 100);
      byId('shareUploadButton').classList.remove('hidden'); byId('shareUploadButton').disabled = false;
    } catch (e) { shareSet(e.message, null); msg(e.message, true); }
  };
  const uploadSharedModel = async () => {
    if (!shareState || !shareState.archive || !shareState.details) return;
    const modelName = byId('shareModelName').value.trim();
    if (!modelName) { msg('Model name is required.', true); return; }
    const d = shareState.details, fd = new FormData();
    fd.append('model_name', modelName); fd.append('original_credits', byId('shareCredits').value.trim());
    const sourceLayers = Number(d.sourceLayers) || Number(d.layers) || 0;
    fd.append('license', byId('shareLicense').value.trim() || 'CC-BY-NC');
    fd.append('firmware_version', (statusData && statusData.firmwareVersion) || '');
    fd.append('printer_name', (connectConfig && connectConfig.connectPrinterName) || '');
    fd.append('leaderboard_opt_in', (connectConfig && connectConfig.connectLeaderboardOptIn) ? '1' : '0');
    if (connectConfig && connectConfig.connectLeaderboardOptIn && statusData && statusData.lifetimePrintSecs !== undefined) fd.append('lifetime_print_secs', statusData.lifetimePrintSecs);
    fd.append('layers', sourceLayers); fd.append('height_mm', (sourceLayers * 0.05).toFixed(2)); if (d.resinEstimated) fd.append('resin_ml', d.resinMl);
    fd.append('archive', shareState.archive, modelName.replace(/[^A-Za-z0-9_.-]/g, '_') + '.zip');
    if (shareState.preview05) fd.append('preview05', shareState.preview05, 'preview05.png');
    if (shareState.preview1) fd.append('preview1', shareState.preview1, 'preview1.png');
    byId('shareUploadButton').disabled = true;
    try {
      shareSet('6. Uploading...', 0);
      const res = await connectPostForm('/api/models', fd, (l, t) => shareSet('6. Uploading... ' + Math.round(100 * l / t) + '%', Math.round(100 * l / t)));
      const m = res.model || {};
      const meta = new URLSearchParams();
      meta.append('name', shareState.sdName);
      meta.append('shared_model_name', modelName);
      meta.append('original_credits', byId('shareCredits').value.trim());
      if (m.public_id) { meta.append('connect_public_id', m.public_id); meta.append('connect_url', connectBase() + '/model/' + enc(m.public_id)); selectedModelConnectPublicId = m.public_id; show('modelShareButton', false); }
      if (m.license) meta.append('license', m.license);
      if (d.resinEstimated) meta.append('resin_ml', d.resinMl);
      await api('/api/files/model/metadata', { method: 'POST', body: meta });
      shareSet('7. Model uploaded and metadata saved.', 100); loadConnectTab();
    } catch (e) { shareSet(e.message, null); msg(e.message, true); byId('shareUploadButton').disabled = false; }
  };
  window.connectInstallBootAnim = connectInstallBootAnim;
  window.connectActivateBootAnim = connectActivateBootAnim;
  window.connectModelAction = connectModelAction;
  window.connectImportModel = connectImportModel;
  window.TinyMakerConnectShareModel = name => shareModel(decodeMaybe(name));

  const connectModelHtml = (m, mine) => {
    const detail = connectBase() + '/model/' + enc(m.public_id);
    const previewPath = (connectPreviewMode === '1' ? m.preview_1_url : m.preview_05_url) || (m.preview_05_url || m.preview_1_url || '');
    const preview = previewPath ? connectBase() + previewPath : '';
    const resin = (m.resin_ml === null || m.resin_ml === undefined) ? '-' : Number(m.resin_ml).toFixed(2) + ' ml';
    const rating = m.rating_count > 0 ? (Number(m.rating_average || 0).toFixed(1) + '/5') : 'No ratings';
    const localName = connectLocalModels[m.public_id || ''] || '';
    const actionDisabled = (statusData && statusData.busy) || (statusData && statusData.webControl === false);
    let h = '<div class="connectTile">';
    h += '<a href="' + esc(detail) + '" target="_blank" rel="noopener">';
    h += '<div class="connectPreview">' + (preview ? '<img src="' + esc(preview) + '" alt="">' : '<span class="meta">No preview</span>') + '</div>';
    h += '<h2>' + esc(m.model_name) + '</h2>';
    h += '<div class="meta">' + esc(m.original_credits || '') + '</div>';
    h += '<div class="connectStats">';
    h += '<div class="connectStat"><div class="label">Layers</div><div class="value">' + esc(m.layers || 0) + '</div></div>';
    h += '<div class="connectStat"><div class="label">Height</div><div class="value">' + Number(m.height_mm || 0).toFixed(2) + ' mm</div></div>';
    h += '<div class="connectStat"><div class="label">Resin</div><div class="value">' + esc(resin) + '</div></div>';
    h += '</div><div class="pills">';
    h += '<span class="pill">' + esc(m.download_count || 0) + ' downloads</span>';
    h += '<span class="pill">' + esc(rating) + '</span>';
    if (m.bookmark_count) h += '<span class="pill">' + esc(m.bookmark_count) + ' bookmarks</span>';
    if (mine && m.status) h += '<span class="pill">' + esc(m.status) + '</span>';
    if (localName) h += '<span class="pill">On SD</span>';
    h += '</div></a><div class="connectActions">';
    if (localName) h += '<button class="small"' + (actionDisabled ? ' disabled' : '') + ' onclick="startPrint(\'' + enc(localName) + '\')">Print</button>';
    else h += '<button class="small secondaryBtn"' + (actionDisabled ? ' disabled' : '') + ' onclick="connectImportModel(\'' + enc(m.public_id) + '\',\'' + enc(m.model_name || 'Model') + '\',\'' + enc(m.download_url) + '\',\'' + enc(m.original_credits || '') + '\',\'' + enc(m.license || '') + '\',\'' + enc(m.preview_05_url || '') + '\',\'' + enc(m.preview_1_url || '') + '\',\'' + enc(m.resin_ml === null || m.resin_ml === undefined ? '' : m.resin_ml) + '\',\'' + enc(m.layers || '') + '\')">Import</button>';
    if (mine) {
      if (m.status === 'hidden') h += '<button class="small secondaryBtn" onclick="connectModelAction(\'' + enc(m.public_id) + '\',\'published\')">Publish</button>';
      else h += '<button class="small secondaryBtn" onclick="connectModelAction(\'' + enc(m.public_id) + '\',\'hidden\')">Hide</button>';
      h += '<button class="delete" onclick="connectModelAction(\'' + enc(m.public_id) + '\',\'removed\')">Remove</button>';
    }
    return h + '</div></div>';
  };

  const loadConnectModels = async refreshLocal => {
    if (!connectIsReady()) return;
    refreshLocal = refreshLocal !== false;
    byId('connectModelsList').innerHTML = '<div class="hint">Loading models...</div>';
    byId('connectMineList').innerHTML = '<div class="hint">Loading your shared models...</div>';
    if (refreshLocal) try { await loadFiles(); } catch (e) {}
    try {
      const all = await connectFetchJson('/api/models');
      const items = all.items || [];
      byId('connectModelsList').innerHTML = items.length ? '<div class="connectTiles">' + items.slice(0, 20).map(m => connectModelHtml(m, false)).join('') + '</div>' : '<div class="hint">No shared models yet.</div>';
    } catch (e) { byId('connectModelsList').innerHTML = '<div class="hint warn">' + esc(e.message) + '</div>'; }
    try {
      const mine = await connectFetchJson('/api/printers/me/models', true);
      const items = mine.items || [];
      byId('connectMineList').innerHTML = items.length ? '<div class="connectTiles">' + items.map(m => connectModelHtml(m, true)).join('') + '</div>' : '<div class="hint">This printer has not shared models yet.</div>';
    } catch (e) { byId('connectMineList').innerHTML = '<div class="hint warn">' + esc(e.message) + '</div>'; }
  };

  const bootAnimIntervals = [];
  const clearBootAnimPreviews = () => { while (bootAnimIntervals.length) clearInterval(bootAnimIntervals.pop()); };
  const rgb565 = (lo, hi) => {
    const v = lo | (hi << 8), r = ((v >> 11) & 31) * 255 / 31, g = ((v >> 5) & 63) * 255 / 63, b = (v & 31) * 255 / 31;
    return [r | 0, g | 0, b | 0, 255];
  };
  const renderTmbCanvas = async (cv, url) => {
    const r = await fetch(url, { cache: 'no-store' });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const buf = await r.arrayBuffer(), u = new Uint8Array(buf);
    if (u.length < 12 || u[0] !== 84 || u[1] !== 77 || u[2] !== 66 || u[3] !== 49) throw new Error('invalid TMB1');
    const w = u[4] | (u[5] << 8), h = u[6] | (u[7] << 8), frames = u[8] | (u[9] << 8), fps = u[10] | (u[11] << 8);
    const frameBytes = w * h * 2, total = Math.min(frames, Math.floor((u.length - 12) / frameBytes), 120);
    if (!w || !h || w > 160 || h > 80 || !total) throw new Error('invalid TMB size');
    cv.width = w; cv.height = h;
    const ctx = cv.getContext('2d'), img = ctx.createImageData(w, h);
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
      bootAnimIntervals.push(setInterval(() => { i = (i + 1) % total; draw(i); }, delay));
    }
  };
  const renderBootAnimPreviews = root => {
    root.querySelectorAll('canvas[data-tmb-url]').forEach(cv => {
      const fallback = cv.dataset.tmbFallback || '';
      const fail = () => { const p = cv.parentElement; if (p) p.innerHTML = '<span class="meta">Preview unavailable</span>'; };
      renderTmbCanvas(cv, cv.dataset.tmbUrl).catch(() => {
        if (fallback && fallback !== cv.dataset.tmbUrl) renderTmbCanvas(cv, fallback).catch(fail);
        else fail();
      });
    });
  };
  const loadLocalBootAnimations = async () => {
    const d = await api('/api/boot-anim');
    activeBootAnim = d.selected || '';
    localBootAnims = {};
    (d.animations || []).forEach(a => { localBootAnims[a.name] = a; });
    return d;
  };
  const connectInstalledBootAnimHtml = (a, isDefault) => {
    const active = isDefault ? !activeBootAnim : activeBootAnim === a.name;
    const actionDisabled = (statusData && statusData.busy) || (statusData && statusData.webControl === false);
    let h = '<div class="connectTile">';
    if (isDefault) h += '<div class="bootAnimPreview"><span class="meta">Built-in TinyMaker boot screen</span></div>';
    else {
      const localUrl = '/api/boot-anim/file?name=' + enc(a.name);
      const connectPreview = a.connectPublicId ? connectBase() + '/api/boot-animations/' + enc(a.connectPublicId) + '/preview' : '';
      h += '<div class="bootAnimPreview"><canvas data-tmb-url="' + esc(connectPreview || localUrl) + '"' + (connectPreview ? ' data-tmb-fallback="' + esc(localUrl) + '"' : '') + '></canvas></div>';
    }
    h += '<h2>' + esc(isDefault ? 'Default' : (a.animationName || a.display)) + '</h2><div class="pills">';
    if (active) h += '<span class="pill">Active</span>';
    if (!isDefault) h += '<span class="pill">' + formatBytes(a.sizeBytes || 0) + '</span>';
    if (a.version) h += '<span class="pill">v' + esc(a.version) + '</span>';
    if (a.connectPublicId) h += '<span class="pill">Connect</span>';
    h += '</div><div class="connectActions">';
    if (!active) h += '<button class="small secondaryBtn"' + (actionDisabled ? ' disabled' : '') + ' onclick="connectActivateBootAnim(\'' + enc(isDefault ? '' : a.name) + '\')">Activate</button>';
    return h + '</div></div>';
  };
  const renderInstalledBootAnimations = d => {
    const items = d.animations || [], canShuffle = items.length > 1, shuffleActive = activeBootAnim === '__shuffle';
    show('connectBootAnimShuffleBox', canShuffle);
    if (canShuffle) {
      const actionDisabled = (statusData && statusData.busy) || (statusData && statusData.webControl === false);
      byId('connectBootAnimShuffleButton').disabled = !!actionDisabled || shuffleActive;
      byId('connectBootAnimShuffleButton').textContent = shuffleActive ? 'Shuffle active' : 'Shuffle installed';
    }
    byId('connectInstalledBootAnimList').innerHTML = '<div class="connectTiles">' + [connectInstalledBootAnimHtml({}, true)].concat(items.map(a => connectInstalledBootAnimHtml(a, false))).join('') + '</div>';
    renderBootAnimPreviews(byId('connectInstalledBootAnimList'));
  };
  const connectBootAnimHtml = a => {
    const actionDisabled = (statusData && statusData.busy) || (statusData && statusData.webControl === false) || (statusData && statusData.sdReady === false);
    const local = localBootAnims[a.install_name || ''];
    const active = local && activeBootAnim === local.name;
    const updateAvailable = !!(local && ((a.version && local.version && a.version !== local.version) || (a.checksum_sha256 && local.checksumSha256 && a.checksum_sha256 !== local.checksumSha256)));
    const previewUrl = a.preview_url || a.download_url || '';
    const url = previewUrl ? (previewUrl.indexOf('http') === 0 ? previewUrl : connectBase() + previewUrl) : '';
    let h = '<div class="connectTile">';
    h += '<div class="bootAnimPreview">' + (url ? '<canvas data-tmb-url="' + esc(url) + '"></canvas>' : '<span class="meta">No preview</span>') + '</div>';
    h += '<h2>' + esc(a.animation_name || 'Boot animation') + '</h2>';
    h += '<div class="meta">' + esc(a.description || a.original_credits || '') + '</div><div class="pills">';
    if (a.original_credits) h += '<span class="pill">' + esc(a.original_credits) + '</span>';
    if (a.license) h += '<span class="pill">' + esc(a.license) + '</span>';
    if (a.version) h += '<span class="pill">v' + esc(a.version) + '</span>';
    h += '<span class="pill">' + formatBytes(a.file_size || 0) + '</span><span class="pill">' + esc(a.download_count || 0) + ' installs</span>';
    if (local) h += '<span class="pill">Installed</span>';
    if (active) h += '<span class="pill">Active</span>';
    if (updateAvailable) h += '<span class="pill">Update available</span>';
    h += '</div><div class="connectActions">';
    if (local && !active) h += '<button class="small secondaryBtn"' + (actionDisabled ? ' disabled' : '') + ' onclick="connectActivateBootAnim(\'' + enc(local.name) + '\')">Activate</button>';
    h += '<button class="small' + (local && !updateAvailable ? ' secondaryBtn' : '') + '"' + (actionDisabled ? ' disabled' : '') + ' onclick="connectInstallBootAnim(\'' + enc(a.download_url || '') + '\',\'' + enc(a.install_name || a.animation_name || 'downloaded') + '\',\'' + enc(a.public_id || '') + '\',\'' + enc(a.animation_name || '') + '\',\'' + enc(a.version || '') + '\',\'' + enc(a.checksum_sha256 || '') + '\',\'' + enc(a.original_credits || '') + '\',\'' + enc(a.license || '') + '\')">' + (updateAvailable ? 'Update' : (local ? 'Reinstall' : 'Install')) + '</button>';
    return h + '</div></div>';
  };
  const loadConnectBootAnimations = async () => {
    if (!connectIsReady()) return;
    clearBootAnimPreviews();
    byId('connectInstalledBootAnimList').innerHTML = '<div class="hint">Loading installed boot animations...</div>';
    byId('connectBootAnimList').innerHTML = '<div class="hint">Loading boot animations...</div>';
    show('connectBootAnimShuffleBox', false);
    try { renderInstalledBootAnimations(await loadLocalBootAnimations()); }
    catch (e) { byId('connectInstalledBootAnimList').innerHTML = '<div class="hint warn">' + esc(e.message) + '</div>'; }
    try {
      const all = await connectFetchJson('/api/boot-animations');
      const items = all.items || [];
      byId('connectBootAnimList').innerHTML = items.length ? '<div class="connectTiles">' + items.map(connectBootAnimHtml).join('') + '</div>' : '<div class="hint">No boot animations published yet.</div>';
      renderBootAnimPreviews(byId('connectBootAnimList'));
    } catch (e) { byId('connectBootAnimList').innerHTML = '<div class="hint warn">' + esc(e.message) + '</div>'; }
  };
  const loadConnectLeaderboard = async () => {
    if (!connectIsReady()) return;
    byId('connectLeaderboardList').innerHTML = '<div class="hint">Loading leaderboard...</div>';
    try {
      const all = await connectFetchJson('/api/leaderboard');
      const items = all.items || [];
      const fmtSecs = secs => {
        secs = Number(secs || 0);
        const h = Math.floor(secs / 3600), m = Math.floor((secs % 3600) / 60);
        return h > 0 ? (h + 'h ' + m + 'm') : (m + 'm');
      };
      byId('connectLeaderboardList').innerHTML = items.length ? items.map((r, i) =>
        '<div class="leaderRow"><div class="value">#' + (i + 1) + '</div><div><div class="value">' + esc(r.printer_name || r.public_id || 'TinyMaker') + '</div><div class="meta">' + esc(r.public_id || '') + '</div></div>' +
        '<span class="pill">FW ' + esc(r.firmware_version || '-') + '</span><span class="pill">' + esc(fmtSecs(r.lifetime_print_secs)) + ' lifetime</span><span class="pill">' + esc(r.uploads || 0) + ' uploads</span><span class="pill">' + esc(r.downloads || 0) + ' downloads</span><span class="pill">' + esc(r.ratings || 0) + ' ratings</span><span class="pill">' + esc(r.bookmarks || 0) + ' bookmarks</span></div>'
      ).join('') : '<div class="hint">No printers opted in yet.</div>';
    } catch (e) { byId('connectLeaderboardList').innerHTML = '<div class="hint warn">' + esc(e.message) + '</div>'; }
  };
  const loadConnectTab = async () => {
    if (!connectIsReady()) return;
    if (connectTab === 'boot') return loadConnectBootAnimations();
    if (connectTab === 'leaderboard') return loadConnectLeaderboard();
    return loadConnectModels();
  };
  const setConnectTab = tab => {
    if (tab !== 'boot') clearBootAnimPreviews();
    connectTab = tab;
    show('connectModelsPane', tab === 'models');
    show('connectBootAnimsPane', tab === 'boot');
    show('connectLeaderboardPane', tab === 'leaderboard');
    byId('connectModelsTabButton').classList.toggle('active', tab === 'models');
    byId('connectBootAnimsTabButton').classList.toggle('active', tab === 'boot');
    byId('connectLeaderboardTabButton').classList.toggle('active', tab === 'leaderboard');
    loadConnectTab();
  };

  const render = () => {
    const root = byId('connectHostedRoot');
    if (!root || rendered) return;
    injectCss();
    rendered = true;
    root.classList.add('connectHosted');
    root.innerHTML =
      '<h2>TinyMaker Connect</h2>' +
      '<div id="connectReadyBox" class="hidden">' +
        '<div class="hint connectNoTop">Registered as <b id="connectPrinterIdValue">-</b> &middot; <a href="#" id="connectSettingsButton">Connect settings</a></div>' +
        '<div id="connectReadyHint" class="hint">This printer is ready to publish and manage TinyMaker Connect models.</div>' +
        '<div class="connectTabs">' +
          '<button id="connectModelsTabButton" type="button" class="active">Models</button>' +
          '<button id="connectBootAnimsTabButton" type="button">Boot animations</button>' +
          '<button id="connectLeaderboardTabButton" type="button">Leaderboard</button>' +
        '</div>' +
      '</div>' +
      '<div id="connectModelsPane">' +
        '<div id="connectPublishBox" class="hidden connectSection">' +
          '<h2>Share model</h2>' +
          '<div class="configGrid">' +
            '<label><span>Model name</span><input id="shareModelName" type="text" maxlength="120"></label>' +
            '<label><span>Original credits</span><input id="shareCredits" type="text" maxlength="255"></label>' +
            '<label><span>License</span><input id="shareLicense" type="text" maxlength="32" value="CC-BY-NC"></label>' +
          '</div>' +
          '<canvas id="connectPreviewCanvas" class="hidden connectShareCanvas"></canvas>' +
          '<div id="shareSteps" class="hint"></div>' +
          '<div id="shareProgress" class="storageBar hidden"><span id="shareProgressFill"></span></div>' +
          '<button id="shareUploadButton" class="hidden" type="button">Upload model</button>' +
        '</div>' +
        '<div id="connectBrowserBox" class="hidden connectSection">' +
          '<h2>Models</h2>' +
          '<div class="hint">Shared models can be downloaded from TinyMaker Connect. To publish one of your own models, open SD manager, press Details, then Share model.</div>' +
          '<div class="connectFilters">' +
            '<button id="connectPreview05Button" type="button" class="active">Show 0.05 mm</button>' +
            '<button id="connectPreview1Button" type="button">Show 0.10 mm</button>' +
          '</div>' +
          '<div id="connectModelsList" class="files connectMt10"></div>' +
        '</div>' +
        '<div id="connectManagerBox" class="hidden connectSection">' +
          '<h2>Manager</h2>' +
          '<div class="hint">Models shared by this printer can be hidden, republished, or removed here.</div>' +
          '<div id="connectMineList" class="files connectMt10"></div>' +
        '</div>' +
      '</div>' +
      '<div id="connectBootAnimsPane" class="hidden connectSection">' +
        '<h2>Boot animations</h2>' +
        '<div class="hint">Install community boot animations to the SD card. The installed animation becomes the active power-on animation.</div>' +
        '<h2 class="connectSubhead">Installed</h2>' +
        '<div id="connectInstalledBootAnimList" class="files connectMt10"></div>' +
        '<div id="connectBootAnimShuffleBox" class="actions hidden connectMt10">' +
          '<button id="connectBootAnimShuffleButton" class="button secondary" type="button">Shuffle installed</button>' +
        '</div>' +
        '<h2 class="connectSubhead">Connect library</h2>' +
        '<div id="connectBootAnimList" class="files connectMt10"></div>' +
      '</div>' +
      '<div id="connectLeaderboardPane" class="hidden connectSection">' +
        '<h2>Leaderboard</h2>' +
        '<div class="hint">Shows printers that opted in to public leaderboard stats.</div>' +
        '<div id="connectLeaderboardList" class="leaderRows connectMt10"></div>' +
      '</div>' +
      '<div id="connectSetupBox">' +
        '<div class="hint">TinyMaker Connect is not configured on this printer yet.</div>' +
        '<div class="grid connectMt12">' +
          '<div><div class="label">Step 1</div><div class="value">Enable Connect</div></div>' +
          '<div><div class="label">Step 2</div><div class="value">Test server</div></div>' +
          '<div><div class="label">Step 3</div><div class="value">Register printer</div></div>' +
          '<div><div class="label">Optional</div><div class="value">Leaderboard sharing</div></div>' +
        '</div>' +
        '<div id="connectSetupHint" class="hint">Setup uses the default TinyMaker Connect server. You can change the server URL before registering.</div>' +
        '<button id="connectSetupButton" type="button">Set up TinyMaker Connect</button>' +
      '</div>';

    bind('connectModelsTabButton', 'click', () => setConnectTab('models'));
    bind('connectBootAnimsTabButton', 'click', () => setConnectTab('boot'));
    bind('connectLeaderboardTabButton', 'click', () => setConnectTab('leaderboard'));
    bind('connectPreview05Button', 'click', () => setConnectPreviewMode('05'));
    bind('connectPreview1Button', 'click', () => setConnectPreviewMode('1'));
    bind('connectBootAnimShuffleButton', 'click', () => connectActivateBootAnim('__shuffle'));
    bind('shareUploadButton', 'click', uploadSharedModel);
    bind('connectSetupButton', 'click', async () => {
      openView('config');
      setSettingsTab('network');
      await loadConfig();
      if (configIsLocallyLocked() || (statusData && statusData.webControl === false)) return;
      byId('cfgConnectEnabled').checked = true;
      updateConnectFields();
      if (!byId('cfgConnectBaseUrl').value) byId('cfgConnectBaseUrl').value = 'https://connect.tinymakerwifi.com';
      byId('connectFields').scrollIntoView({ behavior: 'smooth', block: 'start' });
      byId('cfgConnectBaseUrl').focus();
      msg('Check the Connect settings, then test the server and register.');
    });
    bind('connectSettingsButton', 'click', async e => {
      e.preventDefault();
      openView('config');
      setSettingsTab('network');
      await loadConfig();
      updateConnectFields();
      const target = byId('connectFields').classList.contains('hidden') ? byId('cfgConnectEnabled').parentElement : byId('connectFields');
      target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
    setConnectPreviewMode(connectPreviewMode);
  };

  window.TinyMakerConnectHostedUpdate = c => {
    render();
    c = c || connectConfig || {};
    const id = c.connectPrinterPublicId || '';
    const registered = !!id;
    const tokenMissing = registered && !connectIsReady();
    show('connectReadyBox', registered);
    show('connectModelsPane', registered && connectTab === 'models');
    show('connectBootAnimsPane', registered && connectTab === 'boot');
    show('connectLeaderboardPane', registered && connectTab === 'leaderboard');
    show('connectBrowserBox', registered);
    show('connectManagerBox', registered);
    show('connectSetupBox', !registered);
    if (registered) {
      setText('connectPrinterIdValue', id);
      byId('connectReadyHint').textContent = !c.connectEnabled
        ? 'Connect is disabled in Settings - enable it to publish or sync.'
        : (tokenMissing
          ? 'This printer is registered on Connect, but the local publish token is missing. Reclaim the profile with your recovery code or set it up as a new printer in Settings.'
          : 'This printer is ready to publish and manage TinyMaker Connect models.');
      if (tokenMissing) {
        byId('connectMineList').innerHTML = '<div class="hint warn">Manager needs the local Connect publish token. Open Settings > Connect and reclaim this printer with your recovery code, or set it up as a new printer.</div>';
      }
    } else {
      byId('connectSetupHint').textContent = c.connectLastStatus
        ? ('Last status: ' + c.connectLastStatus)
        : 'Setup uses the default TinyMaker Connect server. You can change the server URL before registering.';
    }
    byId('connectSetupButton').disabled = !!(statusData && statusData.busy) || !!(statusData && statusData.webControl === false);
  };

  window.TinyMakerConnectLoadTab = loadConnectTab;
  window.TinyMakerConnectSetTab = setConnectTab;
  window.TinyMakerConnectClearBootAnimPreviews = clearBootAnimPreviews;
  window.TinyMakerConnectHostedReady = true;
  render();
  if (typeof connectConfig !== 'undefined') window.TinyMakerConnectHostedUpdate(connectConfig);
})();

const $ = selector => document.querySelector(selector);

const log = message => {
  const el = $('#flashLog');
  if (!el) return;
  el.textContent += `${message}\n`;
  el.scrollTop = el.scrollHeight;
};

const setProgress = pct => {
  const bar = $('#flashProgressBar');
  const label = $('#flashProgressLabel');
  const value = Math.max(0, Math.min(100, pct || 0));
  if (bar) bar.style.width = `${value}%`;
  if (label) label.textContent = `${Math.round(value)}%`;
};

const setBusy = busy => {
  ['flashConnectButton', 'flashLatestButton', 'flashLocalButton'].forEach(id => {
    const el = $(`#${id}`);
    if (el) el.disabled = busy;
  });
};

const terminal = {
  clean() {
    const el = $('#flashLog');
    if (el) el.textContent = '';
  },
  writeLine(data) {
    log(String(data));
  },
  write(data) {
    const el = $('#flashLog');
    if (!el) return;
    el.textContent += String(data);
    el.scrollTop = el.scrollHeight;
  },
};

let device = null;
let transport = null;
let loader = null;

const bytesToBinaryString = bytes => {
  let out = '';
  const chunkSize = 0x8000;
  for (let i = 0; i < bytes.length; i += chunkSize) {
    out += String.fromCharCode(...bytes.subarray(i, i + chunkSize));
  }
  return out;
};

const getEsptool = async () => {
  return import('https://esm.sh/esptool-js@0.5.0?bundle');
};

const ensureConnected = async () => {
  if (!('serial' in navigator)) {
    throw new Error('Web Serial is not available. Use Chrome or Edge over HTTPS.');
  }
  const { ESPLoader, Transport } = await getEsptool();
  if (!device) {
    log('Select the TinyMaker USB serial port...');
    device = await navigator.serial.requestPort({});
    transport = new Transport(device, true);
  }
  if (!loader) {
    const baudrate = parseInt($('#flashBaud').value || '921600', 10);
    loader = new ESPLoader({ transport, baudrate, terminal, debugLogging: false });
    const chip = await loader.main();
    log(`Connected: ${chip}`);
  }
  return loader;
};

const loadLatestFirmware = async () => {
  const url = '/api/firmware/latest-full';
  log('Downloading latest firmware-full.bin...');
  const response = await fetch(url, { cache: 'no-store' });
  if (!response.ok) throw new Error(`Firmware download failed: HTTP ${response.status}`);
  const bytes = new Uint8Array(await response.arrayBuffer());
  const version = response.headers.get('X-TinyMaker-Firmware-Version');
  log(`Downloaded${version ? ` ${version}` : ''} (${(bytes.length / 1048576).toFixed(2)} MB).`);
  return bytes;
};

const loadLocalFirmware = async () => {
  const file = $('#flashLocalFile').files[0];
  if (!file) throw new Error('Select firmware-full.bin first.');
  const bytes = new Uint8Array(await file.arrayBuffer());
  log(`Loaded ${file.name} (${(bytes.length / 1048576).toFixed(2)} MB).`);
  return bytes;
};

const flashBytes = async bytes => {
  if (!bytes || bytes.length < 1024) throw new Error('Firmware file is empty or invalid.');
  const esploader = await ensureConnected();
  setProgress(0);
  log('Writing firmware-full.bin to 0x0...');
  await esploader.writeFlash({
    fileArray: [{ data: bytesToBinaryString(bytes), address: 0x0 }],
    flashSize: 'keep',
    flashMode: 'keep',
    flashFreq: 'keep',
    eraseAll: false,
    compress: true,
    reportProgress: (_fileIndex, written, total) => setProgress(total ? (written / total) * 100 : 0),
  });
  if (typeof esploader.after === 'function') {
    await esploader.after();
  } else if (transport) {
    try {
      await transport.setDTR(false);
      await new Promise(resolve => setTimeout(resolve, 100));
      await transport.setDTR(true);
    } catch (_error) {
      log('Flash written. Automatic reset was not available; power-cycle the printer if it does not reboot.');
    }
  }
  setProgress(100);
  log('Flash complete. The printer should reboot now.');
  await disconnectSerial();
};

const disconnectSerial = async () => {
  if (transport) {
    try {
      await transport.disconnect();
      log('Serial connection closed.');
    } catch (_error) {
      log('Flash complete. You can unplug USB if the browser still shows the port as busy.');
    }
  }
  device = null;
  transport = null;
  loader = null;
};

const runFlash = async source => {
  setBusy(true);
  try {
    const bytes = source === 'local' ? await loadLocalFirmware() : await loadLatestFirmware();
    await flashBytes(bytes);
  } catch (error) {
    log(`ERROR: ${error && error.message ? error.message : String(error)}`);
  } finally {
    setBusy(false);
  }
};

const init = () => {
  const unsupported = $('#flashUnsupported');
  if (unsupported) unsupported.classList.toggle('hidden', 'serial' in navigator);
  $('#flashConnectButton')?.addEventListener('click', async () => {
    setBusy(true);
    try {
      await ensureConnected();
    } catch (error) {
      log(`ERROR: ${error && error.message ? error.message : String(error)}`);
    } finally {
      setBusy(false);
    }
  });
  $('#flashLatestButton')?.addEventListener('click', () => runFlash('latest'));
  $('#flashLocalButton')?.addEventListener('click', () => runFlash('local'));
};

init();

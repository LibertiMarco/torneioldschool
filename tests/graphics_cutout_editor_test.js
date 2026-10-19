// Run with node; needs Chromium (or CHROMIUM_BIN), no npm dependencies.
const fs = require('fs');
const os = require('os');
const path = require('path');
const {spawnSync} = require('child_process');
const {pathToFileURL} = require('url');
const assert = require('assert');
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'cutout-editor-'));
const asset = name => pathToFileURL(path.resolve(__dirname, '../api/' + name)).href;
const html = `<!doctype html><meta charset="utf-8">
<link rel="stylesheet" href="${asset('grafiche_scontorno_editor.css')}">
<script src="${asset('grafiche_scontorno_editor.js')}"></script>
<pre id="result">RUNNING</pre><script>
(async () => {
  const check = (value, message) => {if (!value) throw new Error(message);};
  const original = document.createElement('canvas');original.width = original.height = 100;
  original.getContext('2d').fillRect(0, 0, 100, 100);
  const mask = document.createElement('canvas');mask.width = mask.height = 100;
  const ctx = mask.getContext('2d');ctx.fillRect(0, 0, 100, 100);
  const alpha = (x, y) => ctx.getImageData(x, y, 1, 1).data[3];
  let result = GraphicsCutoutEditor.refine(original, mask);
  let dialog = document.querySelector('dialog'), canvas = dialog.querySelector('canvas');
  check(dialog.open, 'Preview dialog did not open');
  check(getComputedStyle(canvas).touchAction === 'none', 'Touch drawing would scroll');
  check(dialog.getBoundingClientRect().width <= innerWidth, 'Dialog overflows mobile viewport');
  // Synthetic pointer events do not register with native pointer capture.
  // Exercise the drawing path, while keeping capture lifecycle deterministic.
  let captured = null;
  canvas.setPointerCapture = id => {captured = id;};
  canvas.hasPointerCapture = id => captured === id;
  canvas.releasePointerCapture = () => {captured = null;};
  const pointer = (type, x, y, id = 1) => {
    const rect = canvas.getBoundingClientRect();
    canvas.dispatchEvent(new PointerEvent(type, {pointerId:id, isPrimary:true, button:0,
      clientX:rect.left + x * rect.width / 100, clientY:rect.top + y * rect.height / 100, bubbles:true}));
  };
  dialog.querySelector('[data-size]').value = 20;
  pointer('pointerdown', 20, 50);pointer('pointermove', 80, 50);pointer('pointerup', 80, 50);
  check(alpha(20, 50) === 0 && alpha(50, 50) === 0 && alpha(80, 50) === 0, 'Gomma left gaps in stroke');
  check(alpha(50, 10) === 255, 'Gomma changed pixels outside the stroke');
  check(captured === null, 'Pointer capture leaked');
  dialog.querySelector('[data-undo]').click();
  check(alpha(50, 50) === 255, 'Undo did not restore mask');
  pointer('pointerdown', 50, 50);pointer('pointerup', 50, 50);
  dialog.querySelector('[data-tool]').value = 'restore';
  pointer('pointerdown', 50, 50);pointer('pointerup', 50, 50);
  check(alpha(50, 50) === 255, 'Recupera failed');
  dialog.querySelector('[data-tool]').value = 'erase';
  pointer('pointerdown', 50, 50);pointer('pointercancel', 50, 50);
  check(captured === null, 'Cancelled touch retained capture');
  dialog.querySelector('[data-reset]').click();
  check(alpha(50, 50) === 255, 'Reset failed');
  dialog.querySelector('[data-undo]').click();
  check(alpha(50, 50) === 0, 'Reset could not be undone');
  dialog.querySelector('[data-apply]').click();
  check(await result === true, 'Apply failed');
  check(!document.querySelector('dialog'), 'Dialog leaked after apply');
  check(alpha(50, 50) === 0, 'Apply discarded mask edits');
  result = GraphicsCutoutEditor.refine(original, mask);
  document.querySelector('[data-cancel]').click();
  check(await result === false, 'Cancel applied edits');
  result = GraphicsCutoutEditor.refine(original, mask);
  document.querySelector('dialog').dispatchEvent(new Event('cancel', {cancelable:true}));
  check(await result === false, 'Escape applied edits');
  check(!document.querySelector('dialog'), 'Dialog leaked after cancellation');
  document.querySelector('#result').textContent = 'PASS: erase, continuous strokes, restore, undo, reset, touch cancel, apply, cancel, Escape, cleanup';
})().catch(error => {document.querySelector('#result').textContent = 'FAIL: ' + error.message;});
</script>`;
try {
  const file = path.join(dir, 'test.html');fs.writeFileSync(file, html);
  for (const width of [390, 1280]) {
    const run = spawnSync(process.env.CHROMIUM_BIN || 'chromium', [
      '--headless', '--no-sandbox', '--disable-gpu', '--no-zygote', '--single-process', '--disable-dev-shm-usage', '--allow-file-access-from-files',
      '--user-data-dir=' + path.join(dir, 'profile-' + width), '--disable-extensions', '--window-size=' + width + ',900', '--dump-dom', '--timeout=15000', pathToFileURL(file).href
    ], {encoding:'utf8', timeout:25000});
    if (run.error) throw run.error;
    assert.strictEqual(run.status, 0, run.stderr);
    const status = run.stdout.match(/<pre id="result">([^<]+)<\/pre>/)?.[1];
    assert(status?.startsWith('PASS:'), status || run.stdout);
    console.log(width + 'px: ' + status);
  }
} finally {fs.rmSync(dir, {recursive:true, force:true});}

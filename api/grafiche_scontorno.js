/* Person instances and soft matting. Photos stay on the device. */
'use strict';
const GraphicsCutout = (() => {
  const workerUrl = new URL('grafiche_scontorno_worker.js?v=20261020-speed-5', document.currentScript.src);
  let worker = null, pending = null, serial = 0, idleTimer = null;
  let cached = null;

  function dispose() {
    clearTimeout(idleTimer);
    if (worker) worker.terminate();
    worker = null;
  }
  function infer(data, width, height, subjects, onProgress) {
    if (pending) return Promise.reject(new Error('Attendi la rimozione sfondo in corso.'));
    clearTimeout(idleTimer);
    return new Promise((resolve, reject) => {
      const id = ++serial;
      const finish = (error, result) => {
        if (pending?.id !== id) return;
        clearTimeout(pending.timer);pending = null;
        if (error) { dispose();reject(error); }
        else { dispose();resolve(result); }
      };
      pending = {id, timer:setTimeout(() => finish(new Error('La rimozione sta impiegando troppo. Controlla la connessione e riprova.')), 300000)};
      try {
        if (!worker) worker = new Worker(workerUrl);
        worker.onmessage = event => {
          if (event.data.id !== id) return;
          if (event.data.progress) { if (onProgress) onProgress(event.data.progress);return; }
          finish(event.data.error ? new Error(event.data.error) : null, event.data);
        };
        worker.onerror = event => { event.preventDefault();finish(new Error('Impossibile avviare la rimozione sfondo. Controlla la connessione e riprova.')); };
        worker.postMessage({id, data, width, height, subjects}, [data.buffer]);
      } catch (error) { finish(error); }
    });
  }
  function geometry(width, height) {
    if (!(Number.isFinite(width) && Number.isFinite(height) && width > 0 && height > 0)) throw new Error('Dimensioni della foto non valide.');
    // Fixed model input; letterboxing keeps people and clothing proportional.
    const side = 1024;
    const scale = side / Math.max(width, height);
    const dw = width * scale, dh = height * scale;
    const w = side, h = side;
    return {width:w, height:h, x:(w - dw) / 2, y:(h - dh) / 2, dw, dh};
  }
  function cleanMatte(alpha, width, height) {
    if (alpha.length !== width * height) throw new Error('Maschera di ritaglio non valida.');
    const output = new Uint8ClampedArray(alpha.length);
    for (let p = 0; p < alpha.length; p++) output[p] = Number.isFinite(alpha[p]) ? Math.round(Math.max(0, Math.min(1, alpha[p])) * 255) : 0;
    return output;
  }
  function maskFromMatte(original, result, g, subjects = 'all') {
    if (result.width !== g.width || result.height !== g.height || result.alpha.length !== g.width * g.height) throw new Error('Maschera di ritaglio non valida.');
    const small = document.createElement('canvas'), mask = document.createElement('canvas');
    small.width = g.width;small.height = g.height;
    mask.width = original.naturalWidth;mask.height = original.naturalHeight;
    try {
      const ctx = small.getContext('2d'), pixels = ctx.createImageData(g.width, g.height);
      const alpha = cleanMatte(result.alpha, g.width, g.height, subjects);
      if (!alpha.some(value => value > 128)) throw new Error('Non è stato possibile riconoscere il soggetto. Prova una foto più ravvicinata.');
      for (let p = 0; p < alpha.length; p++) {
        const i = p * 4;
        pixels.data[i] = pixels.data[i + 1] = pixels.data[i + 2] = 255;
        pixels.data[i + 3] = alpha[p];
      }
      ctx.putImageData(pixels, 0, 0);
      const output = mask.getContext('2d');output.imageSmoothingEnabled = true;output.imageSmoothingQuality = 'high';
      output.drawImage(small, g.x, g.y, g.dw, g.dh, 0, 0, mask.width, mask.height);
      return mask;
    } catch (error) { mask.width = mask.height = 1;throw error; }
    finally { small.width = small.height = 1; }
  }
  async function createMask(original, {subjects = 'main', onProgress = null} = {}) {
    const g = geometry(original.naturalWidth, original.naturalHeight);
    // Cache the automatic alpha, never the mask modified by the editor.
    // Retain only one result (4 MiB), without keeping the decoded photo alive.
    if (cached?.original.deref() === original && cached.subjects === subjects) {
      return maskFromMatte(original, cached.result, g, subjects);
    }
    cached = null;
    const canvas = document.createElement('canvas');
    canvas.width = g.width;canvas.height = g.height;
    let data;
    try {
      const ctx = canvas.getContext('2d', {willReadFrequently:true});
      ctx.fillStyle = '#808080';ctx.fillRect(0, 0, g.width, g.height);
      ctx.drawImage(original, g.x, g.y, g.dw, g.dh);
      const rgba = ctx.getImageData(0, 0, g.width, g.height).data;
      const size = g.width * g.height;
      data = new Float32Array(size * 3);
      for (let p = 0; p < size; p++) for (let c = 0; c < 3; c++) data[c * size + p] = rgba[p * 4 + c] / 255;
    } finally { canvas.width = canvas.height = 1; }
    const result = await infer(data, g.width, g.height, subjects, onProgress);
    const mask = maskFromMatte(original, result, g, subjects);
    if (typeof WeakRef !== 'undefined') cached = {original:new WeakRef(original), subjects, result};
    return mask;
  }
  return {createMask, geometry, cleanMatte, maskFromMatte};
})();


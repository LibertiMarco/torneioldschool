/* MODNet portrait matting. Photos stay on the device; inference runs in a worker. */
'use strict';
const GraphicsCutout = (() => {
  const workerUrl = new URL('grafiche_scontorno_worker.js?v=20260927-modnet', document.currentScript.src);
  let worker = null, pending = null, serial = 0, idleTimer = null;

  function dispose() {
    clearTimeout(idleTimer);
    if (worker) worker.terminate();
    worker = null;
  }
  function infer(data, width, height) {
    if (pending) return Promise.reject(new Error('Attendi la rimozione sfondo in corso.'));
    clearTimeout(idleTimer);
    return new Promise((resolve, reject) => {
      const id = ++serial;
      const finish = (error, result) => {
        if (pending?.id !== id) return;
        clearTimeout(pending.timer);pending = null;
        if (error) { dispose();reject(error); }
        else { idleTimer = setTimeout(dispose, 90000);resolve(result); }
      };
      pending = {id, timer:setTimeout(() => finish(new Error('La rimozione sta impiegando troppo. Controlla la connessione e riprova.')), 120000)};
      try {
        if (!worker) worker = new Worker(workerUrl);
        worker.onmessage = event => {
          if (event.data.id !== id) return;
          finish(event.data.error ? new Error(event.data.error) : null, event.data);
        };
        worker.onerror = event => { event.preventDefault();finish(new Error('Impossibile avviare la rimozione sfondo. Controlla la connessione e riprova.')); };
        worker.postMessage({id, data, width, height}, [data.buffer]);
      } catch (error) { finish(error); }
    });
  }
  function geometry(width, height) {
    // Preserve the aspect ratio and pad to multiples of 32 for MODNet.
    const scale = Math.min(512 / Math.min(width, height), 768 / Math.max(width, height));
    const dw = width * scale, dh = height * scale;
    const w = Math.max(32, Math.ceil(dw / 32) * 32), h = Math.max(32, Math.ceil(dh / 32) * 32);
    return {width:w, height:h, x:(w - dw) / 2, y:(h - dh) / 2, dw, dh};
  }
  function cleanMatte(alpha, width, height) {
    // Remove detached, uncertain silhouettes, keeping ALL confident subjects.
    const out = new Uint8ClampedArray(alpha.length), seen = new Uint8Array(alpha.length);
    const queue = new Int32Array(alpha.length);
    for (let start = 0; start < alpha.length; start++) {
      if (seen[start] || !(alpha[start] > .08)) continue;
      let head = 0, tail = 1, solid = 0;
      queue[0] = start;seen[start] = 1;
      while (head < tail) {
        const p = queue[head++], x = p % width, y = Math.floor(p / width);
        if (alpha[p] >= .85) solid++;
        for (let dy = -1; dy <= 1; dy++) for (let dx = -1; dx <= 1; dx++) {
          if ((!dx && !dy) || x + dx < 0 || x + dx >= width || y + dy < 0 || y + dy >= height) continue;
          const q = p + dy * width + dx;
          if (!seen[q] && alpha[q] > .08) { seen[q] = 1;queue[tail++] = q; }
        }
      }
      if (solid < 4) continue;
      for (let i = 0; i < tail; i++) {
        const p = queue[i];
        const value = Math.max(0, Math.min(1, (alpha[p] - .08) / .88));
        out[p] = Math.round(value * 255);
      }
    }
    return out;
  }
  function maskFromMatte(original, result, g) {
    if (result.width !== g.width || result.height !== g.height || result.alpha.length !== g.width * g.height) throw new Error('Maschera di ritaglio non valida.');
    const small = document.createElement('canvas'), mask = document.createElement('canvas');
    small.width = g.width;small.height = g.height;
    mask.width = original.naturalWidth;mask.height = original.naturalHeight;
    try {
      const ctx = small.getContext('2d'), pixels = ctx.createImageData(g.width, g.height);
      const alpha = cleanMatte(result.alpha, g.width, g.height);
      if (!alpha.some(value => value > 128)) throw new Error('Non è stato possibile riconoscere una persona. Prova una foto più ravvicinata.');
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
  async function createMask(original) {
    const g = geometry(original.naturalWidth, original.naturalHeight), canvas = document.createElement('canvas');
    canvas.width = g.width;canvas.height = g.height;
    let data;
    try {
      const ctx = canvas.getContext('2d', {willReadFrequently:true});
      ctx.fillStyle = '#808080';ctx.fillRect(0, 0, g.width, g.height);
      ctx.drawImage(original, g.x, g.y, g.dw, g.dh);
      const rgba = ctx.getImageData(0, 0, g.width, g.height).data, size = g.width * g.height;
      data = new Float32Array(size * 3);
      for (let p = 0; p < size; p++) for (let c = 0; c < 3; c++) data[c * size + p] = rgba[p * 4 + c] / 127.5 - 1;
    } finally { canvas.width = canvas.height = 1; }
    const result = await infer(data, g.width, g.height);
    return maskFromMatte(original, result, g);
  }
  return {createMask, geometry, cleanMatte, maskFromMatte};
})();

/* Local mask refinement, including people touching the foreground subject. */
'use strict';
const GraphicsCutoutEditor = (() => {
  function refine(original, mask) {
    const dialog = document.createElement('dialog');
    dialog.className = 'cutout-editor';
    dialog.setAttribute('aria-labelledby', 'cutout-title');
    dialog.innerHTML = `<h2 id="cutout-title">Rifinisci lo scontorno</h2>
      <p>Passa la gomma sulle persone rimaste dietro. Usa Recupera per riprendere parti della foto originale. Su telefono puoi usare il dito.</p>
      <div class="cutout-tools">
        <label>Strumento<select data-tool><option value="erase">Gomma</option><option value="restore">Recupera</option></select></label>
        <label>Dimensione pennello<input data-size type="range" min="1" max="20" value="5"></label>
        <button type="button" data-undo disabled>Annulla tratto</button>
        <button type="button" data-reset>Riparti dallo scontorno automatico</button>
      </div>
      <canvas aria-label="Foto da rifinire con il dito o il mouse"></canvas>
      <div class="cutout-tools"><button type="button" data-apply>Applica scontorno</button><button type="button" data-cancel>Annulla</button></div>`;
    const preview = dialog.querySelector('canvas'), ctx = preview.getContext('2d');
    const brush = mask.getContext('2d', {willReadFrequently:true});
    const initial = document.createElement('canvas');
    preview.width = initial.width = mask.width;
    preview.height = initial.height = mask.height;
    initial.getContext('2d').drawImage(mask, 0, 0);
    let pointer = null, last = null, undo = null, frame = null;
    const draw = () => {
      ctx.clearRect(0, 0, preview.width, preview.height);
      ctx.globalCompositeOperation = 'source-over';
      ctx.drawImage(original, 0, 0, preview.width, preview.height);
      ctx.globalCompositeOperation = 'destination-in';
      ctx.drawImage(mask, 0, 0);
      ctx.globalCompositeOperation = 'source-over';
    };
    const redraw = () => {
      if (frame !== null) return;
      frame = requestAnimationFrame(() => {frame = null;draw();});
    };
    const stop = () => {
      const id = pointer;pointer = null;last = null;
      if (id !== null && preview.hasPointerCapture(id)) preview.releasePointerCapture(id);
    };
    const paint = event => {
      const rect = preview.getBoundingClientRect();
      const point = {x:(event.clientX - rect.left) * mask.width / rect.width, y:(event.clientY - rect.top) * mask.height / rect.height};
      const size = Number(dialog.querySelector('[data-size]').value) / 100 * Math.min(mask.width, mask.height);
      brush.save();
      brush.globalCompositeOperation = dialog.querySelector('[data-tool]').value === 'erase' ? 'destination-out' : 'source-over';
      brush.fillStyle = brush.strokeStyle = '#fff';brush.lineWidth = size;brush.lineCap = 'round';
      if (last) {
        brush.beginPath();brush.moveTo(last.x, last.y);brush.lineTo(point.x, point.y);brush.stroke();
      } else {
        brush.beginPath();brush.arc(point.x, point.y, size / 2, 0, Math.PI * 2);brush.fill();
      }
      brush.restore();last = point;redraw();event.preventDefault();
    };
    preview.addEventListener('pointerdown', event => {
      if (pointer !== null || event.button > 0 || event.isPrimary === false) return;
      undo = brush.getImageData(0, 0, mask.width, mask.height);
      dialog.querySelector('[data-undo]').disabled = false;
      pointer = event.pointerId;preview.setPointerCapture(pointer);paint(event);
    });
    preview.addEventListener('pointermove', event => {if (pointer === event.pointerId) paint(event);});
    for (const type of ['pointerup', 'pointercancel', 'lostpointercapture']) {
      preview.addEventListener(type, event => {if (pointer === event.pointerId) stop();});
    }
    dialog.querySelector('[data-undo]').addEventListener('click', () => {
      if (!undo) return;
      stop();brush.putImageData(undo, 0, 0);undo = null;
      dialog.querySelector('[data-undo]').disabled = true;redraw();
    });
    dialog.querySelector('[data-reset]').addEventListener('click', () => {
      stop();undo = brush.getImageData(0, 0, mask.width, mask.height);
      dialog.querySelector('[data-undo]').disabled = false;
      brush.clearRect(0, 0, mask.width, mask.height);brush.drawImage(initial, 0, 0);redraw();
    });
    return new Promise((resolve, reject) => {
      let finished = false;
      const finish = (accepted, error = null) => {
        if (finished) return;
        finished = true;stop();
        if (frame !== null) cancelAnimationFrame(frame);
        undo = null;dialog.remove();preview.width = preview.height = initial.width = initial.height = 1;
        if (error) reject(error);
        else resolve(accepted);
      };
      dialog.querySelector('[data-apply]').addEventListener('click', () => finish(true));
      dialog.querySelector('[data-cancel]').addEventListener('click', () => finish(false));
      dialog.addEventListener('cancel', event => {event.preventDefault();finish(false);});
      dialog.addEventListener('close', () => finish(false));
      try {document.body.append(dialog);draw();dialog.showModal();}
      catch (error) {finish(false, error);}
    });
  }
  return {refine};
})();

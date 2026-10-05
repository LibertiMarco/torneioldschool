const tournament = document.getElementById('torneo');
const team = document.getElementById('team');
const controls = document.getElementById('image-controls');
const imageInput = document.getElementById('image');
const preview = document.getElementById('image-preview');
const statusText = document.getElementById('ocr-status');
const ocrButton = document.getElementById('ocr');
function filterTeams(reset = false) {
    if (reset) team.value = '';
    for (const option of team.options) {
        const unavailable = !!option.value && option.dataset.torneo !== tournament.value;
        option.hidden = unavailable; option.disabled = unavailable;
    }
    team.disabled = !tournament.value;
    controls.disabled = !team.value;
}
tournament.addEventListener('change', () => filterTeams(true));
team.addEventListener('change', () => filterTeams());
filterTeams();
imageInput.addEventListener('change', () => {
    preview.hidden = true;
    const file = imageInput.files[0];
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 20 * 1024 * 1024) {
        statusText.textContent = 'Scegli un JPEG, PNG o WebP fino a 20 MB.'; imageInput.value = ''; return;
    }
    const reader = new FileReader();
    reader.onload = () => { preview.src = reader.result; preview.hidden = false; };
    reader.readAsDataURL(file);
    statusText.textContent = 'Immagine pronta per la lettura.';
});
document.querySelectorAll('input[name$="[skip]"]').forEach(input => {
    input.addEventListener('change', () => {
        input.closest('tr').querySelectorAll('input[type="text"]').forEach(field => { field.required = !input.checked; });
    });
});
document.querySelectorAll('.import-suggestion').forEach(button => {
    button.addEventListener('click', () => {
        const row = button.closest('tr');
        row.querySelector('input[name$="[nome]"]').value = button.dataset.nome;
        row.querySelector('input[name$="[cognome]"]').value = button.dataset.cognome;
        const confirmation = row.querySelector('input[name$="[confirm_new]"]');
        if (confirmation) confirmation.checked = false;
        row.closest('form').requestSubmit();
    });
});
document.querySelectorAll('input[name$="[nome]"], input[name$="[cognome]"]').forEach(input => {
    input.addEventListener('input', () => {
        const confirmation = input.closest('tr').querySelector('input[name$="[confirm_new]"]');
        if (confirmation) confirmation.checked = false;
    });
});

async function prepareOcrImage(file) {
    const url = URL.createObjectURL(file);
    try {
        const image = new Image();
        image.src = url;
        await image.decode();
        // Enlarge small text without allocating an unbounded canvas for phone photos.
        const scale = Math.min(2, 2800 / image.naturalWidth, 2800 / image.naturalHeight);
        const width = Math.max(1, Math.round(image.naturalWidth * scale));
        const height = Math.max(1, Math.round(image.naturalHeight * scale));
        const canvas = document.createElement('canvas');
        canvas.width = width + 48; canvas.height = height + 48;
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.imageSmoothingEnabled = true; ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(image, 24, 24, width, height);
        const pixels = ctx.getImageData(24, 24, width, height);
        const histogram = new Uint32Array(256);
        for (let i = 0; i < pixels.data.length; i += 4) {
            const gray = Math.round(.299 * pixels.data[i] + .587 * pixels.data[i + 1] + .114 * pixels.data[i + 2]);
            pixels.data[i] = pixels.data[i + 1] = pixels.data[i + 2] = gray;
            histogram[gray]++;
        }
        const total = width * height;
        let low = 0, high = 255, count = 0;
        for (let i = 0; i < 256; i++) { count += histogram[i]; if (count >= total * .01) { low = i; break; } }
        count = 0;
        for (let i = 255; i >= 0; i--) { count += histogram[i]; if (count >= total * .01) { high = i; break; } }
        // Preserve shades instead of a hard threshold that could erase thin letters.
        if (high - low > 30) {
            for (let i = 0; i < pixels.data.length; i += 4) {
                const gray = Math.max(0, Math.min(255, Math.round((pixels.data[i] - low) * 255 / (high - low))));
                pixels.data[i] = pixels.data[i + 1] = pixels.data[i + 2] = gray;
            }
        }
        ctx.putImageData(pixels, 24, 24);
        return canvas;
    } finally { URL.revokeObjectURL(url); }
}
ocrButton.addEventListener('click', async () => {
    const file = imageInput.files[0];
    if (!file) { statusText.textContent = 'Seleziona prima una foto.'; return; }
    ocrButton.disabled = true;
    let worker;
    try {
        statusText.textContent = 'Caricamento del riconoscimento testo…';
        worker = await Tesseract.createWorker('ita+eng', 1, { logger: m => {
            if (m.status === 'recognizing text') statusText.textContent = `Lettura immagine: ${Math.round(m.progress * 100)}%`;
        }});
        await worker.setParameters({ tessedit_pageseg_mode: '6', preserve_interword_spaces: '1', user_defined_dpi: '300' });
        let source = file;
        if (document.getElementById('enhance-image').checked) {
            try { source = await prepareOcrImage(file); } catch (error) { source = file; }
        }
        const result = await worker.recognize(source);
        document.getElementById('text').value = result.data.text;
        statusText.textContent = 'Lettura completata. Controlla il testo e mostra l’anteprima.';
    } catch (error) {
        statusText.textContent = 'Lettura non riuscita. Riprova o incolla i nomi nel campo testo.';
    } finally {
        try { if (worker) await worker.terminate(); } finally { ocrButton.disabled = false; }
    }
});

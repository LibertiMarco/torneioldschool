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
        const result = await worker.recognize(file);
        document.getElementById('text').value = result.data.text;
        statusText.textContent = 'Lettura completata. Controlla il testo e mostra l’anteprima.';
    } catch (error) {
        statusText.textContent = 'Lettura non riuscita. Riprova o incolla i nomi nel campo testo.';
    } finally {
        try { if (worker) await worker.terminate(); } finally { ocrButton.disabled = false; }
    }
});

/* Shared PNG download and iOS sharing for graphics generators. */
window.GraphicsDownloads = (() => {
const isIPhoneSafari = /iP(hone|ad|od)/.test(navigator.userAgent) && /Safari/.test(navigator.userAgent) && !/CriOS|FxiOS|EdgiOS/.test(navigator.userAgent);
const canvasToBlob = canvas => new Promise((resolve,reject) => canvas.toBlob(
  blob => blob ? resolve(blob) : reject(new Error('Impossibile creare il file PNG.')),
  'image/png'
));
async function saveImage(item) {
  let fallbackWindow=null;
  if(isIPhoneSafari && !navigator.share) fallbackWindow=window.open('about:blank','_blank');
  try {
    const blob=await canvasToBlob(item.canvas);
    const file=new File([blob],item.name,{type:'image/png'});
    if(navigator.share && (!navigator.canShare || navigator.canShare({files:[file]}))) {
      await navigator.share({files:[file],title:item.name});
      return;
    }
    const url=URL.createObjectURL(blob);
    if(fallbackWindow) {
      fallbackWindow.location.href=url;
      setTimeout(()=>URL.revokeObjectURL(url),60000);
      return;
    }
    const a=document.createElement('a'); a.download=item.name; a.href=url; document.body.appendChild(a); a.click(); a.remove();
    setTimeout(()=>URL.revokeObjectURL(url),2000);
  } catch(error) {
    if(fallbackWindow) fallbackWindow.close();
    if(error?.name!=='AbortError') throw error;
  }
}
async function saveAllImages(generated, onError = () => {}) {
  if(isIPhoneSafari) {
    const files=await Promise.all(generated.map(async item => new File(
      [await canvasToBlob(item.canvas)],item.name,{type:'image/png'}
    )));
    if(navigator.share && (!navigator.canShare || navigator.canShare({files}))) {
      await navigator.share({files,title:'Grafiche Tornei Old School'});
      return;
    }
    throw new Error('Questa versione di iOS non supporta il salvataggio multiplo. Usa “Salva immagine” su ogni grafica.');
  }
  generated.forEach((item,index)=>setTimeout(
    ()=>saveImage(item).catch(error=>{onError(error)}),index*350
  ));
}
return {isIPhoneSafari, saveImage, saveAllImages, canvasToBlob};
})();

/* Local person detection, instance segmentation and matting. See TERZE_PARTI.md. */
'use strict';
importScripts('grafiche_scontorno_core.js?v=20261020-silhouette-11');
const runtimeBase='https://cdn.jsdelivr.net/npm/onnxruntime-web@1.17.3/dist/';
const modelFiles={
  detector:'rtdetr-r18-int8-ec641af.onnx',
  encoder:'slimsam-vision-d827ef0.onnx',
  decoder:'sam-decoder-b64d034.onnx',
  matte:'modnet-fa2fa546.onnx'
};
const sessions=new Map();
let runtimeReady=false;
function model(name) {
  if(!runtimeReady) {
    importScripts(runtimeBase+'ort.wasm.min.js');
    ort.env.wasm.wasmPaths=runtimeBase;ort.env.wasm.numThreads=1;ort.env.wasm.proxy=false;
    runtimeReady=true;
  }
  if(!sessions.has(name))sessions.set(name,ort.InferenceSession.create(
    new URL('models/'+modelFiles[name],self.location.href).href,
    {executionProviders:['wasm'],graphOptimizationLevel:'all',enableCpuMemArena:false,enableMemPattern:false}));
  return sessions.get(name);
}
async function releaseModel(name) {
  const pending=sessions.get(name);
  sessions.delete(name);
  if(pending)await (await pending).release();
}
function dispose(tensors) {if(tensors)Object.values(tensors).forEach(tensor=>tensor.dispose());}
async function detect(rgb,width,height,subjects) {
  const session=await model('detector');
  const input=new ort.Tensor('float32',GraphicsCutoutCore.resizeRGB(rgb,width,height,640),[1,3,640,640]);
  let output;
  try {
    output=await session.run({[session.inputNames[0]]:input});
    const {logits,pred_boxes:boxes}=output;
    if(logits?.dims.length!==3||boxes?.dims[2]!==4||logits.dims[1]!==boxes.dims[1])throw new Error('Risultato del riconoscimento non valido.');
    return GraphicsCutoutCore.selectPeople(logits.data,boxes.data,logits.dims[1],logits.dims[2],subjects);
  } finally {input.dispose();dispose(output);await releaseModel('detector');}
}
async function encode(rgb,width,height) {
  const encoder=await model('encoder'),size=width*height;
  const normalized=new Float32Array(rgb.length),mean=[.485,.456,.406],std=[.229,.224,.225];
  for(let c=0;c<3;c++)for(let p=0;p<size;p++)normalized[c*size+p]=(rgb[c*size+p]-mean[c])/std[c];
  const input=new ort.Tensor('float32',normalized,[1,3,height,width]);
  let output;
  try {
    output=await encoder.run({pixel_values:input});
    const tensor=output.image_embeddings;
    return {data:new Float32Array(tensor.data),dims:tensor.dims};
  } finally {input.dispose();dispose(output);await releaseModel('encoder');}
}
async function segment(width,height,people,embedding) {
  const size=width*height,foreground=new Uint8Array(size),background=new Uint8Array(size);
  const decoder=await model('decoder');
  const embeddings={image_embeddings:new ort.Tensor('float32',embedding.data,embedding.dims)};
  try {
    for(const person of [...people.foreground,...people.background]) {
      // Native corner labels 2/3 need the SAM box decoder; point-only exports
      // do not support them and can corrupt the resulting object mask.
      const selected=people.foreground.includes(person);
      // Negative point prompts prevent SAM from merging a selected figure
      // with a small, partially occluded person touching its outline.
      const excluded=selected?people.background:[];
      const coords=[person.left*width,person.top*height,person.right*width,person.bottom*height];
      const labels=[2,3];
      for(const other of excluded) {
        coords.push((other.left+other.right)*width/2,(other.top+other.bottom)*height/2);
        labels.push(0);
      }
      const feeds={
        image_embeddings:embeddings.image_embeddings,
        point_coords:new ort.Tensor('float32',new Float32Array(coords),[1,labels.length,2]),
        point_labels:new ort.Tensor('float32',new Float32Array(labels),[1,labels.length]),
        mask_input:new ort.Tensor('float32',new Float32Array(256*256),[1,1,256,256]),
        has_mask_input:new ort.Tensor('float32',new Float32Array([0]),[1]),
        orig_im_size:new ort.Tensor('float32',new Float32Array([height,width]),[2])
      };
      let output;
      try {
        output=await decoder.run(feeds);
        const mask=output.masks;
        if(mask?.data.length!==size||mask.dims[mask.dims.length-1]!==width||mask.dims[mask.dims.length-2]!==height)throw new Error('Maschera del soggetto non valida.');
        if(people.foreground.includes(person)) {
          for(let p=0;p<size;p++)if(mask.data[p]>0)foreground[p]=1;
        } else {
          // An excluded person's mask cannot erase distant pixels.
          for(let y=Math.max(0,Math.floor(person.top*height)-2);y<Math.min(height,Math.ceil(person.bottom*height)+2);y++) {
            for(let x=Math.max(0,Math.floor(person.left*width)-2);x<Math.min(width,Math.ceil(person.right*width)+2);x++) {
              const p=y*width+x;if(mask.data[p]>0)background[p]=1;
            }
          }
        }
      } finally {
        dispose(output);
        Object.entries(feeds).forEach(([name,tensor])=>{if(name!=='image_embeddings')tensor.dispose();});
      }
    }
    return {foreground,background};
  } finally {dispose(embeddings);await releaseModel('decoder');}
}
async function matte(rgb,width,height) {
  const session=await model('matte'),normalized=new Float32Array(rgb.length);
  for(let p=0;p<rgb.length;p++)normalized[p]=rgb[p]*2-1;
  const input=new ort.Tensor('float32',normalized,[1,3,height,width]);
  let output;
  try {
    output=await session.run({[session.inputNames[0]]:input});
    const result=output[session.outputNames[0]];
    if(result.data.length!==width*height)throw new Error('Maschera dei bordi non valida.');
    return new Float32Array(result.data);
  } finally {input.dispose();dispose(output);await releaseModel('matte');}
}
self.onmessage=async event=>{
  const {id,data,width,height,subjects='main',stage,people,embedding,masks,compact=false}=event.data;
  try {
    const side=compact?512:1024;
    if(width!==side||height!==side||!(data instanceof Float32Array)||data.length!==3*width*height||!['main','all'].includes(subjects))throw new Error('Dati della foto non validi.');
    let result;
    if(stage==='detect')result={people:await detect(data,width,height,subjects)};
    else if(stage==='encode')result={embedding:await encode(data,width,height)};
    else if(stage==='segment')result={masks:await segment(width,height,people,embedding)};
    else if(stage==='matte') {
      const detail=await matte(data,width,height);
      const alpha=compact
        ?GraphicsCutoutCore.composeCompactMatte(detail,width,height,people)
        :GraphicsCutoutCore.composeMatte(detail,masks.foreground,masks.background,width,height,5,people.foreground);
      self.postMessage({id,alpha,width,height},[alpha.buffer]);return;
    } else throw new Error('Fase di elaborazione non valida.');
    const transfers=[data.buffer];
    if(result.embedding)transfers.push(result.embedding.data.buffer);
    if(result.masks)transfers.push(result.masks.foreground.buffer,result.masks.background.buffer);
    self.postMessage({id,data,width,height,...result},transfers);
  } catch(error) {
    console.error('Graphics cutout:',error);
    self.postMessage({id,error:error instanceof Error&&/persona|valid/.test(error.message)?error.message:'Rimozione sfondo non riuscita. Controlla la connessione e riprova con una foto JPG, PNG o WebP.'});
  }
};

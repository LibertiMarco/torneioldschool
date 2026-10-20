/* Local person detection, instance segmentation and matting. See TERZE_PARTI.md. */
'use strict';
importScripts('grafiche_scontorno_core.js?v=20261020-instances-2');
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
async function segment(rgb,width,height,people) {
  const size=width*height,foreground=new Uint8Array(size),background=new Uint8Array(size);
  const encoder=await model('encoder');
  const normalized=new Float32Array(rgb.length),mean=[.485,.456,.406],std=[.229,.224,.225];
  for(let c=0;c<3;c++)for(let p=0;p<size;p++)normalized[c*size+p]=(rgb[c*size+p]-mean[c])/std[c];
  const input=new ort.Tensor('float32',normalized,[1,3,height,width]);
  let embeddings;
  try {
    let encoded;
    try {
      encoded=await encoder.run({pixel_values:input});
      // Keep only the small embedding; release the encoder before loading the decoder.
      const tensor=encoded.image_embeddings;
      embeddings={image_embeddings:new ort.Tensor('float32',new Float32Array(tensor.data),tensor.dims)};
    } finally {dispose(encoded);await releaseModel('encoder');}
    const decoder=await model('decoder');
    for(const person of [...people.foreground,...people.background]) {
      // Native corner labels 2/3 need the SAM box decoder; point-only exports
      // do not support them and can corrupt the resulting object mask.
      const feeds={
        image_embeddings:embeddings.image_embeddings,
        point_coords:new ort.Tensor('float32',new Float32Array([person.left*width,person.top*height,person.right*width,person.bottom*height]),[1,2,2]),
        point_labels:new ort.Tensor('float32',new Float32Array([2,3]),[1,2]),
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
  } finally {input.dispose();dispose(embeddings);await releaseModel('encoder');await releaseModel('decoder');}
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
  const {id,data,width,height,subjects='main'}=event.data;
  const progress=message=>self.postMessage({id,progress:message});
  try {
    const side=1024;
    if(width!==side||height!==side||!(data instanceof Float32Array)||data.length!==3*width*height||!['main','all'].includes(subjects))throw new Error('Dati della foto non validi.');
    progress('Riconoscimento delle persone in corso…');
    const people=await detect(data,width,height,subjects);
    progress('Separazione dei soggetti e delle persone sullo sfondo…');
    const masks=await segment(data,width,height,people);
    progress('Rifinitura di capelli, bordi e spazi tra i corpi…');
    const detail=await matte(data,width,height);
    const alpha=GraphicsCutoutCore.composeMatte(detail,masks.foreground,masks.background,width,height,5,people.foreground);
    self.postMessage({id,alpha,width,height},[alpha.buffer]);
  } catch(error) {
    console.error('Graphics cutout:',error);
    self.postMessage({id,error:error instanceof Error&&/persona|valid/.test(error.message)?error.message:'Rimozione sfondo non riuscita. Controlla la connessione e riprova con una foto JPG, PNG o WebP.'});
  }
};

/* ONNX Runtime (MIT), MODNet (Apache-2.0). See ../TERZE_PARTI.md. */
'use strict';
const runtimeBase = 'https://cdn.jsdelivr.net/npm/onnxruntime-web@1.17.3/dist/';
let sessionPromise = null;
function loadModel() {
  if (!sessionPromise) sessionPromise = (async () => {
    importScripts(runtimeBase + 'ort.wasm.min.js');
    ort.env.wasm.wasmPaths = runtimeBase;
    ort.env.wasm.numThreads = 1;
    ort.env.wasm.proxy = false;
    return ort.InferenceSession.create(new URL('models/modnet-fa2fa546.onnx', self.location.href).href, {executionProviders:['wasm'], graphOptimizationLevel:'all'});
  })();
  return sessionPromise;
}
self.onmessage = async event => {
  const {id, data, width, height} = event.data;
  let input = null, outputs = null;
  try {
    const session = await loadModel();
    input = new ort.Tensor('float32', data, [1, 3, height, width]);
    outputs = await session.run({[session.inputNames[0]]:input});
    const output = outputs[session.outputNames[0]], dims = output.dims;
    const alpha = new Float32Array(output.data);
    self.postMessage({id, alpha, width:dims[dims.length - 1], height:dims[dims.length - 2]}, [alpha.buffer]);
  } catch (error) {
    console.error('MODNet:', error);
    self.postMessage({id, error:'Rimozione sfondo non riuscita. Controlla la connessione e riprova con una foto JPG, PNG o WebP.'});
  } finally {
    input?.dispose();
    if (outputs) Object.values(outputs).forEach(output => output.dispose());
  }
};

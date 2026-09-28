# Componenti di terze parti

Il generatore Full Time/MVP usa MODNet per lo scontorno delle persone. Il modello viene servito dal sito e lavora in un Web Worker sul dispositivo: le foto non vengono inviate a servizi esterni.

- Modello originale: https://github.com/ZHKKKe/MODNet — codice, modelli e demo (escluse le GIF) Apache-2.0.
- Conversione ONNX di Xenova: https://huggingface.co/Xenova/modnet/tree/fa2fa546052fba4c08921230a26cc69a333fca12
- File originale, non modificato: `onnx/model.onnx`, distribuito come `api/models/modnet-fa2fa546.onnx` (25.888.640 byte).
- SHA-256: `07c308cf0fc7e6e8b2065a12ed7fc07e1de8febb7dc7839d7b7f15dd66584df9`.
- Licenza inclusa: `api/models/MODNet-LICENSE.txt`.
- ONNX Runtime Web `1.17.3`, caricato su richiesta da jsDelivr: https://www.npmjs.com/package/onnxruntime-web/v/1.17.3 — licenza MIT: https://github.com/microsoft/onnxruntime/blob/v1.17.3/LICENSE.

La prima rimozione scarica il modello (circa 26 MB) e il runtime WebAssembly. Il worker viene chiuso dopo 90 secondi di inattività; il modello può essere conservato nella cache HTTP del browser.

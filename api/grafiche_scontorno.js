/* Refine MediaPipe's soft alpha using the photo as a colour guide.
 * No erosion: narrow details and separate people must remain in the mask. */
'use strict';
const GraphicsCutout = (() => {
  function refineAlpha(photo, mask, width, height) {
    const output = new Uint8ClampedArray(width * height);
    // A small, fixed number of samples keeps 2048px mobile uploads affordable.
    const step = Math.max(1, Math.round(Math.max(width, height) / 768));
    const neighbours = [];
    for (let dy = -2; dy <= 2; dy++) {
      for (let dx = -2; dx <= 2; dx++) {
        neighbours.push([dx * step, dy * step, Math.exp(-(dx * dx + dy * dy) / 4)]);
      }
    }
    const colourWeights = new Float32Array(766);
    for (let d = 0; d < colourWeights.length; d++) colourWeights[d] = Math.exp(-d * d / (2 * 60 * 60));
    for (let y = 0; y < height; y++) {
      for (let x = 0; x < width; x++) {
        const pixel = y * width + x, i = pixel * 4, alpha = mask[i + 3];
        if (!photo[i + 3] || alpha <= 8) { output[pixel] = 0; continue; }
        if (alpha >= 247) { output[pixel] = 255; continue; }
        let total = 0, weightSum = 0;
        for (const [dx, dy, spatialWeight] of neighbours) {
          const nx = x + dx, ny = y + dy;
          if (nx < 0 || nx >= width || ny < 0 || ny >= height) continue;
          const j = (ny * width + nx) * 4;
          if (!photo[j + 3]) continue;
          const distance = Math.abs(photo[i] - photo[j]) + Math.abs(photo[i + 1] - photo[j + 1]) + Math.abs(photo[i + 2] - photo[j + 2]);
          const weight = spatialWeight * colourWeights[distance];
          total += mask[j + 3] * weight;
          weightSum += weight;
        }
        // A continuous curve removes faint background haze without a hard cut.
        const confidence = Math.max(0, Math.min(1, (total / weightSum / 255 - .06) / .88));
        output[pixel] = Math.round(255 * confidence * confidence * (3 - 2 * confidence));
      }
    }
    return output;
  }

  function createMask(original, segmentationMask) {
    const canvas = document.createElement('canvas');
    canvas.width = original.naturalWidth;
    canvas.height = original.naturalHeight;
    try {
      const ctx = canvas.getContext('2d', {willReadFrequently: true});
      ctx.drawImage(original, 0, 0, canvas.width, canvas.height);
      const photo = ctx.getImageData(0, 0, canvas.width, canvas.height);
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      // Copy during onResults: the model may reuse its backing texture later.
      ctx.drawImage(segmentationMask, 0, 0, canvas.width, canvas.height);
      const mask = ctx.getImageData(0, 0, canvas.width, canvas.height);
      const alpha = refineAlpha(photo.data, mask.data, canvas.width, canvas.height);
      for (let p = 0; p < alpha.length; p++) {
        const i = p * 4;
        mask.data[i] = mask.data[i + 1] = mask.data[i + 2] = 255;
        mask.data[i + 3] = alpha[p];
      }
      ctx.putImageData(mask, 0, 0);
      return canvas;
    } catch (error) {
      canvas.width = canvas.height = 1;
      throw error;
    }
  }
  return {refineAlpha, createMask};
})();

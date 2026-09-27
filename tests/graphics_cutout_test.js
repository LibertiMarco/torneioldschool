const fs = require('fs');
const vm = require('vm');
const path = require('path');
const assert = require('assert');
const sandbox = {};
vm.createContext(sandbox);
vm.runInContext(fs.readFileSync(path.join(__dirname, '../api/grafiche_scontorno.js'), 'utf8') + '\nthis.cutout = GraphicsCutout;', sandbox);
const refine = sandbox.cutout.refineAlpha;
function fixture(width, height, pixel) {
  const photo = new Uint8ClampedArray(width * height * 4), mask = photo.slice();
  for (let y = 0; y < height; y++) for (let x = 0; x < width; x++) {
    const [colour, alpha, sourceAlpha = 255] = pixel(x, y), i = (y * width + x) * 4;
    photo.set([...colour, sourceAlpha], i);
    // MediaPipe confidence is alpha. RGB must not be mistaken for confidence.
    mask.set([255, 0, 0, alpha], i);
  }
  return {photo, mask, result: refine(photo, mask, width, height)};
}
const flat = alpha => fixture(8, 8, () => [[100, 80, 60], alpha]).result;
assert(flat(0).every(a => a === 0));
assert(flat(255).every(a => a === 255));
assert(flat(20).every(a => a < 5), 'Faint background haze remains');
assert(flat(235).every(a => a > 250), 'Confident subject should be opaque');
assert(flat(128).every(a => a > 110 && a < 145), 'Soft edges became binary');
const ramp = Array.from({length:256}, (_, a) => flat(a)[0]);
assert(ramp.every((a, i) => !i || a >= ramp[i - 1]), 'Alpha curve must be monotonic');
const edge = fixture(21, 9, x => [x < 10 ? [20, 150, 30] : [230, 50, 40], x < 9 ? 0 : x === 9 ? 70 : x === 10 ? 180 : 255]);
assert(edge.result[4 * 21 + 9] < 50, 'Background halo was not reduced at colour boundary');
assert(edge.result[4 * 21 + 10] > 205, 'Subject edge did not follow its colour boundary');
// A thin strand should survive between background pixels of a different colour.
const hair = fixture(9, 9, x => [x === 4 ? [10, 10, 10] : [230, 230, 230], x === 4 ? 190 : 0]);
assert(hair.result[4 * 9 + 4] > 190, 'Narrow subject detail was eroded');
// Retain two disconnected people; no largest-component assumption.
const people = fixture(30, 10, x => [[60, 80, 120], (x >= 3 && x <= 8) || (x >= 20 && x <= 26) ? 255 : 0]);
assert.strictEqual(people.result[5 * 30 + 5], 255);
assert.strictEqual(people.result[5 * 30 + 23], 255);
assert.strictEqual(people.result[5 * 30 + 15], 0);
assert(fixture(4, 4, () => [[0, 0, 0], 255, 0]).result.every(a => a === 0), 'Source transparency was filled');
assert.strictEqual(fixture(1, 1, () => [[0, 0, 0], 128]).result.length, 1);
assert.strictEqual(edge.mask[(4 * 21 + 9) * 4 + 3], 70, 'Input mask was mutated');
console.log('Cutout refinement: haze, colour boundaries, soft alpha, thin details, multiple people and transparency OK');

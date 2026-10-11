const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');
const template = fs.readFileSync(path.join(root, 'mobile/web/flutter_bootstrap.js'), 'utf8');
const compiled = fs.readFileSync(path.join(root, 'app-preview/build/flutter_bootstrap.js'), 'utf8');
const marker = 'const previewVersion =';
const script = template.slice(template.indexOf(marker));
assert.equal(compiled.slice(compiled.indexOf(marker)).trim(), script.trim());

for (const version of [null, 'new-build-123', 'value&with=characters']) {
  const build = { mainJsPath: 'main.dart.js' };
  let loaded = false;
  const context = {
    URL,
    document: { currentScript: { src: 'https://example.test/app-preview/build/flutter_bootstrap.js' +
      (version === null ? '' : '?v=' + encodeURIComponent(version)) } },
    _flutter: {
      buildConfig: { builds: [build, { compileTarget: 'dart2wasm' }] },
      loader: { load(options) {
        loaded = true;
        assert.equal(options.config.canvasKitBaseUrl, 'canvaskit/');
      } },
    },
  };
  vm.runInNewContext(script, context);
  assert.equal(build.mainJsPath, 'main.dart.js' + (version === null ? '' : '?v=' + encodeURIComponent(version)));
  assert.equal(loaded, true);
}
console.log('Preview cache: version propagation and compiled loader passed.');

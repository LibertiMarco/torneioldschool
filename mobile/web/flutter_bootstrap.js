{{flutter_js}}
{{flutter_build_config}}

// The protected PHP entry point versions this script using the compiled files.
// Carry the same version to the app URL to bypass previously immutable caches.
const previewVersion = new URL(document.currentScript.src).searchParams.get('v');
if (previewVersion) {
  for (const build of _flutter.buildConfig.builds) {
    if (build.mainJsPath) {
      build.mainJsPath += '?v=' + encodeURIComponent(previewVersion);
    }
  }
}
_flutter.loader.load({config: {canvasKitBaseUrl: 'canvaskit/'}});

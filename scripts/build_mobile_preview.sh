#!/usr/bin/env bash
set -euo pipefail
task_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
task_flutter="${FLUTTER_BIN:-flutter}"
cd "$task_root/mobile"
"$task_flutter" build web --release --no-wasm-dry-run \
  --base-href=/app-preview/build/ --dart-define=TOS_WEB_PREVIEW=true \
  --no-web-resources-cdn
# Copy generated files only. Keep the PHP entry point and Apache guards.
cp -R build/web/. "$task_root/app-preview/build/"
# Normalize third-party license line endings without changing their text.
tr -d '\r' < build/web/assets/NOTICES > "$task_root/app-preview/build/assets/NOTICES"
printf '%s\n' 'Preview compilata aggiornata nel repository. Nessun deploy remoto eseguito.'

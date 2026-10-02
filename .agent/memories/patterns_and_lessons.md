# Patterns and lessons

## Current source observations (not historical bug claims)
- The plugin is plain PHP plus a Gutenberg script using `window.wp` globals; the classic script is enqueued directly, with no npm build step (`comfy-image/comfy-image.php`, `comfy-image/assets/block.js`).
- REST route declarations, permission callbacks, input parsing, outbound requests, and response shaping are centralized in `comfy-image/includes/endpoints.php`. When changing the contract, trace both editor requests and responses as well as ComfyUI's API shape.
- Submit requires the explicit `{{prompt}}` marker. Keep prompt-ID validation string/type-safe before sanitization, and keep upstream error bodies/headers out of client responses.
- Before security-sensitive changes, inspect the configured URL policy, redirects, permission checks, and image-import handling. Private/LAN origins are intentional. The per-user quota uses a fixed 60-second window and a MySQL/MariaDB named advisory lock; contract tests cover its lock boundary, and a disposable WordPress concurrency run verified the live limit (see `PROGRESS.md`, 2026-09-29).
- Stub contract suites are useful for route/client contracts but do not establish real WordPress nonce, media-library, or Gutenberg acceptance. Record these as separate live gates.

Update these observations only after rechecking current source. Record verified fixes and their regression checks here; keep temporary task outcomes in `PROGRESS.md` or the session history.
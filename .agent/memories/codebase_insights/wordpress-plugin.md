# WordPress plugin and ComfyUI boundary

## Evidence-backed map
- Plugin setup/settings and editor wiring: `comfy-image/comfy-image.php` (`comfy_image_activate`, settings registration, `comfy_image_enqueue_editor_assets`).
- REST surface and remote calls: `comfy-image/includes/endpoints.php` (`rest_api_init`, `comfy_image_submit_workflow`, `comfy_image_check_status`, `comfy_image_fetch_image`, `comfy_image_upload_image`).
- Gutenberg client: `comfy-image/assets/block.js` registers `comfy/image-generator`; it submits, polls, imports, then inserts a `core/image` block.
- Legacy scaffold mock: `comfy-image/templates/example_workflow.json` is not ComfyUI API format, is not loaded by the plugin, and is excluded from the release archive.

## Boundary notes
The browser calls WordPress REST; PHP calls the administrator-configured ComfyUI HTTP API. All four routes use a shared generation permission helper: `edit_posts` plus an enabled role, with a `manage_options` bypass of the role list (not the capability). Media import additionally checks `upload_files`. WordPress core owns cookie/nonce authentication; the editor sends `X-WP-Nonce`, but stub tests do not prove that middleware.

The URL validator accepts administrator-configured HTTP(S) base URLs, including optional reverse-proxy path prefixes and private/LAN hosts; it rejects credentials/query/fragment, redirects are disabled, and workflow payloads cannot override the destination. No strict host allowlist exists: this is an explicit trusted-administrator boundary with SSRF risk after administrator compromise/misconfiguration.

The per-user 10-per-60-second transient quota is serialized with a MySQL/MariaDB named advisory lock scoped by database, table prefix, multisite blog, and user. Lock or transient-write failure denies the request. Stub tests verify lock acquisition/release/contention; a live disposable WordPress concurrency run accepted 10 of 24 simultaneous requests and denied 14, with counter readback and cleanup (see `PROGRESS.md`, 2026-09-29).

`comfy-image/examples/` contains the selectable API-format FLUX.2 Klein 4B, Z-Image Turbo, and Kandinsky 5 Lite examples. The latter two are API adaptations of editor workflows and have not yet been live-submitted in API format. The legacy `templates/example_workflow.json` remains a non-API mock.

Settings loads example JSON from disk at request time. Verify readability as the actual WordPress web-server identity (`www-data` in the local Compose runtime), not only as root/CLI: helper-generated files may have mode 0600 and silently disappear from the picker when PHP-FPM cannot read them. These bundled text assets need mode 0644; the ZIP builder also normalizes staged runtime files to 0644.

Image import validates filename/subfolder/type, bounds download bytes/time, verifies both MIME header and image bytes, and removes the uploaded file if attachment registration fails. Submit requires an explicit `{{prompt}}` template marker. Multipart upload remains an HTTP 501 stub. `/comfy` is block keyword discovery only; no direct argument-parsing slash command exists.

## Verification anchors
`php tests/php/run.php` and `node tests/js/run.js` are repository-local contract harnesses. PHP stubs cover permission callbacks and mocked HTTP but not WordPress REST nonce middleware, actual media writes, or live ComfyUI; JavaScript tests run the block script in a VM harness. No Composer/npm build pipeline or WP-CLI is configured. Do not infer live acceptance from these tests.
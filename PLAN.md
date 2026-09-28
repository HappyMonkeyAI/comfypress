Project: ComfyUI WordPress Plugin — PLAN

Goal
- Let Gutenberg users generate and insert images from a configured ComfyUI service through a WordPress block. Typing `/comfy` discovers the block through the native inserter; direct `/comfy <prompt>` execution is not implemented.

- Current status: PHP and JavaScript contract harnesses and source-level security bounds are implemented; the latest runs passed 75 PHP checks and 18 JavaScript checks. A live HTTP test of `GET /check-status/{prompt_id}` on the user-authorized WordPress 6.9.4 target verified anonymous/missing/invalid nonce responses, allowed-editor access, and subscriber denial; the submit route was not called. Cleanup completed; read-only verification confirmed the plugin inactive, plugin directory absent, all five plugin settings absent, and no matching temporary user/draft records. Gutenberg browser flow, Media Library import, controlled failure, live quota concurrency, and release/package verification remain open. Three supplied workflows completed direct ComfyUI smoke tests, but that is not WordPress editor acceptance. The repository-only API-format starter workflow is not included in the ZIP without separate approval. Multipart `/upload-image` remains HTTP 501 and is deferred for text-to-image scope.

Verified current contract
- Submit: authenticated `POST /wp-json/comfy-image/v1/submit-workflow` accepts `{ "workflow": <API workflow object>, "prompt": <string> }`; the workflow must contain the literal `{{prompt}}` marker. The plugin substitutes that marker and proxies to ComfyUI `POST /prompt`.
- Status: `GET /wp-json/comfy-image/v1/check-status/{prompt_id}` proxies `/history/{prompt_id}`. The editor looks for the requested prompt's output image metadata.
- Image: `GET /wp-json/comfy-image/v1/fetch-image` returns a view URL by default; `import=1` fetches a bounded response, validates PNG/JPEG/WebP/GIF bytes, and creates a WordPress attachment. The editor preserves filename, subfolder, and output type, then inserts `core/image`.
- Authorization: REST callbacks require `edit_posts` and a role selected in Settings > Comfy Image (default: Editor and Author). Users with `manage_options` bypass the configured role list but still need `edit_posts`. The editor sends `X-WP-Nonce`; a live WordPress 6.9.4 HTTP probe verified cookie-auth nonce handling and editor/subscriber role outcomes. Media import separately requires `upload_files` and remains untested live.
- Outbound/resource handling: the administrator-configured HTTP(S) base URL may include a reverse-proxy path and may target a local/private host; it cannot include user info/query/fragment, ordinary workflow payloads cannot override it, and redirects are disabled. This deliberately trusts administrators rather than enforcing a strict egress allowlist, so SSRF risk remains if an administrator is compromised or misconfigures the host. Workflow submission is limited to 10 per authenticated user in a fixed 60-second window anchored by the first request; a MySQL/MariaDB named advisory lock serializes counter updates. Contract coverage exists, but live multi-worker DB concurrency is not verified. Image size is clamped to 1–100 MB and external requests have finite timeouts.
- Editor: `/comfy` is a block keyword, not an argument-parsing command. Progress and timeout messages, retry, duplicate-click guard, and local polling cancellation exist; stopping polling does not cancel a running ComfyUI job.
- Evidence: `php -n tests/php/run.php` (75 checks) and `node tests/js/run.js` (18 checks) use stubs. A separate live `GET /check-status/{prompt_id}` probe on WordPress 6.9.4 observed anonymous/missing-nonce 401, invalid-nonce 403 `rest_cookie_invalid_nonce`, valid editor 200, and subscriber 403 `rest_forbidden`; submit was not called. Cleanup completed and read-only checks verified the plugin inactive, plugin directory absent, five settings absent, and no matching temporary user/draft records. Live database-lock concurrency, Media Library import, Gutenberg/browser interaction, and controlled-failure recovery remain unverified. Three direct ComfyUI workflow submissions do not exercise the WordPress editor path.

Files I reviewed
- `comfy-image/comfy-image.php` — plugin bootstrap, settings, and editor asset wiring.
- `comfy-image/includes/endpoints.php` — REST permissions, request validation, ComfyUI calls, and media import.
- `comfy-image/assets/block.js` — Gutenberg block submission, polling, and image insertion.
- `tests/php/run.php` and `tests/js/run.js` — local contract harnesses; these do not substitute for live acceptance.

Key takeaways from the current implementation
- The plugin proxies API-format workflows to ComfyUI, polls prompt history, and returns or imports validated output images.
- WordPress REST permissions, validation, upstream requests, and response shaping are centralized in the plugin REST module; the block client uses the WordPress REST nonce.
- The current block is plain JavaScript using WordPress globals; no npm/webpack build pipeline or external image-generation submodule is required.

Original implementation proposal (historical; it differs from the verified current contract above)
- Plugin slug: `comfy-image` (changeable).
- PHP backend (WP plugin):
  - Main plugin file `comfy-image.php` with plugin header and initialization.
  - Register admin settings (Comfy UI base URL, default model/workflow template, allowed origins/CORS options, optional API key or auth token if needed).
  - REST-like AJAX endpoints (via `wp_ajax_` and optionally a `wp-json` REST route) for:
    - `comfy_upload_image` — accept media from editor and forward to ComfyUI `upload/image`, returning uploaded filename.
    - `comfy_submit_workflow` — submit a workflow JSON to `/prompt` (allow template override and text prompt), return `prompt_id`.
    - `comfy_check_status` — poll `/history/{prompt_id}` and return output filename(s).
    - `comfy_fetch_image` — proxy `view` requests or return signed URL to the editor.
  - Optional: server-side caching / rate-limiting, sanitization, and domain allowlist for ComfyUI URL.
  - Media handling: when the result image is available, either (A) download from ComfyUI to WP media library (recommended) and return attachment ID/URL to Gutenberg, or (B) return remote URL to be shown in editor (less integrated).

- Gutenberg integration (JS):
  - Register a block `comfy/image-generator` that provides an input area and a slash-command handler.
  - The current implementation exposes `/comfy` as a native block keyword; it inserts the block, and the prompt is entered in the block UI. Direct `/comfy {prompt}` parsing remains a possible follow-up.
  - UX: prompt input, optional seed/steps/CFG settings, progress indicator, preview, and an Insert button that places image into the post content (uses returned attachment ID or URL).
  - The current implementation is in `comfy-image/assets/block.js` and uses WordPress REST endpoints; an npm/webpack build is not configured.

Security & safety (verified implementation)
- REST permission callbacks require `edit_posts` and an allowed role; users with `manage_options` bypass the role list but still need `edit_posts`. The editor sends the WordPress REST nonce; live core cookie-auth middleware and editor/subscriber outcomes were verified on the `check-status` route only. Submit, administrator-bypass, and media-capability paths remain unverified live.
- The administrator-configured ComfyUI HTTP(S) base URL may include a path prefix and private/LAN hosts; user info, query, and fragment are rejected, ordinary request payloads cannot override the destination, and redirects are disabled. The first release intentionally trusts the site administrator and does not enforce a strict host allowlist; internal-network SSRF is possible if an administrator is compromised or misconfigures the target.
- Workflow submission is limited to 10 per authenticated user in a fixed 60-second transient window anchored by the first request; later successful submissions do not extend the window. A MySQL/MariaDB named advisory lock serializes the transient read/modify/write across PHP workers; lock contention/storage errors deny the request. Contract tests cover the fixed window and lock boundary, but live DB concurrency is not verified, and no site-wide quota is included.
- Imported output requires `upload_files`, a bounded response, supported image bytes/MIME, safe filename/subfolder handling, and WordPress upload/attachment APIs. Attachment registration failure removes the uploaded file.

Current settings and workflow contract
- Admin settings are under `Settings > Comfy Image`: HTTP(S) ComfyUI base URL, API-format workflow JSON, auto-save-to-media, max image size, and allowed roles.
- The workflow template is required at generation time and must contain the literal `{{prompt}}` marker. `examples/flux1-krea-dev.api.json` is a repository-only API-format starter fixture; its node/model dependencies must exist on the ComfyUI host. It is not auto-loaded or included in the ZIP without separate approval. `templates/example_workflow.json` remains a legacy mock, not a ComfyUI API workflow, and is not loaded or packaged.
- Anonymous generation is not supported. The default allowed roles are Editor and Author; administrators with `manage_options` still require `edit_posts`.

Testing & validation
- Deterministic checks: `php tests/php/run.php`, `node tests/js/run.js`, production PHP syntax checks, JavaScript syntax checks, JSON parsing, and `git diff --check`.
- These are stub-based contract checks. Manual Gutenberg/WordPress/ComfyUI integration and install/activate verification remain release gates; do not infer them from the stub suites.

Original milestones (historical; superseded by the verified status and dated queue above)
- The initial scaffold, settings, REST endpoints, Gutenberg block, media import, security hardening, documentation, and package builder are represented in the current source tree.
- Remaining acceptance work is tracked in `docs/plans/2026-09-28-comfypress-next-development-queue.md`; do not treat the historical estimate below as current.

Original first-pass deliverables (historical; not current acceptance evidence)
- Working plugin scaffold that registers admin page and settings.
- Backend endpoints that proxy/upload to ComfyUI using configured `comfy_base_url` (no Gutenberg UI yet).
- One example endpoint test via `curl` or admin AJAX that demonstrates image upload and workflow submission.

Estimated effort (rough)
- Scaffold + settings + endpoints: 1–2 days
- Gutenberg block + slash command + UX polish: 2–3 days
- Media handling + security + testing: 1–2 days

Open gates
- Complete the live Gutenberg-to-ComfyUI-to-Media-Library browser flow, including controlled failure and cleanup; the status-route REST probe does not establish generation/import acceptance.
- Verify live quota concurrency, test the chosen outbound-network boundary, and rebuild/recheck release packages and the supported-version matrix.
- First-release scope is text-to-image via the existing block and Media Library insertion. Direct inline `/comfy <prompt>` and per-block seed/steps/CFG controls are deferred because they are not required by the opaque workflow contract.
- Multipart upload forwarding remains deferred unless image-to-image is brought into scope; `/upload-image` intentionally remains HTTP 501.
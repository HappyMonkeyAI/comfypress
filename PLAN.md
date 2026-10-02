Project: ComfyUI WordPress Plugin — PLAN

Goal
- Let Gutenberg users generate and insert images from a configured ComfyUI service through a WordPress block. Typing `/comfy` discovers the block through the native inserter; direct `/comfy <prompt>` execution is not implemented.

- Current status: PHP and JavaScript contract harnesses and source-level security bounds are implemented; the latest runs passed 104 PHP checks and 38 JavaScript checks. The unreleased worktree adds optional per-block seed/steps/CFG overrides with exact numeric workflow markers and pre-network validation; the current ComfyUI core schema bounds are recorded in `PROGRESS.md`. This feature has contract coverage but has not had live Gutenberg/ComfyUI generation acceptance and is not in the 0.1.2 package. A live HTTP test of `GET /check-status/{prompt_id}` on the user-authorized WordPress 6.9.4 target verified anonymous/missing/invalid nonce responses, allowed-editor access, and subscriber denial. Separately, the local Gutenberg happy path generated/imported images, inserted native Image blocks, and restored the saved prompt after reload. Plugin 0.1.2 installed and activated on WordPress 6.9.4/PHP 8.3.30 and WordPress 5.9.3/PHP 8.0.19, with all four routes registered on both. On the minimum runtime, a mocked submit callback returned a sanitized 502 then recovered with HTTP 200; a browser-level mocked failure/retry path and live quota concurrency also passed. Three supplied workflows completed direct ComfyUI smoke tests; that evidence is separate from WordPress acceptance. The maintained repository-only API-format example is not included in the ZIP without separate approval. Distribution is on hold; multipart `/upload-image` remains HTTP 501 and deferred for text-to-image scope.

Verified current contract
- Submit: authenticated `POST /wp-json/comfy-image/v1/submit-workflow` accepts `{ "workflow": <API workflow object>, "prompt": <string>, "seed?": <decimal>, "steps?": <integer>, "cfg?": <decimal> }`; the workflow must contain the literal `{{prompt}}` marker. Optional numeric overrides require their respective complete-value markers (`{{seed}}`, `{{steps}}`, `{{cfg}}`) and are validated and inserted as JSON numbers before proxying to ComfyUI `POST /prompt`. Empty overrides preserve all configured workflow values; core schema bounds and exact seed handling are documented in `PROGRESS.md`.
- Status: `GET /wp-json/comfy-image/v1/check-status/{prompt_id}` proxies `/history/{prompt_id}`. The editor looks for the requested prompt's output image metadata.
- Image: `GET /wp-json/comfy-image/v1/fetch-image` returns a view URL by default; `import=1` fetches a bounded response, validates PNG/JPEG/WebP/GIF bytes, and creates a WordPress attachment. The editor preserves filename, subfolder, and output type, then inserts `core/image`.
- Authorization: REST *** require `edit_posts` and a role selected in Settings > Comfy Image (default: Editor and Author). Users with `manage_options` bypass the configured role list but still need `edit_posts`. The editor sends `X-WP-Nonce`; WordPress 6.9.4 verified cookie-auth nonce handling, and WordPress 5.9.3/PHP 8.0.19 live HTTP checks verified anonymous 401 and excluded-role 403 on all four routes, the `manage_options`-without-`edit_posts` denial, administrator bypass, allowed-role submit, and the `upload_files` denial before image fetch.
- Outbound/resource handling: the administrator-configured HTTP(S) base URL may include a reverse-proxy path and may target a local/private host; it cannot include user info/query/fragment, ordinary workflow payloads cannot override it, and redirects are disabled. This deliberately trusts administrators rather than enforcing a strict egress allowlist, so SSRF risk remains if an administrator is compromised or misconfigures the target. Workflow submission is limited to 10 per authenticated user in a fixed 60-second window anchored by the first request; a MySQL/MariaDB named advisory lock serializes counter updates. A live disposable WordPress test accepted 10 of 24 simultaneous requests and denied 14, with counter readback and cleanup verified. Image size is clamped to 1–100 MB and external requests have finite timeouts.
- Editor: `/comfy` is a block keyword, not an argument-parsing command. Progress and timeout messages, retry, duplicate-click guard, and local polling cancellation exist; stopping polling does not cancel a running ComfyUI job.
- Evidence: `php -n tests/php/run.php` (104 checks) and `node tests/js/run.js` (38 checks) use stubs. WordPress 6.9.4 live HTTP verified cookie/nonce middleware on the status route. On WordPress 5.9.3/PHP 8.0.19, real HTTP requests verified auth denials and permitted calls across the submit/status/fetch/upload routes, `edit_posts`/role/admin/`upload_files` boundaries, attachment-failure cleanup, configured-destination binding, and no redirect following; all upstream calls went only to a local test mock. The separate local Gutenberg run generated/imported images, inserted native Image blocks, and restored the saved prompt after reload. The final 0.1.2 ZIP installed on both WordPress 6.9.4/PHP 8.3.30 and 5.9.3/PHP 8.0.19; browser-level mocked retry and live quota concurrency also passed. Per-block numeric overrides are new to the unreleased worktree and have contract coverage only; no live acceptance for these controls is claimed. No security probe made a real ComfyUI generation, and no strict production egress allowlist is claimed. Distribution is currently on hold; see `PROGRESS.md` for exact evidence.

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
- REST permission callbacks require `edit_posts` and an allowed role; users with `manage_options` bypass the role list but still need `edit_posts`. The editor sends the WordPress REST nonce. Live evidence covers nonce middleware on `check-status` (WordPress 6.9.4) and role/capability denials, submit/admin bypass, and media-import permission/cleanup paths over HTTP on WordPress 5.9.3/PHP 8.0.19; see the dated evidence in `PROGRESS.md`.
- The administrator-configured ComfyUI HTTP(S) base URL may include a path prefix and private/LAN hosts; user info, query, and fragment are rejected, ordinary request payloads cannot override the destination, and redirects are disabled. The first release intentionally trusts the site administrator and does not enforce a strict host allowlist; internal-network SSRF is possible if an administrator is compromised or misconfigures the target.
- Workflow submission is limited to 10 per authenticated user in a fixed 60-second window anchored by the first request; later successful submissions do not extend the window. A MySQL/MariaDB named advisory lock serializes the transient read/modify/write across PHP workers; lock contention/storage errors deny the request. Contract tests cover the fixed window and lock boundary; a live disposable WordPress concurrency run accepted 10 of 24 simultaneous requests and denied 14, with counter readback and cleanup verified. No site-wide quota is included.
- Imported output requires `upload_files`, a bounded response, supported image bytes/MIME, safe filename/subfolder handling, and WordPress upload/attachment APIs. Attachment registration failure removes the uploaded file.

Current settings and workflow contract
- Admin settings are under `Settings > Comfy Image`: HTTP(S) ComfyUI base URL, API-format workflow JSON, auto-save-to-media, max image size, and allowed roles.
- The workflow template is required at generation time and must contain the literal `{{prompt}}` marker. `comfy-image/examples/flux2-klein-4b.api.json` is a repository-only API-format example smoke-tested against installed ComfyUI models; its node/model dependencies must exist on the ComfyUI host. It is not auto-loaded or included in the ZIP without separate approval. `templates/example_workflow.json` remains a legacy mock, not a ComfyUI API workflow, and is not loaded or packaged.
- Anonymous generation is not supported. The default allowed roles are Editor and Author; administrators with `manage_options` still require `edit_posts`.

Testing & validation
- Deterministic checks: `php tests/php/run.php`, `node tests/js/run.js`, production PHP syntax checks, JavaScript syntax checks, JSON parsing, and `git diff --check`.
- These are stub-based contract checks. Manual Gutenberg/WordPress/ComfyUI integration and package install/activate have separate live evidence; do not infer them from the stub suites. The declared WordPress 5.9/PHP 8.0 floor is verified at WordPress 5.9.3/PHP 8.0.19; this is a point test, not an exhaustive support matrix.

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
- First-release live security checks now cover denied submit/import requests, administrator bypass, failed-import cleanup, configured-destination binding, and disabled redirect following. The trusted-admin URL policy still has no strict host/egress allowlist; do not describe it as one. Repeat these acceptance checks if the plugin's routes or URL policy change.
- Live quota concurrency and the declared minimum WordPress/PHP floor are verified. The floor evidence is WordPress 5.9.3/PHP 8.0.19, not every patch combination; re-run if support claims or runtime code change.
- First-release scope is text-to-image via the existing block and Media Library insertion. Direct inline `/comfy <prompt>` and per-block seed/steps/CFG controls are deferred because they are not required by the opaque workflow contract.
- Remaining release action: select WordPress.org or hosted ZIP distribution, then perform only that channel's submission/hosting requirements. Multipart upload forwarding remains deferred unless image-to-image is brought into scope; `/upload-image` intentionally remains HTTP 501.
Project: ComfyUI WordPress Plugin — PLAN

Goal
- Build a WordPress plugin that lets Gutenberg users generate images from ComfyUI on the fly via a slash command (`/comfy`) or a Page Block widget. Include a plugin admin settings page to configure ComfyUI URL and related options.

Files I reviewed
- `image-image-gen/js/main.js:1` — frontend ComfyUI integration (upload, submit workflow, poll, display).
- `image-image-gen/README.md:1` — usage notes and API endpoints for the image frontend.
- `LLM_Auto_Redirect_v1.2.0/LLM_Auto_Redirect.php:1` — WordPress plugin patterns (admin settings, register_setting, AJAX handlers, nonces, wp_remote_post).
- `LLM_Auto_Redirect_v1.2.0/LLM_Auto_Redirect_multi.php:1` — variant with similar admin UI patterns and AJAX flows.

Key takeaways from the examples
- ComfyUI flow: `POST /upload/image` -> `POST /prompt` -> poll `GET /history/{prompt_id}` -> `GET /view?filename={filename}` for resulting image. `image-image-gen/js/main.js:1` demonstrates file uploads via FormData, workflow JSON construction, and polling logic.
- WP plugin patterns: `register_setting`, `add_management_page`, nonce checks with `check_ajax_referer`, capability checks (`current_user_can('manage_options')`), `wp_ajax_` hooks, use of `wp_remote_post` for external services, server-side sanitization and URL validation in `LLM_Auto_Redirect_v1.2.0/LLM_Auto_Redirect.php:1`.
- Useful patterns to reuse: admin settings UI, AJAX endpoints, JS client patterns for request/response, and error handling.

Proposed implementation architecture
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
  - Slash command `/comfy` available inside block editor (use `@wordpress/editor` block autocomplete / block variations or the `RichText`/`Inserter` APIs). Typing `/comfy {prompt}` launches the same flow.
  - UX: prompt input, optional seed/steps/CFG settings, progress indicator, preview, and an Insert button that places image into the post content (uses returned attachment ID or URL).
  - Reuse `image-image-gen/js/main.js:1` logic for upload/prompt/poll UI flow adapted to WP AJAX endpoints and React-like structure (ESNext + webpack or wp-scripts).

Security & safety
- Always `check_ajax_referer` for AJAX POSTs and `current_user_can('edit_posts')` when actions may publish media.
- Validate and sanitize all user inputs (`sanitize_text_field`, `esc_url_raw`, `wp_kses_post` where appropriate).
- Restrict ComfyUI host to an admin-configured URL and optionally block public setting changes.
- Rate limit requests per-user or per-site (simple transient-based counters) to prevent abuse.
- When downloading images into WP media, verify MIME type and use `wp_upload_bits` + `wp_insert_attachment`.

Developer experience & configuration
- Admin settings page under `Settings > Comfy Image` (or Tools). Fields:
  - `comfy_base_url` (required) — example `http://192.168.1.2:8188`
  - `default_workflow_template` — optional JSON template or path to template in plugin `templates/`
  - `auto_save_to_media` (bool) — whether to save generated images to WP Media
  - `max_image_size_mb` — client/server limit
  - `allow_anonymous_generation` — whether non-privileged editors can call the feature
- Provide example workflow JSON in `templates/` (adapt from `image-image-gen/image_qwen_image_edit_2509.json`).

Testing & validation
- Manual tests: upload + edit flow in Gutenberg, slash command, admin options, media insertion, error handling.
- Automated: unit tests where feasible (PHP `wp-cli` bootstrap tests), basic JS integration tests if repo uses jest.

Milestones / Implementation Plan (ordered, actionable)
1. Plugin scaffold and repo (files and basic plugin header) — create `comfy-image/` with `comfy-image.php`, `readme.txt`, `assets/`, `templates/` (IN_PROGRESS if you want me to start).
2. Admin settings page and options (Comfy URL, template selection, save options) — reuse patterns from `LLM_Auto_Redirect_v1.2.0/LLM_Auto_Redirect.php:1`.
3. Server-side endpoints: `comfy_upload_image`, `comfy_submit_workflow`, `comfy_check_status`, `comfy_fetch_image` with nonce and capability checks.
4. Simple Gutenberg block + slash-command handler (UI with prompt input). Ensure editor JS enqueues with `wp-scripts` build.
5. Integrate image workflow: adapt `image-image-gen/js/main.js:1` logic to call the plugin endpoints instead of direct ComfyUI URLs.
6. Media handling: save outputs to WP media library and return attachment ID.
7. Security hardening, input validation, and rate-limiting.
8. Polish UX, add settings UI for workflow templates, add example templates in `templates/`.
9. Documentation, README, and packaging.

Deliverables for first pass
- Working plugin scaffold that registers admin page and settings.
- Backend endpoints that proxy/upload to ComfyUI using configured `comfy_base_url` (no Gutenberg UI yet).
- One example endpoint test via `curl` or admin AJAX that demonstrates image upload and workflow submission.

Estimated effort (rough)
- Scaffold + settings + endpoints: 1–2 days
- Gutenberg block + slash command + UX polish: 2–3 days
- Media handling + security + testing: 1–2 days

Next steps (choose one)
- I can scaffold the plugin now (create files, implement admin settings and endpoints) — say “Scaffold now”.
- Or I can implement the full upload->workflow->poll->media flow next.

If you want me to proceed, tell me which option to start with and confirm plugin slug/name (default: `comfy-image`) and whether to auto-save generated images into WP Media by default.
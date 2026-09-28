=== Comfy Image ===
Contributors: stephen
Tags: comfyui,image,generator,gutenberg
Requires at least: 5.9
Tested up to: 6.5
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later

Generate and insert ComfyUI images from the Gutenberg editor. Type `/comfy` in the native block inserter to find the Comfy Image Generator block, enter a prompt, and select Generate. This does not implement a direct `/comfy <prompt>` inline command.

== Setup ==
1. Copy this `comfy-image` directory into `wp-content/plugins/` and activate it.
2. Open Settings → Comfy Image. Set the ComfyUI Base URL to an HTTP(S) origin reachable from the WordPress server.
3. Paste a ComfyUI API-format workflow JSON template containing the literal `{{prompt}}` marker.
4. In the Gutenberg editor, insert the Comfy Image Generator block, enter a prompt, and select Generate.

== Behavior and limits ==
The editor submits the workflow through authenticated WordPress REST routes, polls ComfyUI history, imports supported PNG/JPEG/WebP/GIF output into the Media Library, and inserts a native image block. REST actions require `edit_posts` and a role selected in Settings > Comfy Image (default: Editor and Author); users with `manage_options` bypass the role list but still need `edit_posts`. Importing media additionally requires `upload_files`. Cookie-authenticated REST requests use the WordPress REST nonce.

Workflow submissions are limited to 10 requests per authenticated user per 60-second transient window. A MySQL/MariaDB named advisory lock serializes counter updates across PHP workers; a busy/unavailable lock fails closed. Local tests cover lock handling, but live database concurrency has not been verified. Only administrators configure the ComfyUI destination. HTTP(S) local/private endpoints and reverse-proxy paths are supported; redirects are disabled and credentials, query strings, and fragments are rejected. This is a trusted-administrator boundary, not an egress allowlist; administrators must configure only trusted ComfyUI destinations.

The first release is text-to-image. Direct `/comfy <prompt>` execution, per-block seed/steps/CFG controls, and multipart upload forwarding are deferred; `/upload-image` intentionally returns HTTP 501.

== Development and verification ==
The repository provides `php tests/php/run.php` (75 checks) and `node tests/js/run.js` (18 checks). These are lightweight contract tests using WordPress stubs. A separate WordPress 6.9.4 HTTP smoke test of `GET /check-status/{prompt_id}` verified REST cookie-auth nonce handling and editor/subscriber role gates; the submit route was not called, and the temporary plugin/test setup was removed afterward. Gutenberg browser flow, Media Library import, live quota concurrency, and package acceptance remain unverified.

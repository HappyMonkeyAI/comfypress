=== Comfy Image ===
Contributors: happymonkeyai
Tags: comfyui,image,generator,gutenberg
Requires at least: 5.9
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.1.3
License: GPLv2 or later

Generate and insert ComfyUI images from the Gutenberg editor. Type `/comfy` in the native block inserter to find the Comfy Image Generator block, enter a prompt, and select Generate. This does not implement a direct `/comfy <prompt>` inline command.

== Setup ==
1. Copy this `comfy-image` directory into `wp-content/plugins/` and activate it.
2. Open Settings → Comfy Image. Set the ComfyUI Base URL to an HTTP(S) origin reachable from the WordPress server.
3. Choose a bundled FLUX.2 Klein 4B, Z-Image Turbo, or Kandinsky 5 Lite example, or paste a ComfyUI API-format workflow JSON template containing the literal `{{prompt}}` marker. Each example requires its listed model files and nodes on your ComfyUI server.
4. In the Gutenberg editor, insert the Comfy Image Generator block, enter a prompt, and select Generate.

== Behavior and limits ==
The editor submits the workflow through authenticated WordPress REST routes, polls ComfyUI history, imports supported PNG/JPEG/WebP/GIF output into the Media Library, and inserts a native image block. REST actions require `edit_posts` and a role selected in Settings > Comfy Image (default: Editor and Author); users with `manage_options` bypass the role list but still need `edit_posts`. Importing media additionally requires `upload_files`. Cookie-authenticated REST requests use the WordPress REST nonce.

Workflow submissions are limited to 10 requests per authenticated user per 60-second transient window. A MySQL/MariaDB named advisory lock serializes counter updates across PHP workers; a busy/unavailable lock fails closed. Contract tests cover lock handling; a live disposable WordPress concurrency run accepted 10 of 24 simultaneous requests and denied 14, with counter readback and cleanup verified. Only administrators configure the ComfyUI destination. HTTP(S) local/private endpoints and reverse-proxy paths are supported; redirects are disabled and credentials, query strings, and fragments are rejected. This is a trusted-administrator boundary, not an egress allowlist; administrators must configure only trusted ComfyUI destinations.

== Remote ComfyUI with Cloudflare Tunnel ==
For a ComfyUI instance running on a home computer or workstation, do not expose ComfyUI's port (normally 8188) directly to the internet. Put the companion ComfyPress Gateway (https://github.com/SPhillips1337/comfypress-gateway) in front of ComfyUI and point a Cloudflare Tunnel public hostname only at the gateway (default local listener: http://127.0.0.1:8190). The Tunnel provides a route and HTTPS; it does not authenticate API calls by itself.

Set the ComfyUI Base URL in Settings > Comfy Image to the Tunnel's HTTPS hostname and enter a generated gateway key in the Gateway API token field. ComfyPress sends the key in the HTTP authorization header from the WordPress server for workflow submission, history polling, and image retrieval; it is not sent to visitors' browsers. A token may instead be defined in wp-config.php as COMFY_IMAGE_GATEWAY_API_TOKEN. Keep the original ComfyUI port private, create separate gateway keys per WordPress site, and revoke keys that may have been exposed. See the gateway README for Docker Compose, Cloudflare Tunnel, key rotation, and local host networking instructions.

The released 0.1.2 package is text-to-image through the existing block. The unreleased working tree adds optional per-block seed/steps/CFG controls, validated against the available core node ranges and applied only through exact workflow markers. This feature is contract-tested but not yet verified through live Gutenberg/ComfyUI generation, and it is not in the 0.1.2 package. Direct `/comfy <prompt>` execution and multipart upload forwarding remain deferred; `/upload-image` intentionally returns HTTP 501.

== Development and verification ==
The repository provides `php tests/php/run.php` (104 checks) and `node tests/js/run.js` (38 checks). These are lightweight contract tests using WordPress stubs. The new per-block numeric override behavior is contract-tested only; live Gutenberg and ComfyUI generation acceptance is pending. On local WordPress 6.9.4, the Gutenberg happy path generated/imported images and inserted native image blocks; draft 10 retained its prompt after an editor reload. WordPress readback confirmed the prompt attribute and two 1024×1024 PNG attachments. The user reported the generated result looked good. Plugin 0.1.2 installed and activated on plugin-clean WordPress 6.9.4/PHP 8.3.30 and WordPress 5.9.3/PHP 8.0.19 targets, with all four routes registered on both. A minimum-runtime mocked submit callback returned a sanitized 502 and then recovered with HTTP 200; browser-level mocked failure/retry and live quota concurrency also passed. The minimum point tested was WordPress 5.9.3/PHP 8.0.19; this does not cover every patch-level combination. No WordPress.org submission or hosted release is performed.
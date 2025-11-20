ComfyPress — ComfyUI WordPress Plugin (workspace)

This workspace contains assets for the Comfy Image WordPress plugin (plugin folder `comfy-image`).

Overview
- Plugin goal: Allow Gutenberg users to generate images using a remote ComfyUI instance via a Gutenberg block (and planned slash command `/comfy`).
- Current state: Prototype scaffold with admin settings, REST proxy endpoints, server-side image import into WP Media, and a minimal Gutenberg block that submits workflows and inserts generated images into content.

Files created in this workspace
- `comfy-image/comfy-image.php` — main plugin file, settings registration and block script enqueueing.
- `comfy-image/includes/endpoints.php` — REST endpoints: `submit-workflow`, `check-status`, `fetch-image` (supports `import=1` to download and insert into media), and an upload endpoint stub.
- `comfy-image/assets/block.js` — minimal editor block that accepts a prompt, submits a workflow, polls for results, imports image and inserts it as a `core/image` block.
- `comfy-image/templates/example_workflow.json` — example workflow template.

How to test locally
1. Copy `comfy-image/` into your WordPress `wp-content/plugins/` directory.
2. Activate the plugin in WP Admin > Plugins.
3. Visit Settings > Comfy Image and ensure the `ComfyUI Base URL` points to a reachable ComfyUI instance (e.g. the configured URL in this workspace: `https://owned-schedules-practitioner-reflection.trycloudflare.com/`).
4. Open the Gutenberg editor for a post, add the `Comfy Image` block, type a prompt and click `Generate`.
5. The block will submit a workflow to ComfyUI, poll for results, import the resulting image into WP Media, and insert it into the post.

Notes & next work
- Security: REST routes currently use capability checks (`current_user_can('edit_posts')`). Consider adding REST nonce verification and rate-limiting before broad rollout.
- Upload endpoint: multipart upload forwarding to ComfyUI is not implemented yet (stubbed).
- UX: The block is minimal — we should add controls for steps/seed, progress UX, cancel/pause, and thumbnails.
- Tests & packaging: Add automated tests and package the plugin for distribution when ready.

See `project.json` and `PLAN.md` for the project plan and machine-readable manifest.

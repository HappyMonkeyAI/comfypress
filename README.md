# ComfyPress

ComfyPress is a WordPress plugin that lets editors generate images with a connected ComfyUI server from the Gutenberg editor, then add the results to the WordPress Media Library and a post.

## What it does

- Adds a **Comfy Image Generator** block to Gutenberg. Type `/comfy` in the block inserter to find it.
- Sends a prompt through a ComfyUI API-format workflow and imports the resulting image into WordPress.
- Includes example workflows for FLUX.2 Klein 4B, Z-Image Turbo, and Kandinsky 5 Lite.
- Supports optional per-block seed, steps, and CFG values when the selected workflow contains the matching `{{seed}}`, `{{steps}}`, and `{{cfg}}` markers.

The `/comfy` shortcut finds the block; it does not run an inline `/comfy <prompt>` command.

## Requirements

- WordPress 5.9 or later
- PHP 8.0 or later
- A ComfyUI server that the WordPress host can reach over HTTP(S)

## Install and configure

1. Copy the `comfy-image/` directory into `wp-content/plugins/` and activate **ComfyPress** in WordPress.
2. Open **Settings → Comfy Image** and enter the ComfyUI base URL.
3. Choose a bundled workflow or paste a ComfyUI API-format workflow JSON. It must contain the literal `{{prompt}}` marker where the prompt should go.
4. Make sure the ComfyUI server has the model files and custom nodes required by that workflow.
5. In the block editor, insert the Comfy Image Generator block, enter a prompt, and select **Generate**. The resulting image is imported into the Media Library and inserted into the post.

Leave the seed, steps, or CFG override blank to keep the value already set in the workflow. To use an override, add its matching marker as a whole JSON value in the workflow.

## Security and limits

- Generation routes require `edit_posts` and an allowed WordPress role (Editor and Author by default). Importing an image also requires `upload_files`.
- ComfyUI destinations are configured by administrators. Local and private-network addresses are supported for self-hosted setups, so this is not an outbound-network allowlist. Only configure a ComfyUI server you trust.
- Each authenticated user is limited to 10 workflow submissions per 60-second window.
- The current feature scope is text-to-image. Image-upload forwarding is not implemented, and its endpoint returns HTTP 501.

## Development

Run the repository checks from the project root:

```sh
php -n tests/php/run.php
node tests/js/run.js
```

Build a clean plugin ZIP with:

```sh
scripts/build-plugin.sh /absolute/path/to/comfy-image.zip
```

The build includes the three selectable workflow examples and normalizes archive timestamps for reproducible output. It requires `zip` and refuses to overwrite an existing destination.

The source currently reports version 0.1.3. The 0.1.3 ZIP is a checked candidate, not an installed or released package; the previously verified 0.1.2 package remains unchanged. Live Gutenberg and ComfyUI acceptance for the new per-block numeric overrides is still pending.

## License

ComfyPress is licensed under the MIT License. See [LICENSE](LICENSE).

# ADR-0002: ComfyUI egress trust and per-user quota locking

- Status: Accepted for the text-to-image first release
- Date: 2026-09-28

## Context

ComfyPress proxies authenticated Gutenberg requests to an administrator-configured ComfyUI service. Deployments commonly place ComfyUI on a private/LAN host or behind a reverse-proxy path, so a public-host-only egress allowlist would break a primary deployment mode. Workflow submission also uses a per-user transient counter whose read/modify/write sequence was not safe under concurrent PHP requests.

## Decisions

### ComfyUI network boundary

- The site administrator is the trusted party that selects the ComfyUI HTTP(S) base URL.
- Private/LAN targets and reverse-proxy path prefixes remain supported. Embedded credentials, query strings, and fragments are rejected; outbound redirects are disabled; normal workflow requests cannot override the configured origin.
- This is not a strict egress allowlist and is not a defense against a compromised/misconfigured administrator. Such a target can make WordPress contact internal services (SSRF risk). Operators must restrict who receives `manage_options` and may add network-layer egress controls appropriate to their deployment.
- A stricter host/network allowlist is deferred until there is a concrete deployment requirement and a design that preserves self-hosted ComfyUI support.

### Generation quota

- Keep the first-release contract at no more than 10 submissions per authenticated user per 60-second transient window; no site-wide quota is introduced.
- Serialize the transient read/increment/write using a MySQL/MariaDB named advisory lock scoped by database, table prefix, multisite blog, and user. Wait at most one second; if the lock or transient update fails, reject the request rather than submit without accounting.
- The lock protects only the short counter update, not the ComfyUI generation request. This avoids serializing long-running jobs.
- This implementation assumes a standard WordPress MySQL/MariaDB primary connection with named-lock support. Alternative database/drop-in routing must be verified before claiming the concurrency guarantee.

### First-release scope

- Ship text-to-image through the existing Gutenberg block, authenticated REST proxy, Media Library import, and image insertion.
- Keep direct `/comfy <prompt>` execution, per-block seed/steps/CFG controls, site-wide quotas/usage logs, and image-to-image upload out of scope. `/upload-image` remains HTTP 501.
- Keep `comfy-image/examples/flux2-klein-4b.api.json` as a repository-only smoke-tested example. Do not add it to the release ZIP without separate approval.

## Consequences

- Self-hosted/private ComfyUI remains usable, but the site administrator remains a high-trust network boundary; installations that cannot accept this risk need their own egress restrictions.
- Concurrent quota updates are serialized on supported MySQL/MariaDB deployments. Lock contention is fail-closed and can transiently return the existing quota error response. A live disposable WordPress/MySQL test on 2026-09-29 accepted 10 of 24 concurrent submissions, denied 14, verified the counter, and cleaned up its test state; see `PROGRESS.md`.
- The repository example is validated against the plugin's API-format/placeholder contract, not against an actual ComfyUI model installation.
- The release ZIP remains limited to its existing four runtime/readme files until separate approval changes the package allowlist.

## Verification

- The local PHP contract harness verifies that quota updates acquire/release the advisory lock and fail closed on contention, plus that request payloads cannot override the configured ComfyUI URL.
- The repository-only fixture parses as a ComfyUI API prompt object and has `{{prompt}}` in its intended text input.
- Follow-on live acceptance is recorded in `PROGRESS.md`: WordPress 5.9.3/PHP 8.0.19 exercised REST cookie+nonce role/capability boundaries, media-failure cleanup, configured-destination binding, and redirect handling; separate WordPress 6.9.4 evidence covers the Gutenberg generate/import and prompt-restoration path. The 2026-09-29 quota run closes the documented multi-worker gate for the tested MySQL/MariaDB setup. Security probes used a local mock, not a real ComfyUI generation. Strict egress allowlisting, a broader private-LAN configuration matrix, and non-MySQL/MariaDB database compatibility remain unverified or deferred; do not imply those are covered.

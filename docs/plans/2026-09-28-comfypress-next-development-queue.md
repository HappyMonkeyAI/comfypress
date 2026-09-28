# ComfyPress — Next Development Task Queue

> Planning artifact only. Reconciled with `PROGRESS.md`, `PLAN.md`, the current development ledger, source, and worktree on 2026-09-28. This is a proposed follow-on queue, not a claim that any task below is complete and not a mutation of a separate live task board.

## Goal and boundary

Close the first authenticated text-to-image workflow from Gutenberg through the ComfyPress WordPress REST proxy to ComfyUI, then verify image import/insertion and release readiness. Keep WordPress as the authenticated proxy and media authority; ComfyUI remains the generation backend. Keep the current scope: image-to-image upload is deferred, and `/comfy` is block discovery rather than an inline prompt command.

## Evidence baseline

- The three supplied root workflows are ComfyUI editor-format JSON. Each was converted in the ComfyUI frontend with `app.graphToPrompt()` and completed a direct, bounded ComfyUI `/prompt` smoke run. Results and prompt IDs are in `PROGRESS.md`; the source workflows were not edited.
- ComfyPress settings and submit code require an API-format workflow object with the literal `{{prompt}}` marker (`comfy-image/comfy-image.php`, `comfy-image/includes/endpoints.php`). The direct ComfyUI runs do not prove that the supplied files are ready to paste into the plugin.
- The latest recorded `php -n tests/php/run.php` run passed 75 checks; the last recorded `node tests/js/run.js` run passed 18. These stub-contract tests do not exercise live database-lock concurrency, real Media Library writes, or Gutenberg in a live site. A separate HTTP probe on WordPress 6.9.4 verified cookie-auth nonce and status-route role gates; details are in `PROGRESS.md`.
- The user authorized the WP Invoice Test target in `invoices-wordpress-1`. It was used for a temporary REST probe and restored afterward; preserve existing site data and only clean artifacts created by a test.
- Packaging was verified previously; release readiness remains blocked on submit/Gutenberg/media integration and the package/support matrix. The temporary REST-only setup was cleaned up.

## Priority 0 — unblock the first integrated acceptance path

### 1. Prepare one plugin-compatible workflow template

**Outcome:** Turn one supplied light workflow into a reviewable ComfyUI API-format template suitable for `Settings > Comfy Image`, without overwriting the supplied editor-format originals.

**Work:** Export through ComfyUI's API conversion path, identify the intended positive prompt field, replace its text with `{{prompt}}`, and retain bounded smoke settings for the test fixture. Add the API-format copy and concise provenance/usage note in a clearly named test-fixture location; do not include it in the release ZIP unless separately approved.

**Acceptance:** JSON parses; `{{prompt}}` appears in the intended node; a test prompt substituted for the marker produces a valid ComfyUI API submission and successful image; the original editor-format source files remain unchanged. Then verify the plugin's configured-template contract with the existing PHP tests.

### 2. Obtain an authorized WordPress test target — COMPLETE (WP Invoice Test)

**Outcome:** Make the actual REST/auth/editor path testable without borrowing an unrelated site.

**Work:** Locate a project-owned disposable WordPress instance or get explicit approval for a minimal isolated one. Confirm that the WordPress host can reach the already-tested ComfyUI service. Record the test URL/identity and cleanup boundary without recording credentials in the repository.

**Acceptance:** The user-authorized WP Invoice Test target was temporarily configured and activated for REST checks; cleanup verified the temporary plugin/options/users/draft were removed. Preserve unrelated site data. This authorizes scoped use of this test site but does not imply it is disposable.

### 3. Verify live WordPress REST authentication and role gates — PARTIAL (status route)

**Outcome:** Confirm WordPress core middleware and ComfyPress permission callbacks agree in a real request path.

**Work:** Exercise requests with no login, missing/invalid REST nonce, an allowed editor/author, an excluded role, and an administrator with the required capability. Verify the separate `upload_files` requirement for media import, and exercise both submit and status routes.

**Acceptance:** The status route produced the expected anonymous/missing/invalid nonce and editor/subscriber outcomes; see `PROGRESS.md`. The submit route, administrator bypass, and `upload_files` gate remain to be tested before closing the full task.

### 4. Complete one live Gutenberg-to-Media-Library vertical slice

**Outcome:** Verify the user-facing path against the real plugin and ComfyUI service.

**Work:** Configure the API-format template; open Gutenberg; find the block using `/comfy`; enter a prompt; generate; observe polling; import the image to Media Library; and confirm the inserted `core/image` references the resulting attachment. Also check non-import/view mode if it remains exposed as a supported setting.

**Acceptance:** A live browser run shows the expected block, status/error feedback, final image and attachment; REST calls, Media Library record, inserted post block, and browser console are checked independently. Record browser viewport and evidence separately from PHP/JS tests and raw ComfyUI API results. Include one controlled failure (for example, invalid template or unavailable ComfyUI) and confirm understandable recovery/retry behavior.

## Priority 1 — close release-relevant reliability and security gaps

### 5. Make generation quota behavior safe under concurrent requests

**Outcome:** Resolve the documented non-atomic per-user transient counter, or explicitly accept its bounded risk for the intended deployment.

**Work:** Add a deterministic concurrency-focused test and choose an atomic storage/locking strategy compatible with the supported WordPress baseline; do not add a site-wide limit unless product requirements call for it.

**Acceptance:** Parallel requests cannot exceed the chosen per-user window beyond the documented contract; expiry/reset behavior is tested; the full PHP contract suite passes. If no compatible atomic strategy is selected, retain the gap as an explicit release limitation rather than claiming concurrency hardening.

### 6. Decide and verify the outbound-network policy

**Outcome:** Keep administrator-configured private/LAN ComfyUI support while making the SSRF/network boundary an explicit product decision.

**Work:** Decide whether admin-only configuration plus current URL validation/redirect blocking is sufficient, or whether deployments need a host/network allowlist. Trace DNS resolution/rebinding and proxy-path implications before implementing a stricter rule that could break LAN ComfyUI.

**Acceptance:** Record the decision and test the chosen policy for HTTP(S), embedded credentials, query/fragment, redirects, private/LAN targets, and rejected destinations. Do not silently claim a strict host allowlist; none exists in the current source.

## Priority 2 — roadmap choices and release closeout

### 7. Confirm the first-release feature boundary

**Outcome:** Avoid starting unapproved work that expands the working text-to-image path.

**Decision candidates:** direct `/comfy <prompt>` execution; seed/steps/CFG controls; image-to-image multipart `/upload/image`; site-wide quotas or model selection. Current docs do not require these for the text-to-image vertical slice; upload remains an HTTP 501 stub by explicit deferral.

**Acceptance:** Keep each item deferred unless the product requirement and acceptance criteria are approved. If any is approved, add it as a separately scoped task with tests and a live acceptance gate.

### 8. Re-run package and support-matrix release gates after integration

**Outcome:** Produce a release candidate backed by the newly integrated behavior, not just the previously verified ZIP.

**Work:** Re-run PHP/JavaScript contract suites, syntax checks, JSON checks, and deterministic package build; verify archive contents against source. Test the documented minimum WordPress/PHP combinations or adjust the documented support claims. Add CI only if it can invoke the real repository commands reliably.

**Acceptance:** All deterministic gates pass; archive integrity and file list are checked; supported versions are evidenced; live install/REST/Gutenberg/media gates above pass. Keep “package verified” and “release ready” as separate statuses until every required gate passes.

## Operating constraints

- Preserve the current dirty and untracked worktree, including the supplied workflow files. No cleanup, commit, push, deployment, or unrelated-container use without explicit approval.
- Keep direct ComfyUI HTTP checks, WordPress REST/auth integration, actual Media Library behavior, Gutenberg browser acceptance, and package verification as separate evidence categories.
- Provision or use only a project-owned disposable WordPress target; do not store credentials in docs.
- Update `PROGRESS.md` only after each task has fresh evidence. This queue does not itself change roadmap completion status.

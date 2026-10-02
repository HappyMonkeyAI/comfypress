# ComfyPress — Next Development Task Queue

> Planning artifact only. Reconciled with `PROGRESS.md`, `PLAN.md`, source, and worktree on 2026-09-29. This is a proposed follow-on queue, not a mutation of a separate live task board.

## Goal and boundary

Close the first authenticated text-to-image workflow from Gutenberg through the ComfyPress WordPress REST proxy to ComfyUI, then verify image import/insertion and release readiness. Keep WordPress as the authenticated proxy and media authority; ComfyUI remains the generation backend. Keep the current scope: image-to-image upload is deferred, and `/comfy` is block discovery rather than an inline prompt command.

## Evidence baseline

- The three supplied root workflows are ComfyUI editor-format JSON. Each was converted in the ComfyUI frontend with `app.graphToPrompt()` and completed a direct, bounded ComfyUI `/prompt` smoke run. Results and prompt IDs are in `PROGRESS.md`; the source workflows were not edited.
- ComfyPress settings and submit code require an API-format workflow object with the literal `{{prompt}}` marker (`comfy-image/comfy-image.php`, `comfy-image/includes/endpoints.php`). The direct ComfyUI runs do not prove that the supplied files are ready to paste into the plugin.
- The latest recorded `php -n tests/php/run.php` run passed 75 checks and `node tests/js/run.js` passed 24. These stub-contract tests do not exercise live database-lock concurrency, real Media Library writes, or Gutenberg in a live site. Separate live tests verified quota concurrency, Gutenberg/media insertion, and REST security boundaries; see `PROGRESS.md` for exact scopes.
- The user authorized the WP Invoice Test target in `invoices-wordpress-1`. It was used for a temporary REST probe and restored afterward; preserve existing site data and only clean artifacts created by a test.
- The exact 0.1.2 package was reproducible, source-byte checked, and installed on WordPress 6.9.4/PHP 8.3.30 and 5.9.3/PHP 8.0.19. Submit/Gutenberg/media integration and the minimum-runtime HTTP security checks now have evidence. Distribution channel selection and its publication/hosting steps remain open.

## Current next actions — 2026-09-30 (planning only)

The first-release core path is evidenced in `PROGRESS.md`: Gutenberg generation/import and prompt persistence, the live REST security negatives, quota concurrency, both tested runtime points, and reproducible package checks. Do not repeat these gates unless relevant code changes. The worktree is mixed/dirty; preserve it. This dated list orders remaining work and does not authorize publication or mutate a separate task board.

### Priority 0 — close before calling 0.1.2 release-ready

1. **Verify the save-to-media setting when disabled — VERIFIED.** The authenticated WordPress settings UI saved the checkbox off; after reload it remained unchecked and the option read back as `'0'`. The editor localized the disabled value as `""`, while the prior strict-`false` checks treated it as enabled and added `import=1`. Added a regression for the actual localized value and fixed the client to treat `false` and `""` as disabled. The mocked JS contract confirms `import=1` is omitted and returned `view_url` is inserted as a `core/image` block. Original setting `'1'` was restored and re-verified after reload. No real generation or Media Library write was performed.

2. **Release hold selected.** The user chose to hold 0.1.2 distribution and proceed with one post-release feature. The distribution channel remains undecided; no submission, upload, or publication is authorized. Revisit channel selection only when release work is resumed.

3. **Deferred while release is held.** When the release is resumed, run only the chosen channel’s procedure from a clean, reviewed staging copy; keep package verification distinct from channel/publication readiness.

### Priority 1 — improve the onboarding path after release gates

4. **Prepare and validate an installed-model ComfyUI example — FLUX2 KLEIN SMOKE VERIFIED (2026-09-30).** Added separate `comfy-image/examples/flux2-klein-4b.api.json`, adapted from the repository-supplied API workflow and using models installed on the test ComfyUI. `check_deps.py` reported ready; the bounded 512×512/4-step direct ComfyUI run completed and returned a PNG. This does not establish WordPress-editor acceptance. The fixture is not approved for the release ZIP without separate approval.

5. **Selected post-0.1.2 feature: per-block seed/steps/CFG overrides — implemented in the unreleased worktree (2026-09-30); live editor gate pending.** The block persists optional seed, steps, and CFG strings. Empty values are omitted and workflows without numeric markers keep administrator-provided values unchanged. A populated override requires its corresponding complete-value marker (`{{seed}}`, `{{steps}}`, `{{cfg}}`); a marker without a value, a partial marker, or an override without its exact marker is rejected before any ComfyUI request. Bounds were grounded in the available ComfyUI core `/object_info` schemas: seed 0–18446744073709551615 (kept exact as a decimal string until JSON encoding), steps 1–4096, CFG 0–100 with at most two decimal places. Custom-node schemas may differ. PHP/JS contracts cover the behavior; live Gutenberg/runtime acceptance remains pending. No 0.1.2 package, submission, upload, or publication was changed. Other candidates remain deferred: direct `/comfy <prompt>`, image-to-image upload (HTTP 501), and site-wide quota/logging or model selection.

### Priority 2 — reopen only for a concrete deployment requirement

6. **Revisit network egress and database compatibility only if required.** ADR-0002 accepts the trusted-administrator ComfyUI URL policy; it is not a strict egress allowlist. The live quota guarantee was tested on MySQL/MariaDB, not alternative database drivers. Add stricter network controls or broaden the database matrix only when target deployments require them, preserving private/LAN ComfyUI support and documenting the resulting limits.

### Public-MVP onboarding and extensibility follow-up

- **Settings onboarding — started in the unreleased worktree:** make the workflow textarea resizable in both dimensions; offer the bundled FLUX.2 Klein 4B API workflow as a selectable example that prepopulates the textarea; clearly state its ComfyUI model/node prerequisites; show plugin version, publisher, and ComfyUI documentation links. Keep the example selection additive and preserve manual workflow editing. Add the canonical source-repository link only after its public URL is confirmed; do not guess a GitHub URL.
- **Broaden generation backends after the ComfyUI MVP:** evaluate support for other common image-generation models and services users want to connect. First classify candidates (additional ComfyUI model/workflow templates vs. distinct self-hosted/hosted services), then define a provider-neutral capability/configuration boundary and compare authentication, cost, privacy, availability, and output-import behavior. Do not imply that all models share a ComfyUI workflow or silently send prompts to a hosted service. Select one user-demanded candidate and prove its end-to-end contract before adding more adapters.

## Priority 0 — unblock the first integrated acceptance path

### 1. Prepare one plugin-compatible workflow template

**Outcome:** Turn one supplied light workflow into a reviewable ComfyUI API-format template suitable for `Settings > Comfy Image`, without overwriting the supplied editor-format originals.

**Work:** Export through ComfyUI's API conversion path, identify the intended positive prompt field, replace its text with `{{prompt}}`, and retain bounded smoke settings for the test fixture. Add the API-format copy and concise provenance/usage note in a clearly named test-fixture location; do not include it in the release ZIP unless separately approved.

**Acceptance:** JSON parses; `{{prompt}}` appears in the intended node; a test prompt substituted for the marker produces a valid ComfyUI API submission and successful image; the original editor-format source files remain unchanged. Then verify the plugin's configured-template contract with the existing PHP tests.

### 2. Obtain an authorized WordPress test target — COMPLETE (WP Invoice Test)

**Outcome:** Make the actual REST/auth/editor path testable without borrowing an unrelated site.

**Work:** Locate a project-owned disposable WordPress instance or get explicit approval for a minimal isolated one. Confirm that the WordPress host can reach the already-tested ComfyUI service. Record the test URL/identity and cleanup boundary without recording credentials in the repository.

**Acceptance:** The user-authorized WP Invoice Test target was temporarily configured and activated for REST checks; cleanup verified the temporary plugin/options/users/draft were removed. Preserve unrelated site data. This authorizes scoped use of this test site but does not imply it is disposable.

### 3. Verify live WordPress REST authentication and role gates — COMPLETE

**Outcome:** Confirm WordPress core middleware and ComfyPress permission callbacks agree in a real request path.

**Work:** Exercise requests with no login, missing/invalid REST nonce, an allowed editor/author, an excluded role, and an administrator with the required capability. Verify the separate `upload_files` requirement for media import, and exercise both submit and status routes.

**Acceptance:** WordPress 6.9.4 verified cookie/nonce middleware on the status route. WordPress 5.9.3/PHP 8.0.19 verified anonymous 401 and excluded-role 403 across all four routes, an administrator-capability role without `edit_posts` denied, allowed-role submit, administrator bypass, and `upload_files` denial before any fetch. See the 2026-09-29 security entry in `PROGRESS.md`.

### 4. Complete one live Gutenberg-to-Media-Library vertical slice

**Outcome:** Verify the user-facing path against the real plugin and ComfyUI service.

**Work:** Configure the API-format template; open Gutenberg; find the block using `/comfy`; enter a prompt; generate; observe polling; import the image to Media Library; and confirm the inserted `core/image` references the resulting attachment. Also check non-import/view mode if it remains exposed as a supported setting.

**Acceptance:** A live browser run shows the expected block, status/error feedback, final image and attachment; REST calls, Media Library record, inserted post block, and browser console are checked independently. Record browser viewport and evidence separately from PHP/JS tests and raw ComfyUI API results. Include one controlled failure (for example, invalid template or unavailable ComfyUI) and confirm understandable recovery/retry behavior.

## Priority 1 — close release-relevant reliability and security gaps

### 5. Make generation quota behavior safe under concurrent requests

**Outcome:** Resolve the documented non-atomic per-user transient counter, or explicitly accept its bounded risk for the intended deployment.

**Work:** Add a deterministic concurrency-focused test and choose an atomic storage/locking strategy compatible with the supported WordPress baseline; do not add a site-wide limit unless product requirements call for it.

**Acceptance:** Parallel requests cannot exceed the chosen per-user window beyond the documented contract; expiry/reset behavior is tested; the full PHP contract suite passes. If no compatible atomic strategy is selected, retain the gap as an explicit release limitation rather than claiming concurrency hardening.

### 6. Decide and verify the outbound-network policy — PARTIAL (chosen policy exercised)

**Outcome:** Keep administrator-configured private/LAN ComfyUI support while making the SSRF/network boundary an explicit product decision.

**Work:** Decide whether admin-only configuration plus current URL validation/redirect blocking is sufficient, or whether deployments need a host/network allowlist. Trace DNS resolution/rebinding and proxy-path implications before implementing a stricter rule that could break LAN ComfyUI.

**Acceptance:** The trusted-administrator policy is recorded in ADR 0002. Live HTTP verified that a request payload cannot replace the configured destination and that real mock 302 responses are not followed (`redirection=0`). The policy intentionally has no strict host allowlist; full configuration-validation/private-LAN deployment coverage was not part of this live run. Do not claim strict egress protection.

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

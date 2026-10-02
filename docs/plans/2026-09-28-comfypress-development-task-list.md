# ComfyPress Development Task List

> Execution ledger, initially reconciled on 2026-09-28. Current release evidence is appended below and in `PROGRESS.md`; the Gutenberg/media happy path, quota concurrency, and declared support-floor point are verified. Distribution-channel selection and publication remain open.

**Goal:** Develop the Comfy Image WordPress plugin into a secure, testable Gutenberg-to-ComfyUI workflow while keeping project docs aligned with working behavior.

**Architecture boundary:** Gutenberg calls authenticated WordPress REST routes; the plugin proxies requests to an administrator-configured ComfyUI service and imports generated images into WordPress Media. Keep response validation, outbound-network policy, and media writes on the server. Preserve the current product docs as scope authority, but reconcile their status against live source and the active worktree.

**Tech stack:** WordPress/PHP plugin, classic Gutenberg JavaScript (`window.wp` globals), ComfyUI HTTP API. Repository-local PHP and Node contract harnesses exist; no Composer/npm package manifest or PHPUnit configuration is present. `scripts/build-plugin.sh` builds the normalized ZIP.

---

## Evidence and current constraints

- `PROGRESS.md` began with the Agents Protocol documentation bootstrap; later entries now record product implementation and exact verification evidence.
- Current deterministic evidence: `php -n tests/php/run.php` passes 93 contract checks and `node tests/js/run.js` passes 38. These harnesses stub WordPress APIs and ComfyUI HTTP; they do not replace live database, Media Library, or editor evidence. The unreleased per-block seed/steps/CFG override path has PHP/JS contract coverage; live Gutenberg/generation acceptance is still pending. A separate HTTP probe on WordPress 6.9.4 verified REST cookie-auth nonce and role gates on `GET /check-status/{prompt_id}` only.
- `PLAN.md`, `README.md`, `project.json`, `comfy-image/readme.txt`, `CONTEXT.md`, the new ADR, and canonical `.agent/memories/` describe the admin-trusted outbound boundary, advisory-lock quota, first-release scope, fixture provenance, test limitations, and open live acceptance gate. The legacy mock workflow remains non-API format and excluded from the package.
- The maintained API-format example at `comfy-image/examples/flux2-klein-4b.api.json` is repository-only, parses as a ComfyUI API prompt object, and contains `{{prompt}}` in the positive-prompt path. It was smoke-tested directly against installed ComfyUI models; this does not establish WordPress-editor acceptance or authorize ZIP inclusion.
- The release builder remains an explicit four-file allowlist; the latest package build/archive/source checks and minimum-runtime install evidence are recorded in `PROGRESS.md`.
- `20251120-plan.md` describes an earlier scaffold-only state and conflicts with current implementation; treat it as historical context, not the active plan. It is untracked and must be preserved unless the user authorizes curation.
- The submit/status contract is stable: submit returns the ComfyUI JSON prompt response; the editor accepts `prompt_id`/documented aliases, polls history, and extracts filename/subfolder/type. Error responses are bounded and do not return raw upstream bodies/headers.
- REST routes require `edit_posts` and an enabled role (`editor`/`author` by default); `manage_options` bypasses the role list but not `edit_posts`. Media import additionally requires `upload_files`. Live HTTP results: anonymous and editor-without-nonce requests returned 401; invalid nonce returned 403 `rest_cookie_invalid_nonce`; valid editor returned 200; valid subscriber returned 403 `rest_forbidden`.
- The ComfyUI base URL accepts HTTP(S), optional reverse-proxy path prefixes, and local/private hosts; it rejects embedded credentials/query/fragment, disables redirects, and workflow payloads cannot override the configured destination. The first release explicitly trusts administrators to configure a safe target; this is not an egress allowlist and internal-network SSRF remains possible after admin compromise/misconfiguration.
- Generation uses a fixed per-user 10-per-60-second transient window anchored by the first request; later successful requests do not extend it. A MySQL/MariaDB named advisory lock protects the read/increment/write; contention/storage errors fail closed. Contract tests cover window reset/remaining TTL and lock acquisition/release/contention; a disposable live WordPress run accepted 10 of 24 simultaneous requests and denied 14, then verified the counter and cleaned synthetic data. No site-wide quota is in first-release scope.
- Image import bounds bytes/time, validates supported image MIME against binary data, rejects unsafe filename/subfolder inputs, and deletes the uploaded file when attachment registration fails. The Gutenberg happy path verified a live image import; negative media-capability and import-failure paths remain unverified live.
- The user explicitly authorized the existing `invoices-wordpress-1` WP Invoice Test container. The plugin was temporarily staged/activated with temporary settings, test accounts, and a draft post to exercise real REST HTTP cookie-auth middleware. Cleanup completed; read-only verification confirmed the plugin inactive, plugin directory absent, all five plugin settings absent, and no matching temporary user/draft records. The valid editor status request returned 200. No Gutenberg UI, generation, Media Library import, or controlled-failure path was run. Direct ComfyUI workflow tests remain separate from editor acceptance.
- PHP startup emits local `pdo_sqlite` load and duplicate `sqlite3` warnings; syntax/test commands still exit successfully. No npm/composer build pipeline is needed for the classic editor script.

## Priority 0 — Close the in-flight contract and verification gaps

### Task 1: Establish a repeatable plugin test path — COMPLETE (stub-contract scope)

**Outcome:** Choose the smallest maintainable way to test the PHP REST boundary and Gutenberg client without assuming a test stack that is not present.

**Inspect:** `comfy-image/comfy-image.php`, `comfy-image/includes/endpoints.php`, `comfy-image/assets/block.js`, current local WordPress availability, and existing PHP/Node tooling.

**Acceptance/evidence:** Run `php tests/php/run.php` (55 checks) and `node tests/js/run.js` (18 checks) from the repository root. Tests cover request validation, upstream success/error mapping, status/image extraction, capability/role boundaries, rate-limit exhaustion, image byte/MIME/path rules, media cleanup, and submit/status/import-or-view/insert behavior. Both use stubs and require no live ComfyUI. Live WordPress middleware and Media Library behavior remain separate gates.

### Task 2: Stabilize submit/status response contracts and remove debug disclosure — COMPLETE (deterministic contract)

**Outcome:** Make the PHP endpoint and Gutenberg client agree on a stable, safe response shape before extending the workflow.

**Inspect/change:** `comfy-image/includes/endpoints.php` (`comfy_image_submit_workflow`, `comfy_image_check_status`) and `comfy-image/assets/block.js` (`submitWorkflow`, polling/result extraction).

**Acceptance/evidence:** The stub fixtures cover submit/history shapes and successful editor submit → poll → import/view → insertion, malformed/error responses, and prompt-ID validation. The client handles non-success HTTP responses and bounded errors; the server does not return raw upstream headers/body. In addition, three supplied workflows were converted from editor format and accepted/completed by direct live ComfyUI `/prompt` submissions. This verifies the ComfyUI side only; the WordPress proxy path remains open.

### Task 3: Reconcile product records to the verified contract — COMPLETE

**Outcome:** Leave one clear current description of what is implemented and what remains.

**Inspect/change after Tasks 1–2:** `README.md`, `PLAN.md`, `project.json`, `comfy-image/readme.txt`, and `PROGRESS.md`.

**Acceptance/evidence:** `README.md`, `PLAN.md`, `project.json`, `comfy-image/readme.txt`, `CONTEXT.md`, `.agent/memories/`, `PROGRESS.md`, this ledger, and the asset developer note describe source-backed behavior, security/resource bounds, explicit upload/direct-command scope, the invalid legacy workflow mock, test limitations, and the remaining runtime gates. The untracked `20251120-plan.md` remains unchanged.

## Priority 1 — Security and operational safety

### Task 4: Verify WordPress authorization and harden outbound ComfyUI requests — STATUS-ROUTE AUTH VERIFIED; OTHER PATHS/POLICY OPEN

**Outcome:** Ensure generation and media import are limited to intended authenticated editors and that remote requests cannot be redirected or configured into an unsafe network target.

**Inspect/change:** REST permission callbacks and nonce behavior in `comfy-image/includes/endpoints.php`, nonce setup in `comfy-image/comfy-image.php`, and the ComfyUI URL setting/sanitization.

**Acceptance/evidence:** Stub tests cover capability/role selection, media-import capability, rejected query/fragment URLs, and inability of request payloads to override the configured destination. Live WordPress 6.9.4 HTTP checks on `GET /check-status/{prompt_id}` observed anonymous/missing nonce = 401, invalid nonce = 403 `rest_cookie_invalid_nonce`, valid editor = 200, and disallowed subscriber = 403 `rest_forbidden`. The submit route, administrator bypass, and `upload_files` gate were not tested live. The trusted-administrator policy still has no strict egress allowlist; see ADR-0002 for the SSRF tradeoff. Live quota concurrency was later verified; outbound-boundary behavior remains a separate open check.

### Task 5: Bound generation cost and image-import resource use — SOURCE AND LIVE QUOTA CONCURRENCY VERIFIED; NEGATIVE MEDIA PATHS OPEN

**Outcome:** Prevent accidental or repeated expensive runs and bound bytes/time spent importing images.

**Inspect/change:** `comfy-image/includes/endpoints.php` generation and import paths; relevant settings in `comfy-image/comfy-image.php`.

**Acceptance/evidence:** A per-user fixed 10-per-60-second transient quota is serialized by a database named lock scoped to database/table prefix/site/user; a busy lock fails closed, and the lock is released on quota exhaustion. The PHP harness passed 75 checks covering the fixed window and lock boundary plus quota exhaustion/no upstream request, response-size bounds, MIME/binary validation, filenames/subfolders, redirects, upstream errors, attachment creation, and cleanup. A disposable live WordPress concurrency run accepted 10 of 24 simultaneous requests and denied 14; counter readback and cleanup passed. Negative WordPress HTTP/media paths remain open.

## Priority 2 — Complete the planned editor workflows

### Task 6: Implement multipart upload forwarding if image-to-image is in scope — DEFERRED

**Outcome:** Replace the existing upload stub (HTTP 501) with a bounded ComfyUI `/upload/image` proxy, or explicitly defer it if the first product release is text-to-image only.

**Inspect/change:** `comfy_image_upload_image` in `comfy-image/includes/endpoints.php`, the editor client in `comfy-image/assets/block.js`, and `PLAN.md` scope.

**Acceptance/evidence:** Current product scope is text-to-image. Preserve the permission-gated HTTP 501 stub and document image-to-image forwarding as deferred; reopen only if product scope changes.

### Task 7: Add the planned `/comfy` editor command — PARTIAL / DIRECT COMMAND DEFERRED

**Outcome:** Let users invoke the same verified generation flow from the Gutenberg editor command/inserter experience without duplicating server logic.

**Inspect/change:** `comfy-image/assets/block.js`, editor registration in `comfy-image/comfy-image.php`, and WordPress minimum-version compatibility in `project.json`/`comfy-image/readme.txt`.

**Acceptance/evidence:** `/comfy` keyword discovery inserts the generator block in the native inserter; the prompt is entered in the block. Direct `/comfy <prompt>` parsing/command handoff is explicitly not implemented. Deterministic tests and the live Gutenberg visibility/generation/import/insertion path pass. Live negative submit/media permissions remain unverified. Do not silently claim a direct command.

## Priority 3 — Release readiness

### Task 8: Improve generation controls and failure UX — BASIC PATHS VERIFIED; NUMERIC OVERRIDES IMPLEMENTED UNRELEASED, LIVE GATE PENDING

**Outcome:** Make the basic block usable for real workflows without expanding the server contract unsafely.

**Inspect/change:** `comfy-image/assets/block.js`, settings/localization, and `project.json`.

**Acceptance/evidence:** Source implements progress/error messages, bounded polling timeout, retry, duplicate-submit guard, local polling cancellation, configurable media save/view behavior, and image insertion; JS contract tests exercise submit/poll/import-or-view/insert, duplicates, and cancellation control. The live basic Gutenberg happy path, saved-prompt reload, and mocked failure/retry path are verified; local cancellation does not stop the ComfyUI job. Optional seed/steps/CFG fields now persist per block and are validated/replaced only through explicit workflow markers; PHP/JS contract coverage passes (93/38). Live Gutenberg and generation acceptance for the new fields is still pending.

### Task 9: Package and document a release candidate — FINAL PACKAGE AND SUPPORT-FLOOR VERIFIED; CHANNEL UNDECIDED

**Outcome:** Produce a reproducible plugin package and support matrix after security and functional gates pass.

**Inspect/change:** `comfy-image/readme.txt`, `README.md`, plugin headers/version, ignore/package rules, and a minimal CI/test entry point if justified.

**Acceptance/evidence:** `scripts/build-plugin.sh` normalizes staged file timestamps and refuses to overwrite output. The final refreshed archive was built twice byte-identically, passed integrity and exact four-file/source-byte checks, then the exact archive installed/activated on WordPress 5.9.3/PHP 8.0.19 with all four routes and mocked submit failure/recovery verified. Current PHP (75) and JavaScript (22) suites, syntax/metadata checks, and `git diff --check` passed. The separate WordPress 6.9.4/PHP 8.3.30 install, live quota concurrency, and Gutenberg/media happy path are also verified. WordPress.org versus hosted distribution is undecided; do not submit, publish, or claim channel readiness.

## Release-gate update — 2026-09-29
- Disposable live quota test: 24 concurrent requests yielded 10 accepted and 14 denied; counter readback and synthetic test-data cleanup passed.
- WordPress 5.9.3/PHP 8.0.19 installed/activated plugin 0.1.2 and registered all four routes on an isolated internal Docker network. The submit callback returned sanitized 502 on a mocked upstream error, then HTTP 200 on retry; `pre_http_request` intercepted both calls. No external generation occurred.
- Browser-level mocked failure/retry, WordPress 6.9.4 Gutenberg/media happy path, and support-floor install are separately evidenced in `PROGRESS.md`. The PHP callback check is not an external REST-server/browser end-to-end test.
- The final refreshed archive is recorded in `PROGRESS.md`; reproducibility, integrity, allowlist/source equality, minimum-runtime install/recovery, and deterministic checks passed. No publication or repository change was authorized.

## Follow-on queue execution — 2026-09-28

### Workflow fixture — SUPERSEDED BY THE CURRENT FLUX2 EXAMPLE
- The initial repository-only API-format starter was retired during example-fixture cleanup; the maintained fixture is `comfy-image/examples/flux2-klein-4b.api.json`.
- The current fixture was smoke-tested directly against installed ComfyUI models and is covered by the PHP placeholder/positive-prompt-path contract. Direct ComfyUI success does not establish WordPress-editor acceptance or authorize ZIP inclusion.

### WordPress target, REST auth, and Gutenberg vertical slice — STATUS-ROUTE AUTH VERIFIED; EDITOR/MEDIA FLOW OPEN
- On the user-authorized `invoices-wordpress-1` WP Invoice Test target (WordPress 6.9.4), the plugin was staged temporarily and the real REST HTTP cookie-auth path was exercised with generated test identities/nonces. Anonymous and missing-nonce requests returned 401; invalid nonce returned 403 `rest_cookie_invalid_nonce`; allowed editor returned 200; disallowed subscriber returned 403 `rest_forbidden`.
- Cleanup completed; read-only verification confirmed the plugin inactive, plugin directory absent, all five plugin settings absent, and no matching temporary user/draft records. No Gutenberg/browser, generation, Media Library import, or controlled failure/retry path was run; those gates remain open.

### Network policy and first-release boundary — DECIDED; STATIC TESTED
- Accepted the trusted-administrator outbound policy described in ADR-0002. Private/LAN endpoints remain supported; credentials/query/fragment and redirects are rejected; request payloads cannot override the configured URL. This is not a strict egress allowlist.
- First release remains text-to-image through the existing block and media insertion. Per-block seed/steps/CFG overrides are now implemented and contract-tested in the unreleased worktree, but live Gutenberg/generation acceptance is pending and the existing 0.1.2 package is unchanged. Direct inline command, site-wide quota/usage logging, and image-to-image upload remain deferred; upload remains HTTP 501.

### Package/support matrix — PACKAGE VERIFIED; SUPPORT MATRIX BLOCKED
- The approved four-file ZIP allowlist was preserved; the repository-only example was intentionally not bundled without separate approval. Two earlier builds compared byte-for-byte, archive integrity passed, and all four files matched the then-current source. The quota endpoint changed afterward, so repeat build/integrity/source checks before release.
- Documented minimum-version claims have not been exercised across a live WordPress/PHP support matrix. Keep release status blocked until the live gates above and version matrix can run.

## Operating constraints

- Preserve all existing modified and untracked files; the current worktree is not clean.
- Work one dependency-closed task at a time. Keep live runtime/browser acceptance separate from deterministic stubs. The user has authorized use of the WP Invoice Test container; preserve existing data and clean only explicitly created test artifacts.
- Keep deterministic tests, PHP/JavaScript syntax checks, WordPress integration, ComfyUI HTTP behavior, and interactive browser acceptance as separate evidence categories.
- No commit, push, deployment, or cleanup of user artifacts without explicit approval.

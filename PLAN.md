<!-- GENERATED_BY_CODEX_YOLO_PLAN_V1 -->
# Plan

Codex must keep this file updated during each run.

## Goal
Convert the plugin into a free bring-your-own-key image metadata plugin that no longer depends on OneClickContent licensing, credits, trials, or hosted service positioning. Keep the metadata-generation focus, preserve safe existing behavior where possible, and continue improving the codebase toward the baseline-ready WPPB state defined in `SPEC.md`.

## Product outcome we are aiming for
- OCCIDG remains the plugin for AI-generated metadata on existing Media Library images.
- It stays separate from the plugin that creates featured images or one-off images.
- The plugin becomes free to use.
- Users supply their own provider credentials.
- The first supported providers are OpenAI and Gemini.
- The settings UI becomes provider-centric, not license-centric.

## Assumptions
- Existing admin and metadata-generation behavior should be preserved unless a security, correctness, or product-direction issue requires a safe correction.
- Tool binaries may be unavailable in this sandbox, so configuration and local syntax validation may be the only verifiable checks inside some runs.
- The current codebase contains legacy license, trial, credit, and hosted-service assumptions that should be treated as migration targets, not protected behavior.

## Guardrails
- Do not re-merge this plugin with the separate image-generation plugin.
- Do not introduce new paid activation, credit, or trial flows.
- Do not preserve misleading hosted-service copy just to avoid touching UI text.
- Prefer explicit provider setup over hidden automation or magical fallback behavior.
- Keep the plugin slug and text domain stable unless James explicitly asks for a rename or rebrand.

## Questions (non-blocking)
- Confirm the preferred provider order and fallback logic once the first BYO key pass is in place. For now, implement explicit OpenAI and Gemini settings without automatic fallback.
- Decide later whether model selection should be global, provider-specific, or both. For now, provider-specific settings are acceptable.
- Decide whether to add a scripted repo-to-local deploy command so future sessions do not rely on manual selective file copies.

## Workstreams

### 1. Product migration
- Remove all license activation, validation, trial, credits, and purchase flows.
- Remove or replace all user-facing references to OneClickContent as a hosted metadata service.
- Replace license-centric settings with provider-centric settings.

### 2. Provider architecture
- Add or normalize settings for provider selection.
- Add local settings for OpenAI API key and Gemini API key.
- Move generation logic away from OneClickContent endpoints toward provider-specific request handling.
- Preserve the discovered metadata output contract by normalizing provider responses to the same shape: `title`, `description`, `alt_text`, and `caption`.
- Make provider errors understandable in the admin UI.

### 3. UX cleanup
- Rewrite settings-page copy around metadata generation and BYO provider setup.
- Remove usage meters, upgrade CTAs, trial warnings, and credit exhaustion states.
- Keep bulk and single-image actions usable when valid provider configuration exists.

### 4. Code quality and safety
- Preserve or improve sanitization, escaping, nonce checks, and capability checks.
- Keep WPPB structure and continue converging toward WPCS-clean code.
- Update tests and harnesses as the product contract changes.

### 5. Documentation and repo memory
- Keep SPEC.md aligned with the product direction.
- Keep this PLAN.md concrete and execution-ready.
- Update MEMORY.md and PLAYBOOK.md when durable lessons emerge.

## Files likely to change
- `SPEC.md`
- `PLAN.md`
- `MEMORY.md`
- `PLAYBOOK.md`
- `.codex_index.json`
- `README.md`
- `README.txt`
- `occidg.php`
- `includes/class-occidg.php`
- `includes/class-occidg-loader.php`
- `includes/class-occidg-logger.php`
- `admin/class-occidg-admin.php`
- `admin/class-occidg-admin-settings.php`
- `admin/class-occidg-auto-generate.php`
- `admin/class-occidg-bulk-edit.php`
- `admin/class-occidg-license-update.php`
- `admin/js/occidg-admin.js`
- `admin/js/bulk-edit.js`
- `admin/js/settings-bulk-generate.js`
- `admin/js/one-click-error-check.js`
- `admin/css/occidg-admin.css`
- `tests/bootstrap.php`
- `tests/test-occidg-core.php`
- `tests/test-occidg-bootstrap.php`
- `tests/test-occidg-lifecycle.php`

## Sequenced execution plan
1. Audit all license, trial, credit, activation CTA, usage, and OneClickContent hosted-service references across PHP, JS, CSS, and docs. In progress.
2. Rewrite the repo contract files so future runs follow the free BYO-provider direction by default. Completed in this pass for `SPEC.md`; keep tightening as needed.
3. Replace license-key settings and validation UI with provider selection plus OpenAI and Gemini API key settings.
4. Remove trial, credits, usage-meter, and purchase/upgrade prompts from admin UI and JS flows.
5. Replace hosted generation and usage endpoints with provider-specific request handling.
6. Update tests, smoke checks, docs, and repo memory to match the new product contract.
7. Run `npm run fix`, `npm run check`, and relevant tests where tooling exists, then continue iterating until the repo is stable.

## Background job slices

### Slice 1. Queue domain and persistence
- Add a dedicated queue service class for background metadata jobs.
- Store jobs in plugin options with bounded retention and explicit schemas.
- Normalize job lifecycle states: `queued`, `running`, `paused`, `completed`, `completed_with_errors`, `cancelled`, `failed`.
- Capture job inputs needed to resume work: image IDs, field selection snapshot, provider snapshot, override behavior, timestamps, progress counters, and recent failure details.

Acceptance criteria:
- Jobs can be created, fetched, updated, and pruned through pure class methods without relying on browser state.
- A newly created job stores the exact image ID list and total count.
- Progress counters and status timestamps update deterministically.
- Completed and cancelled jobs remain inspectable until retention pruning removes old entries.
- PHPUnit covers job creation, progress updates, and retention/pruning behavior.

### Slice 2. Worker and scheduler
- Add a worker class that processes queued jobs in small batches instead of processing all images in one request.
- Schedule the worker with single cron events and prevent overlapping runs with a lock.
- Requeue active jobs until all image IDs are processed or the job is paused/cancelled.
- Classify per-image outcomes as success, skipped, or failed and record bounded failure details for the UI.

Acceptance criteria:
- Starting a job schedules background work and marks the job as queued.
- A worker pass processes only the configured batch size and persists its checkpoint.
- Concurrent worker runs are prevented by a lock.
- Jobs transition to `completed` or `completed_with_errors` when all items are processed.
- PHPUnit covers batching, lock behavior, and mixed success/failure transitions.

### Slice 3. AJAX orchestration API
- Replace browser-driven “generate all” loops with enqueue/start/status/pause/resume/cancel AJAX endpoints.
- Return normalized status payloads for the queue UI, including totals, percentages, current state text, and recent failures.
- Keep existing nonce and capability protections aligned with the current admin contract.

Acceptance criteria:
- The admin can create a job for “all images” without running generation inside the initiating request.
- Polling returns a stable job payload that the UI can render without extra transformations.
- Pause, resume, and cancel endpoints only affect valid jobs and fail clearly for invalid IDs.
- PHPUnit covers the new endpoint-facing methods at the service/controller layer where possible.

### Slice 4. Bulk-edit and settings UI migration
- Convert the settings bulk-generate and bulk-edit generate-all controls into queue-backed actions.
- Add a visible job status panel with progress, state, counts, pause/resume, cancel, and retry messaging.
- Preserve current missing-key gating and provider messaging.
- Remove the long-running recursive browser loop once queue-backed controls are wired.

Acceptance criteria:
- Clicking “Generate All Metadata” creates a background job and immediately shows a live status panel.
- The UI keeps updating by polling instead of issuing one generation request per image from the browser.
- Users can pause, resume, and cancel from the UI without reloading the page.
- Bulk-edit and settings views share consistent queue state copy and controls.
- JS syntax checks pass and admin styling remains coherent on desktop and mobile widths.

### Slice 5. Retry ergonomics and queue polish
- Add retry support for failed or cancelled jobs by cloning remaining work into a fresh queued job.
- Add lightweight queue summaries and empty states so users understand whether work is idle, active, or finished with errors.
- Surface recent failure rows with image IDs and error text in a bounded list.

Acceptance criteria:
- A failed or cancelled job can be retried without reprocessing already successful items.
- The queue UI clearly distinguishes active jobs, completed jobs, and completed-with-errors jobs.
- Failure details are visible but bounded so the UI and stored options stay small.
- PHPUnit covers retry job creation and remaining-work selection.

## Current handoff status
- The tracked Git checkout now matches the cleaned deployed `1.2.0` plugin copy.
- Public docs position OCCIDG as a free, bring-your-own-key AI image metadata plugin with OpenAI and Gemini support.
- Admin settings and bulk-edit screens reinforce the free plus BYO-key workflow in first-run copy, setup guidance, and generation gating.
- Queue-backed background bulk generation, retry support, and the related PHPUnit coverage are now present in the tracked repo again.
- Validation in the current sync pass: `php -l`, `npm run fix`, `npm run check`, and `npm run test:local` all passed.
- The remaining source-control step is to review, commit, and push the tracked repo changes from the real checkout path.

## Next recommended steps
1. Browser-smoke-test the Settings, Bulk Edit, and attachment-edit generation flows on a working WordPress site.
2. Specifically verify the missing-key gate, provider-key validation flow, model dropdown hydration, and queue retry controls in wp-admin.
3. Review the tracked repo diff, then commit and push the `1.2.0` sync/rebrand work from `/home/jameswilson/.openclaw/workspace/projects/oneclickcontent/repos/oneclickcontent-images`.
4. Restore local WordPress database connectivity, rerun `npm run plugin-check`, and then publish the release once the browser smoke test is clean.

## Progress note
- Provider malformed-response recovery and embedded JSON handling in
  `admin/class-occidg-admin-settings.php` is hardened and test-covered.
- Release metadata, readmes, admin positioning copy, and distribution packaging are aligned for the free BYO-key relaunch.
- UI once-over pass started from the deployed plugin copy after a machine crash.
- Current top UI issues: settings hierarchy still feels stitched together, provider-specific fields are all shown at once, and bulk-edit save/generate statuses are effectively invisible.
- UI once-over pass completed: settings page now has clearer hierarchy, saved-state context, and provider-aware field visibility.
- Bulk edit now exposes visible inline save and generate feedback instead of silently writing into hidden status spans.
- Validation after the UI pass: `php -l` passed for modified PHP files, `node --check` passed for modified admin scripts, `npm run fix` normalized one PHPCS issue automatically, `npm run check` passed, and `npm run test:local` passed.
- Generation actions are now gated when the selected provider has no saved API key: the settings bulk-generate button, bulk-edit bulk-generate button, modal confirm button, single-image generate button, and per-row bulk-edit generate buttons all render disabled and short-circuit in JS when no saved key exists.
- The settings page now updates that gate live when the provider selector changes, so switching to a provider without a saved key disables generation immediately before the settings are saved.
- Provider setup now validates API keys over AJAX before saving them, keeps invalid keys out of the saved settings state, and turns the provider model fields into selects populated from the provider model-list APIs instead of free-text inputs.
- OpenAI model choices are normalized to compatible multimodal chat-model families, while Gemini model choices are built from `baseModelId` values that support `generateContent`, so the saved model value matches the generation request contract.
- The settings screen now auto-hydrates model lists on load for already-saved provider keys when only the fallback single option is present, so pre-existing keys are upgraded into the new select workflow without requiring the user to retype the key.
- OpenAI metadata generation now requests strict structured JSON via `response_format` on `v1/chat/completions` instead of function/tool-calling, which better matches the plugin’s one-shot metadata use case.
- Generation errors now surface provider `details` in the attachment generate flow, bulk-edit row generation, and settings bulk generation so OpenAI API rejections are no longer hidden behind the generic "returned an error response" message.
- Provider request failures now log sanitized provider/model/status/detail context to `plugin-error.log`, and the new OpenAI structured-output plus refusal-detail path is covered by the local PHPUnit harness.
- Background bulk generation is now queue-backed instead of browser-loop-driven: jobs persist in options, process in cron-sized batches with locking, and expose enqueue/status/pause/resume/cancel AJAX actions plus normalized UI payloads.
- The settings and bulk-edit generate-all panels now share the same queue status UI with live polling, progress, summary cards, recent failure rows, and a retry path for failed/cancelled/completed-with-errors jobs.
- The local fallback test harness now covers background job persistence, batching, retry job creation, and the new core AJAX hook wiring, bringing the queue slice coverage to `39` tests and `186` assertions.
- Release packaging is now WordPress upload-shaped again: `npm run dist` builds `oneclickcontent-images.zip` with a top-level `occidg/` folder, excludes dev/runtime-local files, and drops the root wp.org marketing `assets/` directory from the install artifact.

## Commands to run
- npm run fix
- npm run check
- phpunit (if configured)

## Acceptance criteria
- The repo contract clearly states that OCCIDG is a free BYO-key metadata plugin.
- License, trial, credit, and hosted-service references are removed or explicitly queued for removal in the active workstream.
- The settings UX is designed around OpenAI and Gemini provider configuration.
- Bulk generation runs as resumable background jobs instead of a long browser AJAX loop.
- Failed or cancelled background jobs can be retried without reprocessing already successful images.
- PHPCS clean (0 errors, 0 warnings) or explain why not possible.
- Tests pass (if configured).
- PHP syntax validation passes for modified plugin files.
- Current sandbox blocker, when applicable: `phpcs`, `phpcbf`, `phpunit`, `phpmd`, and `composer` may be unavailable, so full convergence may need follow-up in a fuller environment.

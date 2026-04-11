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

## Commands to run
- npm run fix
- npm run check
- phpunit (if configured)

## Acceptance criteria
- The repo contract clearly states that OCCIDG is a free BYO-key metadata plugin.
- License, trial, credit, and hosted-service references are removed or explicitly queued for removal in the active workstream.
- The settings UX is designed around OpenAI and Gemini provider configuration.
- PHPCS clean (0 errors, 0 warnings) or explain why not possible.
- Tests pass (if configured).
- PHP syntax validation passes for modified plugin files.
- Current sandbox blocker, when applicable: `phpcs`, `phpcbf`, `phpunit`, `phpmd`, and `composer` may be unavailable, so full convergence may need follow-up in a fuller environment.

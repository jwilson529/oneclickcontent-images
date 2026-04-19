<!-- GENERATED_BY_CODEX_YOLO_MEMORY_V1 -->
# Project Memory

This file is persistent context for Codex runs. Keep it short and practical.

## Project
- Plugin slug: oneclickcontent-images
- Repo root: /home/jameswilson/.openclaw/workspace/projects/oneclickcontent/repos/oneclickcontent-images

## Non-negotiables
- WordPress Coding Standards (WPCS).
- Tabs for indentation in code.
- No em dashes in assistant output.
- When updating code you must output full updated methods or full updated files when requested.
- Prefer WPPB structure: includes/, admin/, public/, tests/.
- Do not stop mid-run. Keep moving forward in the SPEC. Test and lint.
- Treat OCCIDG as the metadata plugin, not the image-generation plugin.
- The product direction is now a free bring-your-own-key plugin with OpenAI and Gemini support.
- License activation, trial, credits, and hosted OneClickContent service assumptions are migration debt, not features to preserve.

## Tooling workflow
- Primary loop:
  - npm run fix
  - npm run check (writes check.txt)
- Treat check.txt as the backlog.

## Packaging
- Keep development-only files out of the distributable zip, especially `tests/`, Codex runner artifacts, local logs, and tool configs.

## Run recap log
Append a brief recap after each run:
- Date:
- Summary:
- Notable changes:
- Tool results:
- Remaining gaps:

- Date: 2026-04-08
- Summary: Repaired the plugin bootstrap/tooling state and completed the missing WPPB support layers so the repo is closer to baseline-ready development.
- Notable changes: Added i18n and logger classes, introduced the `public/` scaffold, rewired the core loader to own admin/public hooks, hardened admin tab and AJAX nonce handling, added PHPUnit harness tests, fixed the invalid `package.json`, and created `.codex_index.json`.
- Tool results: `node` now parses `package.json`; `php -l` passed for all modified PHP files; `npm run fix`, `npm run check`, and `npm run test:local` all failed because `phpcbf`, `phpcs`, and `phpunit` are not installed in this sandbox.
- Remaining gaps: Full WPCS and PHPUnit convergence still need to be rerun in an environment with the PHP tooling binaries available.

- Date: 2026-04-08
- Summary: Hardened the plugin baseline further by wiring Composer dev-tool declarations, improving admin UX/security details, and extending the PHPUnit fallback harness with core wiring coverage.
- Notable changes: Added Composer `require-dev` entries for WPCS/PHPMD/PHPUnit tooling, updated `npm` scripts to prefer `vendor/bin` and write clear missing-tool diagnostics, fixed admin usage checks to hit the remote OneClickContent endpoint, translated additional user-facing admin strings, added external-link `rel` attributes, added ABSPATH guards to the PHPUnit test files, and created `tests/test-occidg-core.php`.
- Tool results: `npm run fix`, `npm run check`, and `npm run test:local` now fail with explicit missing-tool messages; `check.txt` captures the PHPCS blocker; `php -l` passed for the updated PHP files; a direct PHP smoke test passed with `plugin=occidg version=1.1.15 actions=25 filters=6 logged=yes`.
- Remaining gaps: `composer`, `phpcbf`, `phpcs`, `phpmd`, and `phpunit` are still unavailable in this sandbox, so Composer lock refresh and full WPCS/PHPUnit convergence remain environment-blocked.

- Date: 2026-04-08
- Summary: Tightened the remaining plugin scaffolding and packaging hygiene so the repo is safer to ship and cleaner to continue developing in YOLO mode.
- Notable changes: Added `index.php` directory guards for `admin/assets`, `admin/css`, `admin/js`, `assets`, `languages`, and `tests`; refined the PHPUnit bootstrap so the fallback harness can establish `ABSPATH` without breaking WP test-suite runs; updated `.gitignore`; and hardened the `dist` script to exclude `tests/` plus local Codex runner artifacts.
- Tool results: `npm run fix`, `npm run check`, and `npm run test:local` still fail fast because `phpcbf`, `phpcs`, and `phpunit` are not installed; `check.txt` records the PHPCS blocker; `git diff --check` passed; `php -l` passed for every PHP file in the repo; and a direct PHP smoke test passed with `plugin=occidg version=1.1.15 actions=25 filters=5`.
- Remaining gaps: Full WPCS, PHPMD, PHPUnit, and Composer lock convergence remain blocked until `composer`, `phpcbf`, `phpcs`, `phpmd`, and `phpunit` are available in the environment.

- Date: 2026-04-08
- Summary: Extended the offline-ready test harness so activation and deactivation paths are covered even when the full WordPress and PHPUnit toolchain is unavailable.
- Notable changes: Added `add_option()`, `get_option()`, and `delete_option()` test doubles to `tests/bootstrap.php`; added `tests/test-occidg-lifecycle.php` for activation/deactivation coverage; and refreshed `.codex_index.json` plus run metadata to include the new lifecycle test surface.
- Tool results: `npm run fix`, `npm run check`, and `npm run test:local` still fail fast because `phpcbf`, `phpcs`, `phpunit`, and `composer` are missing; `check.txt` records the PHPCS blocker; `git diff --check` passed; `php -l` passed for every PHP file; the core smoke test passed with `plugin=occidg version=1.1.15 actions=25 filters=5 loaded_textdomain=no`; and a lifecycle smoke test passed with `activation_redirect=yes first_time=no trial_expired=no log_exists=yes`.
- Remaining gaps: Full WPCS, PHPMD, PHPUnit, and Composer-backed convergence still depend on `composer`, `phpcbf`, `phpcs`, `phpmd`, and `phpunit` being available in the environment.

- Date: 2026-04-08
- Summary: Aligned the plugin bootstrap more closely with AGENTS/SPEC by moving text-domain loading into the main plugin file, cleaning up injected admin dependencies, and covering the bootstrap/uninstall paths with direct tests.
- Notable changes: Added `occidg_load_textdomain()` to [occidg.php](/home/jameswilson/.openclaw/workspace/projects/oneclickcontent/repos/oneclickcontent-images/occidg.php), removed the duplicate locale hook from [includes/class-occidg.php](/home/jameswilson/.openclaw/workspace/projects/oneclickcontent/repos/oneclickcontent-images/includes/class-occidg.php), injected bulk-edit/settings collaborators into [admin/class-occidg-admin.php](/home/jameswilson/.openclaw/workspace/projects/oneclickcontent/repos/oneclickcontent-images/admin/class-occidg-admin.php), extended [tests/bootstrap.php](/home/jameswilson/.openclaw/workspace/projects/oneclickcontent/repos/oneclickcontent-images/tests/bootstrap.php) with lifecycle hook stubs, added [tests/test-occidg-bootstrap.php](/home/jameswilson/.openclaw/workspace/projects/oneclickcontent/repos/oneclickcontent-images/tests/test-occidg-bootstrap.php), and implemented uninstall cleanup in [uninstall.php](/home/jameswilson/.openclaw/workspace/projects/oneclickcontent/repos/oneclickcontent-images/uninstall.php).
- Tool results: `php -l` passed for every PHP file in the repo; `git diff --check` passed; the bootstrap smoke test passed with `activation=1 deactivation=1 actions=25 filters=5 plugins_loaded=2`; the uninstall smoke test passed with `options=11 transients=3`; `npm run fix`, `npm run check`, and `npm run test:local` still fail fast because `phpcbf`, `phpcs`, and `phpunit` are not installed.
- Remaining gaps: Full WPCS/PHPMD/PHPUnit convergence remains blocked by missing `composer`, `phpcbf`, `phpcs`, `phpmd`, and `phpunit` binaries in this sandbox.

## Run recap (2026-04-08 23:04:33)
- Exit code: 0
- Docker: skipped
- PHPCS: clean
- PHPCS totals: 0 errors, 0 warnings
- Top PHPCS violations: none
- Failed tests: none
- Git: main @ 9464031
- Changed files (0): none

- Date: 2026-04-11
- Summary: Reframed the repo contract around a larger OCCIDG product migration so future runs can work in bigger chunks without drifting.
- Notable changes: Updated `SPEC.md` and `PLAN.md` to define OCCIDG as a free bring-your-own-key metadata plugin, kept it separate from the image-generation plugin, and explicitly marked license, trial, credits, and hosted OneClickContent service logic as migration targets.
- Tool results: Documentation-only contract pass; code migration work remains ahead.
- Remaining gaps: The actual PHP, JS, settings, and generation flows still contain hosted-service and licensing logic that must be replaced in follow-up implementation passes.

- Date: 2026-04-11
- Summary: Started the first real BYO-provider migration slices and removed a large chunk of the old license/trial scaffolding from both the repo and the local deployed plugin copy.
- Notable changes: Added provider settings for OpenAI and Gemini, rewired metadata generation toward direct provider calls while preserving the normalized `title` / `description` / `alt_text` / `caption` contract, removed the old license AJAX hooks and dead license updater/error-check files, simplified the admin and bulk-edit UI away from credits/trial messaging, updated readmes to describe the BYO-key product, improved provider error handling, and synced the current code pass into `/home/jameswilson/sites/siteground/oneclickcontent-com/code/wp-content/plugins/occidg`.
- Tool results: `npm run fix` passed; `npm run check` passed with an empty `check.txt`; `php -l` passed on the changed PHP files in both the repo and the synced local plugin copy; `npm test` remains blocked because the local PHP CLI is missing `mbstring`; pushed commits `17b040c`, `4ef7c7e`, and `850eef2` to `origin/main`.
- Remaining gaps: Provider request/response handling still needs deeper hardening, README/changelog/version polish remains, local site behavior still needs browser-level verification, and PHPUnit cannot run until `mbstring` is available.

- Date: 2026-04-11
- Summary: Finished the obvious anti-upsell cleanup, then recovered the local site from a bad partial OCCIDG deploy sync and documented the handoff state.
- Notable changes: Removed the remaining hosted-service and upsell remnants from runtime files, pushed `2ec2d65` (`Remove final OCCIDG upsell remnants`), restored missing local runtime files after the deployed plugin fatally failed to load, and updated `README.md`, `README.txt`, `PLAN.md`, and `PLAYBOOK.md` with handoff notes and the deploy-sync caution.
- Tool results: `npm run fix` passed; `npm run check` passed with empty `check.txt`; `php -l` passed on the restored local runtime files; local site returned HTTP 200 again after restoring `includes/class-occidg-i18n.php` and the `public/` directory.
- Remaining gaps: Add a safer full-plugin sync path for repo-to-local deploys, browser-test the admin screens, harden provider responses further, and rerun PHPUnit when `mbstring` is available.

- Date: 2026-04-18
- Summary: Finished the release-prep pass for the free BYO-key relaunch and moved the plugin to version `1.2.0`.
- Notable changes: Bumped plugin and package versions to `1.2.0`, updated the public readmes to present OCCIDG as a free bring-your-own-key AI image metadata plugin, removed internal migration/handoff notes from public docs, refreshed the admin welcome and bulk-edit positioning copy, aligned package metadata URLs, tightened the zip exclusions so dev-only files do not ship, and refreshed `.codex_index.json` for this non-git workspace.
- Tool results: `npm run fix` passed; `npm run check` passed with an empty `check.txt`; `npm run test:local` passed with `19` tests and `64` assertions; `npm run dist` produced `oneclickcontent-images.zip`; `unzip -l oneclickcontent-images.zip` confirmed the release zip excludes `README.md`, `.gitignore`, `CLEAN_DOCKER_REPRO.md`, and `tests/`; `npm run plugin-check` failed because the local WordPress install could not establish a database connection.
- Remaining gaps: Browser smoke-test the installed plugin on a working site, restore local DB connectivity so `wp plugin check` can run, and then publish the `1.2.0` release artifact.

- Date: 2026-04-18
- Summary: Completed a UI once-over pass on the deployed OCCIDG plugin copy after the machine crash and wrote the state back into repo notes because this path is not a git repo.
- Notable changes: Added a settings-screen sidebar summary and quick-start cards, improved settings hierarchy styling, converted metadata and toggle controls into clearer card-like choices, hid inactive provider rows based on the selected provider, refreshed the first-run/settings layout shell, exposed inline bulk-edit save/generate statuses that were previously invisible, and fixed the generated-status markup so it no longer emits duplicate `id="image-metadata-table"` tables.
- Tool results: `php -l` passed for `admin/class-occidg-admin.php` and `admin/class-occidg-admin-settings.php`; `node --check` passed for `admin/js/occidg-admin.js`, `admin/js/bulk-edit.js`, and `admin/js/settings-bulk-generate.js`; `npm run fix` auto-fixed one PHPCS issue in `admin/class-occidg-admin.php`; `npm run check` passed; `npm run test:local` passed with `19` tests and `64` assertions.
- Remaining gaps: Browser-test the admin screens on a live local site to confirm the visual pass feels right in WordPress proper, and if commits are needed, sync these changes into the tracked repo copy that actually has `.git`.

- Date: 2026-04-18
- Summary: Added missing-key gating for metadata generation actions so users cannot start image metadata generation until a saved provider API key exists.
- Notable changes: Added a reusable generation gate helper test, disabled the settings-page bulk-generate button, bulk-edit bulk-generate button, modal confirm button, attachment generate button, and bulk-edit row generate buttons when the selected provider lacks a saved key, synced the settings-page gate state live when the provider selector changes, and scoped the generic admin generate handler away from the bulk-edit table so row actions keep a single code path.
- Tool results: `php -l` passed for `admin/class-occidg-admin.php`, `admin/class-occidg-bulk-edit.php`, `admin/class-occidg-admin-settings.php`, and `tests/test-occidg-admin-settings.php`; `node --check` passed for `admin/js/occidg-admin.js`, `admin/js/bulk-edit.js`, and `admin/js/settings-bulk-generate.js`; `npm run fix` ran and auto-fixed PHP formatting; `npm run check` passed; `npm run test:local` passed with `21` tests and `74` assertions.
- Remaining gaps: Browser-test the missing-key gate in WordPress on the Settings tab, Bulk Edit tab, and attachment-edit screen, and if commits are needed, sync this deployed copy back into the tracked repo because this path still has no `.git`.

- Date: 2026-04-18
- Summary: Swapped provider setup from raw text fields to an AJAX-driven key-validation and model-loading workflow.
- Notable changes: Added provider-key validation/save AJAX handlers, added provider-model save AJAX handling, rendered provider model fields as selects backed by cached provider model lists, normalized OpenAI model choices to compatible multimodal chat-model families, normalized Gemini choices from `baseModelId` values that support `generateContent`, blocked the settings form from overwriting unsaved or invalid API keys, and added helper coverage for OpenAI and Gemini model-list normalization.
- Tool results: `php -l` passed for `admin/class-occidg-admin-settings.php`, `admin/class-occidg-admin.php`, `includes/class-occidg.php`, and `tests/test-occidg-admin-settings.php`; `node --check` passed for `admin/js/occidg-admin.js`, `admin/js/bulk-edit.js`, and `admin/js/settings-bulk-generate.js`; `npm run fix` auto-fixed 2 PHPCS issues in `admin/class-occidg-admin-settings.php`; `npm run check` passed; `npm run test:local` passed with `23` tests and `76` assertions.
- Remaining gaps: Browser-test the provider settings UX against real OpenAI and Gemini keys, especially the invalid-key path, the model-dropdown hydration after a successful save, and the interaction between AJAX key validation and the normal settings form on a live WordPress screen.

- Date: 2026-04-18
- Summary: Fixed the one-model-select regression for already-saved provider keys.
- Notable changes: The settings JS now auto-validates and hydrates provider model lists on page load when a saved key exists but the select only has the fallback single option, which upgrades pre-existing saved keys into the new AJAX-loaded model-select workflow without retyping the key.
- Tool results: `node --check admin/js/occidg-admin.js` passed.
- Remaining gaps: Browser-test the load-time hydration with existing saved OpenAI and Gemini keys to confirm the select expands beyond the fallback single option and that the background validation state reads cleanly in the UI.

- Date: 2026-04-18
- Summary: Hardened the OpenAI generation path after live user testing reported generic OpenAI error responses while Gemini continued working.
- Notable changes: Switched OpenAI metadata generation from tool-calling to strict structured JSON output on `v1/chat/completions`, added provider failure logging with sanitized provider/model/status/detail context, exposed provider `details` in the attachment generate flow, bulk-edit row generation, and settings bulk generation UI, and added PHPUnit coverage for OpenAI refusal-detail reporting plus the strict response-format helper.
- Tool results: `php -l admin/class-occidg-admin-settings.php` passed; `php -l tests/test-occidg-admin-settings.php` passed; `node --check admin/js/occidg-admin.js`, `admin/js/bulk-edit.js`, and `admin/js/settings-bulk-generate.js` passed; `npm run fix` passed with no violations; `npm run check` passed with empty `check.txt`; `npm run test:local` passed with `25` tests and `84` assertions.
- Remaining gaps: Re-test OpenAI generation in a working WordPress admin session with the real saved key/model state and inspect `plugin-error.log` if any provider-specific rejection still occurs; local `wp option get` and plugin-check remain blocked by the site’s database connection failure in this environment.

- Date: 2026-04-19
- Summary: Synced the tracked Git checkout back up to the cleaned deployed `1.2.0` plugin copy and tightened the remaining product positioning around free BYO-provider metadata generation.
- Notable changes: Synced the real source repo at `/home/jameswilson/.openclaw/workspace/projects/oneclickcontent/repos/oneclickcontent-images` from the working deployed `occidg` copy, carried over the queue-backed background bulk generation files and tests, kept the plugin on version `1.2.0`, refreshed the admin page title toward generic AI image metadata wording, and removed the last explicit `OneClickContent API` wording from runtime docblocks and package metadata.
- Tool results: `php -l` passed for the modified bootstrap, admin, queue, and test files; `npm run fix` passed with no violations; `npm run check` passed with empty `check.txt`; `npm run test:local` passed with `39` tests and `186` assertions; a post-sync tree diff confirmed the tracked repo matches the deployed plugin copy aside from intentionally excluded local artifacts.
- Remaining gaps: Commit and push the tracked repo changes, browser-smoke-test the Settings, Bulk Edit, and attachment-edit flows on a working WordPress site, and regenerate `.codex_index.json` inside the tracked repo if a later run needs the manifest fully refreshed.

- Date: 2026-04-19
- Summary: Replaced the old browser-driven bulk-generation loop with a queue-backed background job system and finished the retry/polish slice.
- Notable changes: Added `Occidg_Background_Jobs`, `Occidg_Background_Worker`, and `Occidg_Background_Jobs_Admin`; wired cron batching with a transient lock; snapshot provider/model/language/field settings into persisted jobs; replaced settings and bulk-edit generate-all flows with enqueue plus polling actions; added pause/resume/cancel/retry controls; restored the latest active or retryable job on load; and added queue/failure PHPUnit coverage plus the retryable terminal-job UI empty state.
- Tool results: `php -l` passed for all modified PHP files; `node --check admin/js/settings-bulk-generate.js` passed; `npm run fix` auto-fixed PHPCS formatting in the new queue classes; `npm run check` passed with empty `check.txt`; `npm run test:local` passed with `39` tests and `186` assertions.
- Remaining gaps: Browser-test the queue-backed bulk-generation flows in WordPress with real OpenAI and Gemini keys, verify WP-Cron actually advances queued jobs on the local/staging site, and if commits are required sync these changes into the tracked repo copy because this deployed path still has no `.git`.

- Date: 2026-04-19
- Summary: Tightened the release surface after verifying the actual install zip, fixing the packaging shape and ignoring more local runner/test artifacts.
- Notable changes: Updated `.gitignore` to ignore `.codex-backups/`, PHPUnit cache files, and coverage output; changed `npm run dist` so the archive is built as a top-level `occidg/` directory instead of a flat root zip; and excluded the root `assets/` wp.org marketing files from the install artifact while preserving runtime assets under `admin/assets/`.
- Tool results: `node` successfully parsed `package.json`; `npm run dist` passed; the rebuilt `oneclickcontent-images.zip` now lists `occidg/` as its top-level folder and weighs `249K`; spot checks confirmed it excludes `assets/`, `tests/`, `README.md`, `package.json`, `plugin-error.log`, and other local/dev-only files.
- Remaining gaps: I still have not executed an actual browser-based Upload Plugin install from this zip in a working WordPress admin, and `wp plugin check` remains blocked here by the local database connection problem from earlier runs.

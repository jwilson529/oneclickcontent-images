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

<!-- GENERATED_BY_CODEX_YOLO_PLAYBOOK_V1 -->
# Plugin Build Notes (Reusable)

This file captures reusable lessons from building WordPress plugins in this repo style and environment. It is intentionally generic and should apply to future plugins built with similar tooling.

## Environment Assumptions
- Local dev runs inside a full WordPress install; tests run in Docker.
- The repo may include a local WP core checkout and WP test suite to speed up CI and repeatable tests.
- Use WPPB-style structure with a main plugin file, `includes/`, `admin/`, `public/`, `tests/`.

## Test Harness Lessons
- Local `phpunit` should not be the primary path if the DB is only available in Docker. A local run should fail fast or be optional.
- Docker test container must install WP core + WP test suite and then run PHPUnit.
- WordPress tests require Yoast PHPUnit Polyfills; set `WP_TESTS_PHPUNIT_POLYFILLS_PATH` or load Composer autoload before the WP bootstrap.
- WP test config must define required constants (e.g., `WP_TESTS_DOMAIN`, `WP_TESTS_EMAIL`, `WP_TESTS_TITLE`, `WP_PHP_BINARY`).
- The WP tests config must be environment-aware (local vs Docker paths) instead of hardcoding `/work/...`.
- For Docker DB creation, prefer a `mysql` invocation that works with MariaDB clients (avoid unsupported flags).
- If local tests depend on a `.wp-tests` directory, ensure bootstrap guards for missing paths and fail with a clear error to avoid confusing PHPUnit failures.
- Fallback harnesses can stub small option APIs (`add_option()`, `get_option()`, and `delete_option()`) so activation and deactivation flows stay unit-testable without loading full WordPress.

## Tooling and Standards
- Use WPCS (Core/Docs/Extra) with `phpcs.xml.dist` and `npm run check` writing to `check.txt`.
- Provide `npm run fix` and `npm run check` scripts for repeatable linting.
- Keep Composer dev dependencies to standard tooling: PHPCS/WPCS, PHPMD, PHPUnit, and PHPUnit Polyfills.
- If Composer installs PHPCS/WPCS, ensure the installer is allowed in Composer config.
- Keep WordPress Coding Standards enforced in every PHP file: docblocks, short arrays, tabs for indent, no trailing whitespace, and `defined( 'ABSPATH' ) || exit;` guards.
- Add `index.php` placeholders to shipped subdirectories (`admin/css`, `admin/js`, `admin/assets`, `assets`, `languages`, and similar) to prevent directory listing exposure.

## Packaging and Distribution
- Exclude `tests/`, local runner artifacts, logs, and tool config files from distributable zips so production installs only receive runtime plugin assets.
- Keep release packaging rules explicit in the build script even if `.gitignore` also filters local noise.
- When syncing this repo into a separate local WordPress plugin copy, do not rely on selective one-off file copies unless you verify every required runtime path. Missing bootstrap files like `includes/class-occidg-i18n.php` or a whole runtime directory like `public/` can fatal the site even when repo lint and syntax checks pass.

## Plugin Architecture Expectations
- Use a loader class to register actions and filters.
- If repo rules require the main plugin file to own i18n bootstrapping, let the bootstrap hook a small function that delegates to the i18n class rather than duplicating text-domain logic across runtime paths.
- Avoid anonymous hooks when named methods help testability and unhooking.
- Implement settings and logging classes early because they affect admin UX and diagnostics.
- Make internationalization explicit with a text domain and `load_plugin_textdomain` in the main bootstrap.
- Provide a logger early and make it configurable via a constant or filter so tests can redirect logs to a temp file.
- For OCCIDG specifically, treat provider abstractions as first-class architecture and treat old license or hosted-service layers as removable migration debt.
- For OCCIDG local deployment work, treat the tracked repo and the deployed local site copy as separate artifacts that need disciplined sync steps.

## Caching and Performance
- Use object cache + transients with a cache-buster option for invalidation.
- Avoid full table scans and big in-memory result sets; query in chunks.
- Keep sitemap generation deterministic and ordered for caching and diffing.

## Security and Data Handling
- Sanitize all inputs, escape all outputs.
- Admin actions and meta updates must validate nonces and capabilities.
- Use `$wpdb->prepare()` for direct DB access.

## Debug and Observability
- Provide a logger that can be configured via filter to a safe log location.
- When debug mode is enabled, emit headers or metadata that expose cache state, generation time, and exclusions.

## WPCS Gotchas and Fixes
- WPCS flags direct filesystem calls in PHP and tests; prefer `WP_Filesystem()` + `$wp_filesystem` and `wp_delete_file()`.
- Avoid using reserved keywords (like `default`) for parameter names in PHPDoc or method signatures.
- PHPUnit bootstraps that define ABSPATH can satisfy "no direct access" guards; add a const in `phpunit.xml.dist` if needed.

## Settings + Options Patterns
- Register settings with a sanitize callback and merge with defaults using `wp_parse_args`.
- Use a single option array for plugin settings to reduce option churn where practical.
- Admin settings pages should use core `settings_fields()` and `do_settings_sections()` for compatibility.
- When migrating from monetized hosted flows to BYO credentials, rewrite user-facing labels first so the UI stops lying even before every internal option name is cleaned up.
- Prefer provider-centric option names and copy over license-centric wording.

## Data Storage and Queries
- WPCS is strict about `$wpdb->prepare()` usage; avoid interpolating table names/placeholder strings.
- When dynamic `IN (...)` lists are needed, prefer safer alternatives (e.g., looped updates, or WP_Query offsets).
- If you need persistent state between cron runs, store a small crawl state array in a single option.
- Keep storage bounded with pruning so tables remain small.

## Cron and Background Work
- Use a transient lock to prevent overlapping cron runs.
- Schedule events with a small future offset to avoid immediate contention after activation.
- Never run heavy work on frontend requests; hook to WP Cron or background processes.

## Notifications
- Send one summary email per run with only new/changed issues to prevent alert fatigue.
- Cap notification lists to a small number of items, with a “and N more” line for readability.

## Indexing and Autonomy Workflow
- Maintain a lightweight repo index file to avoid re-reading the full tree on every pass.
- Update the index after structural or tooling changes.
- Prefer conservative defaults and keep moving forward without blocking on confirmations.
[2026-04-08 23:04:33] oneclickcontent-images | exit=0 | docker=skipped | phpcs=clean (0e/0w) | top_phpcs=none | failed_tests=none | git=main@9464031 | files=0 | port=n/a | 

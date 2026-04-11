<!-- GENERATED_BY_CODEX_YOLO_SPEC_V1 -->
# SPEC

This SPEC defines how Codex operates inside this repository and the product direction it must preserve while making changes.

Codex must read the following files before making changes:
- AGENTS.md
- SPEC.md
- MEMORY.md
- PLAN.md
- PLAYBOOK.md

## Product North Star

This repository is the home of **OneClickContent Image Detail Generator** as a **free bring-your-own-key WordPress plugin**.

The plugin's job is to:
- generate AI-powered metadata for existing Media Library images
- help users fill or improve image title, alt text, caption, and description
- support user-supplied provider credentials
- remain separate from any plugin that generates the actual images

The plugin must **not** behave like a hosted SaaS client anymore.

That means the repository direction is now:
- no license activation flow
- no subscription validation flow
- no trial logic
- no credit or usage-meter monetization logic
- no dependency on OneClickContent hosted API endpoints for core functionality
- no upgrade CTA copy that assumes a paid hosted service

## Provider Direction

The first-class provider model is:
- OpenAI API key
- Gemini API key

Implementation rules:
- users provide their own keys in plugin settings
- provider settings are stored locally in WordPress options
- provider selection must be explicit and understandable in the UI
- safe defaults are preferred over clever automation
- unless PLAN.md says otherwise, do not invent cross-provider fallback logic silently
- when in doubt, require a chosen provider and its corresponding key

## Absolute Execution Rules

- Never pause to ask for confirmation, review, saving, or continuation.
- Never block on unanswered questions.
- If a choice must be made, pick conservative, standards aligned defaults and continue.
- If clarification would improve quality, record it in PLAN.md under "Questions (non-blocking)" but do not stop.
- Goal: Keep moving forward in the SPEC. Test and lint.

---

## 1. Repository Assumptions

This repository is always a WordPress plugin. The root directory contains or should contain:

- AGENTS.md
- SPEC.md (this file)
- PLAYBOOK.md
- package.json (contains fix and check scripts)
- WPPB-style folders: includes/, admin/, public/, tests/
- Optional runner artifacts:
  - .codex_index.json
  - .codex_run_meta.json
  - .codex_tasks.csv
  - .codex-backups/

If any required part is missing or incomplete, Codex must create or repair it autonomously.

---

## 2. Product Boundaries

Codex must preserve these product boundaries unless the user explicitly changes them:

- This plugin is for **image metadata generation**, not image creation.
- The separate featured-image or one-off image generation plugin remains a separate product.
- The admin UX should talk about providers, models, metadata fields, and automation settings, not licenses, trials, credits, or subscriptions.
- Any remaining references to OneClickContent as a hosted generation service are technical debt and should be removed or replaced.
- Existing settings or code paths that only exist to support hosted-service monetization should be removed as part of the migration when safe.

---

## 3. Execution Modes

Codex may be invoked under different modes. These modes are advisory and allow flexibility.

Supported modes include:

- yolo (full autonomy, default)
- fix-only (linters and autofixes, no structural work)
- test-only (focus on PHPUnit)
- analyze (classify repo and improve by changes)
- scaffold (create missing WPPB structure, tests, logging, tooling)
- bootstrap (scaffold then converge)
- product-migration (remove hosted-service assumptions and convert to BYO providers)
- release (optional, future)

If no mode is specified, treat the run as yolo.

For this repo, `product-migration` is a normal safe mode and does not require separate approval.

---

## 4. Workflow: Fix, Check, Converge

Codex must use an iterative convergence loop:

1. Apply changes based on AGENTS.md, repository state, and this product direction.
2. Run npm run fix
3. Run npm run check (writes check.txt)
4. Read check.txt and treat violations as the backlog
5. Continue until check.txt shows:
   - errors == 0 and warnings == 0
   or the runner reaches the iteration cap

Notes:
- If tests fail and block execution, fix tests first.
- Do not stop mid-run. Keep moving forward.
- During the BYO-key migration, correctness and UX clarity beat preserving obsolete licensing code.

---

## 5. Required Functional Direction

Codex should move the plugin toward this functional target:

### 5.1 Settings UX

The settings page should center on:
- provider selection
- OpenAI API key
- Gemini API key
- model selection relevant to the chosen provider
- metadata field toggles
- overwrite behavior
- language and automation options

The settings page should not center on:
- licenses
- activation
- subscriptions
- credit purchases
- free trials
- usage bars tied to monetization

### 5.2 Generation Flow

Metadata generation should:
- use the selected provider and its saved key
- fail clearly when the required key is missing
- return actionable admin-facing errors
- avoid vendor lock assumptions in method names, settings names, and UI copy when practical

### 5.3 Admin Messaging

Admin copy should emphasize:
- what metadata will be generated
- which provider is being used
- what setup is required
- how to fix missing credentials or invalid provider responses

Admin copy should avoid:
- upsell language
- hosted service claims
- license validation language
- credit exhaustion language

---

## 6. Tests and Diagnostics

If PHPUnit, PHPMD, or other tools exist:

- Tools must be respected.
- Failures must not be ignored.
- Test failures should be corrected before chasing style violations when failures block execution.
- New code should have test coverage where appropriate.
- When behavior changes from hosted-service licensing to BYO provider settings, tests must be updated to match the new contract.

---

## 7. Indexing, Cached State, and Manifest

Codex may use .codex_index.json to reduce full repo re-reads:

- If manifest commit matches current commit and tree is clean:
  - Use the manifest and avoid full re-index.
- If commit differs or tree is dirty:
  - Re-index and rewrite the manifest.

---

## 8. Modernization Policy: Safe vs Surgery

Codex must read the MODERNIZE_POLICY environment variable when set.

### 8.1 Safe (default)

If MODERNIZE_POLICY is safe or unset:

- Preserve existing structure as much as possible.
- Do not refactor large procedural sections into class-based architecture unless trivial.
- Do not change public APIs, hook names, or function signatures unless they are tightly coupled to removed licensing behavior.
- Do not remove files or rename directories unless they are clearly obsolete and replaced safely.
- May add:
  - PHPCS fixes
  - ABSPATH guards
  - logging
  - tests
  - i18n wrappers
  - sanitization and escaping
  - fixes for deprecated APIs
  - security fixes
  - provider-setting abstractions
  - WPPB scaffolding only if the plugin is basically empty or missing structure
- May remove:
  - license validation code
  - trial enforcement code
  - hosted-service CTA UI
  - credits and usage logic tied only to monetization
  - OneClickContent hosted endpoints that are no longer part of the product

### 8.2 Surgery

If MODERNIZE_POLICY is surgery, Codex may:

- Create or rearrange WPPB directories
- Extract procedural code into classes
- Split monolithic files into smaller modules
- Consolidate duplicated logic
- Add provider service classes or adapters
- Add or update tests for extracted logic
- Rename old license-centric internals where needed to fit the new product direction

### 8.3 Behavioral Guarantees

Regardless of policy, Codex must:

- Preserve the plugin header, slug, and text domain unless explicitly instructed otherwise
- Keep or improve security posture
- Converge toward PHPCS clean where possible
- Update or add tests when behavior changes
- Prefer the new BYO-key contract over preserving obsolete monetization behavior

---

## 9. Naming and Migration Guidance

During the migration away from hosted-service assumptions:

- keep external plugin identity stable unless the user asks for a rebrand
- prefer incremental renames over risky wide rewrites
- update user-facing text first when it is misleading
- remove dead license and usage logic instead of leaving half-disabled code paths behind
- record non-blocking migration follow-ups in PLAN.md

If a legacy option name is still used internally for compatibility, that is acceptable temporarily, but new user-facing labels should reflect the BYO provider model.

---

## 10. Archival and Backup Behavior

Backups, metadata, run summaries, or changelogs may be written to:

- .codex-backups/
- .codex_index.json
- .codex_run_meta.json
- .codex_tasks.csv

Codex must treat these artifacts as protected and must not lint or rewrite them unless the run specifically updates managed repo metadata.

---

## 11. Uncertainty Policy

When requirements are unclear:

- Choose the most conservative, standards aligned implementation.
- Prefer scaffolding, interfaces, placeholders, or TODOs over blocking execution.
- Never ask the user to choose between approaches mid-run.
- Record notes in PLAN.md if helpful, but continue.
- For provider support, default to explicit configuration over hidden fallback behavior.

---

## 12. Completion Criteria

A session is considered converged when:

- check.txt indicates 0 errors and 0 warnings, or the runner hits its iteration limit after making best effort progress
- tests pass (when configured)
- AGENTS.md rules are satisfied
- the repo is in a better state than it started
- any removed hosted-service behavior has been replaced with a coherent BYO-key UX or clearly documented as the next step

---

## 13. Summary of Required Behavior

- Follow AGENTS.md and this SPEC.
- Preserve the product boundary: metadata plugin, not image generator.
- Use npm run fix and npm run check to converge.
- Treat check.txt as the backlog.
- Remove hosted-service licensing, credits, and trial assumptions as part of normal product work.
- Prefer explicit BYO OpenAI and Gemini provider settings.
- Create missing elements instead of asking for guidance.
- Use cached index when valid.
- Maintain autonomy and keep moving forward.

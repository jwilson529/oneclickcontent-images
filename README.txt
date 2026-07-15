=== OneClickContent - Image Detail Generator ===
Contributors: jwilson529
Tags: images, seo, alt-text, openai, gemini
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.2
Stable tag: 2.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Free AI-powered image metadata generation for existing Media Library images using your own OpenAI or Gemini API key.

== Description ==

**OneClickContent - Image Detail Generator** is a free, bring-your-own-key plugin for enhancing existing WordPress images with AI-generated metadata.

Use your own **OpenAI** or **Gemini** API key to generate:
- alt text
- image titles
- captions
- descriptions

This plugin is focused on metadata generation for images already in your Media Library. It does not create images.

SVG attachments remain available for audits and manual metadata editing. AI metadata generation skips SVG files consistently across single-image, bulk, automatic, background, and WP-CLI workflows without making a provider request.

**Key Benefits:**
- Improve SEO for image search and content relevance
- Improve accessibility with descriptive alt text
- Save time on repetitive metadata writing
- Use your own provider account and models
- Bulk-generate metadata across your library
- Control which fields get updated
- Run preflight counts and dry runs before making provider requests
- Review, edit, approve, reject, or defer field-level suggestions
- Process resumable batches with pause, resume, cancel, and retry controls
- Restore individual fields or complete batches from immutable change history

== Features ==

- Bring-your-own-key support for OpenAI and Gemini
- Single-image generation inside the Media Library
- Bulk generation for existing image attachments
- Auto-generate on upload
- Multilingual output based on plugin settings
- Manual editing after generation
- Forward-compatible OpenAI model filtering for GPT-5.6 API model IDs when OpenAI exposes them to API accounts
- Safe fill-missing, suggestion, overwrite-confirmation, and dry-run modes
- Confidence-aware review with decorative-image decisions
- Custom capabilities, CSV exports, environment-managed API keys, and WP-CLI commands

== External Services ==

This plugin connects directly to the AI provider you configure.

= OpenAI =
- Endpoint: `https://api.openai.com/v1/chat/completions`
- Data sent: image data and generation instructions needed to return image metadata
- Purpose: generate title, description, alt text, and caption
- Terms: https://openai.com/policies/terms-of-use/
- Privacy: https://openai.com/policies/privacy-policy/

= Gemini =
- Endpoint: `https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent`
- Data sent: image data and generation instructions needed to return image metadata
- Purpose: generate title, description, alt text, and caption
- Terms: https://ai.google.dev/terms
- Privacy: https://policies.google.com/privacy

== Third-party Libraries ==

- **DataTables (with Buttons and HTML5 Export)**: (v2.2.2, Buttons v3.2.2) used for table display and export functionality.
  Sources:
  - [DataTables GitHub](https://github.com/DataTables/DataTablesSrc/releases/tag/2.2.2)
  - [Buttons GitHub](https://github.com/DataTables/Buttons/releases/tag/3.2.2)

== Source Code ==

Full source code, including unminified JavaScript files, is available at the [GitHub Repository](https://github.com/jwilson529/oneclickcontent-images).

== Installation ==

1. In WordPress, go to **Plugins > Add New Plugin > Upload Plugin**.
2. Choose the plugin ZIP and click **Install Now**.
3. Activate **OneClickContent - Image Detail Generator**.
4. Open **Image Metadata** in the WordPress admin menu.
5. Choose OpenAI or Gemini, add the matching API key, select a model and language, and choose the metadata fields to generate.
6. Save the settings, then open **Image Metadata > Image Library**.

For a manual installation, upload the plugin folder to `/wp-content/plugins/occidg/`, then activate it from the Plugins screen.

== Quick Start ==

1. Keep overwrite disabled for the safest starting point.
2. Open **Image Metadata > Image Library**.
3. Find an image and click **Preview**.
4. Compare the current and suggested values. Preview does not change the image.
5. Edit a suggestion if needed, then click **Use this suggestion** for each value you approve.
6. Use **Generate** when you want the current plugin rules applied automatically. With the recommended defaults, Generate fills empty selected fields and preserves existing metadata.

Whitespace-only fields are treated as empty. SVG attachments can be edited manually, but AI generation skips them.

== How to Process a Batch ==

1. Open **Image Metadata > Dashboard** and review what is missing.
2. Under **Choose what to run**, select the fields and a small starting batch such as 10 or 25 images.
3. Leave the mode on **Preview only - change nothing**, then click **Run Preview**.
4. Review the estimated images, fields, provider requests, and cost.
5. Open **Advanced batch options** and choose one of these modes:
   * **Create suggestions for review** saves proposed values without changing attachments.
   * **Fill missing fields** applies values only where selected fields are empty.
   * **Overwrite selected fields** replaces existing values only when overwrite is enabled and immediately confirmed.
6. Run the batch, then open **Image Metadata > Batches** to monitor it.

Batches run in the background. The Batches screen provides pause, resume, cancel, and retry controls, so the original browser page does not need to remain open.

Your field, batch-size, mode, ordering, and missing-field choices are remembered when you run the form. Overwrite confirmation is never remembered.

== How to Review and Restore ==

Open **Image Metadata > Image Library** and filter for images with suggestions ready. Each suggestion can be approved, edited before approval, rejected, or left for later.

The plugin records metadata before applying a workflow change:

* Open **Image Metadata > History** to restore an individual field.
* Open **Image Metadata > Batches** to restore a completed batch.

A normal restore protects values that someone edited after the recorded plugin change. An individual field can be force-restored only after explicit confirmation.

== Recommended First Run ==

1. Enable only Alternative text and Title.
2. Keep overwrite disabled.
3. Preview 10 images.
4. Check several results against the images and page context.
5. Run Fill missing fields on a small batch.
6. Confirm the results in the Image Library before increasing the batch size.

Advanced workflow settings contain processing limits, request ceilings, retry behavior, editorial guidance, context controls, overwrite permissions, history retention, reports, capabilities, and WP-CLI support. Most sites can leave these settings at their defaults.

== Frequently Asked Questions ==

= Does this plugin create images? =

No. It generates metadata for images that already exist in the Media Library.

= Does AI metadata generation support SVG files? =

No. SVG attachments can be audited and edited manually, but AI generation skips them without making a provider request.

= Do I need my own API key? =

Yes. This is a bring-your-own-key plugin. Add your OpenAI or Gemini API key in the plugin settings.

= Which AI providers are supported? =

OpenAI and Gemini.

= Does this support GPT-5.6? =

The OpenAI model dropdown is populated from the models returned for your API key. OCCIDG accepts GPT-5.6-compatible model IDs, including `gpt-5.6`, `gpt-5.6-sol`, `gpt-5.6-terra`, and `gpt-5.6-luna`, and will list them when they are available through the OpenAI API.

= Which fields can it generate? =

The plugin can generate titles, alt text, captions, and descriptions.

= Can I choose which fields to generate? =

Yes. Use the metadata field settings and override controls in the admin screen.

= Does it work with WooCommerce product images? =

Yes. It works with image attachments in the WordPress Media Library, including WooCommerce product images stored there.

= What happens if I enable automatic generation on upload? =

Newly uploaded images will trigger metadata generation according to your configured settings.

= What languages are supported? =

The plugin supports the language options currently provided in the admin settings.

== Screenshots ==

1. **Settings Screen:** Configure provider, API key, model, language, and auto-generate settings.
2. **Media Library Integration:** Generate metadata directly from the WordPress Media Library.
3. **Image Library:** Filter images, edit metadata inline, preview or generate values, and review saved suggestions.
4. **Generated Image Details:** Preview and edit AI-generated titles, captions, alt texts, and descriptions.

== Upgrade Notice ==

= 2.0.3 =

Adds selected-image background generation with clearer live progress, restores caption generation, and refreshes workflow table layouts.

= 2.0.2 =

Prevents API-key clearing during normal settings saves and fixes reliable creation of repeated background batches.

= 2.0.1 =

Adds a clearer how-to guide for setup, previews, safe generation, batches, review, and restoration. Also tightens release packaging.

= 2.0.0 =

This major upgrade adds the production-safe preflight, review, queue, history, rollback, permissions, and reporting workflow. Existing metadata remains preserved by default.

= 1.2.4 =

This release adds GPT-5.6-compatible OpenAI model filtering and verifies live GPT-5.6 metadata generation.

= 1.2.3 =

This release declares compatibility with WordPress 7.0.

= 1.2.2 =

This release trims the WordPress.org plugin tags to the supported five-tag limit.

= 1.2.1 =

This release adds forward-compatible OpenAI model filtering for GPT-5.5 API model IDs and tightens release packaging automation.

= 1.2.0 =

This release repositions the plugin as a free, bring-your-own-key AI metadata generator for the Media Library with OpenAI and Gemini support.

== Changelog ==

= 2.0.3 =
* Added selected-image bulk generation to the Image Library with durable background processing and live progress feedback.
* Fixed caption generation for upload and bulk workflows, including four-field completion reporting.
* Improved the workflow UX with styled confirmations, full-width Batches and History tables, and clearer DataTables controls.
* Removed the obsolete settings tab and legacy provider-help panel.

= 2.0.2 =
* Fixed an upgrade regression where saving the main settings form could clear an existing provider API key because the masked key field submits an empty value.
* Added regression coverage that preserves existing OpenAI and Gemini keys while still accepting explicit replacement keys.
* Fixed batch persistence so requested fields are stored correctly and multiple background batches can be created safely.
* Hardened approved-suggestion sanitization and destructive uninstall cleanup.

= 2.0.1 =
* Added step-by-step instructions for installation, configuration, Preview, Generate, batch processing, suggestion review, and restoration.
* Documented the recommended first-run workflow and the difference between Preview and Generate.
* Excluded the internal upgrade specification from installable release packages.

= 2.0.0 =
* Added preflight metrics, missing-field filters, safe batch presets, and exportable dry runs with request and cost estimates.
* Added fill-missing, suggestion, explicitly confirmed overwrite, and dry-run processing modes.
* Added field-level confidence, suggestion review/edit/approval/rejection, decorative decisions, and attachment status integrations.
* Added custom suggestions, history, batches, and batch-item tables with immutable audit records and conflict-aware field/batch rollback.
* Added resumable background batches with pause, resume, cancellation, temporary-error backoff, retry, locking, request ceilings, and item-level failures.
* Added OpenAI/Gemini provider abstraction, environment-key constants, masked key fields, privacy disclosure, and credential-redacted logging.
* Added custom role capabilities, CSV reports, and `wp occ-idg` operational commands.

= 1.2.4 =
* Added GPT-5.6-compatible OpenAI model ID coverage for the API model dropdown, including gpt-5.6, gpt-5.6-sol, gpt-5.6-terra, and gpt-5.6-luna.
* Verified live OpenAI metadata generation with gpt-5.6-sol.

= 1.2.3 =
* Declared compatibility with WordPress 7.0.

= 1.2.2 =
* Limited WordPress.org readme tags to five canonical tags to satisfy plugin directory import rules.

= 1.2.1 =
* Added explicit GPT-5.5-compatible OpenAI model ID coverage for the API model dropdown.
* Improved metadata field selector layout on the settings screen.
* Fixed the GitHub Actions deploy packaging step for the WordPress.org release workflow.

= 1.2.0 =
* Re-released the plugin as a free, bring-your-own-key AI image metadata solution.
* Centered the user experience on OpenAI and Gemini provider settings.
* Removed public-facing migration and handoff notes from the plugin readme.
* Cleaned up release packaging so the distributable zip stays focused on runtime files.

= 1.1.15 =
* Update to media library API calls

= 1.1.13 =
* Plugin description, FAQs, and marketing language improved.
* Minor settings screen layout enhancements.
* Preparation for upcoming new language expansions.

= 1.1.11 =
* Vendor DataTables assets restored.
* Asset loading issues corrected.

= 1.1.10 =
* Security enhancements, WPCS compliance updates.
* Improved nonce verification and asset handling.

= 1.1.8 =
* Improved settings management and bulk edit handling.
* Better transient management for update checking.

= 1.1.7 =
* Fixed nonce issue in get_thumbnail AJAX call.

= 1.1.6 =
* Redirects to settings screen on first activation.

= 1.1.5 =
* Update system fixes and performance improvements.

= 1.1.1 =
* Multilingual generation support added.
* Data transparency improvements.

= 1.1.0 =
* Auto-generation of metadata on image upload.

= 1.0.0 =
* Initial plugin release.

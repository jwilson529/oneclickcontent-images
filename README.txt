=== OneClickContent - Image Detail Generator ===
Contributors: jwilson529
Tags: images, seo, alt-text, openai, gemini
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.2
Stable tag: 2.0.4
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

SVG attachments remain available for audits and manual editing. AI generation skips them without making a provider request.

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
- Model-list endpoint: `https://api.openai.com/v1/models`
- When used: when an administrator validates an OpenAI API key and loads compatible model choices
- Data sent for model listing: the configured OpenAI API key in the authorization header; no image or site content is sent
- Generation endpoint: `https://api.openai.com/v1/chat/completions`
- When used: when an authorized user requests image metadata generation, including upload processing, single-image generation, or a batch
- Data sent for generation: the configured OpenAI API key, image data, filename, existing title/description/alt text/caption, site name, configured organization and editorial guidance, and the parent content title/excerpt/type when available. Published parent context is included by default; draft or private parent context is included only when the corresponding administrator opt-in is enabled.
- Purpose: validate credentials, provide compatible model choices, and generate title, description, alt text, and caption
- Terms: https://openai.com/policies/terms-of-use/
- Privacy: https://openai.com/policies/privacy-policy/

= Gemini =
- Model-list endpoint: `https://generativelanguage.googleapis.com/v1beta/models?key={api_key}`
- When used: when an administrator validates a Gemini API key and loads compatible model choices
- Data sent for model listing: the configured Gemini API key; no image or site content is sent
- Generation endpoint: `https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={api_key}`
- When used: when an authorized user requests image metadata generation, including upload processing, single-image generation, or a batch
- Data sent for generation: the configured Gemini API key, image data, filename, existing title/description/alt text/caption, site name, configured organization and editorial guidance, and the parent content title/excerpt/type when available. Published parent context is included by default; draft or private parent context is included only when the corresponding administrator opt-in is enabled.
- Purpose: validate credentials, provide compatible model choices, and generate title, description, alt text, and caption
- Terms: https://ai.google.dev/terms
- Privacy: https://policies.google.com/privacy

== Third-party Libraries ==

- **DataTables (with Buttons and HTML5 Export)**: (v2.2.2, Buttons v3.2.2) used for table display and export functionality.
  Sources:
  - [DataTables GitHub](https://github.com/DataTables/DataTablesSrc/releases/tag/2.2.2)
  - [Buttons GitHub](https://github.com/DataTables/Buttons/releases/tag/3.2.2)

== Source Code ==

Full source code and unminified JavaScript are available on [GitHub](https://github.com/jwilson529/oneclickcontent-images).

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

The model dropdown uses the models returned for your API key. Compatible GPT-5.6 model IDs appear when OpenAI makes them available to your account.

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

1. **Dashboard:** Review library coverage and configure a safe preview or background batch.
2. **Image Library:** Filter images, select exactly what to process, edit metadata, and generate or review suggestions.
3. **Batches:** Monitor durable background work with live totals and pause, resume, cancel, retry, or restore controls.
4. **History:** Audit generated changes and restore individual metadata fields when needed.
5. **Settings:** Configure safe review controls, metadata fields, providers, and defaults.

== Upgrade Notice ==

= 2.0.4 =

Major workflow upgrade with safe previews, selected-image background batches, review, history, rollback, and reliable processing on hosts with WP-Cron spawning disabled. Existing metadata is preserved by default; test a small preview before the first production batch.

== Changelog ==

= 2.0.4 =
* Added preflight metrics, missing-field filters, safe batch presets, and exportable dry runs with request and cost estimates.
* Added fill-missing, suggestion, explicitly confirmed overwrite, and dry-run processing modes.
* Added selected-image background batches with live progress, pause, resume, cancel, retry, and a lock-protected browser fallback when normal WP-Cron spawning is disabled.
* Added field-level suggestion review, history, audit records, decorative decisions, and conflict-aware field or batch rollback.
* Added OpenAI/Gemini provider abstraction, environment-key constants, masked key fields, privacy disclosure, and credential-redacted logging.
* Fixed caption generation for uploads, single-image requests, and bulk workflows, honored the low-confidence review setting, preserved masked API keys during normal saves, and improved the Image Library, Batches, and History interfaces.
* Added custom role capabilities, CSV reports, safe uninstall controls, and `wp occ-idg` operational commands.

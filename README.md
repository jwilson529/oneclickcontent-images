# OneClickContent – Image Detail Generator

Generate accessible, useful metadata for images already in your WordPress Media Library using your own OpenAI or Gemini API key.

![OneClickContent Banner](assets/banner-1544x500.png)

The plugin can generate:

- Alternative text
- Image titles
- Captions
- Descriptions

It does not create images. AI generation supports standard raster images such as JPEG, PNG, GIF, and WebP. SVG attachments remain visible in the Image Library for manual editing, but are intentionally skipped by AI generation so the plugin behaves consistently across hosts.

## Quick Start

1. Install and activate the plugin.
2. In wp-admin, open **Image Metadata**.
3. Choose **OpenAI** or **Gemini**, enter your API key, and select a model.
4. Select the metadata fields you want the plugin to generate.
5. Keep overwrite disabled for the safest starting point, then save your settings.
6. Open **Image Metadata → Image Library**.
7. Find an image and click **Preview** to compare suggestions without changing anything.
8. Edit a suggestion if needed, then click **Use this suggestion** for each value you want to keep.

That is all you need for normal day-to-day use.

## Install the Plugin

### Install from a ZIP

1. In WordPress, go to **Plugins → Add New Plugin → Upload Plugin**.
2. Choose the `oneclickcontent-images.zip` file.
3. Click **Install Now**, then **Activate Plugin**.
4. Open **Image Metadata** in the WordPress admin menu to finish setup.

### Install from the plugin folder

1. Upload the plugin folder to `/wp-content/plugins/occidg/`.
2. Activate **OneClickContent - Image Detail Generator** from **Plugins**.
3. Open **Image Metadata** in wp-admin.

## Configure It Once

On the main **Image Metadata** screen:

1. Choose your AI provider.
2. Add the API key for that provider.
3. Select a model and output language.
4. Choose which fields to generate: alt text, title, caption, and/or description.
5. Save the settings.

The recommended defaults protect existing human-written metadata. Captions and uncertain results can also be held for review instead of being applied automatically.

For a server-managed API key, define `OCC_IDG_OPENAI_API_KEY` or `OCC_IDG_GEMINI_API_KEY` in the environment or WordPress configuration. An environment key takes priority over a key saved in WordPress.

## Work with Individual Images

Open **Image Metadata → Image Library**. The table lets you search, filter, sort, and edit image metadata without leaving the page.

Each supported image has two AI actions:

- **Preview** asks the provider for candidate metadata but changes nothing. Review or edit each candidate, then use only the suggestions you approve. Preview can show what AI would write even when overwrite protection is enabled.
- **Generate** applies the current plugin rules immediately. With the recommended settings, it fills selected fields that are empty and preserves existing metadata. Whitespace-only fields are treated as empty.

You can also edit a field directly in the table. Saved suggestions awaiting a decision appear with the image so you can approve, edit, reject, or leave them for later.

## Process a Batch Safely

For more than a few images, start at **Image Metadata → Dashboard**:

1. Review the library totals to see what is missing.
2. Under **Choose what to run**, select the metadata fields.
3. Choose a small batch size for the first run—10 or 25 images is a good starting point.
4. Leave the mode on **Preview only — change nothing** and click **Run Preview**.
5. Check the estimated images, fields, provider requests, and cost.
6. When the preview looks right, open **Advanced batch options** and choose:
   - **Create suggestions for review** to save proposed values without changing attachments.
   - **Fill missing fields** to apply values only where selected fields are empty.
   - **Overwrite selected fields** only when overwrite has been enabled and you explicitly confirm the batch.
7. Run the batch.

Your field, batch-size, mode, ordering, and missing-field choices are saved to your WordPress account when you run the form. Overwrite confirmation is deliberately never remembered.

Batch work runs in the background. Open **Image Metadata → Batches** to watch progress, pause or resume work, cancel a batch, or retry failed items. You do not need to keep the original browser page open while the batch runs.

## Review Suggestions

Open **Image Metadata → Image Library** and filter for images with suggestions ready.

For each suggested field you can:

- Approve it as written
- Edit it before approval
- Reject it
- Decide later

Low-confidence values and captions can be routed here automatically, depending on the workflow settings.

## Restore a Change

Every metadata value applied through the workflow is recorded before it is changed.

- Open **Image Metadata → History** to restore an individual field.
- Open **Image Metadata → Batches** to restore changes from a completed batch.

A normal restore will not replace a value that someone edited after the recorded plugin change. Individual history entries can be force-restored after an explicit confirmation when you intentionally want the older value.

## Recommended First Run

For the safest introduction to an existing site:

1. Enable only **Alternative text** and **Title**.
2. Keep overwrite disabled.
3. Preview 10 images.
4. Check several suggestions against the images and surrounding page context.
5. Run **Fill missing fields** on a small batch.
6. Confirm the results in the Image Library.
7. Expand the batch size only after the output matches your editorial standards.

## Advanced Options

The advanced workflow settings include processing limits, request ceilings, retry behavior, editorial guidance, context controls, overwrite permissions, history retention, CSV reports, custom role capabilities, and WP-CLI commands.

Most sites do not need to change these settings to get started. Overwrite mode is disabled by default and requires both permission and a fresh confirmation before a destructive batch.

## Provider Data and Costs

The plugin sends the selected image and generation instructions directly to the provider configured in WordPress:

- OpenAI
- Gemini

You are responsible for the provider account, API key, usage policy, and API costs. The plugin does not require a OneClickContent license, credits, or a hosted OneClickContent service.

OpenAI model choices are loaded from the models available to the saved API key. GPT-5.6-compatible model IDs are allowed by the plugin filter and appear when OpenAI exposes them to the API account.

## Frequently Asked Questions

### Does the plugin create images?

No. It generates metadata for images already stored in the WordPress Media Library.

### Does AI metadata generation support SVG files?

No. SVG attachments can be filtered and edited manually in the Image Library, but single-image, batch, automatic, background, and CLI generation skip them without making a provider request.

### Does Preview change metadata?

No. Preview returns candidate values so you can compare, edit, and apply them one field at a time.

### What does Generate do?

Generate applies metadata automatically using the current field and overwrite rules. With the recommended defaults, existing metadata is preserved.

### Can I process a large Media Library?

Yes. Use the Dashboard batch planner. Batches are resumable and include pause, resume, cancel, and retry controls.

### Can I undo generated metadata?

Yes. Use History for an individual field or Batches for a completed batch.

### Does it work with WooCommerce images?

Yes. It works with supported image attachments stored in the WordPress Media Library, including WooCommerce product images.

### Which providers are supported?

OpenAI and Gemini.

## Release Highlights

- Current release: 2.0.3
- Unified, searchable Image Library with inline editing, Preview, Generate, and suggestion review
- Safe fill-missing behavior with opt-in, confirmed overwrite controls
- Preflight estimates and remembered batch-planner choices
- Resumable background processing with pause, resume, cancel, and retry
- Immutable field history and conflict-aware restoration
- CSV reports, custom capabilities, environment-managed API keys, and WP-CLI support
- Tested with WordPress 7.0

## Development and Testing

Run PHPUnit locally when the required PHP extensions are available:

```bash
npm run test:local
```

Or run PHPUnit with Docker:

```bash
npm run test:docker
```

Run code-style checks:

```bash
npm run check
```

Build an installable ZIP:

```bash
npm run dist
```

## Developers

- Source code: [GitHub Repository](https://github.com/jwilson529/oneclickcontent-images)
- Built with DataTables v2.2.2

## License

GPLv2 or later

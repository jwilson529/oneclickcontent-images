# OneClickContent – Image Detail Generator

Generate AI-powered alt text, titles, captions, and descriptions for existing WordPress Media Library images using your own OpenAI or Gemini API key.

![OneClickContent Banner](assets/banner-1544x500.png)

## Overview

OneClickContent Image Detail Generator helps you enrich existing images in your Media Library with structured metadata.

Use it to generate:
- alt text
- image titles
- captions
- descriptions

The plugin is designed for image metadata generation only. It does not create images.

## Key Benefits

- Improve SEO for image search and page relevance
- Improve accessibility with better alt text
- Save time on repetitive metadata work
- Use your own OpenAI or Gemini account
- Bulk-generate metadata across your library
- Choose which metadata fields should be updated

## Features

- Bring-your-own-key support for OpenAI and Gemini
- Single-image generation from the Media Library
- Bulk generation for existing image attachments
- Auto-generate on upload
- Multilingual output based on plugin settings
- Editable metadata after generation

## Provider Model

This plugin sends image data directly to the provider you configure in WordPress:
- OpenAI
- Gemini

You are responsible for your own provider account, API key, and usage costs.

## Installation

1. Upload the `occidg` folder to `/wp-content/plugins/`
2. Activate the plugin in WordPress
3. Open **Image Metadata** in wp-admin
4. Choose a provider
5. Enter your OpenAI or Gemini API key
6. Choose your preferred model and metadata settings
7. Generate metadata from the Media Library or Bulk Edit tab

## FAQ

### Does this plugin create images?
No. This plugin generates metadata for images that already exist in the Media Library.

### Do I need my own API key?
Yes. This is a bring-your-own-key plugin. Configure OpenAI or Gemini in plugin settings.

### Which fields can it generate?
The plugin can generate:
- title
- description
- alt text
- caption

### Can I control which fields are overwritten?
Yes. Use the metadata field and override settings in the plugin configuration.

### Does it support bulk operations?
Yes. You can bulk-generate metadata for existing Media Library images.

### What languages are supported?
The plugin includes configurable language support for the existing language options in the plugin settings.

## Screenshots

1. Settings screen with provider configuration
2. Media Library integration with one-click generation
3. Bulk Edit mode for existing images
4. Generated metadata preview and editing flow

## Current Migration Status

The plugin is actively being migrated away from the old OneClickContent hosted-service model.

Completed so far:
- BYO provider settings for OpenAI and Gemini
- direct provider request handling with normalized metadata output
- removal of core license, trial, credits, and upsell UI flows
- bulk and single-image flows updated toward provider-based generation

Still in progress:
- deeper provider response hardening and edge-case handling
- final browser-level admin verification on the local site
- PHPUnit rerun once the local PHP CLI has `mbstring` enabled

## Handoff Notes

If you resume work in a new session, start here:
- tracked repo: `/home/jameswilson/.openclaw/workspace/projects/oneclickcontent/repos/oneclickcontent-images`
- local deployed plugin copy: `/home/jameswilson/sites/siteground/oneclickcontent-com/code/wp-content/plugins/occidg`
- legacy hosted-service inventory: `/home/jameswilson/.openclaw/workspace/projects/oneclickcontent/inventory/occidg-azure-proxy-inventory.md`

Important:
- OCCIDG is the metadata plugin for existing Media Library images. Keep it separate from the image-generation plugin.
- The local deployed copy is separate from the tracked repo copy.
- A partial manual sync caused a fatal local site error because `includes/class-occidg-i18n.php` and the `public/` directory were missed.
- For future deploy-sync steps, prefer a full-plugin sync or an explicit runtime-file checklist instead of copying only a few changed files.

## Developers

- Source code: [GitHub Repository](https://github.com/jwilson529/oneclickcontent-images)
- Built with DataTables v2.2.2

## License

GPLv2 or later

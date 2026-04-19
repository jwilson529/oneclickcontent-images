# OneClickContent – Image Detail Generator

Free AI-powered image metadata generation for existing WordPress Media Library images using your own OpenAI or Gemini API key.

![OneClickContent Banner](assets/banner-1544x500.png)

## Overview

OneClickContent Image Detail Generator is a free, bring-your-own-key WordPress plugin for enriching existing images in your Media Library with structured metadata.

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

## Release Highlights

- Free, bring-your-own-key image metadata workflow
- Direct provider support for OpenAI and Gemini
- Bulk generation and single-image generation inside the Media Library
- Configurable metadata fields, overwrite behavior, language, and automatic generation
- No license activation, trial gating, credits, or hosted-service dependency in the core workflow

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

### Which AI providers are supported?
OpenAI and Gemini.

### What languages are supported?
The plugin includes configurable language support for the existing language options in the plugin settings.

## Screenshots

1. Settings screen with provider configuration
2. Media Library integration with one-click generation
3. Bulk Edit mode for existing images
4. Generated metadata preview and editing flow

## Testing

If your local PHP CLI lacks required extensions like `mbstring`, run PHPUnit in Docker:

```bash
npm run test:docker
```

If your environment has all required extensions, run tests locally:

```bash
npm run test:local
```

Run code style checks:

```bash
npm run fix
npm run check
```

## Developers

- Source code: [GitHub Repository](https://github.com/jwilson529/oneclickcontent-images)
- Built with DataTables v2.2.2

## License

GPLv2 or later

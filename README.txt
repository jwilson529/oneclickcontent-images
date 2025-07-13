=== OneClickContent - Image Detail Generator ===
Contributors: jwilson529
Donate link: https://oneclickcontent.com/donate
Tags: images, seo, alt text, OpenAI, AI, accessibility, WordPress plugins
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.2
Stable tag: 1.1.14
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate SEO-optimized alt text, titles, captions, and descriptions for your images automatically using AI. Save time, improve accessibility, and boost your search rankings — with one click.

== Description ==

**OneClickContent – Image Detail Generator** automatically enhances your WordPress images with AI-powered metadata creation.

Boost your SEO, improve accessibility, and save hours of manual editing by generating **alt text**, **image titles**, **captions**, and **descriptions** automatically — powered by **OpenAI’s GPT-4o-mini** model.

**Perfect for bloggers, WooCommerce stores, agencies, and content creators** who want smarter, faster SEO without the tedious work.

**Key Benefits:**
- **SEO Optimization**: Boost image rankings in Google Search and Image results.
- **Accessibility Compliance**: Meet WCAG and ADA standards with rich alt text.
- **One-Click Simplicity**: Instantly generate high-quality metadata in your Media Library.
- **Multilingual Support**: Generate details in English, Spanish, French, German, Italian, Chinese, and Japanese.
- **Bulk Editing Support**: Process hundreds of images at once — no manual editing needed.
- **No OpenAI Account Required**: Powered through the OneClickContent API.

**Spend less time writing alt tags — and more time creating.**

== Features ==

- **Automated Image Metadata**: Titles, descriptions, alt texts, and captions generated in one click.
- **Auto-Generate on Upload**: Automatically generate metadata when uploading new images.
- **Multilingual Generation**: Support for 7 languages, with more coming soon.
- **Free Trial Included**: Process up to 5 images for free — no credit card required.
- **License-Based Usage**: Unlock unlimited generation with a OneClickContent license key.
- **Seamless WordPress Integration**: Manage everything inside the Media Library.
- **Accessibility Enhancements**: Improve user experience for screen readers and assistive devices.
- **Bulk Media Library Editing**: Quickly manage multiple images with the Bulk Edit tab.

== Transparency ==

**OneClickContent** sends the following data securely to [https://oneclickcontent.com](https://oneclickcontent.com):
- Image Data (either the file or URL)
- Website URL (for licensing and debugging)
- License Key (for usage validation)

Data is processed by OpenAI’s GPT models to generate your image details. **You do not need an OpenAI account** — all AI processing is managed by OneClickContent servers.

== External Services ==

This plugin connects to the OneClickContent API to verify license keys and usage limits:
- **Endpoint**: `https://oneclickcontent.com/wp-json/subscriber/v1/check-usage`
- **Data Sent**: Subscriber and usage verification information
- **Documentation**: [Terms of Service](https://oneclickcontent.com/terms/) | [Privacy Policy](https://oneclickcontent.com/privacy/)

== Third-party Libraries ==

- **DataTables (with Buttons and HTML5 Export)**: (v2.2.2, Buttons v3.2.2) used for table display and export functionality.  
  Sources:  
  - [DataTables GitHub](https://github.com/DataTables/DataTablesSrc/releases/tag/2.2.2)  
  - [Buttons GitHub](https://github.com/DataTables/Buttons/releases/tag/3.2.2)

== Source Code ==

Full source code, including unminified JavaScript files, is available at the [GitHub Repository](https://github.com/jwilson529/oneclickcontent-images).

== Installation ==

1. **Upload the Plugin:**
   - Upload the `occidg` folder to the `/wp-content/plugins/` directory.

2. **Activate the Plugin:**
   - Activate through the Plugins menu in WordPress.

3. **Configure Your Settings:**
   - Go to **Image Metadata** in your WordPress admin menu.
   - Enter your **OneClickContent license key**.
   - Choose your preferred language.
   - (Optional) Enable **Auto Add on Upload** to generate details automatically.

4. **Generate Image Details:**
   - Visit your **Media Library**.
   - Select any image and click **"Generate Details"**, or use the Bulk Edit tab to process multiple images at once.

== Frequently Asked Questions ==

= Do I need an OpenAI API key to use this plugin? =

No. The plugin handles AI interactions through the OneClickContent service. You only need a valid license key from [oneclickcontent.com](https://oneclickcontent.com).

= What’s included in the free trial? =

You can generate metadata for **5 images** completely free — no account or credit card required.

= Which AI model powers the generation? =

OneClickContent currently uses **OpenAI’s GPT-4o-mini** model, ensuring fast, high-quality results. The model selection may be updated over time for performance improvements.

= Is my data secure? =

Yes. Image data is sent securely to OneClickContent servers, processed via OpenAI, and never stored beyond what is necessary for generation.

= Can I choose which fields to generate? =

Currently, the plugin automatically generates titles, alt text, captions, and descriptions for images where fields are empty. Manual edits are fully supported after generation.

= Does it work with WooCommerce product images? =

Yes! You can optimize WooCommerce product images to improve product SEO and accessibility instantly.

= What happens if I enable "Auto Add Details on Upload"? =

Any image you upload will automatically have metadata generated according to your plugin settings — no manual clicking needed.

= What languages are supported? =

The plugin supports generating metadata in:
- English
- Spanish
- French
- German
- Italian
- Chinese
- Japanese

More languages are planned for future updates.

== Screenshots ==

1. **Settings Screen:** Configure your license key, language, and auto-generate settings.
2. **Media Library Integration:** Generate metadata directly from the WordPress Media Library.
3. **Bulk Edit Mode:** Bulk generate metadata across multiple images.
4. **Generated Image Details:** Preview and edit AI-generated titles, captions, alt texts, and descriptions.
5. **Language Selection Panel:** Choose your preferred language for generation.

== Changelog ==

= 1.1.14 =
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

== Upgrade Notice ==

= 1.1.13 =
* Important content updates and preparations for future multilingual support improvements. Please update.

= 1.1.11 =
* Bugfix for missing DataTables assets.

= 1.1.10 =
* Security updates and WPCS improvements.

= 1.1.8 =
* Enhanced update system and nonce handling.

= 1.1.5 =
* Optimized update handling and transient clearing.

= 1.1.1 =
* Multilingual support and transparency improvements.

= 1.1.0 =
* Added automatic generation on upload feature.

= 1.0.0 =
* Initial launch of OneClickContent.

== License ==

This plugin is licensed under the GPLv2 or later.
=== OneClickContent - Image Meta Generator ===
Contributors: jwilson529  
Donate link: https://oneclickcontent.com/donate  
Tags: images, metadata, OpenAI, AI, accessibility  
Requires at least: 5.0  
Tested up to: 6.7  
Requires PHP: 7.2  
Stable tag: 1.1.7
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html  

Automatically generate image metadata using OpenAI's GPT models with vision capabilities, now with multilingual support.

== Description ==

**OneClickContent - Images** is a WordPress plugin that enhances your images by automatically generating metadata using AI technology. Improve your website's SEO and accessibility by adding descriptive titles, alt texts, captions, and descriptions to your images effortlessly.

= Features: =

* Automated Metadata Generation: Generate image titles, descriptions, alt texts, and captions with a single click.
* Auto Add on Upload: Automatically generate metadata for images upon upload based on plugin settings.
* Multilingual Support: Generate metadata in multiple languages, including English, Spanish, French, German, Italian, Chinese, and Japanese.
* Free Trial: Process up to 5 images for free—no upfront subscription required.
* License-Based Usage: Use your **OneClickContent license key** to unlock unlimited metadata generation.
* Seamless Integration: Easily accessible within the WordPress Media Library.
* Improved Accessibility: Enhance user experience for visitors using assistive technologies.

= Transparency: =

To provide metadata generation, this plugin sends the following data to **oneclickcontent.com**:
- **Image Data**: Either the image itself or its URL.
- **Website URL**: Used for licensing and debugging.
- **License Key**: Used to verify your subscription.

From there, this data is processed by **OpenAI's GPT-4o-mini model** (or other models at our discretion) to generate metadata. This process is fully managed by **OneClickContent**, and you do not need an OpenAI API key.

== Installation ==

1. **Upload the Plugin:**
   - Upload the `oneclickcontent-images` folder to the `/wp-content/plugins/` directory.

2. **Activate the Plugin:**
   - Activate through the Plugins menu in WordPress.

3. **Configure Settings:**
   - Navigate to **Settings > OneClickContent - Images**.
   - Enter your **OneClickContent license key**.
   - Choose the desired language for metadata generation.
   - Optionally enable **Auto Add Details on Upload** to generate metadata automatically during image uploads.

4. **Generate Metadata:**
   - Go to your **Media Library**.
   - Select an image and click the **"Generate Metadata"** button.

== Frequently Asked Questions ==

= Do I need an OpenAI API key to use this plugin? =

No. The plugin handles all AI integration through **OneClickContent**. You only need a valid license key for extended usage beyond the free trial.

= What is the free trial? =

The plugin allows you to generate metadata for up to 5 images for free. After the trial, you will need a valid license key from **oneclickcontent.com** to continue generating metadata.

= Which AI models are supported? =

The plugin currently uses the GPT-4o-mini model but will adapt to the latest advancements in AI models to ensure optimal performance.

= Is my image data sent to OpenAI? =

Yes, your image data (or URLs), along with your website URL and license key, is sent to **oneclickcontent.com** for processing and then forwarded to OpenAI for metadata generation. You do not interact directly with OpenAI.

= Can I choose a specific language for metadata? =

Yes! The plugin supports multiple languages, including English, Spanish, French, German, Italian, Chinese, and Japanese. Select your preferred language from the plugin's settings page.

= Can I edit the generated metadata? =

Absolutely! After generation, you can review and edit the metadata in the Media Library before saving.

= What does "Auto Add Details on Upload" do? =

If enabled, the plugin will automatically generate metadata for any images you upload to the Media Library, saving you time.

== Screenshots ==

1. **Settings Page:** Configure your OneClickContent license key, select a language, and enable auto metadata generation on upload.
2. **Media Library Integration:** Generate metadata directly from the Media Library.
3. **Generated Metadata:** View and edit the generated metadata for your images.
4. **Language Selection:** Choose your preferred language for metadata generation.

== Changelog ==

= 1.1.7 =
* Fixed nonce issue in get_thumbnail AJAX call.

= 1.1.6 =
* Redirects to settings on activate.

= 1.1.5 =
* General fixes and improvements to the update system.
* Resolved issues with stale transients causing incorrect update indicators.
* Improved transient management to ensure updates are accurately reflected.
* Enhanced handling of plugin update checks with better validation and cleanup.

= 1.1.1 =
* Added multilingual support for metadata generation (English, Spanish, French, German, Italian, Chinese, Japanese).
* Improved transparency regarding data sent to oneclickcontent.com and OpenAI.
* Simplified user requirements—no OpenAI API key needed.

= 1.1.0 =
* Added "Auto Add Details on Upload" feature to automatically generate metadata for uploaded images.

= 1.0.0 =
* Initial release of OneClickContent - Images.

== Upgrade Notice ==

= 1.1.7 =
* Fixes nonce verification in thumbnail fetching for improved security.

= 1.1.5 =
* Fixes and enhancements to the update system. Resolves issues with stale update indicators and transient handling.

= 1.1.1 =
* Added multilingual support and improved transparency. Please review the updated plugin description.

= 1.1.0 =
* Added "Auto Add Details on Upload" feature.

= 1.0.0 =
* Initial release.

== License ==

This plugin is licensed under the GPLv2 or later.
=== Gawain AI Video ===
Contributors: nogeass
Tags: woocommerce, video, ai, promotional, product
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 0.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate AI promotional videos from your WooCommerce product images — one click, no editing required.

== Description ==

Gawain AI Video lets WooCommerce store owners generate short promotional videos directly from product data. Select a product, click "Generate", and the plugin sends the product information to the Gawain video generation service, which returns a ready-to-use video.

**Features**

* One-click AI video generation from product images
* Video carousel widget on product pages (automatic WooCommerce hook + `[gawain_videos]` shortcode)
* Deploy / undeploy videos to your storefront without editing theme files
* Admin dashboard for generation, preview, deployment, and deletion
* Full Japanese UI

**How it works**

1. Enable "External processing" in the plugin settings (disabled by default).
2. Open the Videos tab and click "Generate" on any product with an image.
3. The plugin sends product data to the Gawain API, which generates a video.
4. Preview the result, then click "Deploy" to display it on your product page.

= External Service =

This plugin relies on the **Gawain AI Video Generation API** hosted at [gawain.nogeass.com](https://gawain.nogeass.com) to generate promotional videos. **No data is sent to any external server until the site administrator explicitly enables "External processing" in the plugin settings.**

When enabled, the following data is transmitted to `gawain.nogeass.com` upon user-initiated actions (Generate, Deploy, Undeploy, Delete):

* **Product title** (max 80 characters, HTML stripped)
* **Product short description** (max 200 characters, HTML stripped)
* **Product image URL** (one image)
* **Product price and currency**
* **Site hostname** (used as installation identifier)

**Purpose:** AI video generation and storefront video delivery (CDN).

**Data retention:** Generated videos are stored on Gawain CDN servers. Deleting a video through the plugin dashboard removes it from both the local database and the remote server.

**Third-party sharing:** Product data is not shared with any third party beyond the Gawain service itself.

**Privacy policy:** [https://gawain.nogeass.com/privacy](https://gawain.nogeass.com/privacy)
**Terms of service:** [https://gawain.nogeass.com/terms](https://gawain.nogeass.com/terms)

= WooCommerce Requirement =

This plugin requires [WooCommerce](https://wordpress.org/plugins/woocommerce/) to function. When WooCommerce is not active, the plugin displays a notice and disables video generation features. The settings page remains accessible under the Tools menu.

== Installation ==

1. Upload the `gawain-ai-video` folder to `/wp-content/plugins/`, or install directly through the WordPress plugin screen.
2. Activate the plugin.
3. Go to **WooCommerce > Gawain AI Video > Settings**.
4. Check **"Enable external processing"** and optionally enter your API key.
5. Click **Save Settings**.
6. Switch to the **Videos** tab and click **"Generate"** on any product.

= API Key =

An API key is optional. Without a key, you can generate free preview videos (watermarked). Get a production key at [gawain.nogeass.com](https://gawain.nogeass.com).

== Frequently Asked Questions ==

= Does this plugin send data to an external server? =

Yes, but only after you explicitly enable "External processing" in the Settings tab. By default, no data is sent anywhere. When enabled, product data (title, description, image URL, price) is sent to gawain.nogeass.com for video generation.

= Does it work without WooCommerce? =

No. WooCommerce is required for product data. The plugin will show a notice if WooCommerce is not active.

= Can I use it without an API key? =

Yes. Without an API key, you can generate free preview videos with a watermark. A paid API key removes the watermark.

= How do I display videos on product pages? =

Videos are automatically displayed below the product description when deployed. You can also use the `[gawain_videos product_id="123"]` shortcode for manual placement.

= What happens when I uninstall the plugin? =

If you enable "Delete data on uninstall" in Settings, the plugin removes all its settings and the video tracking database table. Otherwise, data is preserved for future reinstallation.

= Where are the videos hosted? =

Generated videos are hosted on the Gawain CDN (`cdn.gawain.nogeass.com`). The storefront player loads videos directly from this CDN.

== Screenshots ==

1. Admin dashboard — product list with one-click generation
2. Video management — preview, deploy, and delete videos
3. Settings page — consent toggle, API key, and configuration
4. Product page — video carousel on the storefront

== Changelog ==

= 0.1.0 =
* Initial release
* One-click AI video generation from WooCommerce product images
* Admin dashboard with video management (generate, deploy, undeploy, delete)
* Storefront video carousel (WooCommerce hook + shortcode)
* Explicit consent toggle for external API communication (default: OFF)
* Uninstall handler with optional data deletion

== Upgrade Notice ==

= 0.1.0 =
Initial release.

# Gawain AI Video — WordPress/WooCommerce Plugin

AI-powered promotional video generation for WooCommerce products.

## Features

- Generate AI promotional videos from product images
- Video carousel widget on product pages (shortcode + WooCommerce hook)
- Admin dashboard to manage video generation, deployment, and deletion

## Requirements

- WordPress 5.8+
- WooCommerce 5.0+
- PHP 7.4+

## Installation

1. Download the latest release zip
2. Go to WordPress Admin > Plugins > Add New > Upload Plugin
3. Upload the zip and activate
4. Go to WooCommerce > Gawain AI Video > Settings
5. Enable "External processing" and optionally enter your API key

## Configuration

1. Optionally get an API key from [gawain.nogeass.com](https://gawain.nogeass.com) (free preview works without one)
2. Enter the API key in WooCommerce > Gawain AI Video > Settings

## Usage

### Automatic (WooCommerce)
Videos are automatically displayed below the product description on WooCommerce product pages when deployed.

### Shortcode
```
[gawain_videos product_id="123"]
```

## External Service

This plugin sends product data to `gawain.nogeass.com` for AI video generation. No data is sent until the administrator explicitly enables "External processing" in settings. See [readme.txt](readme.txt) for full disclosure.

## Building the Submission ZIP

```bash
./scripts/build-zip.sh
```

This produces `gawain-wordpress-plugin.zip` in the repo root, ready for WordPress.org upload.

## License

This plugin is licensed under **GPLv2 or later**, in line with the WordPress.org plugin directory requirements.
See [LICENSE](LICENSE) for the full text.

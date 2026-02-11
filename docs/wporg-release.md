# WordPress.org Plugin Directory — Release Checklist

## Pre-Submission

- [ ] Plugin slug: `gawain-ai-video`
- [ ] Text domain matches slug: `gawain-ai-video`
- [ ] `readme.txt` follows [WordPress readme standard](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/)
- [ ] License: GPL-2.0-or-later (header in main plugin file)
- [ ] `Requires at least: 5.8`, `Requires PHP: 7.4`

## External Service Disclosure

This plugin communicates with `gawain.nogeass.com` for AI video generation.

**What is sent** (only when user clicks "Generate"):
- Product title (max 80 chars)
- Product short description (max 200 chars)
- Product image URL
- Product price + currency
- Site hostname (as `installId`)

**Controls**:
- Default: OFF — no data is sent until admin enables "External processing" in Settings
- `Gawain_AI_Video::can_call_api()` gates every outbound request
- Admin JS checks `gawainData.hasConsent` before allowing generate/deploy/undeploy
- Storefront JS only loads when consent is enabled

## Security Checklist

- [ ] All REST endpoints require `manage_woocommerce` capability
- [ ] WP REST nonce verified automatically by REST API infrastructure
- [ ] All user input sanitized (`absint`, `sanitize_text_field`, `esc_url_raw`)
- [ ] All HTML output escaped (`esc_html`, `esc_attr`, `esc_url`)
- [ ] API key never printed in full (masked in HTML)
- [ ] No `eval()`, no remote code execution
- [ ] No data sent without explicit admin consent

## Uninstall Behavior

- `uninstall.php` respects "Delete data on uninstall" setting
- When enabled: deletes `gawain_settings` option and drops `wp_gawain_videos` table
- When disabled: all data preserved

## WooCommerce Compatibility

- Plugin loads admin page even without WooCommerce (shows activation notice)
- REST endpoints and storefront only initialize when WooCommerce is active
- Menu falls back to Tools when WooCommerce is absent

## Build & Release

```bash
# Create release zip (exclude dev files)
zip -r gawain-ai-video.zip gawain-ai-video/ \
  -x "gawain-ai-video/.git/*" \
  -x "gawain-ai-video/docs/*" \
  -x "gawain-ai-video/node_modules/*" \
  -x "gawain-ai-video/.DS_Store"
```

## Submission

1. Submit at https://wordpress.org/plugins/developers/add/
2. Upload zip or provide SVN access
3. Wait for review (typically 1-2 weeks)
4. Address any reviewer feedback

# WooCommerce 1.5.7 — public distribution

This release updates validation, sanitization, final output escaping, URL parsing and translation comments. Payment API contracts and saved settings remain compatible.

- WooCommerce 5.8 or later with the native gateway API; PHP 7.4–8.4, subject to the exact store/dependency requirements.
- ON_SITE and HPP are available; ON_SITE is the default for new installations.
- Buyer languages: English, Chinese, Spanish, Brazilian Portuguese, French, German, Dutch, Persian, Russian and Arabic. Browser preferences initialize the language, with English fallback; an explicit buyer choice takes priority.
- Merchant settings and setup instructions remain English.

## Install or upgrade

Read `mochipay-woocommerce/readme.txt`. The installation and language documents are consolidated there. Update the existing folder in place and preserve settings and historical order mappings; do not uninstall first or activate both website and WordPress.org builds together.

Download: https://github.com/hznetnewpower/mochipay-plugins/releases/tag/woocommerce-v1.5.7

Guide: https://mochi.bz/Guides/GuideWooCommerce.aspx

The original folder name is retained for upgrades, so Plugin Check may report a folder-name trademark warning. GitHub's whole-repository ZIP is not an installable plugin ZIP.

## Verification scope

PHP 7.4 syntax and archive integrity were checked. Existing nonce, order-key and authenticated payment verification are preserved. CSS matches the supplied Multilanguages 1.5.7 installation package. Store/theme compatibility, ON_SITE/HPP behavior and real verified payments still need staging acceptance. Earlier validation reports describe their baseline only.

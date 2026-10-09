=== MochiPay for WooCommerce ===
Contributors: hznetnewpower
Tags: woocommerce, cryptocurrency, payment gateway, usdt, usdc
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.5.14
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept cryptocurrency payments with an on-site dialog or MochiPay hosted checkout.

Official website: https://mochi.bz/

== Description ==
MochiPay cryptocurrency payments with on-site and HPP modes.
New defaults: https://mochi.bz, on-site, all five assets, UP.
See the installation and multilingual sections below for environments, installation and validation scope.


== Installation ==

# WooCommerce 5.8 or later with the native gateway API — MochiPay

Plugin version: 1.5.14

# MochiPay setup

MochiPay URL: **https://mochi.bz** (already filled in).
Payment interface: **On-site** (default); **HPP** remains available.
Unique amount direction: **UP** (default); choose **DOWN** if preferred.
All five payment choices are selected: USDT on TRON (TRC20), USDC on Ethereum
(ERC20), BTC on Bitcoin, ETH on Ethereum (ERC20), and SOL on Solana.

Enter your merchant API Key and API Secret once. Enable MochiPay, select your
Unique amount direction preference, and save. Existing saved values take
precedence over defaults during an in-place file update. Magento uses its native
multiselect control for currencies; WooCommerce uses its native multiselect;
Zen Cart, OpenCart and PrestaShop display checkboxes.

Only offer currencies/networks for which your MochiPay merchant account has an
active receiving wallet. The plugin cannot create or activate merchant wallets.
Merchant credentials belong in the store administration settings only.

## Payment behavior

On-site displays the payment address, network, exact amount and QR code on the
merchant domain. For platforms with a redirect-based gateway lifecycle, checkout
opens a merchant-local payment page and then the dialog. WooCommerce supports a
checkout dialog and a protected order payment link. No iframe is used.
HPP redirects the customer to the MochiPay hosted payment page.

By default, the QR contains the address only; customers enter the exact displayed amount. With amount is optional for supported assets and precision. See PAYMENT_QR_MODES.txt in the package root.
Automatic polling and callbacks query the authenticated MochiPay API before
marking an order paid. Underpayment, overpayment, cancellation and expiration
require review and do not automatically mark orders paid. Payable amounts retain
server decimal precision. A saved attempt is reused; uncertain creation is queried
by its merchant reference instead of blindly submitting another create request.

## Server requirements

Use the PHP version allowed by BOTH this package and the exact shopping-platform
release, including installed platform patches. The environment table below is a
package selection guide, not permission to run an older core on newer PHP.
Enable JSON, OpenSSL and a working HTTPS transport (cURL recommended). The store
must accept the generated callback URL over HTTPS. Portable adapters use the
store's MySQL/MariaDB connection, InnoDB and GET_LOCK/RELEASE_LOCK. They add a
prefixed mochipay_attempt table automatically. No MochiPay service migration is
needed for these plugin packages. WooCommerce uses native order metadata/options.

## Updating

Keep existing credentials, status mappings and historical payment mappings.
Replace plugin files in place using the same package branch. Do not uninstall or
remove the module as an upgrade step: removal may delete settings. Clear the
platform's module/template caches after replacing files. New defaults apply only
to unset configuration; an existing saved HPP preference remains HPP.
Historical mapping imports are supported for earlier supplied Zen Cart,
OpenCart, PrestaShop and Magento 2 packages. A mismatch requires manual review.
If an older order used the wrong currency-conversion amount, review it manually.

## Validation scope

This release was checked with PHP syntax validation, mocked platform order and
configuration adapters, a local HTTPS API fixture, and browser dialog checks.
Full installations of every shopping platform, custom checkout themes and real
blockchain transfers have not been exercised. Run a staging checkout and verify
an actual callback before accepting live payments.

## Platform and PHP environment

| Shopping platform | PHP environment for this package |
|---|---|
| WooCommerce + WordPress | 7.4-8.4 within both core releases' requirements |

## Installation

Upload the ZIP through **Plugins > Add New > Upload Plugin**, activate it, then open **WooCommerce > Settings > Payments > MochiPay**. Enter credentials, enable the gateway and save. Includes Classic Checkout and Checkout Block integration and HPOS-compatible order access. PHP below 7.4 is outside this package's range. Future WooCommerce releases may raise their own PHP minimum.

## Platform references

- https://woocommerce.com/document/server-requirements/


## Buyer-language UI — build82.1

ON_SITE payment dialogs offer English (default), Chinese, Spanish, Brazilian Portuguese, French, German, Dutch, Persian, Russian, Arabic, Japanese, Korean, Italian, Turkish and Indonesian. Choose a language manually in the dialog. Switching does not recreate the order, change its amount or extend expiry. HPP uses the MochiPay hosted page language selector.

This is an optional frontend update. Existing installations remain usable; no account, API-signature, callback, database or PHP business-flow change is required. Preserve your settings. Replace the listed onsite.js file and clear browser/CDN/compiled storefront caches, or update this package through your platform’s normal process. Installation instructions and merchant gateway settings remain in English. The package core version/PHP compatibility range is unchanged; buyer UI build82.1 identifies this distribution.

Frontend file: mochipay-for-woocommerce/assets/js/onsite.js

Local language/browser checks cover the reusable dialog assets. Real store checkout and live payment acceptance still require your own staging validation.


## ON_SITE layout update — build 76

The payment dialog keeps all four outer corners rounded. Long content scrolls inside an inset region; the close button and language selector remain outside it. Update both mochipay-for-woocommerce/assets/css/onsite.css and mochipay-for-woocommerce/assets/js/onsite.js. Preserve gateway settings and order mappings. Clear browser/CDN/storefront caches after replacing these assets. API, PHP payment business logic, exact amount, wallet address and callback verification are unchanged. HPP is unchanged.

Also update mochipay-for-woocommerce/includes/class-mochipay-onsite.php together with the two assets: it versions their browser URLs for layout build 76. This file changes asset cache metadata only. Use the complete package update if unsure which files to replace.


== Multilingual buyer interface ==

MochiPay Multilanguages frontend UI82.1

Buyer checkout supports English, Chinese, Spanish, Brazilian Portuguese, French, German, Dutch, Persian, Russian, Arabic, Japanese, Korean, Italian, Turkish and Indonesian.
Browser language is used on first opening unless the URL or a previous manual selection takes priority. Arabic and Persian use RTL; amounts and wallet addresses remain unchanged. Merchant plugin settings and installation instructions stay English.
ON_SITE remains the default; HPP remains supported. No API, signature, callback, payment-state or order-creation contract changes. Existing installed versions keep working. Update this platform package or frontend assets to receive these language choices, retaining merchant credentials and configuration.
Read the platform-specific PHP table and installation instructions included in this package.


== Changelog ==

= 1.5.14 =
* Remove direct cURL calls; use the WordPress HTTP API and Requests hook to retain at least30-second connection and response budgets.
* Use the mochipay-for-woocommerce directory and matching text domain.
* Keep all four setup documents as TXT; no unexpected root Markdown files.
* Preserve independent attempts, signed verification, exact amounts and saved gateway/order data.

= 1.5.13 =
* Each explicit checkout creates an independent payment, including repeated merchant/local order references. Transport retries alone retain the original request_id and payload.
* Old links and callbacks verify their original system-order binding; exact received amount remains mandatory before fulfillment.

== Upgrade from the website package ==
1. Back up the existing gateway configuration and normal site database.
2. Deactivate the old MochiPay gateway in WordPress. DO NOT uninstall it or delete its settings/order data.
3. Install this ZIP. It has exactly one mochipay-for-woocommerce folder. Activate only this gateway, not both builds.
4. Confirm existing gateway settings, saved order links and callbacks before accepting new payments. The gateway ID remains mochipay; settings, meta keys and saved attempt options retain their original names.

If using FTP, the new root is mochipay-for-woocommerce. Do not overlay it into the old mochipay-woocommerce directory, which would retain the slug warning and leave old Markdown files behind. Existing inactive files may be kept for rollback outside the active plugin directory; do not call an uninstall routine.

== Independent creation and transport retries ==
Requires MochiPay WEB82.6 or newer. Each explicit checkout gets its own request_id and saved system order, even when merchant_order_id repeats. Status refresh and old links continue that exact attempt. A transport retry uses the original saved request_id and unchanged payload. Never recover by a repeated merchant reference. Authentication, PAID, exact received_amount==pay_amount, asset/network/address and once-only fulfillment remain mandatory. HPP return is not payment proof.

== Validation scope for 1.5.14 ==
PHP syntax and actual gateway/payment-data fixtures run offline with mocked WordPress/upstream services. Root file format, directory/text-domain consistency, no direct cURL calls and minimum30-second Requests budgets are checked against the final ZIP. These checks are not a full live WordPress Plugin Check or a real payment. Run Plugin Check on the installed new directory and accept the gateway in your staging store.

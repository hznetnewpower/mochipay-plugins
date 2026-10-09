# Zen Cart 1.5.8 / 2.0.x / 2.1.x / 2.2.x — MochiPay

Plugin version: 1.2.0

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

By default, the QR contains the address only; customers enter the exact displayed amount. With amount is optional for supported assets and precision. See PAYMENT_QR_MODES.md in the package root.
Automatic polling and callbacks query the authenticated MochiPay API before
marking an order paid. Underpayment, overpayment, cancellation and expiration
require review and do not automatically mark orders paid. Payable amounts retain
server decimal precision. Every explicit checkout creates a new payment attempt and system order, even when
the local order ID or merchant_order_id repeats. A payment token identifies one
attempt. Refreshing that payment page queries the same system order. Only a
transport retry of that attempt reuses its saved request_id and payload.

## Server requirements

Use the PHP version allowed by BOTH this package and the exact shopping-platform
release, including installed platform patches. The environment table below is a
package selection guide, not permission to run an older core on newer PHP.
Enable JSON, OpenSSL and a working HTTPS transport (cURL recommended). The store
must accept the generated callback URL over HTTPS. Portable adapters use the
store's MySQL/MariaDB connection, InnoDB and GET_LOCK/RELEASE_LOCK. They add a
prefixed mochipay_attempt_v2 table automatically. No MochiPay service migration is
needed for these plugin packages. WooCommerce uses native order metadata/options.

## Updating

Keep existing credentials, status mappings and historical payment mappings.
Replace plugin files in place using the same package branch. Do not uninstall or
remove the module as an upgrade step: removal may delete settings. Clear the
platform's module/template caches after replacing files. New defaults apply only
to unset configuration; an existing saved HPP preference remains HPP.
This release targets the merchant's pre-launch store with no historical orders.
It does not migrate old payment attempts. The new prefixed mochipay_attempt_v2
table is created automatically; preserve gateway settings during a file update.

## Validation scope

This release was checked with PHP syntax validation, mocked platform order and
configuration adapters, a local HTTPS API fixture, and browser dialog checks.
Full installations of every shopping platform, custom checkout themes and real
blockchain transfers have not been exercised. Run a staging checkout and verify
an actual callback before accepting live payments.

## Platform and PHP environment

| Shopping platform | PHP environment for this package |
|---|---|
| 1.5.8/1.5.8a | 7.3-8.3; installation utility requires 8.2 or lower |
| 2.0.x / 2.1.x | 8.0-8.3 |
| 2.2.x | 8.2-8.4 for this package |

## Installation

Extract and merge `includes/` plus the three root `mochipay_*.php` entry points into the store root. Open **Modules > Payment > MochiPay**, install it if new, enter credentials, enable it and save. Waiting defaults to status 1; paid defaults to status 2. They must differ. This package uses the array-based English language format introduced in Zen Cart 1.5.8. When moving from the legacy package, remove its old English payment language file `includes/languages/english/modules/payment/mochipay.php` after placing the new `lang.mochipay.php`.

## Platform references

- https://docs.zen-cart.com/user/first_steps/server_requirements/


## Buyer-language UI — build 75

ON_SITE payment dialogs offer English (default), Chinese, Spanish, Brazilian Portuguese, French and German. Choose a language manually in the dialog. Switching does not recreate the order, change its amount or extend expiry. HPP uses the MochiPay hosted page language selector.

This is an optional frontend update. Existing installations remain usable; no account, API-signature, callback, database or PHP business-flow change is required. Preserve your settings. Replace the listed onsite.js file and clear browser/CDN/compiled storefront caches, or update this package through your platform’s normal process. Installation instructions and merchant gateway settings remain in English. The package core version/PHP compatibility range is unchanged; language UI build 75 identifies this distribution.

Frontend file: includes/classes/mochipay/onsite.js

Local language/browser checks cover the reusable dialog assets. Real store checkout and live payment acceptance still require your own staging validation.


## ON_SITE layout update — build 76

The payment dialog keeps all four outer corners rounded. Long content scrolls inside an inset region; the close button and language selector remain outside it. Update both includes/classes/mochipay/onsite.css and includes/classes/mochipay/onsite.js. Preserve gateway settings and order mappings. Clear browser/CDN/storefront caches after replacing these assets. API, PHP payment logic, exact amount, wallet address and callback verification are unchanged. HPP is unchanged.


## Independent payment release — Zen Cart1.2.0 / WEB82.6

Deploy and rebuild the MochiPay WEB82.6 payment API before updating this plugin.
The API permits repeated merchant_order_id labels; only request_id identifies a
retry. Each explicit checkout creates its own record, token and system order.
Existing unpaid attempts never block or replace a new checkout. ON_SITE polling
and callbacks use the selected attempt token/system ID, not the latest payment
for that store order. Confirmed native orders still fulfill once.

Replace includes/modules/payment/mochipay.php, includes/classes/mochipay_bridge.php,
includes/classes/mochipay_client.php, includes/classes/mochipay/mochipay_client.php,
includes/classes/mochipay/mochipay_core.php, mochipay_pay.php and mochipay_callback.php.
Do not uninstall or reset settings. The attempt_v2 table is created automatically;
no manual plugin SQL or main payment-system SQL is required. Clear storefront caches.

Zen Cart payment and advisory-lock queries explicitly bypass queryCache; this
prevents consumed or stale query results from producing an incomplete payment
link. Retain saved-order amount conversion, null-product fallback, static endpoint
URLs, stage-seven query deadlines, retryable polling, QR and buyer languages.

Offline fixtures cover checkout123/124/124, ON_SITE/HPP, null products, independent
links, callbacks, exact received amounts, repeated settlement, request retries and
query caching. Real Zen Cart installation, IIS/.NET compilation and real payment
acceptance must still be performed on the merchant environment.

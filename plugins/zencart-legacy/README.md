# Zen Cart 1.5.3-1.5.7 — MochiPay

Plugin version: 1.1.0

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

The QR contains the address only; customers must enter the exact displayed amount.
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
| 1.5.3-1.5.4 | 5.6 |
| 1.5.5 | 5.6-7.1 |
| 1.5.6 | 5.6-7.3 |
| 1.5.7 | 5.6-8.0 |

## Installation

Extract and merge `includes/` plus the three root `mochipay_*.php` entry points into the store root. Open **Modules > Payment > MochiPay**, install it if new, enter credentials, enable it and save. Waiting defaults to status 1; paid defaults to status 2. They must differ. For a store using another language, the module supplies English fallback text. This legacy package uses define-based language files. PHP below 5.6 and Zen Cart below 1.5.3 are outside this package's range.

## Platform references

- https://docs.zen-cart.com/user/first_steps/server_requirements/


## Buyer-language UI — build 75

ON_SITE payment dialogs offer English (default), Chinese, Spanish, Brazilian Portuguese, French and German. Choose a language manually in the dialog. Switching does not recreate the order, change its amount or extend expiry. HPP uses the MochiPay hosted page language selector.

This is an optional frontend update. Existing installations remain usable; no account, API-signature, callback, database or PHP business-flow change is required. Preserve your settings. Replace the listed onsite.js file and clear browser/CDN/compiled storefront caches, or update this package through your platform’s normal process. Installation instructions and merchant gateway settings remain in English. The package core version/PHP compatibility range is unchanged; language UI build 75 identifies this distribution.

Frontend file: includes/classes/mochipay/onsite.js

Local language/browser checks cover the reusable dialog assets. Real store checkout and live payment acceptance still require your own staging validation.


## ON_SITE layout update — build 76

The payment dialog keeps all four outer corners rounded. Long content scrolls inside an inset region; the close button and language selector remain outside it. Update both includes/classes/mochipay/onsite.css and includes/classes/mochipay/onsite.js. Preserve gateway settings and order mappings. Clear browser/CDN/storefront caches after replacing these assets. API, PHP payment logic, exact amount, wallet address and callback verification are unchanged. HPP is unchanged.

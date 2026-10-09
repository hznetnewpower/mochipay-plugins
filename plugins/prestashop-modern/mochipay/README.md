# PrestaShop 1.7.6-1.7.8 / 8.x / 9.0.x — MochiPay

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
| 1.7.6 | 5.6-7.2 |
| 1.7.7 | 7.1-7.3 |
| 1.7.8 | 7.1-7.4 |
| 8.0-8.2 | 7.2.5-8.1 |
| 9.0.x | 8.1-8.4 |

## Installation

Upload the ZIP through **Module Manager > Upload a module**, install MochiPay and open Configure. Alternatively merge `mochipay/` into `modules/mochipay/`. Enter credentials, set Enabled to Yes and save. Allow the module for the native order currencies/countries/customer groups. This package uses `paymentOptions` and binds the currency/network choice to the native payment form. Waiting state is created automatically; verified payment uses Payment accepted. PHP 8 is for PrestaShop 8/9, not PrestaShop 1.7. Versions before 1.7.6 and after 9.0.x are outside this package's declared range.

## Platform references

- https://devdocs.prestashop-project.org/1.7/basics/installation/system-requirements/
- https://devdocs.prestashop-project.org/8/basics/installation/system-requirements/
- https://devdocs.prestashop-project.org/9/basics/installation/system-requirements/


## Buyer-language UI — build 75

ON_SITE payment dialogs offer English (default), Chinese, Spanish, Brazilian Portuguese, French and German. Choose a language manually in the dialog. Switching does not recreate the order, change its amount or extend expiry. HPP uses the MochiPay hosted page language selector.

This is an optional frontend update. Existing installations remain usable; no account, API-signature, callback, database or PHP business-flow change is required. Preserve your settings. Replace the listed onsite.js file and clear browser/CDN/compiled storefront caches, or update this package through your platform’s normal process. Installation instructions and merchant gateway settings remain in English. The package core version/PHP compatibility range is unchanged; language UI build 75 identifies this distribution.

Frontend file: mochipay/classes/portable/onsite.js

Local language/browser checks cover the reusable dialog assets. Real store checkout and live payment acceptance still require your own staging validation.


## ON_SITE layout update — build 76

The payment dialog keeps all four outer corners rounded. Long content scrolls inside an inset region; the close button and language selector remain outside it. Update both mochipay/classes/portable/onsite.css and mochipay/classes/portable/onsite.js. Preserve gateway settings and order mappings. Clear browser/CDN/storefront caches after replacing these assets. API, PHP payment logic, exact amount, wallet address and callback verification are unchanged. HPP is unchanged.


## WEB82.5 integration repair

Back up files and gateway settings, then update the package files in place. Do not uninstall the gateway or delete existing attempt/order tables. Stage-seven query deadlines, transient-query recovery, stable request IDs, QR assets and buyer languages are retained. Store invoice totals are normalized as decimal strings for the order currency; fiat defaults to two decimal places, USDT/USDC to six, BTC/ETH/SOL to eight. This is not the blockchain payable amount or matching precision. Custom server currency precision needs store-side acceptance.

Existing saved payment payloads are reused unchanged. A legacy mapping with an incorrect conversion must be reviewed manually; it is never silently relinked or rewritten. Check one ON_SITE and one HPP order plus callback verification on staging. Offline fixtures do not substitute for a live installation.

# Magento CE 1.9.3.0-1.9.4.5; OpenMage 19/20 native M1 API — MochiPay

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
| Magento CE 1.9.3.x | 5.6 |
| Magento CE 1.9.4.x | 5.6 or 7.2, with the applicable PHP/security patches |
| OpenMage 19 / older 20 releases | Use the exact release's PHP requirements, within 5.6-8.4 |
| OpenMage 20.14+ | 8.1-8.4; upstream notes possible warnings on 8.4 |

## Installation

Merge `app/` into the store root. Disable Magento 1 compilation before installation and rebuild it afterwards if used. Clear configuration/block caches, sign out of Admin and sign in again. Open **System > Configuration > Sales > Payment Methods > MochiPay**. Enter credentials, enable it and save at the correct website/store scope. The module activation file is `app/etc/modules/MochiPay_Payment.xml`. For a custom theme, include the supplied payment form template or retain the native base/default fallback. This is an M1 module, not an M2 module. Magento 1.0-1.8 and CE 1.9.0-1.9.2 are outside this package's declared range. Original Magento CE 1.x is not advertised as PHP 8 compatible; PHP 8 applies to a compatible OpenMage core.

## Platform references

- https://devdocs-openmage.org/guides/m1x/ce19-ee114/ce1.9_release-notes.html
- https://docs.openmage.org/users/requirements/


## Buyer-language UI — build 75

ON_SITE payment dialogs offer English (default), Chinese, Spanish, Brazilian Portuguese, French and German. Choose a language manually in the dialog. Switching does not recreate the order, change its amount or extend expiry. HPP uses the MochiPay hosted page language selector.

This is an optional frontend update. Existing installations remain usable; no account, API-signature, callback, database or PHP business-flow change is required. Preserve your settings. Replace the listed onsite.js file and clear browser/CDN/compiled storefront caches, or update this package through your platform’s normal process. Installation instructions and merchant gateway settings remain in English. The package core version/PHP compatibility range is unchanged; language UI build 75 identifies this distribution.

Frontend file: app/code/local/MochiPay/Payment/portable/onsite.js

Local language/browser checks cover the reusable dialog assets. Real store checkout and live payment acceptance still require your own staging validation.


## ON_SITE layout update — build 76

The payment dialog keeps all four outer corners rounded. Long content scrolls inside an inset region; the close button and language selector remain outside it. Update both app/code/local/MochiPay/Payment/portable/onsite.css and app/code/local/MochiPay/Payment/portable/onsite.js. Preserve gateway settings and order mappings. Clear browser/CDN/storefront caches after replacing these assets. API, PHP payment logic, exact amount, wallet address and callback verification are unchanged. HPP is unchanged.


## WEB82.5 integration repair

Back up files and gateway settings, then update the package files in place. Do not uninstall the gateway or delete existing attempt/order tables. Stage-seven query deadlines, transient-query recovery, stable request IDs, QR assets and buyer languages are retained. Store invoice totals are normalized as decimal strings for the order currency; fiat defaults to two decimal places, USDT/USDC to six, BTC/ETH/SOL to eight. This is not the blockchain payable amount or matching precision. Custom server currency precision needs store-side acceptance.

Existing saved payment payloads are reused unchanged. A legacy mapping with an incorrect conversion must be reviewed manually; it is never silently relinked or rewritten. Check one ON_SITE and one HPP order plus callback verification on staging. Offline fixtures do not substitute for a live installation.

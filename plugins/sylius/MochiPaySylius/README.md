# MochiPay for Sylius

Version 1.0.0 — English interface and documentation.

## Compatibility

- Store: Sylius >=2.0.0 <2.1.0 with PayumBundle 2.6+ / Payum 1.7-compatible core.
- PHP: PHP 8.2.x / 8.3.x / 8.4.x (also satisfy the store dependency lock).
- Integration: Payum gateway factory/actions and Sylius payment state machine.
- Native interface reference: https://github.com/Sylius/Sylius/tree/v2.0.0.

The PHP range is the intersection of this adapter and the shopping system's requirements. Follow the exact core release and its Composer lock; PHP 7 is not supported by a core that requires PHP 8.

## Installation

1. Extract `MochiPaySylius` to `plugins/MochiPaySylius` in your Sylius project. Add a Composer path repository for `plugins/MochiPaySylius`, preserving existing repositories, then run `composer require mochipay/sylius-plugin:1.0.0`.
2. Register `MochiPay\Sylius\MochiPayBundle::class => ['all' => true]` in `config/bundles.php`.
3. Add a routing resource to `config/routes/mochipay.yaml`: `mochipay: { resource: '@MochiPayBundle/Resources/config/routes.yaml' }`.
4. Run `bin/console cache:clear` and `bin/console mochipay:install`. The command creates/retains attempt storage; it does not change existing payments.
5. In the Sylius admin, create a payment method using gateway factory MochiPay. Set credentials in its gateway configuration, check Enable MochiPay, set direction, enable the native payment method and assign channels.
6. Complete native shop checkout acceptance. The public capability endpoint is `/mochipay/checkout`.

Use the Payum checkout integration provided by Sylius 2.0. A custom headless payment-request implementation needs to call the same native capture actions rather than bypassing them. Amounts are converted exactly from integer minor units using Symfony Intl's currency fraction digits. Capture/convert/status actions preserve native payment details; verified payment applies the native COMPLETE transition. MySQL/MariaDB and PostgreSQL are supported. This package does not cover Sylius 1.x or 2.1+ interfaces.

## Settings

The defaults are MochiPay URL `https://mochi.bz`, payment mode `ON_SITE`, unique amount direction `UP`, and all five supported methods selected: USDT/TRC20, USDC/ERC20, BTC/Bitcoin, ETH/Ethereum and SOL/Solana. The gateway starts disabled. Enter your merchant API key and secret, choose UP or DOWN, enable the native payment method and save. Enable only methods for which the merchant has a matching active wallet configured in MochiPay. There is no third-party facilitator registration.

`ON_SITE` shows the exact cryptocurrency amount, network, address and a locally generated address-only QR code in a dialog on the store's own origin. `HPP` redirects to MochiPay's hosted payment page. The “Use hosted checkout” link reuses the saved payment; closing, polling, refreshing or switching presentation never creates a second payment. The browser receives no merchant API secret.

One currency/network is fixed for each attempt. If creating an order times out, continue the saved checkout. Its immutable payload and `request_id` are reused. Do not create a replacement order or send a second transfer while the first is unresolved.

## Payment verification

All create/query requests are signed on the server with Base64 HMAC-SHA256 over the exact POST body or exact query string. Callbacks trigger a signed query; a callback body or browser return cannot assert payment. The adapter checks the remote/local mapping, original total and currency, asset/network, address and exact payable/received amount. Only a verified PAID result updates the native payment. Underpayment, overpayment, expiration, cancellation and review states remain unpaid. Native settlement is guarded against duplicate notifications and supports retry after a partial acknowledgement failure.

The initial local attempt is persisted before the remote API request. Database locks serialize callbacks and opening/retrying an attempt. Native order totals and gateway ownership are checked again at settlement. These packages handle whole-order payments; partial invoices, recurring billing, refunds and split carts need their own workflows.

## Environment and operation

Use HTTPS for the store and the public callback URL. Require PHP cURL and JSON, with the database driver required by the store. This release uses MySQL/MariaDB storage; Drupal Commerce, Sylius and EC-CUBE can also use PostgreSQL. SQLite is not supported by the attempt storage. Configure the proxy's HTTPS headers correctly so generated URLs use the store's HTTPS origin. Keep the capability token in the payment URL private; exclude these endpoints from caches and access-log query-string collection.

The MochiPay server must support `request_id` deduplication (the MochiPay Web65 generation or later). No new customer-store protocol is imposed on the existing 11 plugin packages. No MochiPay database or Monitor change is included or required by this plugin release.

Payment attempts and native payment history are retained when the gateway is disabled or removed. Back up both configuration and payment tables before an update. Replace files/update the Composer package in place; do not uninstall/reinstall to upgrade. Do not change the merchant API identity while its saved payments are unresolved.

## Validation and acceptance

PHP 8.3 lint, minimum-version PHP syntax parsing, exact-money/retry/verification fixtures, upstream interface checks and shared Chromium dialog checks were performed. These are integration builds: installation in a complete running store and a real end-to-end paid order still require staging acceptance. No live payment or complete-store certification is claimed.

On a staging store, verify a new checkout in both modes; refresh and reopen it; simulate a lost create response; confirm that the same remote order is retained; pay once; deliver duplicate notifications; check that exactly one native payment is recorded. Also verify an unpaid return, a wrong/short amount, an expired order, a changed native total, and mobile checkout. Keep the gateway disabled on the live store until those checks pass.


## Buyer-language UI — build 75

ON_SITE payment dialogs offer English (default), Chinese, Spanish, Brazilian Portuguese, French and German. Choose a language manually in the dialog. Switching does not recreate the order, change its amount or extend expiry. HPP uses the MochiPay hosted page language selector.

This is an optional frontend update. Existing installations remain usable; no account, API-signature, callback, database or PHP business-flow change is required. Preserve your settings. Replace the listed onsite.js file and clear browser/CDN/compiled storefront caches, or update this package through your platform’s normal process. Installation instructions and merchant gateway settings remain in English. The package core version/PHP compatibility range is unchanged; language UI build 75 identifies this distribution.

Frontend file: MochiPaySylius/src/lib/onsite.js

Local language/browser checks cover the reusable dialog assets. Real store checkout and live payment acceptance still require your own staging validation.


## ON_SITE layout update — build 76

The payment dialog keeps all four outer corners rounded. Long content scrolls inside an inset region; the close button and language selector remain outside it. Update both MochiPaySylius/src/lib/onsite.css and MochiPaySylius/src/lib/onsite.js. Preserve gateway settings and order mappings. Clear browser/CDN/storefront caches after replacing these assets. API, PHP payment logic, exact amount, wallet address and callback verification are unchanged. HPP is unchanged.

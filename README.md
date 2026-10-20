# MochiPay payment integrations

Cryptocurrency checkout for ecommerce stores, merchant backends and AI agents. Customer payments go directly to the merchant wallet. The hosted MochiPay service requires a merchant account and active subscription; plugin code is free under its package-specific license.

[Get started](https://mochi.bz/GettingStarted.aspx) · [API reference](https://mochi.bz/Developers/Reference.aspx) · [Download release assets](https://github.com/hznetnewpower/mochipay-plugins/releases/latest)

## Current publication

**2026.10.20-sandbox.1** includes WooCommerce 1.5.24, current native store packages and backend demos, and integration Skill 1.2.15. Merchant sandbox mode supports simulated success/failure in ON_SITE and hosted checkout. See [release notes](docs/RELEASE_NOTES.md) and the [download catalog](docs/DOWNLOADS.md).

Use internal orders for sandbox testing: a simulated paid order can trigger the connected store's normal fulfillment. Switch to Live before taking real customer payments. Current sandbox controls require the corresponding WEB82.102 server deployment.

Each explicit checkout is independent. Retain the original request_id and payload for retries. Verify the saved invoice binding and authenticated payment status on the server before fulfillment; browser returns and QR scans do not prove payment.

## Installation

Download the platform-specific ZIP from Release Assets. Do not install the entire GitHub Source code ZIP in your store. Preserve saved gateway configuration and order bindings. WooCommerce changed its root to mochipay-for-woocommerce: deactivate the old mochipay-woocommerce gateway before activating the new one; do not uninstall or run both.

## Developer resources

Source folders: plugins/, examples/, skills/, mcp/, mobile/, extensions/chrome-payment-assistant/. Each resource retains its native package root. API secrets stay on the merchant backend; mobile demos do not embed them. The separate PHP SDK lives at https://github.com/hznetnewpower/mochipay-php and is not changed by this publication.

This repository contains only integration sources and public documentation. Native acceptance, marketplace review and real payment verification remain required for deployment. See [publication checks](docs/VALIDATION.md).

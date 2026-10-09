# MochiPay payment integrations

Cryptocurrency checkout for ecommerce stores, merchant backends and AI agents. Customer payments go directly to the merchant wallet. The hosted MochiPay service requires a merchant account and active subscription; plugin code is free under its package-specific license.

[Get started](https://mochi.bz/GettingStarted.aspx) · [API reference](https://mochi.bz/Developers/Reference.aspx) · [Download release assets](https://github.com/hznetnewpower/mochipay-plugins/releases/latest)

## Current publication

2026.10.09-payment-flow.1 / WEB82.7 integration rollout. WooCommerce 1.5.14, PHP Demo 1.2.0, MCP 1.0.4, Skill 1.2.5 and Chrome 1.0.4. See [CHANGELOG](CHANGELOG.md) and [download catalog](docs/DOWNLOADS.md).

Each explicit create uses an independent attempt; repeated merchant references are allowed. Retry the same saved request with its original request_id and payload. Bind callback/return processing to the original payment attempt and verify payment on the server before fulfilling orders. A browser return is navigation only.

ON_SITE and HPP checkout are supported. Buyer assets support en, zh, es, pt-br, fr, de, nl, fa, ru, ar, ja, ko, it, tr and id. Setup/technical documents remain English. Exact network and amount must match the saved invoice.

## Installation

Download the platform-specific ZIP from Release Assets. Do not install the entire GitHub Source code ZIP in your store. Preserve saved gateway configuration and order bindings. WooCommerce changed its root to mochipay-for-woocommerce: deactivate the old mochipay-woocommerce gateway before activating the new one; do not uninstall or run both.

## Developer resources

Source folders: plugins/, examples/, skills/, mcp/, mobile/, extensions/chrome-payment-assistant/. Each resource retains its native package root. API secrets stay on the merchant backend; mobile demos do not embed them. The separate PHP SDK lives at https://github.com/hznetnewpower/mochipay-php and is not changed by this publication.

This repository contains only integration sources and public documentation. Native acceptance, marketplace review and real payment verification remain required for deployment. See [publication checks](docs/VALIDATION.md).

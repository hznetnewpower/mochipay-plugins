# 2026.10.17-checkout.1

# MochiPay integration update — 2026-10-17

Public integration packages are synchronized with WEB82.76 / buyer assets82.75. This publication includes WooCommerce1.5.21, all four OpenCart branches (including the previously missed nested OpenCart4 download), the other native store plugins, and PHP/Node/Python/C#/Java checkout demos. The compact warm-orange QR-first interface retains a centered asset icon, visible address/amount copy feedback, and a countdown based on saved UTC expiry and server time. Expired QR/address stay masked in place. Buyer language choices include English, Chinese, Spanish and more.

Skill1.2.13 starts from the live ClawHub1.2.12 MIT-0 files, refreshes exact catalogs/hashes and the current API contract, and documents checkout/expiry behavior. Merchant references remain repeatable labels; request_id is the retry identity. The PHP SDK is maintained independently. MCP1.0.7 and Chrome1.0.7 code were already current and are preserved byte-for-byte. Chrome distribution is a ZIP for manual desktop installation; no Chrome Web Store publication is claimed.

Install the exact platform archive and update gateways in place, preserving settings and order bindings. Match platform/runtime requirements in the package. The plugins are free; the hosted service requires a merchant subscription. No payment transaction commission. Real blockchain acceptance/native installation on each core remains separate.

No MochiPay server/Monitor/database change is introduced by this integration publication. The separate WEB82.76 hosted-page repair still needs its two ASPX deployments if not already applied.


## 2026.10.13-multilanguages-trx.1

- Synced published website installer ZIPs, including WooCommerce 1.5.19 and native TRX support.
- Refreshed MCP, AI Skill, demos and package catalogs.
- Retained OpenCart 4 as a legacy package until a new website distribution is available.

# MochiPay Plugins — Payment Flow Update

- Each explicit checkout starts a new payment attempt, including repeated merchant_order_id values. Retries of the same transport request retain the saved request_id and exact payload.
- Native plugins, PHP/backend/report/mobile demos, MCP and Chrome are synchronized with WEB82.7.
- Hosted checkout offers a merchant return link when a saved validated destination exists. Navigation does not prove payment or stop chain monitoring.
- Query budgets and transient-failure handling are retained; buyer-facing assets support fifteen languages.
- WooCommerce 1.5.14 uses mochipay-for-woocommerce and includes the latest class-prefix/text-domain/WordPress HTTP timeout repairs. Deactivate the former mochipay-woocommerce gateway before activating the new directory; do not uninstall or activate both.
- Zen Cart 1.2.0 ZIPs retain the accepted source-release bytes.

Downloads: 19 native store packages, 10 developer resources, and Chrome Payment Assistant 1.0.4. Choose the platform-specific ZIP from Assets.

This publication packaging does not change runtime code and does not claim current live WordPress Plugin Check, native store/mobile builds, live-chain payment tests, or marketplace approval. Use WEB82.7 for the complete rollout; request-id recovery requires WEB82.6 or later. No payment backend, Monitor, database or production configuration is included. The separate PHP SDK repository and WordPress.org submission are not updated by this release.

# MochiPay integrations — 2026-10-20

- Test payment success and failure from ON_SITE checkout or the hosted checkout page using merchant sandbox mode. Test orders are identified in the dashboards and excluded from live collection totals.
- Update WooCommerce to 1.5.24 for the current sandbox checkout and responsive payment interface.
- Refresh native store packages and backend demos with the current checkout controls and layout. Existing order verification and callback rules are retained.
- Update the MIT-0 integration Skill to 1.2.15 with current package checksums and sandbox guidance.

Choose the ZIP for your platform from Release Assets. The repository Source code ZIP is not a store installer. Preserve gateway settings and existing order bindings when upgrading. Marketplace review and native-store acceptance remain separate.

Sandbox orders can trigger a connected store's normal fulfillment flow. Use internal test orders and switch the merchant back to Live before accepting real customer payments. Current sandbox controls require the corresponding MochiPay WEB82.102 server deployment. Publishing these integration packages does not deploy the server, Monitor or database.

MCP 1.0.7, Chrome Payment Assistant 1.0.7 and PHP SDK 1.0.2 retain their existing client versions. Chrome is distributed as a ZIP for manual installation. PHP SDK is maintained in its separate repository.

# Merchant setup

## Prepare your account

Create an account at https://mochi.bz/Register.aspx and activate the chosen
subscription. Configure active receiving wallets in the MochiPay dashboard.
Obtain your merchant API key and secret. Keep credentials on the server and
never include them in a support screenshot or issue.

## Install the matching package

Choose the platform branch and PHP environment in DOWNLOADS.md. Follow the
README inside that archive; installer layouts are intentionally different.
OpenCart 4 uses the installer filename `mochipay.ocmod.zip`. EC-CUBE expects
composer.json at archive root. Do not add a wrapper folder to those archives.

## Review the defaults

| Setting | Default |
|---|---|
| MochiPay URL | https://mochi.bz |
| Payment mode | ON_SITE |
| Unique amount direction | UP |
| Supported asset/network selections | All five selected |
| Gateway enabled | No; enable after credentials and wallets are ready |

Select UP or DOWN for your preference, enable the gateway and save. Complete
native payment, delivery or channel configuration. Offer only methods with
matching active wallets. A saved HPP choice stays HPP during an in-place update.

## Test checkout

Check a staging order in ON_SITE and HPP. Reopen or refresh the same attempt;
verify its amount, network and order mapping. Complete a real payment and check
the native paid state only after server-side verification. Test an unpaid
return and repeated notifications. Custom themes may need separate acceptance.

## Troubleshooting

| Symptom | First checks |
|---|---|
| Gateway absent | Correct store branch, native activation and cache |
| Payment method unavailable | Delivery/channel restrictions and gateway settings |
| No wallet available | Active wallet for the selected asset and network |
| Order remains pending | Credentials, subscription, exact amount and public callback reachability |
| Payment dialog assets look stale | Store, browser and CDN cache |

Refunds, partial invoices, recurring billing and split carts are not added by
this publication. Follow the current platform guide for actual capabilities.

For existing installations, replace files in place while preserving settings
and payment history. Do not uninstall/reinstall as a routine upgrade.

## PHP, Skill and MCP

See [Developer and AI resources](DEVELOPER_RESOURCES.md). These four resources are independent of the store plugins and do not install into a shopping-platform extensions screen.

# Drupal Commerce — public distribution

Publication revision: **2026.10.07-multilanguages.1**. This identifies packaging and documentation,
not a new payment protocol or a new native plugin version.

## Choose the correct environment

| Setting | Supported environment |
|---|---|
| Store | Commerce 2.40.x with Drupal 9.3–10.x; or Commerce 3.3.10+ <3.4 with Drupal 10.3–11.x |
| PHP | Drupal 9.3–9.5: PHP 7.4–8.1; Drupal 10: PHP 8.1–8.3; Drupal 11: PHP 8.3–8.4, only when allowed by the exact Drupal release |
| Checkout modes | ON_SITE and HPP; ON_SITE is the default |
| Merchant settings and installation instructions | English |
| Buyer payment interface | English (default), Chinese, Spanish, Brazilian Portuguese, French, German |

Use a PHP version permitted by both the plugin and the exact installed store
release, including its dependency lock and patches. The plugin does not upgrade
the store's PHP compatibility. Choose buyer language manually; this distribution
does not add automatic store-language detection.

## Installation

Follow the original platform-specific instructions inside this ZIP:

- `mochipay/README.md`

Full setup guide: https://mochi.bz/Guides/GuideDrupalCommerce.aspx

Download the platform ZIP attached to a GitHub Release, or the current package
on https://mochi.bz/Developers/Reference.aspx#plugins. GitHub's automatic whole-repository
"Source code (zip)" is not an installable store plugin.
For OpenCart 4, follow the original guide's filename instruction: save the
selected package as `mochipay.ocmod.zip` before installing so its extension
directory matches the native `mochipay` routes.

## Ready-made defaults

- MochiPay URL: `https://mochi.bz`.
- Checkout: `ON_SITE`; `HPP` is also available.
- Unique amount direction: `UP`; choose `DOWN` if preferred.
- All five choices selected: USDT/TRC20, USDC/ERC20, BTC/Bitcoin,
  ETH/Ethereum and SOL/Solana.
- The gateway starts disabled. Enter the merchant API credentials, enable it
  and save. Offer only methods with a matching active receiving wallet.
- Native payment method, delivery and channel restrictions still apply.

## Existing installations

This revision adds publication documents and missing license text, and removes
bundled validation screenshots. Retained plugin files are byte-identical to the
current website distribution. There is no new payment logic, callback format,
API signature, language behavior or PHP requirement. Existing merchants do not
need to reinstall for this publication revision. If updating files, preserve
credentials, settings and historical payment mappings; do not uninstall first.

## Before accepting live payments

This package is an initial integration build. Verify installation, both checkout modes, callbacks and a real paid order in
your own staging store. Source and simulated checks do not certify every native
store, custom theme or live blockchain payment. No "tested store versions"
badge is claimed by this publication revision.

Never expose API secrets in a public issue or in browser code. For a payment
problem, check the server-side verified status rather than trusting a return URL.

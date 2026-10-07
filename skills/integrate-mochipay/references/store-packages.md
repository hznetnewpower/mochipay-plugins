# Store packages and setup

This catalog is a release snapshot, not a promise that every core accepts every PHP version in an envelope. Read the exact core row, package instructions and the installed core's requirements before installing. For an unknown patch release, report compatibility as unverified; inspect its requirements instead of guessing.

All 19 native store packages support ON_SITE and HPP. Use the catalog's exact download URL. Rename the downloaded OpenCart 4 archive to `mochipay.ocmod.zip` before using its Installer; the website Download action supplies this installation filename. Download only from the official site; compare the bundled SHA-256 before executing a pinned package. If the official package changed, verify the updated release and instructions; do not silently bypass a hash mismatch.

## WooCommerce (`woocommerce`)

Classic Checkout, Checkout Blocks and HPOS.

Plugin PHP envelope: PHP 7.4–8.4.

| Core | PHP / constraint |
| --- | --- |
| WooCommerce + WordPress | 7.4-8.4 within both core releases' requirements |

[Download](https://mochi.bz/Downloads/MochiPay_WooCommerce_1.5.2_Multilanguages.zip) · [Guide](https://mochi.bz/Guides/GuideWooCommerce.aspx)

## OpenCart 2.0–2.2 (`opencart20`)

Early payment routes and version-specific template paths.

Plugin PHP envelope: PHP 5.6–7.4; newer PHP needs core patches.

| Core | PHP / constraint |
| --- | --- |
| Stock 2.0.x-2.2.x | 5.6; use 7.0-7.1 only if the exact core build permits it |
| Core with PHP compatibility patches | 7.2-7.4 only where the patched core permits it |

[Download](https://mochi.bz/Downloads/MochiPay_OpenCart_2.0-2.2_Multilanguages.ocmod.zip) · [Guide](https://mochi.bz/Guides/GuideOpenCart2.aspx)

## OpenCart 2.3 (`opencart23`)

The separate 2.3 extension/payment structure.

Plugin PHP envelope: PHP 5.6–7.4; newer PHP needs core patches.

| Core | PHP / constraint |
| --- | --- |
| Stock 2.3.x | 5.6-7.1 within the exact core build's requirements |
| Core with PHP compatibility patches | 7.2-7.4 only where the patched core permits it |

[Download](https://mochi.bz/Downloads/MochiPay_OpenCart_2.3_Multilanguages.ocmod.zip) · [Guide](https://mochi.bz/Guides/GuideOpenCart23.aspx)

## OpenCart 3 (`opencart3`)

Twig views and the native OpenCart 3 payment gateway.

Plugin PHP envelope: PHP 5.6–8.4; exact core/dependency requirements apply.

| Core | PHP / constraint |
| --- | --- |
| Early 3.0.x builds | 5.6-7.x only within the core/dependency requirements |
| 3.0.3.8 and later 3.x builds | At least 7.3; newer locked dependencies may raise this minimum |
| 3.x builds with native PHP 8 compatibility | 8.0-8.4 only within that core build's requirements |

[Download](https://mochi.bz/Downloads/MochiPay_OpenCart_3_Multilanguages.ocmod.zip) · [Guide](https://mochi.bz/Guides/GuideOpenCart3.aspx)

## OpenCart 4 (`opencart4`)

Native 4.x namespaces, routes and extension packaging.

Plugin PHP envelope: PHP 8.0.2–8.4; 4.1.0.4 requires 8.1+.

| Core | PHP / constraint |
| --- | --- |
| 4.0.2.x | 8.0.2-8.4 within the exact core/dependency requirements |
| 4.1.x | 8.1-8.4 where required by the release; 4.1.0.4 composer minimum is 8.1 |

[Download](https://mochi.bz/Downloads/OpenCart4/mochipay-multilanguages.ocmod.zip) · [Guide](https://mochi.bz/Guides/GuideOpenCart4.aspx)

## Zen Cart 1.5.3–1.5.7 (`zencart-legacy`)

Legacy define-based language files and native status settings.

Plugin PHP envelope: PHP 5.6–8.0, depending on the core version.

| Core | PHP / constraint |
| --- | --- |
| 1.5.3-1.5.4 | 5.6 |
| 1.5.5 | 5.6-7.1 |
| 1.5.6 | 5.6-7.3 |
| 1.5.7 | 5.6-8.0 |

[Download](https://mochi.bz/Downloads/MochiPay_ZenCart_1.5.3-1.5.7_Multilanguages.zip) · [Guide](https://mochi.bz/Guides/GuideZenCartLegacy.aspx)

## Zen Cart 1.5.8–2.2 (`zencart-modern`)

Modern array-based language files and native status settings.

Plugin PHP envelope: PHP 7.3–8.4, depending on the core version.

| Core | PHP / constraint |
| --- | --- |
| 1.5.8/1.5.8a | 7.3-8.3; installation utility requires 8.2 or lower |
| 2.0.x / 2.1.x | 8.0-8.3 |
| 2.2.x | 8.2-8.4 for this package |

[Download](https://mochi.bz/Downloads/MochiPay_ZenCart_1.5.8-2.2_Multilanguages.zip) · [Guide](https://mochi.bz/Guides/GuideZenCart.aspx)

## Magento 1 / OpenMage (`magento1`)

Magento CE 1.9.3.0–1.9.4.5 and compatible OpenMage 19/20.

Plugin PHP envelope: PHP 5.6–8.4; PHP 8 requires a compatible OpenMage core.

| Core | PHP / constraint |
| --- | --- |
| Magento CE 1.9.3.x | 5.6 |
| Magento CE 1.9.4.x | 5.6 or 7.2, with the applicable PHP/security patches |
| OpenMage 19 / older 20 releases | Use the exact release's PHP requirements, within 5.6-8.4 |
| OpenMage 20.14+ | 8.1-8.4; upstream notes possible warnings on 8.4 |

[Download](https://mochi.bz/Downloads/MochiPay_Magento1_OpenMage_Multilanguages.zip) · [Guide](https://mochi.bz/Guides/GuideMagento1.aspx)

## Magento 2 (`magento2`)

Magento Open Source 2.3.7–2.4.8 with native checkout.

Plugin PHP envelope: PHP 7.3–8.4, depending on the core version.

| Core | PHP / constraint |
| --- | --- |
| 2.3.7 | 7.3/7.4 according to the exact release |
| 2.4.0-2.4.3 | 7.3/7.4 according to the exact release; later releases in this group require 7.4 |
| 2.4.4-2.4.5 | 8.1 |
| 2.4.6 | 8.1/8.2 |
| 2.4.7 | 8.2/8.3 |
| 2.4.8 | 8.3/8.4 |

[Download](https://mochi.bz/Downloads/MochiPay_Magento2_Multilanguages.zip) · [Guide](https://mochi.bz/Guides/GuideMagento2.aspx)

## PrestaShop 1.6 (`prestashop-legacy`)

The PrestaShop 1.6.1 payment hook and submit form.

Plugin PHP envelope: PHP 5.6–7.1.

| Core | PHP / constraint |
| --- | --- |
| 1.6.1.x | 5.6-7.1 for this module; no PHP 7.2+ or PHP 8 |

[Download](https://mochi.bz/Downloads/MochiPay_PrestaShop_1.6_Multilanguages.zip) · [Guide](https://mochi.bz/Guides/GuidePrestaShop16.aspx)

## PrestaShop 1.7 / 8 / 9 (`prestashop-modern`)

PrestaShop 1.7.6–1.7.8, 8.x and 9.0.x paymentOptions.

Plugin PHP envelope: PHP 5.6–8.4, depending on the core version.

| Core | PHP / constraint |
| --- | --- |
| 1.7.6 | 5.6-7.2 |
| 1.7.7 | 7.1-7.3 |
| 1.7.8 | 7.1-7.4 |
| 8.0-8.2 | 7.2.5-8.1 |
| 9.0.x | 8.1-8.4 |

[Download](https://mochi.bz/Downloads/MochiPay_PrestaShop_1.7-9_Multilanguages.zip) · [Guide](https://mochi.bz/Guides/GuidePrestaShop.aspx)

## Shopware 6.6 (`shopware66`)

Shopware asynchronous payment handler. Initial integration build; native-store staging and real-payment acceptance required.

Plugin PHP envelope: PHP 8.2.x / 8.3.x / 8.4.x.

| Core | PHP / constraint |
| --- | --- |
| Shopware >=6.6.10.0 <6.7.0.0 | PHP 8.2.x / 8.3.x / 8.4.x |

[Download](https://mochi.bz/Downloads/MochiPay_Shopware_6.6_1.0.0_Multilanguages.zip) · [Guide](https://mochi.bz/Guides/GuideShopware.aspx)

## Shopware 6.7 (`shopware67`)

Shopware AbstractPaymentHandler. Initial integration build; native-store staging and real-payment acceptance required.

Plugin PHP envelope: PHP 8.2.x / 8.3.x / 8.4.x.

| Core | PHP / constraint |
| --- | --- |
| Shopware >=6.7.0.0 <6.8.0.0 | PHP 8.2.x / 8.3.x / 8.4.x |

[Download](https://mochi.bz/Downloads/MochiPay_Shopware_6.7_1.0.0_Multilanguages.zip) · [Guide](https://mochi.bz/Guides/GuideShopware.aspx)

## Drupal Commerce (`drupal`)

Commerce offsite payment gateway and completed commerce_payment entity. Initial integration build; native-store staging and real-payment acceptance required.

Plugin PHP envelope: Drupal 9.3–9.5: PHP 7.4–8.1; Drupal 10: PHP 8.1–8.3; Drupal 11: PHP 8.3–8.4, only when allowed by the exact Drupal release.

| Core | PHP / constraint |
| --- | --- |
| Commerce 2.40.x / Drupal 9.3–9.5 | 7.4–8.1 within exact core requirements |
| Commerce 2.40.x / Drupal 10.x; Commerce 3.3.10+ <3.4 / Drupal 10.3+ | 8.1–8.3; later Drupal 10 releases may require 8.3 |
| Commerce 3.3.10+ <3.4 / Drupal 11.x | 8.3–8.4 only where allowed by the exact core/dependency lock |

[Download](https://mochi.bz/Downloads/MochiPay_Drupal_Commerce_1.0.0_Multilanguages.zip) · [Guide](https://mochi.bz/Guides/GuideDrupalCommerce.aspx)

## EC-CUBE 4.3 (`eccube`)

PaymentMethodInterface with native PurchaseFlow prepare/commit. Initial integration build; native-store staging and real-payment acceptance required.

Plugin PHP envelope: PHP 8.1.x / 8.2.x / 8.3.x.

| Core | PHP / constraint |
| --- | --- |
| EC-CUBE >=4.3.0 <4.4.0 | PHP 8.1.x / 8.2.x / 8.3.x |

[Download](https://mochi.bz/Downloads/MochiPay_EC-CUBE_4.3_1.0.0_Multilanguages.zip) · [Guide](https://mochi.bz/Guides/GuideECCube.aspx)

## Bagisto 2.3 (`bagisto`)

Laravel package and Webkul payment adapter with native order/invoice repositories. Initial integration build; native-store staging and real-payment acceptance required.

Plugin PHP envelope: PHP 8.2.x / 8.3.x / 8.4.x (also satisfy the store dependency lock).

| Core | PHP / constraint |
| --- | --- |
| Bagisto >=2.3.0 <2.4.0 | PHP 8.2.x / 8.3.x / 8.4.x (also satisfy the store dependency lock) |

[Download](https://mochi.bz/Downloads/MochiPay_Bagisto_2.3_1.0.0_Multilanguages.zip) · [Guide](https://mochi.bz/Guides/GuideBagisto.aspx)

## Sylius 2.0 (`sylius`)

Payum gateway factory/actions and Sylius payment state machine. Initial integration build; native-store staging and real-payment acceptance required.

Plugin PHP envelope: PHP 8.2.x / 8.3.x / 8.4.x (also satisfy the store dependency lock).

| Core | PHP / constraint |
| --- | --- |
| Sylius >=2.0.0 <2.1.0 with PayumBundle 2.6+ / Payum 1.7-compatible core | PHP 8.2.x / 8.3.x / 8.4.x (also satisfy the store dependency lock) |

[Download](https://mochi.bz/Downloads/MochiPay_Sylius_2.0_1.0.0_Multilanguages.zip) · [Guide](https://mochi.bz/Guides/GuideSylius.aspx)

## osCommerce 4.14 (`oscommerce`)

ModulePayment, platform configuration and native OrdersPayment ledger. Initial integration build; native-store staging and real-payment acceptance required.

Plugin PHP envelope: PHP 7.4.x–8.3.x, subject to the installed osCommerce release and dependency lock.

| Core | PHP / constraint |
| --- | --- |
| osCommerce 4.14.x; native V4 orderPayment module API | PHP 7.4.x–8.3.x, subject to the installed osCommerce release and dependency lock |

[Download](https://mochi.bz/Downloads/MochiPay_osCommerce_4.14_1.0.0_Multilanguages.zip) · [Guide](https://mochi.bz/Guides/GuideOsCommerce.aspx)

## thirty bees 1.6 (`thirtybees`)

PaymentModule, native waiting state and OrderHistory payment transition. Initial integration build; native-store staging and real-payment acceptance required.

Plugin PHP envelope: PHP 7.4.x / 8.0.x / 8.1.x / 8.2.x / 8.3.x; use the matching thirty bees distribution.

| Core | PHP / constraint |
| --- | --- |
| thirty bees >=1.6.0 <1.7.0 | PHP 7.4.x / 8.0.x / 8.1.x / 8.2.x / 8.3.x; use the matching thirty bees distribution |

[Download](https://mochi.bz/Downloads/MochiPay_thirty_bees_1.6_1.0.0_Multilanguages.zip) · [Guide](https://mochi.bz/Guides/GuideThirtyBees.aspx)

New adapters require MochiPay Web65+ for optional request_id deduplication. Do not install both Shopware branches. osCommerce means V4.14, not 2.x/3.x; EC-CUBE means 4.3 with JPY; Sylius means 2.0 with its native Payum checkout. Read each package README for native installation, database and channel constraints. Treat source/interface/simulated checks as distinct from full native-store installation or paid acceptance. Keep existing customer plugins and API programs working unchanged.

## New installation defaults

MochiPay URL `https://mochi.bz` without `/api`; payment interface ON_SITE; five asset/network choices enabled; unique amount direction UP; gateway disabled until the merchant enables it. Enter the merchant's API key and secret privately. Keep only methods supported by active receiving wallets; choose UP or DOWN according to merchant preference. These are new-install defaults, not instructions to overwrite existing saved settings.

Create an account, activate a subscription, set a display name, and configure an active receiving wallet. If using a generated wallet, the merchant must privately back up its recovery phrase; never request it. Obtain API credentials in the dashboard. Do not regenerate an existing secret during troubleshooting: it invalidates integrations using the old secret.

Update native packages in place, preserve settings and historical order mappings, and follow each core's cache/compiler instructions. Do not uninstall as an upgrade step. Original Magento CE 1.0–1.8 and 1.9.0–1.9.2 are outside this M1 package; PHP 8 requires a compatible OpenMage release. Zen Cart before 1.5.3 is outside these packages even if an earlier core runs on PHP 5.

## Classic SaaS and payment links

Classic SaaS: Shopyy / Shopoem (shared guide), Shoplus, Wooshoppaas and Fecify use HPP only. Read their guides from https://mochi.bz/IntegrationGuides.aspx. Dashboard payment links also use HPP; a link alone does not synchronize an external store order. Custom applications and the PHP Demo offer both ON_SITE and HPP.

Current native downloads include one English-default buyer frontend with a manual selector for English, Chinese, Spanish, Brazilian Portuguese, French and German. Gateway settings and installation instructions stay English. No automatic store-language forwarding is required. The current catalog supersedes older download URLs; no old package or redirect is published. Existing installed gateways still work.

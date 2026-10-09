# Checkout product-data fix (2026-10-09)

Fixes the productType() array/null error, INVALID_AMOUNT/PRECISION, and incorrect standalone endpoint routes.

- Restore missing checkout products from the saved orders_products records.
- Reject zero/invalid values locally and use exact decimal string multiplication, followed by currency rounding (fiat 2, USDT/USDC 6, BTC/ETH/SOL 8 places). Creation and settlement share the same calculation. Custom API currency precision overrides need a matching adapter change.
- Read amount and currency from the saved orders row, using the same conversion as payment verification.
- Clear the cart only after the payment attempt has been saved.
- Use standalone catalog PHP URLs for ON_SITE, polling, HPP return and callback. Reject incomplete saved payment tokens before navigating.
- Handle PHP 7+ Throwable failures through the existing order-history error path.
- An empty product list defaults to PHYSICAL instead of being classified as a digital service.

## Update

Back up and replace includes/modules/payment/mochipay.php and includes/classes/mochipay_bridge.php and mochipay_pay.php in place. No uninstall, configuration reset or database migration is required. README.md and this file are documentation only.

Run a new unpaid checkout with FEC Checkout enabled. Confirm the local order amount, product information and chosen asset, and confirm the payment page opens. Then verify payment through the existing authenticated callback/query flow. A full FEC installation and live payment were not available for testing here.

An order saved before the fatal error may still exist without a payment attempt. Review that order separately; this file update does not automatically create a payment for historical orders.

## Checks performed

PHP 8.3 syntax checks passed for all nine PHP files. Mocked checkouts passed for null products, a null order object, empty saved products, digital products, ON_SITE/HPP, zero-amount rejection, and PHP Throwable handling. Currency-rounded amount multiplication passed 109 fixture comparisons, including half-cent rounding, carry into the whole amount, fiat and all five supported crypto currencies. These checks do not establish full FEC/core/PHP-version or live-payment compatibility.

Current payment behavior: WEB82.6 permits repeated merchant references. Each explicit checkout starts an independent system order; no historical-order migration is included. Payment state queries bypass Zen queryCache.

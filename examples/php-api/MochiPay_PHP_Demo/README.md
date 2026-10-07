# MochiPay PHP API Demo 1.1.1 — On-site + HPP

Requirements: PHP 7.0–8.4, cURL, JSON, sessions, secure random_bytes, HTTPS, and writable PRIVATE temporary storage. This is an integration demo, not a production shopping cart. Restrict order.php to your administrators or a protected staging environment; the create/query forms are not a public checkout.

## Configuration
1. Upload this entire directory, including portable/ and its bundled QR-code license. Never serve PHP source as text.
2. In order.php set MOCHIPAY_API_KEY and MOCHIPAY_API_SECRET. MochiPay URL defaults to https://mochi.bz. Configure the merchant wallet for each offered asset/network.
3. Set MOCHIPAY_DEMO_PUBLIC_URL to the absolute HTTPS directory containing order.php and callback.php (for example https://your-store.example/mochipay-demo). An empty value infers the direct request URL; set it explicitly behind a proxy.
4. On-site is the default. MOCHIPAY_CHECKOUT_MODE can be ON_SITE or HPP. This selects the preferred link in the demo; it is not a field sent to Create Order. All five methods are available: USDT_TRC20, USDC_ERC20, BTC_BITCOIN, ETH_ERC20, SOL_SOLANA. UP is the default unique amount direction; DOWN is available.
5. The supplied cURL requests use CURLOPT_SSL_VERIFYPEER => false. API credentials remain server-side.

## Try both payment flows
Create an order with a new merchant reference. Open the on-site dialog to see the exact chain amount, address, asset/network, address QR code and status without leaving your domain. The address QR code contains the address only; enter the exact displayed amount in your wallet. Polling goes to your own backend, which signs Query Order. The HPP link redirects to MochiPay and uses the SAME order. Neither reopening nor switching the display creates a second payment.

Use decimal strings for money. The decoder preserves API amount numbers as strings; do not convert them to float, round the tail amount, or replace pay_amount with the original fiat amount. Do not expose the complete Query response, API key, secret or customer data to browser scripts. The local poll returns only a safe payment view.

## Verification and recovery
Private records bind merchant reference, original amount/currency/method, system order ID, chain amount and address. They live outside the website under the OS temporary directory (mochipay-demo-*). Configure OS permissions/ACLs so only the PHP service account can read/write them. Temporary records can be lost when the host clears its temp directory: never use this storage as a production order database.

The attempt is written before Create Order. An uncertain response is retried by Query Order using the SAME merchant reference. Do not blindly repeat Create Order or change the reference after a timeout: the API does not guarantee reference idempotency. A damaged/missing record requires review before another create. Treat references as unique; retain the MochiPay order_id once known. The demo retains the reference after a failed create for recovery; edit it only for a genuinely new order.

callback.php POST queries the authenticated API and compares the result with the saved local order. It returns OK only for a bound PAID order with the expected received amount. GET queries the saved order before displaying a safe result. A notification or browser return alone is never proof of payment.

## Production adaptation
Replace demo temporary files with your durable order database and transaction/locking model. Bind every payment to the trusted server-side order total, currency and selected asset/network. Implement the callback TODO as an atomic, idempotent fulfillment transaction; repeated callbacks and polls must not deliver twice. This demo does not fulfill orders, send goods or update your shopping cart. Verify expiry and handle UNDERPAID / OVERPAID / EXPIRED by your business rules. Protect creation/query endpoints with authentication, CSRF protection, authorization and rate limits. Deploy behind HTTPS and keep customer information off public payment responses.

Classic SaaS integrations remain HPP-only. This custom PHP/API demo supports both on-site and HPP.

## Concrete HPP and embedded ON_SITE code (checkout.php)
After creating an order in order.php, use the two code-example links under the result. checkout.php reads the SAME saved local attempt and verifies its token and authenticated Query response. It never calls Create Order.

- mode=HPP: the PHP controller validates the payment binding and destination, sends HTTP 303 Location to payment_url and exits before rendering HTML.
- mode=ON_SITE: the PHP controller builds a local poll/complete configuration, then renders your store checkout with bundled onsite.css, qrcode.min.js and onsite.js. The dialog opens over the existing merchant page; closing/reopening it keeps the checkout and original order intact. This is a local dialog, not an iframe of the HPP.
- order.php?view=...&token=...&poll=1 is the existing local status endpoint. It signs the query and checks the saved order and exact amounts before returning the safe payment view. callback.php demonstrates verified browser return/notification; production fulfillment belongs in your durable idempotent database transaction.

Reference and token in example URLs identify an existing demo attempt. In a production store, resolve the authorized local order from your customer session/database and protect its capability token. Do not expose the complete API/customer response or credentials to browser scripts. The demo temporary-file storage remains demo-only.


## Buyer-language UI — build 75

ON_SITE payment dialogs offer English (default), Chinese, Spanish, Brazilian Portuguese, French and German. Choose a language manually in the dialog. Switching does not recreate the order, change its amount or extend expiry. HPP uses the MochiPay hosted page language selector.

This is an optional frontend update. Existing installations remain usable; no account, API-signature, callback, database or PHP business-flow change is required. Preserve your settings. Replace the listed onsite.js file and clear browser/CDN/compiled storefront caches, or update this package through your platform’s normal process. Installation instructions and merchant gateway settings remain in English. The package core version/PHP compatibility range is unchanged; language UI build 75 identifies this distribution.

Frontend file: MochiPay_PHP_Demo/portable/onsite.js

Local language/browser checks cover the reusable dialog assets. Real store checkout and live payment acceptance still require your own staging validation.


## ON_SITE layout update — build 76

The payment dialog keeps all four outer corners rounded. Long content scrolls inside an inset region; the close button and language selector remain outside it. Update both MochiPay_PHP_Demo/portable/onsite.css and MochiPay_PHP_Demo/portable/onsite.js. Preserve gateway settings and order mappings. Clear browser/CDN/storefront caches after replacing these assets. API, PHP payment business logic, exact amount, wallet address and callback verification are unchanged. HPP is unchanged.

Also update MochiPay_PHP_Demo/checkout.php together with the two assets: it versions their browser URLs for layout build 76. This file changes asset cache metadata only. Use the complete package update if unsure which files to replace.

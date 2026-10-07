# API integration contract

Snapshot: Web80 / PHP Demo 1.1.4, 2026-10-07. Full reference: https://mochi.bz/Developers/Reference.aspx#checkout-modes and https://mochi.bz/Developers/APIGuide.aspx. Runnable PHP 7.0–8.4 example: https://mochi.bz/Downloads/MochiPay_PHP_API_Demo_1.1.4_Multilanguages_Onsite_HPP.zip. Demo PHP support is independent of shopping core requirements.

## Authentication and fields

Base URL https://mochi.bz, without /api. POST /api/v1/orders/create sends UTF-8 JSON with Content-Type: application/json, X-Mochi-Key and X-Mochi-Signature. Sign the exact body and transmit those same bytes; do not reserialize afterwards. Signature is Base64 of raw HMAC-SHA256 with the API secret.

```php
$body = json_encode($payload, JSON_UNESCAPED_SLASHES);
$signature = base64_encode(hash_hmac('sha256', $body, $apiSecret, true));
// Send $body unchanged with X-Mochi-Key and X-Mochi-Signature.
```

Required fields: merchant_order_id (max 100 characters), positive exact decimal amount, currency, payment_method. Use decimal strings and validate supported precision; never round through binary floats. Methods: USDT_TRC20, USDC_ERC20, BTC_BITCOIN, ETH_ERC20, SOL_SOLANA. Enable only choices backed by active receiving wallets and exchange-rate pairs. unique_amount_direction: UP or DOWN, default UP. Optional notify_url and **redirect_url** are merchant HTTPS endpoints; the latter is not named return_url. See the full reference for product/customer fields.

GET /api/v1/orders/query uses exactly one identifier, order_id or merchant_order_id. Sign the exact encoded query string **without ?**, preserving ordering/encoding; send the same auth headers. Prefer the saved MochiPay order_id once known.

```php
$query = http_build_query(array('order_id' => $savedOrderId), '', '&', PHP_QUERY_RFC3986);
$signature = base64_encode(hash_hmac('sha256', $query, $apiSecret, true));
// GET $baseUrl . '/api/v1/orders/query?' . $query
```

Errors have success:false and message. Check HTTP status and schema; 200 alone is insufficient. Create includes success, order_id, merchant_order_id, status, amount, currency, base_pay_amount, unique_amount_delta, unique_amount_direction, pay_amount, payment_method, payment_address, payment_url, expires_at. Query adds wallet_type, network, received_amount, transaction/confirmation and optional customer details. Query has no payment_method field; reconstruct wallet_type + '_' + network. Preserve JSON money values before float parsing. Dates are yyyy-MM-dd HH:mm:ss with no offset; do not silently interpret as browser local time.

## One order, two interfaces

Keep the interface mode in local merchant config, not a payment_mode API field. Save and reuse one create result:

- ON_SITE: show address, exact pay_amount, method/network, QR and copy controls in a merchant-local dialog/page. The address QR omits the amount. Poll your authenticated backend, which makes signed API queries and returns a minimal DTO. Do not expose secrets or the whole query result.
- HPP: validate the returned URL has HTTPS, configured official origin and exact /pay/<saved-order-id> path, no credentials/unexpected port; redirect with HTTP303. Check application order ownership before either interface.

ON_SITE keeps the store experience and can reduce checkout friction; do not claim measured conversion uplift without merchant evidence.

## Durable attempts and fulfillment

Repeated merchant_order_id alone is **not server idempotency**. The current server supports optional request_id: 1–64 ASCII letters/digits or . _ : -. Persist it and the exact payload before creation. Repeating an identical request_id with unchanged creation fields returns the same order; conflicting fields yield REQUEST_ID_CONFLICT, active creation may yield REQUEST_IN_PROGRESS (Retry-After:2), and a missing saved result yields REQUEST_ORDER_UNAVAILABLE. Once order_id is saved, query it. Only on a verified current server with request_id support may an uncertain initial create replay the unchanged payload/request_id. Preserve legacy attempts without request_id with query-first/manual recovery; do not invent historical IDs or retry blindly. A reference query does not prove historical uniqueness.

On callback/browser return, ignore unverified incoming payment values. Query server-to-server and compare ID, reference, currency, original amount, method/network and address with the immutable local binding. Require PAID and exact received_amount == pay_amount. Use decimal arithmetic, preserving the unique payable amount. Underpayment, overpayment, expiration or inconsistent fields require pending/review handling. Fulfill atomically once; duplicate notifications must not ship/credit again. Neither a return nor screenshot proves payment.

The Demo uses private demo records and does not fulfill production stores. Replace storage with durable transactions/locks and application ownership checks while keeping callback verification. Do not treat a demo record token as a complete production authorization system.

Review the integration's HTTP transport configuration before deployment. Certificate-chain validation and hostname validation are separate controls. Use the merchant project's approved transport policy; do not introduce an insecure TLS setting as a troubleshooting shortcut. The official Demo is a reference implementation, not a guarantee of production security.

Read returns-notifications.md for separate HPP synchronous-return and ON_SITE/HPP asynchronous routes. Read developer-examples.md to choose the correct backend/mobile package and common mobile contract.

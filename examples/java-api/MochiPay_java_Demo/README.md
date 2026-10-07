# MochiPay Java Demo 1.0.0 — ON_SITE + HPP

JDK 17+; JDK HTTP client/server and a small bundled strict JSON codec preserving number text. Maven 3.8+ is optional. No external runtime dependency.

## Run

Run `mvn package` then `java -jar target/mochipay-demo-1.0.0.jar` from this directory. Without Maven: `javac -d classes src/main/java/com/mochipay/demo/*.java`, then `java -cp classes com.mochipay.demo.Main`. Bind behind HTTPS for public access.

Set the values listed in `.env.example` in your service environment. This example does not load `.env` automatically. Set an independent random `DEMO_ACCESS_TOKEN` (32+ characters), your MochiPay API key/secret, and an active receiving wallet. Never put the API key or secret in a browser or mobile app. The service listens only on 127.0.0.1; use a correctly configured HTTPS reverse proxy for external access. Set `APP_PUBLIC_URL` to that public HTTPS origin (no path or trailing slash). Loopback HTTP is permitted only for local development. MochiPay defaults to https://mochi.bz; API redirects are disabled and TLS certificates are verified.

Open http://127.0.0.1:8080. Enter the staging access token, choose an enabled asset/network and language, and create a payment. `DEMO_AMOUNT`, `DEMO_CURRENCY` and `DEMO_DIRECTION` are server-owned, default 10.00 USD and UP. All five methods are listed; configure a receiving wallet for every method you offer. ON_SITE is selected by default. Both links reuse the same saved order. The local popup includes address QR, exact amount, ten-language selector and RTL. HPP redirects to the validated MochiPay payment URL, with the buyer language added locally.

## Routes and mobile contract

| Route | Purpose | Authorization |
|---|---|---|
| POST /payments | Create/recover the server-priced demo item | Authorization: Bearer DEMO_ACCESS_TOKEN |
| GET /checkout?r=ID&t=TOKEN&mode=ON_SITE&lang=en | Merchant-local popup | Saved random payment capability |
| GET /checkout?...&mode=HPP | 303 redirect to validated hosted payment | Same saved capability |
| GET /status?r=ID&t=TOKEN | Authenticated upstream query, minimal DTO | Same saved capability |
| POST /callback?r=ID&t=TOKEN | Notification trigger; never trust its body | Same saved capability + signed upstream query |
| GET /complete?r=ID&t=TOKEN | Return/result page that verifies again | Same saved capability |

POST body: `{"request_id":"your-stable-UUID","payment_method":"USDT_TRC20"}`.
Response: `{"success":true,"request_id":"...","checkout_url":"/checkout?r=...&t=..."}`.
The status response is `{"success":true,"data":{"status":"PENDING","payAmount":"10.000001","asset":"USDT","network":"TRC20","address":"...","amount":"10.00","currency":"USD","expiresAt":"...","reference":"..."}}`.
Money is transmitted as decimal strings, never binary floating-point. There are no full upstream responses or customer details in public DTOs.

iOS Swift and Android Kotlin samples use exactly these routes on any of the Node.js, Python, C# or Java backends. Their staging access token is separate from API credentials; replace it with your real app's authenticated session before production. The existing standalone PHP Demo has its own routes; use this common contract with the mobile samples.

## Retry, binding and verification

An attempt (including its exact payload and random capability) is atomically saved before the API call. Its `request_id` remains unchanged. Once an order ID is saved, recovery queries that ID. An uncertain initial create can repeat the identical payload and request_id against the current MochiPay idempotent API. Do not change the method, amount, reference or callback URLs for a retry. Older MochiPay server versions without request_id deduplication require query-first/manual recovery; do not run this retry strategy against them.

Verify order ID, merchant reference, original amount/currency, asset/network, address and exact pay_amount against the immutable saved binding. PAID is accepted only when exact received_amount equals pay_amount. UNDERPAID, OVERPAID, EXPIRED and all binding mismatches are never treated as paid. A callback/return, screenshot or app message is only a verification trigger. The demo writes a paid_verified flag once; no goods are fulfilled. Production fulfillment must use an atomic idempotent transaction in your own order database.

`DEMO_DATA_DIR` must be private, writable and durable; it is never served by these routes. Run one process per directory. An exclusive startup lock prevents accidental concurrent instances; normal shutdown removes it. After a crash, stop all instances and confirm the process is dead before removing `.instance.lock`. Never delete order records merely to retry. Temporary write files are replaced atomically. Protect files with OS service-account permissions and backup policy. This file store demonstrates the flow, not a replacement for your production order database/ownership/CSRF/rate-limiting controls. Payment-link capability tokens are sensitive: do not log query strings, put analytics on checkout, or share an order-specific URL publicly.

## Buyer languages

English, Chinese, Spanish, Brazilian Portuguese, French, German, Dutch, Persian, Russian and Arabic. Explicit lang wins; otherwise the popup uses saved/browser language then English. Installation instructions and staging controls remain English. API calls and payment identity are unchanged by language or mode.

## Mobile store policy

Use this for permitted payment scenarios. App Store / Google Play rules may require platform billing for digital content or subscriptions, with regional exceptions. A working demo is not app-store approval. Review current rules for your distribution and product.

## Validation

Read TESTING.md in the release for exact checks and limitations. Use fixtures first; configure a small live payment yourself only after staging validation. No real payment, wallet transfer or fulfillment is performed by this package.

# MochiPay Report Unlock Demo 1.0.0 — ON_SITE + HPP

PHP 7.0–8.x, cURL, JSON, sessions, random_bytes, HTTPS and persistent private writable storage are required. No model-provider dependency: the report is explicitly marked SAMPLE CONTENT, not a live AI-generated analysis. This is a protected staging integration example, not a production AI marketplace. Original API Demo and store plugins remain separate and unchanged.

## Configure and install

Upload the whole `mochipay-report-demo` folder to a PHP-enabled HTTPS staging host. Set these private server environment variables (or edit only config.php privately):

- MOCHIPAY_URL: defaults to https://mochi.bz.
- MOCHIPAY_API_KEY / MOCHIPAY_API_SECRET: your active merchant's credentials.
- MOCHIPAY_REPORT_PUBLIC_URL: exact HTTPS directory URL, e.g. https://your-store.example/report-demo; no trailing file name.
- MOCHIPAY_REPORT_STORAGE: absolute persistent directory OUTSIDE the entire document root. Examples: `/srv/mochipay-private/report-demo` or `D:\MochiPayPrivate\ReportDemo`. Grant only the PHP service account read/write permission; back up this directory. The package rejects storage inside the document root. Do not use a temporary directory.
- MOCHIPAY_REPORT_ACCESS_CODE: a private staging access code, separate from your API secret. This gate limits who can create demo orders. Protect/rate-limit the staging site at your web server as well.

Default price is 10.00 USD, default checkout ON_SITE, default direction UP. Five payment assets appear; remove methods from REPORT_METHODS unless their receiving wallets/rates are active. Price/currency are trusted server configuration, never client-submitted totals. API cURL uses `CURLOPT_SSL_VERIFYPEER => false` and hostname verification 2, matching the requested PHP example transport setting.

Open index.php, enter the demo access code, enter a topic and select asset/mode/direction. It saves the attempt before calling Create Order. ON_SITE shows a local payment dialog with exact chain amount, address QR and status. HPP redirects to the saved order's validated MochiPay payment URL. Switching between them, refreshing or retrying a lost response reuses one order.

Configure a writable, private PHP session.save_path for your PHP service account. Keep the same browser/session to reopen saved requests. Session cookies use HttpOnly, Secure and SameSite=Lax; serve over HTTPS. An HPP return works in the same session. Losing the session does not grant access through an order ID; there is deliberately no public token-based report download. A production account system must add authenticated ownership/recovery without weakening this check.

## Actual payment and delivery logic

The browser and unsigned callback payload never decide payment success. A signed server-to-server query must match the saved order ID, merchant reference, original amount/currency, payment method/network, receiving address and exact chain amount. PAID plus exact received_amount == pay_amount is required. Waiting, underpaid, overpaid, expired, mismatched and unverifiable orders stay locked.

Per-order locks and private JSON records preserve create attempts and once-only sample generation. Updates use temporary files plus atomic rename while holding a separate lock. A duplicate callback or repeated poll returns the same persisted report. The download endpoint re-verifies payment and session ownership. File locks are for a single host/local filesystem: a production multi-server application should use its transaction database and fulfillment queue. OS/process crash durability and Windows filesystem behavior must be verified on your deployment.

Callback URL includes a separate random callback token. The token only authorizes a verification trigger; the callback must still identify the saved order and pass the authenticated query. Do not publish callback tokens or log their full URLs. If a notification races with saving the initial order snapshot, it is rejected and can be retried; browser polling also verifies the saved order.

## Replace sample content with real AI

Keep checkout, trusted price, ownership and payment checks. Replace `report_sample()` with your own provider integration and persistent job workflow. For slow/paid model calls, atomically enqueue a single job after verified payment rather than holding a PHP web request/file lock for generation. Keep model API keys server-side, show a generating state and provide the saved result after completion. This release makes no live model calls and bills no model-provider usage.

## Test checklist

Check waiting remains locked; follow ON_SITE and HPP using the same order; reject forged PAID callbacks, incorrect amounts, addresses and other browser sessions; simulate a lost create response and retry the original request; verify a real correctly paid order unlocks and duplicate notifications retain one result; verify expiry/review states; download and refresh. Included validation uses simulated API responses and real PHP/browser code, not real funds or production deployment.

MCP creates separate requests unless you deliberately bind it to your own business orders. Installing the MCP server does not automatically unlock this sample. See https://mochi.bz/AIAgents.aspx for both downloads and setup steps.


## Buyer-language UI — build 75

ON_SITE payment dialogs offer English (default), Chinese, Spanish, Brazilian Portuguese, French and German. Choose a language manually in the dialog. Switching does not recreate the order, change its amount or extend expiry. HPP uses the MochiPay hosted page language selector.

This is an optional frontend update. Existing installations remain usable; no account, API-signature, callback, database or PHP business-flow change is required. Preserve your settings. Replace the listed onsite.js file and clear browser/CDN/compiled storefront caches, or update this package through your platform’s normal process. Installation instructions and merchant gateway settings remain in English. The package core version/PHP compatibility range is unchanged; language UI build 75 identifies this distribution.

Frontend file: mochipay-report-demo/portable/onsite.js

Local language/browser checks cover the reusable dialog assets. Real store checkout and live payment acceptance still require your own staging validation.


## ON_SITE layout update — build 76

The payment dialog keeps all four outer corners rounded. Long content scrolls inside an inset region; the close button and language selector remain outside it. Update both mochipay-report-demo/portable/onsite.css and mochipay-report-demo/portable/onsite.js. Preserve gateway settings and order mappings. Clear browser/CDN/storefront caches after replacing these assets. API, PHP payment business logic, exact amount, wallet address and callback verification are unchanged. HPP is unchanged.

Also update mochipay-report-demo/index.php together with the two assets: it versions their browser URLs for layout build 76. This file changes asset cache metadata only. Use the complete package update if unsure which files to replace.

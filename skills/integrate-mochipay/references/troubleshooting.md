# Troubleshooting

Inspect sanitized evidence and authenticated queries. Never print credentials, recovery phrases, raw private configs or complete customer query responses.

| Symptom | Check and next action |
| --- | --- |
| Gateway absent | Correct branch/PHP, enabled setting, scope, currencies, cache/modification/compiler steps and theme hooks. |
| Invalid API key | Header name and private config source; merchant/whitespace. Do not print or rotate the key automatically. |
| Invalid signature | Base64 raw HMAC-SHA256; exact create bytes; encoded query without ?. Avoid BOM/newline/reserialization changes. |
| Create rejected | Actual HTTP status/message, subscription, merchant, wallet, method/network, precision and rate pair. |
| Create timeout | Preserve attempt; query/reconcile before retry. Merchant references are not server idempotency. |
| HPP URL rejected | Expected HTTPS origin and /pay/<saved-id>, order ownership; retain URL validation. |
| Waiting/underpaid | Actual network, address, exact payable amount and confirmations; screenshots are not payment evidence. |
| Dashboard paid, store pending | Callback HTTPS reachability/firewall/auth/routing, signed query, original binding, decimal checks, repeat-safe transition and sanitized logs. |
| Upgrade regression | Settings/order mappings preserved, no uninstall, refreshed core caches/compiler and retained HPP preference. |

Report concrete findings, fix and verification. Without live access, report live payment status unverified.


## Return, notification and mobile troubleshooting

A browser returning to redirect_url is not payment confirmation. Check the asynchronous notify_url separately; verify its public HTTPS reachability and that the saved URL matches the creation payload. Test browser-closed notifications and duplicate delivery. All paths must query the saved ID and compare exact received/payable amounts. For lost initial creation responses retain request_id and payload; interpret REQUEST_IN_PROGRESS/CONFLICT/ORDER_UNAVAILABLE before retrying. Never solve conflicts by generating a fresh request ID for the same uncertain purchase.

For Swift/Kotlin inspect the merchant backend URL, certificate chain, request ownership and common route contract before changing API credentials. HPP uses an external browser; ON_SITE uses merchant WebView with local popup. No incoming native/browser success message can unlock content. Report simulator/device/live acceptance separately from source/fixture results. See developer-examples.md and returns-notifications.md.

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

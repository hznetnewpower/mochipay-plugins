# Independent payment requests (WEB82.7)

Each explicit store checkout creates a new system order, including repeated local order IDs and merchant_order_id values. An earlier unpaid or uncertain attempt never blocks a new checkout. Merchant order references remain unchanged.

Each attempt keeps its own token, request_id, original payload and system-order binding. Opening an existing payment link, switching ON_SITE/HPP or querying status continues only that attempt. A transport retry uses the persisted request_id and identical payload. Never recover using merchant_order_id when several payments share that reference.

Returns and callbacks verify the exact system order, currency, amount, asset/network, receiving address and final pay_amount through the authenticated API. Fulfillment is atomic and runs once for the native store order. Browser return/cancel does not assert payment success.

Upgrade in place, preserve gateway settings, and do not uninstall. Plugin attempt tables are created automatically. No historical migration is included. Requires WEB82.6 or newer. Keep TLS peer verification enabled and verify the host; configure a trusted CA bundle for cURL certificate errors.

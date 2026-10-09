# Independent payment attempts — WEB82.6 / Zen Cart1.2.0

Every explicit checkout is a new attempt, including repeated local order IDs or
merchant_order_id values. Do not query earlier unpaid payments to decide whether
a new checkout may create. Keep the merchant reference unchanged.

Persist one token, request_id and immutable payload per attempt before sending.
A transport retry of that exact attempt queries request_id, or reposts the same
payload only after authenticated404 ORDER_NOT_FOUND with request-id-v1. The new
checkout path does not enter recovery. A saved system ID is queried by order_id.
Refreshing ON_SITE or opening HPP keeps the selected payment. Callbacks resolve
system IDs to their own token and local order; repeated callbacks fulfill once.
Require authenticated PAID and received_amount exactly equal to pay_amount.

This pre-launch release does not migrate historical orders. Gateway settings,
amount precision, receiving address and query-timeout rules remain intact.

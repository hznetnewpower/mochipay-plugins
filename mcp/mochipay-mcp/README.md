# MochiPay MCP Server 1.0.4

An English, local stdio MCP tool server for a single MochiPay merchant. Python 3.10+ and the pinned official MCP Python SDK 2.3.0 are required. It calls the existing create/query API; no .NET website upgrade or database migration is needed to run this package.

## Install

Extract the ZIP once. From the `mochipay-mcp` directory:

```sh
python3 -m venv .venv
.venv/bin/python -m pip install -r requirements.txt
```

Windows PowerShell:

```powershell
py -3 -m venv .venv
.\.venv\Scripts\python.exe -m pip install -r requirements.txt
```

Copy the `mochipay` entry from mcp-config.example.json into your client's documented local MCP server configuration. Replace the interpreter, server and state paths with absolute paths. On Windows use `.venv\\Scripts\\python.exe` and escaped backslashes in JSON. Restart/reconnect the client. Configuration file locations differ by client; this package is not a remotely hosted ChatGPT connector or a Skill ZIP. Your host must support launching stdio servers. The official MCP Inspector or SDK client can verify tool discovery independently of an AI model.

Set API key/secret in private host environment configuration. Do not paste credentials into prompts, commit configured JSON, or upload the local state database. Set `MOCHIPAY_ALLOW_CREATE=true` only for the merchant connection intended to create requests; otherwise creation is disabled. The configurable maximum is a nominal amount in the requested currency, not a converted USD spending budget. Requests do not spend funds. Configure the appropriate active receiving wallets and rates in MochiPay.

## Tools and prompts

- `create_payment_request`: required `request_id` and decimal-string `amount`. Defaults: USD, USDT_TRC20, ON_SITE, unique amount UP. HPP is also supported as a local presentation choice; no fictitious payment_mode is sent to the API.
- `get_payment_status`: required `request_id` of a request created by this installation. It returns only payment fields, not customer information or secrets. `payment_verified` requires bound PAID and exact received_amount == pay_amount. Status polling does not fulfill goods.

Example prompts:

> Use MochiPay to create a 10 USD payment request for my report, using USDT TRC20. Use request_id report-sale-20261004-001. Return the hosted payment link.

> Query report-sale-20261004-001 and tell me whether its payment is verified.

The creation call is equivalent to:

```json
{"request_id":"report-sale-20261004-001","amount":"10.00","currency":"USD","payment_method":"USDT_TRC20","description":"Report access","checkout_mode":"HPP"}
```

## Retries, ownership and checkout

SQLite attempts commit BEFORE a create API request. Every explicit new checkout uses a new request_id; merchant_order_id may repeat unchanged. Same saved request_id and payload query/reuse only that attempt. Resolve a lost response by request_id, never by merchant reference. A previous uncertain request never blocks a new checkout. Same request_id with changed financial details is rejected. Back up the private database and preserve it during upgrades. Merchant scope isolates records when credentials change. Requires WEB82.6+; see PAYMENT_RECOVERY.md. Optional merchant_order_id is a repeatable business label and does not determine deduplication.

ON_SITE provides the exact payment amount/address/network for your application's checkout. It does not insert a modal into an arbitrary chat host. HPP uses the returned, validated MochiPay payment_url. Both use one order. Wallet signing, transfers, report generation and fulfillment belong to separate authorized application workflows. Never round pay_amount or include recovery phrases/private keys in this server.

This is a single trusted merchant/operator connection, not a public multi-tenant MCP endpoint. Tool annotations guide hosts but do not enforce approval. Use your host's permissions for creation. API TLS certificate and hostname verification are enabled; HTTP redirects are rejected. External payment errors are sanitized.

## Pair with the report example

The separate PHP Report Unlock Demo demonstrates local checkout and server-verified delivery. It has its own private session/order records. The MCP tool does not directly unlock a report; a production application must intentionally associate its own business order, price, customer and MochiPay payment before fulfillment. Do not assume MCP-created orders belong to the demo.

Package validation uses mock API transport and real MCP stdio, not production credentials or blockchain transfers. See the included validation summary. Official developer documentation: https://mochi.bz/Developers.aspx and https://mochi.bz/AIAgents.aspx.

## Buyer language (1.0.4)

Both tools accept an optional `language` display preference (default `en`): en, zh, es, pt-br, fr, de, nl, fa, ru, ar, ja, ko, it, tr, id. Regional forms such as ja-JP and id-ID are normalized. Unsupported values fail before order creation. Only the returned hosted URL/display metadata is localized; signed API bodies, stable request_id, decimal amounts and payment verification are unchanged. ON_SITE implementers pass this preference to their updated buyer UI. Tool names, schemas and technical setup remain English.

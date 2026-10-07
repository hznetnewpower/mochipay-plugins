# Developer and AI integration resources

Download the corresponding ZIP from [the latest release](https://github.com/hznetnewpower/mochipay-plugins/releases/latest).
These resources have separate roots, runtimes and private configuration. They
are not four shopping-platform plugins. Installation ZIPs contain the complete
original files; do not upload the entire repository Source code ZIP to a store.

| Resource | Version | Runtime | Original install directory |
|---|---|---|---|
| PHP API Demo | 1.1.2 | PHP 7.0–8.4, cURL, JSON, sessions, HTTPS, private writable temporary storage | MochiPay_PHP_Demo |
| Report Unlock Demo | 1.0.1 | PHP 7.0–8.x, cURL, JSON, sessions, HTTPS, persistent private storage | mochipay-report-demo |
| AI Integration Skill | 1.1.2 | An Agent Skills-compatible host; Python 3 is optional for offline checks | integrate-mochipay |
| MCP Server | 1.0.0 | Python 3.10+, the pinned dependency in requirements.txt, local stdio host support | mochipay-mcp |

Installation instructions are English. The PHP ON_SITE dialogs offer a manual
buyer selector for English, Chinese, Spanish, Brazilian Portuguese, French and
German. Skill and MCP are developer tools, not extra payment-language packages.
Merchant account access, an active subscription and enabled receiving wallets
are required for actual payment requests. The free Skill can also guide setup
before the merchant account is ready. API credentials stay in private server or
host configuration, outside prompts and public commits.

## PHP API Demo

Source: [examples/php-api/MochiPay_PHP_Demo](../examples/php-api/MochiPay_PHP_Demo).
Official guide: https://mochi.bz/GuideAPI.aspx.

1. Extract MochiPay_PHP_Demo and read its README.md. Deploy it on a protected
   PHP-enabled HTTPS staging host, including portable/ and the QR license.
2. Set your private API key/secret and the absolute public directory URL in
   order.php. The default MochiPay URL is https://mochi.bz; default mode ON_SITE.
3. Open order.php in staging. Create one order and save its attempt. The result
   includes both checkout choices, which reopen the same order.
4. HPP uses checkout.php to verify the saved attempt and redirect to payment_url.
   ON_SITE uses checkout.php, portable/onsite.js and your local poll endpoint to
   open the dialog on your domain. The API does not require a payment_mode field.
5. callback.php verifies notifications and browser returns through authenticated
   Query Order. Complete your own durable, once-only fulfillment transaction;
   a browser flag or unsigned callback alone is not proof of payment.

The original README heading retains backend revision 1.1.1; its appended UI
notes describe the later frontend. The distributed archive version is 1.1.2.
This publication preserves the original files rather than rewriting that
history. The supplied PHP transport's requested TLS settings are unchanged.
Temporary-file attempts are demonstration storage, not a production order DB.

## Report Unlock Demo

Source: [examples/report-unlock/mochipay-report-demo](../examples/report-unlock/mochipay-report-demo).
Official walkthrough: https://mochi.bz/AIAgents.aspx.

The workflow is: enter a topic, create one payment request, choose ON_SITE or
HPP, verify the saved payment on the server, then unlock the saved sample report.
The original README heading retains backend revision 1.0.0; the UI archive is 1.0.1.

1. Deploy the entire mochipay-report-demo directory on a protected HTTPS PHP host.
2. Configure private MOCHIPAY_API_KEY, MOCHIPAY_API_SECRET, an exact
   MOCHIPAY_REPORT_PUBLIC_URL, MOCHIPAY_REPORT_ACCESS_CODE and
   MOCHIPAY_REPORT_STORAGE outside the document root.
3. Open index.php with the staging access code, enter a topic and choose the
   asset/mode. Default price 10.00 USD, default ON_SITE, default amount direction UP.
4. Keep the same session when reopening the attempt or returning from HPP.
   Bound PAID and exact received_amount == pay_amount are required for delivery;
   waiting, mismatched and review states stay locked.
5. Replace report_sample() with your real AI provider/job workflow only after
   preserving ownership, trusted price, durable binding and server verification.

This package has no live model provider and generates SAMPLE CONTENT. Slow
model jobs should be queued once after verified payment in your production
application. MCP-created requests do not automatically unlock this demo: the
two examples have separate private records and business ownership.

## AI Integration Skill

Source: [skills/integrate-mochipay](../skills/integrate-mochipay).
Detailed client installation: [existing installation reference](../skills/integrate-mochipay/references/installation.md)
and https://mochi.bz/GuideSkill.aspx.

The ZIP contains one integrate-mochipay directory with SKILL.md, references,
assets and an optional Python check script. Install it through your host's
documented Agent Skills mechanism; merely attaching a ZIP to a conversation is
not persistent installation. This publication does not install or update your
personal skill automatically.

Example request:

> Use MochiPay Integration to connect my WooCommerce store. Default to ON_SITE,
> also support HPP, and verify payment on the server before fulfilling an order.

The Skill chooses the exact store/PHP branch, handles API signing/verification
and helps troubleshoot payment states. It does not contain merchant credentials
or install a gateway without access to your authorized project. For offline
checks, use the included script's --help to choose the appropriate command.

## MCP Server

Source: [mcp/mochipay-mcp](../mcp/mochipay-mcp).
Official walkthrough: https://mochi.bz/AIAgents.aspx.

Extract mochipay-mcp and create a private Python environment:

```sh
python3 -m venv .venv
.venv/bin/python -m pip install -r requirements.txt
```

Windows PowerShell:

```powershell
py -3 -m venv .venv
.\.venv\Scripts\python.exe -m pip install -r requirements.txt
```

Use mcp-config.example.json with a host that can launch local stdio servers.
Supply absolute interpreter/server/state paths and set API credentials privately.
Store the SQLite state file persistently outside public directories. Creation
requires MOCHIPAY_ALLOW_CREATE=true; choose your host's tool permissions.

The two runtime tools are:

- create_payment_request: creates or recovers a durable request by request_id.
- get_payment_status: checks a request from this installation and reports its
  bound payment state, including payment_verified when applicable.

Example create arguments:

```json
{"request_id":"report-example-001","amount":"10.00","currency":"USD","payment_method":"USDT_TRC20","description":"Report access","checkout_mode":"HPP"}
```

Then query with:

```json
{"request_id":"report-example-001"}
```

Use checkout_mode ON_SITE to obtain the payment view for your application's own
checkout; HPP returns the hosted link for the same saved order. MCP does not
insert an interactive payment popup into arbitrary chat software. Neither tool
signs wallet transfers or fulfills goods. Retry an uncertain creation with the
same request_id and financial details; changing IDs can create another request.

This is a trusted single-merchant/operator connection, not a public multitenant
endpoint or a remotely hosted ChatGPT connector. The SDK pin and original
transport behavior are preserved. Host support, model access and costs remain
separate from MochiPay. No x402 implementation is included.

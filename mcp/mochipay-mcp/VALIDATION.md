# Validation summary — MCP Server 1.0.0

11 unittest scenarios passed with Python 3.12.14 and official MCP SDK 2.3.0. Covered exact UTF-8 body/raw-query HMAC headers; ON_SITE/HPP reuse; ambiguous create recovery after restart; eight concurrent same-ID calls producing one POST; rejected detail changes; exact received amount and all waiting/review states; immutable order/address/currency/method/payable binding; private local merchant scope; decimal precision; URL/redirect rejection and option/amount limits.

Actual MCP discovery and create/query calls passed through an in-memory SDK transport; independent stdio discovery and configured-denial/error handling passed through a subprocess. API responses were simulated. No live merchant credentials, blockchain transfers, model provider or remote hosted MCP deployment were tested. Python 3.10/3.11 and Windows native execution remain deployment checks.


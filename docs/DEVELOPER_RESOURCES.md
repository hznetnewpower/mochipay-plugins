# Developer resources

Download one current ZIP from Releases → Assets, or read the corresponding source directory. All setup instructions are English. Buyer checkout supports ten languages.

| Resource | Version | Environment | Source |
|---|---|---|---|
| PHP API Demo | 1.1.4 | PHP 7.0–8.4; cURL, JSON, sessions, HTTPS and private storage | [examples/php-api](../examples/php-api) |
| Report Unlock Demo | 1.0.2 | PHP 7.0–8.x; cURL, JSON, sessions, HTTPS and persistent private storage | [examples/report-unlock](../examples/report-unlock) |
| AI Integration Skill | 1.1.4 | An Agent Skills-compatible host; optional Python 3 offline checks | [skills](../skills) |
| MCP Server | 1.0.0 | Python 3.10+; pinned MCP SDK dependency; a host that launches stdio servers | [mcp](../mcp) |
| Node.js Demo | 1.0.0 | 22+ | [examples/nodejs-api](../examples/nodejs-api) |
| Python Demo | 1.0.0 | 3.10+ | [examples/python-api](../examples/python-api) |
| C# / .NET Framework Demo | 1.0.0 | 4.6.1 / VS2019 | [examples/dotnet-api](../examples/dotnet-api) |
| Java Demo | 1.0.0 | JDK17+ | [examples/java-api](../examples/java-api) |
| iOS Swift Demo | 1.0.0 | iOS15+ / Xcode14+ | [mobile/ios](../mobile/ios) |
| Android Kotlin Demo | 1.0.0 | API26+ / SDK35 / JDK17 | [mobile/android](../mobile/android) |

## Returns, notifications and retry recovery

HPP has a synchronous browser return and an independent asynchronous server notification. ON_SITE uses the same server notification handling. The backend re-queries the saved system order ID, checks the merchant reference, original amount/currency, method/network, address, exact pay amount and exact received amount, then records verified payment once. Repeated notifications must not repeat the application update.

Save request_id and its unchanged create payload before sending. Reuse them after a lost response; query a known system ID. Changing interface or language never creates a second order.

[Complete return/notification code guide](https://mochi.bz/Developers/Reference.aspx#returns-notifications)

## Mobile configuration

The Swift and Kotlin demos call any one of the four new common backends. Configure the merchant HTTPS origin only in the app. Enter the independent staging token at runtime; never add a MochiPay API key or secret. PHP keeps its existing routes and is not directly interchangeable with the mobile common-route backend. See the mobile README for Xcode/Android Studio settings.

## AI Integration Skill

[Install and use the Skill](https://mochi.bz/Developers/Skill.aspx). Skill1.1.4 covers all seven demos, ON_SITE/HPP, saved request IDs and verified notifications. It provides instructions, while MCP provides callable running tools.

## Free source and live payments

Source downloads are free. Live MochiPay payments require your active merchant subscription and configured receiving wallets. No backend demo, app project or Skill includes credentials.

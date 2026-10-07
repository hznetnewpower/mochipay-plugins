# MochiPay Integrations — Plugins, PHP, Skills and MCP

Publication 2026.10.07-publication.2 now includes 23 independent downloads:
19 native store plugin packages and 4 developer/AI resources.

- PHP API Demo 1.1.2: ON_SITE and HPP with bound server-side verification.
- Report Unlock Demo 1.0.1: trusted payment, server verification and persistent
  delivery of sample report content. Bring your own real AI provider.
- AI Integration Skill 1.1.2: package/PHP selection, API integration and troubleshooting.
- MCP Server 1.0.0: create_payment_request and get_payment_status for local stdio hosts.

The 19 native installation ZIPs are unchanged from publication.1. The four
developer ZIPs exactly match the latest official website distributions. No
payment code, credentials, API contract or installed skill was changed by this
publication. The payment server, Monitor, database and production config remain private.

Install the matching platform/resource ZIP under Assets. The automatic Source
code archive contains all source and is not a single-store installer. Skill and
MCP are separate resources; MCP requests do not automatically unlock the PHP demo.
Plugin/demo checkout supports ON_SITE and HPP. MCP ON_SITE returns checkout data;
the application must render its own payment UI. Integration code is licensed
per package; hosted MochiPay subscriptions and AI-host fees remain separate.

Read docs/DEVELOPER_RESOURCES.md and the original archive README/reference files.
Verify actual host/runtime configuration in staging. This publication adds no
native-store/live-chain/model-provider certification.

Setup: https://mochi.bz/IntegrationGuides.aspx
Developer resources: https://mochi.bz/Developers.aspx
AI tools: https://mochi.bz/AIAgents.aspx

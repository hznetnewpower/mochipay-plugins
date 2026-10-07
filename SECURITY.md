# Security reporting

Never publish API secrets, account passwords, wallet recovery material, private
keys, live order capability URLs or buyer information in an issue.

When publishing a public repository, its owner should enable GitHub **Private
vulnerability reporting** under Settings → Security and quality → Advanced
Security. When it is enabled, use Security → Advisories → Report a vulnerability.
If it is not enabled, ask the maintainer for a private contact through the
official profiles linked from https://mochi.bz/Community.aspx. Do not post exploit
details to the public issue tracker or a public community group.

Include the affected plugin branch, publication revision, reproduction steps
and a minimal redacted example. No response-time guarantee or completed audit
is claimed by this policy.

This distribution preserves the existing transport defaults and payment code.
Its source/package checks do not replace an independent security review or
native-store staging validation. Merchant secrets stay in server-side settings;
browser callbacks and returns must not assert payment completion.

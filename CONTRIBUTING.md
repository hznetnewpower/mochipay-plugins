# Contributing

Open an issue describing the platform branch, PHP version, checkout mode,
steps to reproduce and expected behavior. Use a staging environment and redact
credentials, payment capability tokens and personal information.

For a pull request, keep the change focused on the relevant package. Preserve
its installation paths, PHP minimum, existing settings and order mappings.
Keep English merchant settings and documentation; do not remove buyer languages.
Include meaningful verification of the behavior changed. Never accept a browser
return or unsigned callback as evidence of payment.

Contributions are supplied under the receiving package's license, with original
third-party notices retained. Do not add shopping-platform core files, private
service code, generated dependencies or real account data.

Publication-only changes should pass `python tools/build_release.py` and
`python tools/verify_release.py`. A future runtime edit must intentionally
refresh the original-file baseline with its new reviewed source and native
version, then receive appropriate staging/payment verification. Do not bypass
the baseline merely to make an unreviewed payment edit pass.

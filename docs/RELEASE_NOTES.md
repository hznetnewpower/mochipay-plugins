# MochiPay Plugins — GitHub publication 2026.10.07-publication.1

Crypto checkout integrations for 12 store families, supplied as 19 independent
platform packages. Choose ON_SITE or HPP. Customer payments go directly to the
merchant's receiving wallet.

This revision organizes the existing plugin code for public distribution,
adds English setup/download documentation and missing license text, preserves
third-party notices, and supplies reproducible ZIPs and SHA-256 checksums.
The two Shopware Composer help URLs now point to the actual Shopware guide.
Payment code, checkout assets, signatures and callback formats are unchanged.
Existing installations do not need to reinstall for this publication revision.

**Install:** select your platform ZIP under Assets. Do not install the automatic
whole-repository Source code archive. Read the README inside your ZIP and check
the platform-specific PHP range. OpenCart 4: save its package as
`mochipay.ocmod.zip` before installation.

The newer native adapters remain initial integration builds. Verify a complete
staging checkout, callback and real paid order before live use.

Setup guides: https://mochi.bz/IntegrationGuides.aspx
Checkout simulation: https://mochi.bz/CheckoutDemo.aspx
Service and pricing: https://mochi.bz

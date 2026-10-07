# Publication validation

This publication is based on the current website plugin distributions and does
not add a new payment implementation. The accompanying verification receipt
records checks performed for this packaging revision. The only original-file changes
are two Shopware composer.json supportLink corrections, with no dependency or
runtime change. Their original and published hashes are recorded in
tools/publication-metadata-changes.json.

Checks compare every retained original file to its SHA-256 baseline, test all
archive CRCs, verify the 19-package catalog and native root layouts, preserve QR
attribution and verify that archive entries match the published source tree.
Added documentation is English. Buyer language dictionaries are preserved.

The retained original-file baseline prevents accidental payment-code edits
while adding release documents. It includes both runtime files and retained
original documentation. The removed WooCommerce validation screenshots are
not runtime files and are listed in CHANGED_FILES.json.

Initial adapters are labeled in packages.json and the package publication
documents. No complete native-store installation, real-payment acceptance,
PHP environment upgrade or production deployment is performed by this revision.
Existing transport defaults are retained; no new security-audit claim is made.

MochiPay WEB, Monitor, database, API, merchant settings and service configuration
need no deployment for this independent GitHub publication kit. It contains no
website patch and does not change the website's existing catalog hashes.

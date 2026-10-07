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

## Publication.2 developer resources

The four resources are preserved from the current official website downloads.
All 41 original files and all four archive bytes are checked against frozen
SHA256 baselines. Archive roots, compression metadata, CRCs, source parity and
the 23-asset manifest/checksum file are verified. Python sources are syntax parsed;
JavaScript is syntax checked. A heuristic credential scan reports paths only
and is not an independent security audit. No configured credentials or state DB
are included. Personal installed Skills are not changed or reinstalled.

Exact developer archive reconstruction preserves original zipfile metadata and
compression levels. Byte identity was checked with the available Python/zlib
runtime; the builder refuses archive drift if a different runtime produces
different compressed bytes. Runtime payment code itself is not rebuilt.

This packaging delta does not rerun native-store installation, PHP payment
runtime, MCP model-host configuration, live-chain payments or real report
generation. The original demos' validation notes retain their original scope.
Build/verify tools use only Python 3.10+ standard library; MCP installation uses
its separate pinned dependency. No live server or GitHub deployment is claimed.

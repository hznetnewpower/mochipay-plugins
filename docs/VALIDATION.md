# Publication validation

The release has 30 platform-specific ZIPs. Twenty-eight are copied byte-for-byte from the current MochiPay website Downloads distribution; one AI Skill ZIP contains reviewed multilingual wording; the OpenCart 4 ZIP is copied byte-for-byte from the preceding GitHub release. All ZIPs pass integrity, path-safety and source-member SHA-256 checks. The 25 available website catalog entries with published SHA-256 values match their ZIPs. The release manifest and SHA256SUMS.txt match all 30 staged ZIPs.

The public source tree contains the package members from those ZIPs plus inherited public documentation. No payment backend, Monitor, database, production configuration or private credential is part of this release.

The verification is a publication check. It does not replace staging installation, native mobile builds or real payment acceptance on every platform. WooCommerce 1.5.19 separately passed WordPress Plugin Check in the owner's test environment and was published on WordPress.org.

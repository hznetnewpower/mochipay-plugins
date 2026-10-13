# Publishing this repository

Keep the repository source, package catalogs, checksum map and release assets on the same revision. `tools/package-source-map.json` records the exact file bytes expected in each installer archive. Do not add website/server backups, production configuration or credentials.

For a new release, validate the source and all staged archives with `python tools/verify_release.py --assets dist`, then tag the tested commit. Upload all 30 installable ZIPs from `dist/` together with `SHA256SUMS.txt` and `release-manifest.json`. Publish the release only after checking the asset count and checksums. The automatic GitHub Source code ZIP is not a plugin installer.

OpenCart 4 currently remains a legacy package. Keep that qualification in release notes until its website distribution is refreshed.

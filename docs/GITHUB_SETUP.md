# Publish on GitHub

This folder is prepared for publication but has not been uploaded to GitHub.
No GitHub account, repository URL or access token is embedded.

## Create and upload the repository

1. Sign in at https://github.com and create a repository named
   `mochipay-plugins`. Begin with a private repository if you want to review the
   files before public launch. Do not select a single blanket license: use the
   package licenses provided here.
2. Copy the **contents** of `repository/` into the repository root: README.md,
   plugins/, docs/, tools/, .github/, licenses and packages.json. Do not upload
   the whole publication-kit wrapper, the parent release-assets/ folder or any
   MochiPay website/server backup.
3. For a browser upload, add the files in batches of at most 100. Drag folders
   so their internal paths survive. A file beginning with a dot, such as
   `.github/` or `.gitignore`, is also part of the prepared source.
4. For all files at once, use GitHub Desktop (https://desktop.github.com): clone
   your newly created repository; copy the prepared contents into that local
   clone without replacing its `.git` folder; review Changes; Commit; Push.
5. Review the README, package links and private-reporting settings. Make the
   repository public when ready to launch.

## Release the installable archives

Open **Releases → Draft a new release**, create the tag
`v2026.10.07-publication.1`, and choose the commit containing this source. Copy
docs/RELEASE_NOTES.md into the release description. Attach all 19 ZIPs from the
publication kit's **release-assets/** folder, together with SHA256SUMS.txt and
release-manifest.json. Publish after the attachments finish uploading.

These archive names preserve platform expectations. OpenCart 4 is attached as
mochipay-six-languages.ocmod.zip; its instructions tell the merchant to save it
as mochipay.ocmod.zip for installation. Do not rezip the archives or add a folder.
The tag is a publication revision; it is not a claim that all native plugins
have the same runtime version.

## Optional: point the download table at GitHub

The initial download table uses the real current website links, so it has no
placeholder account links. Once your repository and release exist, run:

```text
python tools/link_github_release.py YOUR_USERNAME mochipay-plugins v2026.10.07-publication.1
```

Use your actual GitHub username or organization. This command changes only the
download table; it does not contact GitHub, create releases, alter plugins or
send social posts. Review and commit docs/DOWNLOADS.md. It points at the selected
release tag; run it with the next tag when publishing a later revision.

## Repository visibility and promotion

In **About**, add the description: "Crypto payment plugins for ecommerce stores.
ON_SITE and HPP checkout with MochiPay." Set the website to https://mochi.bz.

Suggested relevant topics: `mochipay`, `crypto-payments`, `payment-gateway`,
`ecommerce`, `woocommerce`, `opencart`, `prestashop`, `magento`, `shopware`,
`drupal-commerce`, `eccube`, `bagisto`, `sylius`, `php`.

Enable Issues and private vulnerability reporting. Pin the repository on your
profile. Share the repository and the relevant integration guide with merchants
and developers. GitHub visibility can help discovery; it does not guarantee
traffic or paid subscriptions. Do not post repetitive promotions to other
projects' issues or ask for artificial stars.

## Later updates

Keep the same repository. Submit reviewed source changes, build the platform
ZIPs and publish a new release. Change only the affected plugin versions when
runtime behavior changes. Mark initial adapters accurately and never replace
staging acceptance with a marketing badge.

To reproduce the ZIPs locally, use Python 3.10 or later (standard library only;
no pip packages). This is a release-tool requirement, not a store requirement.
Run from the repository root:

```text
python tools/build_release.py
python tools/verify_release.py
```

The resulting 19 ZIPs and checksum files are in `dist/`, which is ignored by Git.
Already-built release assets are supplied in the publication kit, so Python is
not needed for the initial upload or for a merchant installation.

# Publish the developer-resource supplement on GitHub

Repository: https://github.com/hznetnewpower/mochipay-plugins

The first plugin release already exists. This kit prepares the next release;
it does not upload files or publish a release automatically.

## Update the existing repository

1. Use the supplement ZIP's **repository-update/** folder. Upload its contents
   to the repository root, preserving examples/, skills/, mcp/, docs/ and tools/.
   Do not upload the repository-update wrapper itself or release-assets/ as source.
2. The 19 plugin source directories are unchanged and need no second upload.
   The supplement contains only added or changed files. It removes no source files.
3. Use **Add file → Upload files**, with at most 100 files in each browser batch.
   Drag folders to retain their paths. Review the changed paths before committing.
4. Edit the existing root **.gitignore** with GitHub's pencil button and paste
   the supplied .gitignore content if the browser uploader rejects hidden files.
   Existing .github files are unchanged.
5. Commit with: **Add PHP examples, AI Skill and MCP resources**.

GitHub Desktop is another option: clone the existing repository, copy the
supplement contents into the clone, review Changes, Commit and Push. Preserve
its .git directory and any unrelated user changes.

The complete kit's repository/ is a standalone source snapshot. Use the
supplement for the existing repository so unrelated owner changes are retained.
Never upload a MochiPay website/server backup, credentials or runtime state.

## Publish the new release

Open **Releases → Draft a new release** and use:

- Tag: **v2026.10.07-multilanguages.1** (create a new tag).
- Target: **main**, after committing the supplement.
- Release title: **MochiPay Integrations — Plugins, PHP, Skills and MCP**.
- Release label/type, if offered: leave **None / unspecified**.
- Description: copy **docs/RELEASE_NOTES.md**.

Attach **all 23 ZIPs** from release-assets/, plus **SHA256SUMS.txt** and
**release-manifest.json**: 25 attachments total. The 19 plugin ZIPs are unchanged
but are included again so the new release offers every download. Attaching only
the four new ZIPs would leave the new release's plugin links unavailable.

Wait for uploads to finish, review all attachments and publish the release.
Do not rezip these installer archives or add an extra wrapper folder.
OpenCart 4's published asset remains mochipay-multilanguages.ocmod.zip; follow
its instructions to save it as mochipay.ocmod.zip for native installation.

The automatic GitHub Source code ZIP is a repository snapshot, not a store
installer. Merchants install the corresponding archive under **Assets**.
This publication revision is not a shared runtime version for every plugin.

## Download links

The supplied docs/DOWNLOADS.md already points all 23 downloads to the owner's
new release tag. Those links become available after the new release is published.
For a later publication, run from the repository root:

```sh
python tools/link_github_release.py hznetnewpower mochipay-plugins YOUR_NEXT_TAG
```

This edits only the download table and makes no network request. Commit the
updated table alongside the corresponding source revision and release.

## Optional repository About update

Description:

> Crypto payment integrations: store plugins, PHP examples, AI Skill and MCP.
> ON_SITE and HPP checkout with MochiPay.

Website: https://mochi.bz

Relevant topics include mochipay, crypto-payments, payment-gateway, ecommerce,
php, woocommerce, opencart, prestashop, magento, shopware, mcp and agent-skills.
Keep the existing useful topics and add the relevant new ones.

## Rebuild and check the archives

The already-built assets are supplied; Python is not needed for manual upload.
To rebuild locally, use Python 3.10+ with its standard library, then run:

```sh
python tools/build_release.py
python tools/verify_release.py
```

The output is 23 ZIPs plus two checksum/catalog files in ignored dist/.
The builder preserves the original developer ZIP metadata and refuses byte
identity drift. See VALIDATION.md for the tested scope and runtime caveat.
MCP's separate Python dependency is needed only when running the MCP server.

No WEB/Monitor compilation, database script or existing customer upgrade is
needed for this GitHub publication.

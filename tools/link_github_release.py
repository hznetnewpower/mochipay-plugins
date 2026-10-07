#!/usr/bin/env python3
"""Replace website download links with the owner's actual GitHub release URLs."""
from pathlib import Path
import json
import re
import sys
from urllib.parse import quote

ROOT = Path(__file__).resolve().parents[1]


def main():
    if len(sys.argv) != 4:
        raise SystemExit("Usage: python tools/link_github_release.py OWNER REPOSITORY TAG")
    owner, repository, tag = sys.argv[1:]
    if not re.fullmatch(r"[A-Za-z0-9](?:[A-Za-z0-9-]{0,37}[A-Za-z0-9])?", owner):
        raise SystemExit("Invalid GitHub owner.")
    if not re.fullmatch(r"[A-Za-z0-9_.-]{1,100}", repository) or repository in {".", ".."}:
        raise SystemExit("Invalid repository name.")
    if not tag or any(ord(c) < 32 for c in tag):
        raise SystemExit("Invalid tag.")
    packages = json.loads((ROOT / "packages.json").read_text(encoding="utf-8"))["packages"]
    packages += json.loads((ROOT / "developer-resources.json").read_text(encoding="utf-8"))["resources"]
    target = ROOT / "docs/DOWNLOADS.md"
    text = target.read_text(encoding="utf-8")
    for p in packages:
        name = p["asset_name"]
        pattern = r"\[" + re.escape(name) + r"\]\([^\n)]+\)"
        url = "https://github.com/" + owner + "/" + repository + "/releases/download/" + quote(tag, safe="") + "/" + quote(name, safe="")
        text, count = re.subn(pattern, lambda _: "[" + name + "](" + url + ")", text)
        if count != 1:
            raise SystemExit("Expected one download-table entry for " + p["id"])
    start = text.index("Use **Releases → Assets**")
    end = text.index("Every branch has a separate archive.", start)
    text = text[:start] + "Use **Releases → Assets** for the installable ZIPs. Download links below point\nto the selected GitHub release. Check SHA256SUMS.txt attached to that release.\n\n" + text[end:]
    target.write_text(text, encoding="utf-8", newline="\n")
    print("Updated 23 download links; review and commit docs/DOWNLOADS.md. No network request was made.")


if __name__ == "__main__":
    main()

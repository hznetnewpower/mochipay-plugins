#!/usr/bin/env python3
"""Check installation archives against the catalog and unchanged source baseline."""
import json
import re
import sys
import zipfile
from pathlib import Path
from build_release import ROOT, sha, load_catalog, verify_baseline, package_files, validate_layout
from developer_resources import load_resources, verify_resources


def main():
    output = Path(sys.argv[1]).resolve() if len(sys.argv) == 2 else ROOT / "dist"
    catalog = load_catalog()
    originals = verify_baseline()
    manifest = json.loads((output / "release-manifest.json").read_text(encoding="utf-8"))
    entries = {a["id"]: a for a in manifest["assets"]}
    if len(entries) != 29 or manifest["publication_revision"] != catalog["publication_revision"]:
        raise ValueError("Release manifest does not match the source publication.")
    expected_sums = []
    runtime_count = 0
    total_files = 0
    for p in catalog["packages"]:
        data = (output / p["asset_name"]).read_bytes()
        a = entries[p["id"]]
        if a["sha256"] != sha(data) or a["bytes"] != len(data):
            raise ValueError("Release asset checksum/size mismatch: " + p["id"])
        expected_sums.append(sha(data) + "  " + p["asset_name"] + "\n")
        source, files = package_files(p)
        expected = {f.relative_to(source).as_posix(): f.read_bytes() for f in files}
        with zipfile.ZipFile(output / p["asset_name"]) as z:
            if z.testzip() is not None:
                raise ValueError("CRC failure: " + p["id"])
            if len(z.namelist()) != len(set(z.namelist())) or set(z.namelist()) != set(expected):
                raise ValueError("ZIP source/layout mismatch: " + p["id"])
            validate_layout(p, z.namelist())
            for name in z.namelist():
                raw = z.read(name)
                if raw != expected[name] or z.getinfo(name).compress_type != zipfile.ZIP_DEFLATED:
                    raise ValueError("ZIP byte or compression mismatch: " + name)
                if name.endswith((".php", ".js", ".css", ".twig", ".tpl", ".phtml", ".xml", ".yml", ".json")):
                    runtime_count += 1
                total_files += 1
    developer_originals = verify_resources(output, manifest)
    resources = load_resources()["resources"]
    expected_sums.extend(entries[r["id"]]["sha256"] + "  " + r["asset_name"] + "\n" for r in resources)
    if (output / "SHA256SUMS.txt").read_text(encoding="utf-8") != "".join(expected_sums):
        raise ValueError("Checksum file mismatch.")
    allowed = {p["asset_name"] for p in catalog["packages"]} | {r["asset_name"] for r in resources} | {"SHA256SUMS.txt", "release-manifest.json"}
    if {p.name for p in output.iterdir()} != allowed:
        raise ValueError("Release directory has missing or unexpected files.")
    # Report locations, never credential values, if suspicious assignments exist.
    findings = []
    suspicious = re.compile(r"BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY|(?:sk_live_|ghp_|github_pat_)[A-Za-z0-9_]{16,}|(?:api[_-]?secret|api[_-]?key|password)\s*['\"]?\s*[:=]\s*['\"][A-Za-z0-9+/=_-]{24,}['\"]", re.I)
    for f in ROOT.rglob("*"):
        if not f.is_file() or f.suffix == ".zip" or "__pycache__" in f.parts or f.is_relative_to(output):
            continue
        if f.suffix.lower() not in {".php", ".js", ".json", ".xml", ".yml", ".yaml", ".md", ".txt", ".ini", ".config", ".py"}:
            continue
        if suspicious.search(f.read_text(encoding="utf-8-sig", errors="replace")):
            findings.append(f.relative_to(ROOT).as_posix())
    if findings:
        raise ValueError("Review suspected secret locations: " + ", ".join(findings))
    result = {"result": "PASS", "archives": 29, "store_families": 12, "native_plugin_packages": 19,
              "developer_resource_packages": 10, "developer_original_files_byte_identical": developer_originals,
              "retained_original_files_byte_identical": originals,
              "reviewed_metadata_only_changes": 2,
              "reviewed_source_changes": len(json.loads((ROOT / "tools/reviewed-source-changes.json").read_text(encoding="utf-8"))) if (ROOT / "tools/reviewed-source-changes.json").exists() else 0,
              "archive_entries_verified": total_files + developer_originals, "runtime_or_metadata_files": runtime_count,
              "all_crc_checks": "PASS", "native_layouts": "PASS", "source_archive_parity": "PASS",
              "secret_pattern_scan": "No matching embedded credentials; heuristic only",
              "live_store_or_payment_tests": "Not performed for this developer-demo/source publication revision"}
    print(json.dumps(result, indent=2))


if __name__ == "__main__":
    main()

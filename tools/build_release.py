#!/usr/bin/env python3
"""Build 19 store ZIPs and 10 current developer archives with Python3.10+."""
from pathlib import Path, PurePosixPath
import hashlib
import json
import re
import zipfile
from developer_resources import load_resources, build_resources, verify_sources

ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / "dist"
FROZEN = ROOT / "tools/original-files.sha256.json"


def sha(data):
    return hashlib.sha256(data).hexdigest()


def load_catalog():
    data = json.loads((ROOT / "packages.json").read_text(encoding="utf-8"))
    packages = data["packages"]
    ids = [p["id"] for p in packages]
    assets = [p["asset_name"] for p in packages]
    if len(packages) != 19 or len(set(ids)) != 19 or len(set(assets)) != 19:
        raise ValueError("The current publication must contain 19 unique platform packages.")
    if len({p["family"] for p in packages}) != 12:
        raise ValueError("The current publication must contain 12 store families.")
    for p in packages:
        if not re.fullmatch(r"[a-z0-9-]+", p["id"]):
            raise ValueError("Invalid package id.")
        if p["source_directory"] != "plugins/" + p["id"]:
            raise ValueError("Unexpected source directory.")
        asset = p["asset_name"]
        if Path(asset).name != asset or not asset.endswith(".zip"):
            raise ValueError("Invalid asset filename.")
    return data


def verify_baseline():
    baseline = json.loads(FROZEN.read_text(encoding="utf-8"))
    review_path = ROOT / "tools/reviewed-source-changes.json"
    reviewed = json.loads(review_path.read_text(encoding="utf-8")) if review_path.exists() else {}
    if set(reviewed) - set(baseline):
        raise ValueError("Reviewed source entry is absent from the original baseline.")
    for relative, expected in baseline.items():
        parts = PurePosixPath(relative).parts
        if ".." in parts or not relative.startswith("plugins/"):
            raise ValueError("Invalid original-file path.")
        path = ROOT / relative
        change = reviewed.get(relative)
        if change:
            if change.get("original_sha256") != expected:
                raise ValueError("Reviewed source baseline mismatch: " + relative)
            if change.get("removed"):
                if path.exists() or path.is_symlink():
                    raise ValueError("Removed documentation unexpectedly exists: " + relative)
                continue
            expected = change["published_sha256"]
        if path.is_symlink() or not path.is_file() or sha(path.read_bytes()) != expected:
            raise ValueError("Original/reviewed file changed or missing: " + relative)
    metadata = json.loads((ROOT / "tools/publication-metadata-changes.json").read_text(encoding="utf-8"))
    for relative, approved in metadata.items():
        path = ROOT / relative
        if path.is_symlink() or not path.is_file() or sha(path.read_bytes()) != approved["published_sha256"]:
            raise ValueError("Reviewed publication metadata changed: " + relative)
    return len(baseline) - len(reviewed)


def package_files(package):
    folder = ROOT / package["source_directory"]
    files = sorted(p for p in folder.rglob("*") if p.is_file())
    if not files:
        raise ValueError("Empty package: " + package["id"])
    for f in files:
        if f.is_symlink():
            raise ValueError("Symlinks are not allowed in distributable packages.")
        relative = f.relative_to(folder).as_posix()
        if f.suffix.lower() in {".log", ".bak", ".sql", ".exe", ".dll", ".pfx", ".p12", ".pem", ".key"}:
            raise ValueError("Unexpected private/generated file: " + relative)
        if f.name.startswith(".env") or ".git" in f.parts or "validation" in f.parts:
            raise ValueError("Unexpected private/generated file: " + relative)
    return folder, files


def validate_layout(package, names):
    pid = package["id"]
    needed = []
    if pid == "woocommerce":
        needed = ["mochipay-woocommerce/mochipay-woocommerce.php"]
    elif pid == "eccube":
        needed = ["composer.json", "PluginManager.php"]
    elif pid.startswith("opencart") and pid != "opencart4":
        needed = ["install.xml"]
        if not any(n.startswith("upload/") for n in names):
            raise ValueError("OpenCart upload layout missing.")
    elif pid == "opencart4":
        needed = ["install.json"]
        if not any(n.startswith("admin/") for n in names) or not any(n.startswith("catalog/") for n in names):
            raise ValueError("OpenCart 4 native roots missing.")
    elif pid.startswith("prestashop") or pid == "thirtybees":
        needed = ["mochipay/mochipay.php"]
    elif pid.startswith("zencart"):
        needed = ["includes/modules/payment/mochipay.php"]
    elif pid == "magento1":
        needed = ["app/etc/modules/MochiPay_Payment.xml"]
    elif pid == "magento2":
        needed = ["MochiPay/Payment/registration.php", "MochiPay/Payment/composer.json"]
    elif pid.startswith("shopware") or pid == "bagisto":
        needed = ["MochiPay/composer.json"]
    elif pid == "sylius":
        needed = ["MochiPaySylius/composer.json"]
    elif pid == "drupal":
        needed = ["mochipay/mochipay.info.yml"]
    elif pid == "oscommerce":
        if not any(n.startswith("lib/common/modules/orderPayment/") for n in names):
            raise ValueError("osCommerce native module layout missing.")
    for n in needed + [package["publication_document"], package["install_document"]]:
        if n not in names:
            raise ValueError("Required installation file missing: " + n)
    if not any(n.endswith("qrcode-LICENSE.txt") for n in names):
        raise ValueError("QR license missing.")
    if not any(PurePosixPath(n).name == "LICENSE.txt" for n in names):
        raise ValueError("Package license missing.")


def main():
    catalog = load_catalog()
    originals = verify_baseline()
    developer_originals = verify_sources()
    resources = load_resources()
    if resources["publication_revision"] != catalog["publication_revision"]:
        raise ValueError("Publication revisions differ.")
    OUTPUT.mkdir(exist_ok=True)
    known = {p["asset_name"] for p in catalog["packages"]} | {r["asset_name"] for r in resources["resources"]} | {"SHA256SUMS.txt", "release-manifest.json"}
    unexpected = [p.name for p in OUTPUT.iterdir() if p.name not in known]
    if unexpected:
        raise ValueError("Output directory has unrelated files; use a clean dist directory.")
    assets = []
    for p in catalog["packages"]:
        source, files = package_files(p)
        names = [f.relative_to(source).as_posix() for f in files]
        validate_layout(p, names)
        target = OUTPUT / p["asset_name"]
        # Classic DEFLATE and forward-slash paths are understood by standard
        # Windows ZIP tools and the native PHP installers.
        with zipfile.ZipFile(target, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
            for file, name in zip(files, names):
                info = zipfile.ZipInfo(name, (2026, 10, 7, 0, 0, 0))
                info.create_system = 3
                info.compress_type = zipfile.ZIP_DEFLATED
                info.external_attr = 0o100644 << 16
                archive.writestr(info, file.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
        with zipfile.ZipFile(target) as archive:
            if archive.testzip() is not None:
                raise ValueError("ZIP integrity failure: " + target.name)
        assets.append({"id": p["id"], "name": p["name"], "asset_name": target.name,
                       "sha256": sha(target.read_bytes()), "bytes": target.stat().st_size,
                       "store": p["core"], "php": p["php"], "license": p["license"],
                       "modes": p["modes"], "initial_adapter": p["initial_adapter"],
                       "original_sha256": p["original_sha256"], "files": len(files)})
    assets.extend(build_resources(OUTPUT))
    (OUTPUT / "SHA256SUMS.txt").write_text("".join(a["sha256"] + "  " + a["asset_name"] + "\n" for a in assets), encoding="utf-8", newline="\n")
    manifest = {"publication_revision": catalog["publication_revision"], "runtime_change": True, "payment_business_change": False, "buyer_ui_build": "78",
                "store_families": 12, "independent_packages": 19, "developer_resources": 10,
                "total_installation_packages": 29, "original_files_preserved": originals,
                "developer_original_files_preserved": developer_originals,
                "assets": assets}
    (OUTPUT / "release-manifest.json").write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8", newline="\n")
    print(json.dumps({"output": str(OUTPUT), "assets": len(assets), "original_files_preserved": originals}))


if __name__ == "__main__":
    main()

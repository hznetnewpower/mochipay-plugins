"""Preserve the four existing developer archives and their native source layouts."""
from pathlib import Path, PurePosixPath
import hashlib
import json
import zipfile

ROOT = Path(__file__).resolve().parents[1]


def digest(data):
    return hashlib.sha256(data).hexdigest()


def load_resources():
    data = json.loads((ROOT / 'developer-resources.json').read_text(encoding='utf-8'))
    resources = data['resources']
    if len(resources) != 4 or len({r['id'] for r in resources}) != 4:
        raise ValueError('Expected four distinct developer resources.')
    allowed = {'php-api': ('examples/php-api', 'MochiPay_PHP_Demo'),
               'report-unlock': ('examples/report-unlock', 'mochipay-report-demo'),
               'integration-skill': ('skills', 'integrate-mochipay'),
               'mcp-server': ('mcp', 'mochipay-mcp')}
    for resource in resources:
        if allowed.get(resource['id']) != (resource['source_directory'], resource['native_root']):
            raise ValueError('Unexpected developer source layout.')
        if Path(resource['asset_name']).name != resource['asset_name'] or not resource['asset_name'].endswith('.zip'):
            raise ValueError('Invalid developer asset name.')
    return data


def verify_sources():
    frozen = json.loads((ROOT / 'tools/original-developer-files.sha256.json').read_text(encoding='utf-8'))
    expected = set(frozen)
    for resource in load_resources()['resources']:
        folder = ROOT / resource['source_directory'] / resource['native_root']
        files = {p.relative_to(ROOT).as_posix() for p in folder.rglob('*') if p.is_file()}
        if files != {p for p in expected if p.startswith(folder.relative_to(ROOT).as_posix() + '/')}:
            raise ValueError('Missing or unexpected resource file: ' + resource['id'])
    for relative, checksum in frozen.items():
        path = ROOT / relative
        if '..' in PurePosixPath(relative).parts or path.is_symlink() or not path.is_file() or digest(path.read_bytes()) != checksum:
            raise ValueError('Developer source changed or missing: ' + relative)
    return len(frozen)


def layouts():
    return json.loads((ROOT / 'tools/developer-archive-layouts.json').read_text(encoding='utf-8'))


def source_entries(resource):
    layout = layouts()[resource['id']]
    source = ROOT / resource['source_directory']
    expected = {}
    for entry in layout['entries']:
        name = entry['filename']
        allowed_root_document = name == 'MULTILANGUAGES.md' and resource['id'] in {'php-api', 'report-unlock'}
        if not (name.startswith(resource['native_root'] + '/') or allowed_root_document) or '..' in PurePosixPath(name).parts:
            raise ValueError('Invalid developer archive path.')
        if name in expected:
            raise ValueError('Duplicate developer archive entry.')
        expected[name] = (source / name).read_bytes()
    return layout, expected


def build_resources(output):
    verify_sources()
    assets = []
    for resource in load_resources()['resources']:
        layout, expected = source_entries(resource)
        target = output / resource['asset_name']
        with zipfile.ZipFile(target, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=layout['compression_level']) as archive:
            archive.comment = bytes.fromhex(layout['comment'])
            for entry in layout['entries']:
                info = zipfile.ZipInfo(entry['filename'], tuple(entry['date_time']))
                for key, value in entry.items():
                    if key in {'filename', 'date_time'}:
                        continue
                    setattr(info, key, bytes.fromhex(value) if key in {'extra', 'comment'} else value)
                archive.writestr(info, expected[entry['filename']], compress_type=info.compress_type, compresslevel=layout['compression_level'])
        raw = target.read_bytes()
        if digest(raw) != resource['original_sha256'] or len(raw) != resource['original_bytes']:
            raise ValueError('Developer archive no longer matches the website original: ' + resource['id'])
        assets.append({'id': resource['id'], 'name': resource['name'], 'version': resource['version'],
                       'asset_name': resource['asset_name'], 'sha256': digest(raw), 'bytes': len(raw),
                       'original_sha256': resource['original_sha256'], 'source_directory': resource['source_directory'],
                       'license': resource['license'], 'modes': resource['modes'], 'files': len(expected),
                       'asset_type': 'developer_resource', 'byte_identical_to_website': True})
    return assets


def verify_resources(output, manifest):
    count = verify_sources()
    resources = load_resources()['resources']
    by_id = {a['id']: a for a in manifest['assets']}
    if manifest.get('developer_resources') != 4 or manifest.get('total_installation_packages') != 23:
        raise ValueError('Developer resource totals are incorrect.')
    for resource in resources:
        layout, expected = source_entries(resource)
        path = output / resource['asset_name']
        raw = path.read_bytes()
        asset = by_id.get(resource['id'], {})
        if digest(raw) != resource['original_sha256'] or asset.get('sha256') != digest(raw) or asset.get('bytes') != len(raw):
            raise ValueError('Developer release hash mismatch: ' + resource['id'])
        with zipfile.ZipFile(path) as archive:
            if archive.testzip() is not None or archive.namelist() != list(expected):
                raise ValueError('Developer ZIP layout/CRC mismatch.')
            for name, content in expected.items():
                info = archive.getinfo(name)
                if archive.read(name) != content or info.compress_type != zipfile.ZIP_DEFLATED or info.flag_bits & 1:
                    raise ValueError('Developer ZIP source/compression mismatch.')
    return count

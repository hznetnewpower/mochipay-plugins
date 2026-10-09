from pathlib import Path, PurePosixPath
import argparse, hashlib, json, zipfile
ROOT=Path(__file__).resolve().parents[1]
def sha(b): return hashlib.sha256(b).hexdigest()
def mappings(): return json.loads((ROOT/'tools/package-source-map.json').read_text())
def verify_sources():
 count=0
 for pack in mappings():
  folder=ROOT/pack['source_directory']
  actual={p.relative_to(folder).as_posix() for p in folder.rglob('*') if p.is_file()}
  assert actual==set(pack['files']),('Source member mismatch',pack['id'])
  for n,h in pack['files'].items():
   p=folder/n
   assert not p.is_symlink() and sha(p.read_bytes())==h,('Source hash mismatch',p)
   count+=1
 assert len(mappings())==30
 return count
def build(out):
 verify_sources(); out.mkdir(parents=True,exist_ok=True)
 catalog=json.loads((ROOT/'packages.json').read_text())
 dev=json.loads((ROOT/'developer-resources.json').read_text())
 records=catalog['packages']+dev['resources']+[dev['chrome_extension']]
 for pack in mappings():
  with zipfile.ZipFile(out/pack['asset_name'],'w',zipfile.ZIP_DEFLATED,compresslevel=9) as z:
   for n in sorted(pack['files']):z.write(ROOT/pack['source_directory']/n,n)
 for r in records:
  p=out/r['asset_name'];r['sha256']=sha(p.read_bytes());r['bytes']=p.stat().st_size
 manifest={'publication_revision':catalog['publication_revision'],'assets':records,'total_installation_packages':30,'note':'Rebuilt from verified published source; archive container hashes may differ from original release assets.'}
 (out/'release-manifest.json').write_text(json.dumps(manifest,indent=2)+'\n')
 (out/'SHA256SUMS.txt').write_text(''.join(r['sha256']+'  '+r['asset_name']+'\n' for r in records))
 verify_assets(out)
def verify_assets(out):
 man=json.loads((out/'release-manifest.json').read_text())
 assert len(man['assets'])==30
 sums={line.split('  ',1)[1]:line.split('  ',1)[0] for line in (out/'SHA256SUMS.txt').read_text().splitlines()}
 expected={p['asset_name']:p for p in mappings()}
 assert set(sums)==set(expected)
 for r in man['assets']:
  p=out/r['asset_name'];assert sha(p.read_bytes())==r['sha256']==sums[p.name]
  pack=expected[p.name]
  with zipfile.ZipFile(p) as z:
   assert z.testzip() is None
   ns=[n for n in z.namelist() if not n.endswith('/')]
   assert len(ns)==len(set(ns)) and set(ns)==set(pack['files'])
   for n in ns:assert sha(z.read(n))==pack['files'][n]
 return len(expected)
def main():
 ap=argparse.ArgumentParser();ap.add_argument('--assets',type=Path);ap.add_argument('--output',type=Path,default=ROOT/'dist');args=ap.parse_args()
 count=verify_sources()
 if Path(__file__).name=='build_release.py':build(args.output)
 elif args.assets:verify_assets(args.assets)
 print(json.dumps({'source_files_verified':count,'packages':30,'status':'passed'}))
if __name__=='__main__':main()

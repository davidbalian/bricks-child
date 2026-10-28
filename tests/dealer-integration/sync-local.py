#!/usr/bin/env python3
"""Copy only theme source into local staging; never expose the repository or .private via a symlink."""
from pathlib import Path
import shutil

ROOT = Path(__file__).resolve().parents[2]
DEST = ROOT / '.staging/wp/wp-content/themes/bricks-child'
if DEST.is_symlink():
    raise SystemExit('Unsafe theme symlink found. Remove that symlink before syncing.')
DEST.mkdir(parents=True, exist_ok=True)
for file in [*ROOT.glob('*.php'), ROOT / 'style.css', ROOT / 'screenshot.png']:
    if file.is_file(): shutil.copy2(file, DEST / file.name)
for directory in ['assets', 'css', 'elements', 'includes', 'vendor', 'simple_jsons']:
    source = ROOT / directory
    if source.is_dir():
        shutil.copytree(source, DEST / directory, dirs_exist_ok=True, ignore=shutil.ignore_patterns('.DS_Store', '*.log', '*.local.*'))
print('Local theme source synchronized; repository metadata and private files excluded.')

"""Rebuild the zips for the Playground test link and the launch-day upload pack.

Run from website/: python3 playground/build-zips.py
- brra-theme.zip: the theme.
- brra-site.zip: the plugin, with the site setup files bundled in setup/ (Tools > BRRA site setup).
"""
import os
import zipfile


def add_dir(f, src, root, skip=()):
    for d, dirs, files in os.walk(src):
        dirs[:] = sorted(x for x in dirs if x not in skip)
        for n in sorted(files):
            if n.endswith('.pyc'):
                continue
            p = os.path.join(d, n)
            f.write(p, os.path.join(root, os.path.relpath(p, src)))


with zipfile.ZipFile('playground/brra-theme.zip', 'w', zipfile.ZIP_DEFLATED) as f:
    add_dir(f, 'theme/brra', 'brra')
with zipfile.ZipFile('playground/brra-site.zip', 'w', zipfile.ZIP_DEFLATED) as f:
    add_dir(f, 'plugins/brra-site', 'brra-site')
    add_dir(f, 'setup', 'brra-site/setup', skip=('wix', '__pycache__'))

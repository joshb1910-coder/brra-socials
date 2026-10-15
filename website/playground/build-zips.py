"""Rebuild the zips the Playground blueprint loads. Run from website/: python3 playground/build-zips.py"""
import os
import zipfile


def zip_dir(src, root, out):
    with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as f:
        for d, _, files in os.walk(src):
            for n in sorted(files):
                p = os.path.join(d, n)
                f.write(p, os.path.join(root, os.path.relpath(p, src)))


zip_dir('theme/brra', 'brra', 'playground/brra-theme.zip')
zip_dir('plugins/brra-site', 'brra-site', 'playground/brra-site.zip')
zip_dir('setup', 'brra-setup', 'playground/brra-setup.zip')

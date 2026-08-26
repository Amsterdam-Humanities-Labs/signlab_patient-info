#!/usr/bin/env python3
import sys as _sys
from pathlib import Path as _Path
ROOT = _Path(__file__).resolve().parents[2]   # repository root (/web/hh)
DATA = ROOT / 'data'
_sys.path.insert(0, str(ROOT))                 # so `import db_credentials` works from tools/
"""
Inspect the div content after "In het kort" heading.
"""

import requests
from bs4 import BeautifulSoup

TEST_URL = "https://www.thuisarts.nl/aambeien"

resp = requests.get(TEST_URL, timeout=30)
soup = BeautifulSoup(resp.text, 'html.parser')

# Find h2 with "In het kort"
for h2 in soup.find_all('h2'):
    text = h2.get_text(strip=True)
    if 'in het kort' in text.lower():
        heading = h2
        print(f"Found heading: {text}")
        break

# Get next div sibling
div = heading.find_next_sibling('div')

if div:
    print("\nDiv HTML:")
    print("=" * 60)
    print(div.prettify()[:2000])
    print("=" * 60)

    # Check if there's a ul inside
    ul = div.find('ul')
    if ul:
        print("\n✓ Found <ul> inside the div!")
        print(f"Number of <li> elements: {len(ul.find_all('li', recursive=False))}")
        print("\nBullet points:")
        for i, li in enumerate(ul.find_all('li', recursive=False), 1):
            print(f"{i}. {li.get_text(strip=True)}")
    else:
        print("\n✗ No <ul> found inside div")
        print("\nDiv text content:")
        print(div.get_text(strip=True))

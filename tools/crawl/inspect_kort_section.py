#!/usr/bin/env python3
import sys as _sys
from pathlib import Path as _Path
ROOT = _Path(__file__).resolve().parents[2]   # repository root (/web/hh)
DATA = ROOT / 'data'
_sys.path.insert(0, str(ROOT))                 # so `import db_credentials` works from tools/
"""
Inspect the structure around "In het kort" heading.
"""

import requests
from bs4 import BeautifulSoup
import re

TEST_URL = "https://www.thuisarts.nl/aambeien"

resp = requests.get(TEST_URL, timeout=30)
soup = BeautifulSoup(resp.text, 'html.parser')

# Find h2 with "In het kort"
print("Looking for 'In het kort' heading...")
heading = soup.find('h2', string=re.compile(r'in het kort', re.IGNORECASE))

if not heading:
    print("Not found with direct string match. Trying to find h2 containing the text...")
    for h2 in soup.find_all('h2'):
        text = h2.get_text(strip=True)
        if 'in het kort' in text.lower():
            heading = h2
            print(f"Found h2: {text}")
            break

if heading:
    print(f"\nHeading found: <{heading.name}> {heading.get_text(strip=True)}")
    print("\nHeading HTML:")
    print(heading)
    print("\n" + "=" * 60)
    print("Next 5 siblings:")
    print("=" * 60)

    siblings = []
    for i, sibling in enumerate(heading.next_siblings):
        if i >= 10:  # Look at first 10 siblings
            break
        if hasattr(sibling, 'name'):
            print(f"\n{i+1}. <{sibling.name}>")
            print(f"   Text: {sibling.get_text(strip=True)[:100]}")
            if sibling.name == 'ul':
                print(f"   This is a <ul>! Number of <li>: {len(sibling.find_all('li'))}")
                # Print first few li items
                for j, li in enumerate(sibling.find_all('li', recursive=False)[:3]):
                    print(f"   - {li.get_text(strip=True)[:60]}")
else:
    print("Heading not found!")

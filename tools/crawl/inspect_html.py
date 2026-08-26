#!/usr/bin/env python3
import sys as _sys
from pathlib import Path as _Path
ROOT = _Path(__file__).resolve().parents[2]   # repository root (/web/hh)
DATA = ROOT / 'data'
_sys.path.insert(0, str(ROOT))                 # so `import db_credentials` works from tools/
"""
Inspect HTML structure of aambeien page to find "In het kort" section.
"""

import requests
from bs4 import BeautifulSoup

TEST_URL = "https://www.thuisarts.nl/aambeien"

resp = requests.get(TEST_URL, timeout=30)
soup = BeautifulSoup(resp.text, 'html.parser')

# Find all headings
print("All headings on the page:")
print("=" * 60)
for i, heading in enumerate(soup.find_all(['h1', 'h2', 'h3', 'h4', 'h5', 'h6']), 1):
    text = heading.get_text(strip=True)
    print(f"{i}. <{heading.name}> {text}")

print("\n" + "=" * 60)
print("Searching for 'kort' in page text...")
print("=" * 60)

# Find elements containing "kort"
for elem in soup.find_all(string=lambda text: text and 'kort' in text.lower()):
    parent = elem.parent
    print(f"\nFound in <{parent.name}>: {elem.strip()[:100]}")
    print(f"Parent: {parent.name}, Siblings: {[s.name for s in parent.next_siblings if hasattr(s, 'name')]}")

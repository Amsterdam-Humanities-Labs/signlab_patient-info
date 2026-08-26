#!/usr/bin/env python3
import sys as _sys
from pathlib import Path as _Path
ROOT = _Path(__file__).resolve().parents[2]   # repository root (/web/hh)
DATA = ROOT / 'data'
_sys.path.insert(0, str(ROOT))                 # so `import db_credentials` works from tools/
"""
Test script to verify "In het kort" extraction with a single URL.
"""

import requests
from bs4 import BeautifulSoup
import re

TEST_URL = "https://www.thuisarts.nl/aambeien"


def extract_in_het_kort(html_content):
    """Extract 'In het kort' section from thuisarts.nl page HTML."""
    soup = BeautifulSoup(html_content, 'html.parser')

    # Find heading containing "in het kort" (case-insensitive)
    # The text is nested in a span, so we need to check the full text content
    heading = None
    for h in soup.find_all(['h2', 'h3', 'h4']):
        if 'in het kort' in h.get_text(strip=True).lower():
            heading = h
            break

    if not heading:
        return None

    # Get next sibling div (the content is in a div after the heading)
    div_section = heading.find_next_sibling('div')

    if not div_section:
        return None

    # Find <ul> inside the div
    ul_section = div_section.find('ul')

    if not ul_section:
        return None

    # Extract all <li> elements' text content
    bullet_points = []
    for li in ul_section.find_all('li', recursive=False):
        text = li.get_text(separator=' ', strip=True)
        if text:
            bullet_points.append(text)

    if not bullet_points:
        return None

    # Join bullet points with newlines
    return '\n'.join(bullet_points)


def main():
    print(f"Testing extraction from: {TEST_URL}")
    print("=" * 60)

    # Fetch page
    try:
        resp = requests.get(TEST_URL, timeout=30)
        resp.raise_for_status()
        print("✓ Page fetched successfully")
    except requests.RequestException as e:
        print(f"✗ Failed to fetch page: {e}")
        return

    # Extract "In het kort"
    text = extract_in_het_kort(resp.text)

    if text:
        print("✓ 'In het kort' section found")
        print()
        print("Extracted text:")
        print("=" * 60)
        print(text)
        print("=" * 60)
        print()
        print(f"Length: {len(text)} characters")
        print(f"Bullet points: {len(text.splitlines())}")
    else:
        print("✗ 'In het kort' section not found")


if __name__ == "__main__":
    main()

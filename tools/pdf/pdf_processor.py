#!/usr/bin/env python3
import sys as _sys
from pathlib import Path as _Path
ROOT = _Path(__file__).resolve().parents[2]   # repository root (/web/hh)
DATA = ROOT / 'data'
_sys.path.insert(0, str(ROOT))                 # so `import db_credentials` works from tools/
"""
Helper functions for processing PDFs and updating extracted_data.json
"""

import json
import os

EXTRACTED_DATA_FILE = str(DATA / 'extracted_data.json')

def load_extracted_data():
    """Load the extracted data JSON file."""
    if os.path.exists(EXTRACTED_DATA_FILE):
        with open(EXTRACTED_DATA_FILE, 'r') as f:
            return json.load(f)
    return {}

def save_extracted_data(data):
    """Save the extracted data JSON file."""
    with open(EXTRACTED_DATA_FILE, 'w') as f:
        json.dump(data, f, indent=2, ensure_ascii=False)

def add_medication(med_name, bijwerkingen, waarschuwingen):
    """Add or update a medication in the extracted data."""
    data = load_extracted_data()

    # Check if medication already exists
    if med_name in data:
        print(f"  ⚠️  {med_name} already exists in data - SKIPPING")
        return False

    # Add new medication
    data[med_name] = {
        "bijwerkingen": bijwerkingen,
        "waarschuwingen": waarschuwingen
    }

    save_extracted_data(data)
    print(f"  ✓ Added {med_name}")
    return True

if __name__ == '__main__':
    data = load_extracted_data()
    print(f"Currently have {len(data)} medications in extracted_data.json")
    print("\nSample medications:")
    for i, med in enumerate(sorted(data.keys())[:5]):
        print(f"  - {med}")

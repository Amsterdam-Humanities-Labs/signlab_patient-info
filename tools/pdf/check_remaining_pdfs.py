#!/usr/bin/env python3
import sys as _sys
from pathlib import Path as _Path
ROOT = _Path(__file__).resolve().parents[2]   # repository root (/web/hh)
DATA = ROOT / 'data'
_sys.path.insert(0, str(ROOT))                 # so `import db_credentials` works from tools/
"""
Script to identify which PDFs have not been processed yet
"""

import os
import json
import glob

# Load the extracted data
with open(str(DATA / 'extracted_data.json'), 'r', encoding='utf-8') as f:
    extracted_data = json.load(f)

# Get all PDF files
pdf_files = sorted(glob.glob(str(DATA / 'pdfs/*.pdf')))

print(f"Total PDFs: {len(pdf_files)}")
print(f"Medications extracted: {len(extracted_data)}")
print(f"Remaining: {len(pdf_files) - len(extracted_data)}")
print("\nExtracted medications:")
for med in sorted(extracted_data.keys()):
    print(f"  - {med}")

print(f"\nTotal extracted: {len(extracted_data)}")
print(f"Total PDFs: {len(pdf_files)}")

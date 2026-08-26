#!/usr/bin/env python3
import sys as _sys
from pathlib import Path as _Path
ROOT = _Path(__file__).resolve().parents[2]   # repository root (/web/hh)
DATA = ROOT / 'data'
_sys.path.insert(0, str(ROOT))                 # so `import db_credentials` works from tools/
"""Find PDFs that haven't been processed yet."""

import json
import glob
import os
import re

# Load already processed data
with open(str(DATA / 'extracted_data.json'), 'r') as f:
    data = json.load(f)

# Get mapping of processed medication names to their data
processed_meds = set(data.keys())

# Get all PDF files
pdf_files = sorted(glob.glob(str(DATA / 'pdfs/*.pdf')))

print(f"Total PDFs: {len(pdf_files)}")
print(f"Already processed medications: {len(processed_meds)}")
print(f"\nProcessed medications:")
for med in sorted(processed_meds)[:10]:
    print(f"  - {med}")
print(f"  ... and {len(processed_meds) - 10} more")

# Show first 20 PDF filenames
print(f"\nFirst 20 unprocessed PDFs to check:")
count = 0
for pdf in pdf_files:
    if count >= 20:
        break
    pdf_num = os.path.basename(pdf)
    print(f"  {pdf_num}")
    count += 1

print(f"\nReady to process remaining PDFs!")
print(f"Total remaining: approximately {202 - len(processed_meds)} PDFs")

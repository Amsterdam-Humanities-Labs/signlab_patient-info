#!/usr/bin/env python3
import sys as _sys
from pathlib import Path as _Path
ROOT = _Path(__file__).resolve().parents[2]   # repository root (/web/hh)
DATA = ROOT / 'data'
_sys.path.insert(0, str(ROOT))                 # so `import db_credentials` works from tools/
"""
Batch PDF extraction helper
This script helps track which PDFs still need to be processed
"""

import json
import glob
import os

# Load the extracted data
with open(str(DATA / 'extracted_data.json'), 'r', encoding='utf-8') as f:
    extracted_data = json.load(f)

# Get all PDF files sorted
pdf_files = sorted(glob.glob(str(DATA / 'pdfs/*.pdf')))

print(f"Total PDFs: {len(pdf_files)}")
print(f"Medications extracted: {len(extracted_data)}")
print(f"Remaining: {len(pdf_files) - len(extracted_data)}\n")

# Show next 20 PDFs to process (ones that exist)
print("Next PDFs to process:")
count = 0
for pdf in pdf_files:
    if count >= 20:
        break
    pdf_num = os.path.basename(pdf)
    print(f"  {pdf_num}")
    count += 1

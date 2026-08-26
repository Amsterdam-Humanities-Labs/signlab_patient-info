#!/usr/bin/env python3
import sys as _sys
from pathlib import Path as _Path
ROOT = _Path(__file__).resolve().parents[2]   # repository root (/web/hh)
DATA = ROOT / 'data'
_sys.path.insert(0, str(ROOT))                 # so `import db_credentials` works from tools/
"""Find PDFs that haven't been processed yet"""

import json
import glob
import os
import sys

# Load extracted data
with open(str(DATA / 'extracted_data.json'), 'r', encoding='utf-8') as f:
    extracted_data = json.load(f)

# Get all PDF files sorted
pdf_files = sorted(glob.glob(str(DATA / 'pdfs/*.pdf')))

print(f"Total PDFs: {len(pdf_files)}")
print(f"Medications extracted: {len(extracted_data)}")
print(f"Estimated remaining: {len(pdf_files) - len(extracted_data)}\n")

# Since we can't easily determine which PDF corresponds to which medication
# without reading each one, let's sample across the entire range
print("Sampling PDFs across the entire range to find unprocessed ones:\n")

# Sample every 10th PDF
sample_pdfs = []
for i, pdf in enumerate(pdf_files):
    if i % 10 == 0:  # Every 10th PDF
        pdf_num = os.path.basename(pdf).replace('.pdf', '')
        sample_pdfs.append(pdf_num)

print(f"Sample PDFs to check ({len(sample_pdfs)} files):")
for pdf_num in sample_pdfs[:30]:  # Show first 30
    print(f"  {pdf_num}.pdf")

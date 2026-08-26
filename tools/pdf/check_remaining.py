#!/usr/bin/env python3
import sys as _sys
from pathlib import Path as _Path
ROOT = _Path(__file__).resolve().parents[2]   # repository root (/web/hh)
DATA = ROOT / 'data'
_sys.path.insert(0, str(ROOT))                 # so `import db_credentials` works from tools/
"""Check which PDFs still need processing by comparing medication names."""

import json
import glob
import os
import re
import fitz  # PyMuPDF

def extract_medication_name_from_pdf(pdf_path):
    """Extract medication name from PDF."""
    try:
        doc = fitz.open(pdf_path)
        first_page = doc[0]
        text = first_page.get_text()

        # Look for "Samenvatting van [medication_name]"
        match = re.search(r'Samenvatting van\s+(.+?)(?:\n|$)', text, re.IGNORECASE)
        if match:
            med_name = match.group(1).strip()
            doc.close()
            return med_name

        doc.close()
        return None
    except Exception as e:
        return None

# Load already processed data
with open(str(DATA / 'extracted_data.json'), 'r') as f:
    data = json.load(f)

processed_meds = set(data.keys())
print(f"Already processed: {len(processed_meds)} medications\n")

# Get all PDFs and check which are unprocessed
pdf_files = sorted(glob.glob(str(DATA / 'pdfs/*.pdf')))
unprocessed = []

print("Scanning PDFs...")
for pdf_path in pdf_files:
    pdf_name = os.path.basename(pdf_path)
    med_name = extract_medication_name_from_pdf(pdf_path)

    if med_name and med_name not in processed_meds:
        unprocessed.append((pdf_name, med_name, pdf_path))

print(f"\n{'='*70}")
print(f"UNPROCESSED: {len(unprocessed)} PDFs remaining")
print(f"{'='*70}\n")

if unprocessed:
    for pdf_name, med_name, pdf_path in unprocessed:
        print(f"{pdf_name:20} → {med_name}")

    # Save to file for batch processing
    with open(str(DATA / 'remaining_pdfs.txt'), 'w') as f:
        for pdf_name, med_name, pdf_path in unprocessed:
            f.write(f"{pdf_path}\n")

    print(f"\n✓ List saved to remaining_pdfs.txt")

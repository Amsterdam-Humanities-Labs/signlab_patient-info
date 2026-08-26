#!/usr/bin/env python3
import sys as _sys
from pathlib import Path as _Path
ROOT = _Path(__file__).resolve().parents[2]   # repository root (/web/hh)
DATA = ROOT / 'data'
_sys.path.insert(0, str(ROOT))                 # so `import db_credentials` works from tools/
"""
Create a mapping of PDF filenames to medication names.
This helps track which PDFs have been processed.
"""

import json
import glob
import os
import re
import fitz  # PyMuPDF

def extract_medication_name_from_pdf(pdf_path):
    """Extract medication name from PDF by reading 'Samenvatting van [name]'."""
    try:
        doc = fitz.open(pdf_path)
        first_page = doc[0]
        text = first_page.get_text()

        # Look for "Samenvatting van [medication_name]"
        match = re.search(r'Samenvatting van\s+(.+?)(?:\n|$)', text, re.IGNORECASE)
        if match:
            med_name = match.group(1).strip()
            return med_name

        doc.close()
        return None
    except Exception as e:
        print(f"Error reading {pdf_path}: {e}")
        return None

def create_mapping():
    """Create mapping file of PDF -> medication name."""
    pdf_files = sorted(glob.glob(str(DATA / 'pdfs/*.pdf')))
    mapping = {}

    print(f"Scanning {len(pdf_files)} PDF files...")

    for i, pdf_path in enumerate(pdf_files):
        pdf_name = os.path.basename(pdf_path)
        med_name = extract_medication_name_from_pdf(pdf_path)

        if med_name:
            mapping[pdf_name] = med_name
            if (i + 1) % 10 == 0:
                print(f"  Processed {i + 1}/{len(pdf_files)}...")
        else:
            print(f"  ⚠️  Could not extract name from {pdf_name}")

    # Save mapping
    with open(str(DATA / 'pdf_to_medication_mapping.json'), 'w') as f:
        json.dump(mapping, f, indent=2, ensure_ascii=False)

    print(f"\n✓ Created mapping for {len(mapping)} PDFs")
    return mapping

def find_unprocessed():
    """Find PDFs that haven't been processed yet."""
    # Load mapping
    with open(str(DATA / 'pdf_to_medication_mapping.json'), 'r') as f:
        mapping = json.load(f)

    # Load processed medications
    with open(str(DATA / 'extracted_data.json'), 'r') as f:
        processed_data = json.load(f)

    processed_meds = set(processed_data.keys())
    unprocessed = []

    for pdf_name, med_name in sorted(mapping.items()):
        if med_name not in processed_meds:
            unprocessed.append((pdf_name, med_name))

    return unprocessed

if __name__ == '__main__':
    # Create mapping
    mapping = create_mapping()

    # Find unprocessed
    unprocessed = find_unprocessed()

    print(f"\n{'='*60}")
    print(f"UNPROCESSED PDFs: {len(unprocessed)}")
    print(f"{'='*60}")

    if unprocessed:
        print("\nFirst 20 unprocessed PDFs:")
        for pdf_name, med_name in unprocessed[:20]:
            print(f"  {pdf_name} → {med_name}")

        if len(unprocessed) > 20:
            print(f"  ... and {len(unprocessed) - 20} more")

#!/usr/bin/env python3
import sys as _sys
from pathlib import Path as _Path
ROOT = _Path(__file__).resolve().parents[2]   # repository root (/web/hh)
DATA = ROOT / 'data'
_sys.path.insert(0, str(ROOT))                 # so `import db_credentials` works from tools/
"""
Batch process remaining PDFs and provide a summary for Claude to extract.
This helps Claude process PDFs more efficiently.
"""

import json

# Load mapping
with open(DATA / 'pdf_to_medication_mapping.json', 'r') as f:
    mapping = json.load(f)

# Load processed data
with open(DATA / 'extracted_data.json', 'r') as f:
    processed = json.load(f)

processed_meds = set(processed.keys())

# Find unprocessed
unprocessed = []
for pdf_name, med_name in sorted(mapping.items()):
    if med_name not in processed_meds:
        unprocessed.append((pdf_name, med_name))

print(f"Total remaining: {len(unprocessed)}")
print(f"\nNext batch of 10 PDFs to process:")
for i, (pdf_name, med_name) in enumerate(unprocessed[:10]):
    print(f"{i+1}. {pdf_name} → {med_name}")

# Save list for reference
with open(DATA / 'remaining_pdfs.txt', 'w') as f:
    for pdf_name, med_name in unprocessed:
        f.write(f"{pdf_name}\t{med_name}\n")

print(f"\nSaved full list to remaining_pdfs.txt")

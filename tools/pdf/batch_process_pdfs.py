#!/usr/bin/env python3
import sys as _sys
from pathlib import Path as _Path
ROOT = _Path(__file__).resolve().parents[2]   # repository root (/web/hh)
DATA = ROOT / 'data'
_sys.path.insert(0, str(ROOT))                 # so `import db_credentials` works from tools/
"""
Helper script to track which PDFs have been processed.
Creates a mapping file to track processed PDF files by their filename.
"""

import json
import glob
import os

# File to track processed PDFs by filename
TRACKING_FILE = str(DATA / 'processed_pdfs_tracking.json')

def load_tracking():
    """Load the tracking file or create new one."""
    if os.path.exists(TRACKING_FILE):
        with open(TRACKING_FILE, 'r') as f:
            return json.load(f)
    return {"processed_files": [], "last_processed": None}

def save_tracking(tracking_data):
    """Save the tracking file."""
    with open(TRACKING_FILE, 'w') as f:
        json.dump(tracking_data, f, indent=2, ensure_ascii=False)

def get_all_pdfs():
    """Get all PDF files sorted."""
    return sorted(glob.glob(str(DATA / 'pdfs/*.pdf')))

def get_unprocessed_pdfs():
    """Get list of unprocessed PDFs."""
    tracking = load_tracking()
    all_pdfs = get_all_pdfs()
    processed_set = set(tracking['processed_files'])

    unprocessed = [pdf for pdf in all_pdfs if os.path.basename(pdf) not in processed_set]
    return unprocessed

def mark_as_processed(pdf_filename):
    """Mark a PDF as processed."""
    tracking = load_tracking()
    basename = os.path.basename(pdf_filename)
    if basename not in tracking['processed_files']:
        tracking['processed_files'].append(basename)
        tracking['last_processed'] = basename
        save_tracking(tracking)

def get_next_batch(batch_size=10):
    """Get next batch of unprocessed PDFs."""
    unprocessed = get_unprocessed_pdfs()
    return unprocessed[:batch_size]

if __name__ == '__main__':
    tracking = load_tracking()
    all_pdfs = get_all_pdfs()
    unprocessed = get_unprocessed_pdfs()

    print(f"Total PDFs: {len(all_pdfs)}")
    print(f"Processed: {len(tracking['processed_files'])}")
    print(f"Remaining: {len(unprocessed)}")

    if tracking['last_processed']:
        print(f"\nLast processed: {tracking['last_processed']}")

    print(f"\nNext 10 to process:")
    for pdf in unprocessed[:10]:
        print(f"  {os.path.basename(pdf)}")

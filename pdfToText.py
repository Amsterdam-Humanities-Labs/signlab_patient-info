#!/usr/bin/env python
"""
pdf_to_chapters.py  – v2
Extract sections from a leaflet that always contains the same four chapters
and save them to a JSON file, preserving the prescribed order.

Usage:
    python pdf_to_chapters.py input.pdf output.json
"""
from __future__ import annotations
import sys
import json
import re
import os
from pathlib import Path
from typing import List

import pdfplumber


# === 1. Known headings, in canonical order  ===
EXPECTED_HEADINGS = [
    "Waarom gebruikt u dit medicijn?",
    "Gebruik",
    "Bijwerkingen",
    "Waarschuwingen",
]

# Create normalised versions for quick matching (lower-case, no trailing punctuation)
NORMAL_EXPECTED = [re.sub(r"[^\w\s]", "", h.lower()).strip() for h in EXPECTED_HEADINGS]


def is_heading(line: str) -> bool:
    """
    Decide whether a line is a chapter heading.

    • First, match it against the four EXPECTED_HEADINGS (case/ punctuation-insensitive).
    • Otherwise, use a generic heuristic so extra sub-headings are still captured.
    """
    clean = line.strip()
    norm   = re.sub(r"[^\w\s]", "", clean.lower()).strip()

    # 1️⃣ direct match to an expected heading
    if norm in NORMAL_EXPECTED:
        return True

    # 2️⃣ fallback heuristic (as before, but allow ? or !)
    return (
        2 <= len(clean) <= 80
        and not clean.endswith(".")
        and bool(re.fullmatch(r"[A-Za-zÀ-ȕ\s'?!-]+", clean))
        and (clean.istitle() or clean.isupper())
    )


def extract_chapters(pdf_path: Path) -> List[dict]:
    """
    Parse the PDF and return a list of {title, text} objects.
    Missing chapters are still returned with empty text so order is preserved.
    """
    sections = {title: [] for title in EXPECTED_HEADINGS}
    current_title = None

    with pdfplumber.open(str(pdf_path)) as pdf:
        for page in pdf.pages:
            for raw_line in page.extract_text(x_tolerance=2, y_tolerance=2).splitlines():
                line = raw_line.strip()
                if not line:
                    continue

                if is_heading(line):
                    # Normalise to the canonical title if it's one of the expected headings
                    norm = re.sub(r"[^\w\s]", "", line.lower()).strip()
                    if norm in NORMAL_EXPECTED:
                        current_title = EXPECTED_HEADINGS[NORMAL_EXPECTED.index(norm)]
                    else:
                        current_title = line  # extra, unexpected heading
                        if current_title not in sections:
                            sections[current_title] = []
                    continue  # headings themselves aren't part of bodies

                if current_title:
                    sections[current_title].append(line)

    # Turn dict into list in canonical order followed by any extras
    ordered = [
        {"title": title, "text": " ".join(sections[title]).strip()}
        for title in EXPECTED_HEADINGS
    ]
    # Append additional (non-standard) headings in the order encountered
    for title, body in sections.items():
        if title not in EXPECTED_HEADINGS:
            ordered.append({"title": title, "text": " ".join(body).strip()})
    return ordered


def process_pdf_file(pdf_path: Path, output_dir: Path) -> bool:
    """Process a single PDF file and save as JSON"""
    try:
        chapters = extract_chapters(pdf_path)
        
        # Create output filename
        json_filename = pdf_path.stem + '.json'
        json_path = output_dir / json_filename
        
        with json_path.open("w", encoding="utf-8") as f:
            json.dump(chapters, f, ensure_ascii=False, indent=2)
        
        print(f"✔  Processed: {pdf_path.name} -> {json_filename}")
        return True
    except Exception as e:
        print(f"✗  Error processing {pdf_path.name}: {e}")
        return False


def batch_process():
    """Main function to process all PDFs in the pdfs directory"""
    # Define directories
    script_dir = Path(__file__).parent
    pdfs_dir = script_dir / 'pdfs'
    output_dir = script_dir / 'json_texts'
    
    # Create output directory
    output_dir.mkdir(exist_ok=True)
    
    # Check if pdfs directory exists
    if not pdfs_dir.exists():
        print(f"PDFs directory not found: {pdfs_dir}")
        return
    
    # Process all PDF files
    pdf_files = list(pdfs_dir.glob('*.pdf'))
    
    if not pdf_files:
        print("No PDF files found in pdfs directory")
        return
    
    print(f"Found {len(pdf_files)} PDF files to process")
    
    successful = 0
    for pdf_file in pdf_files:
        # Check if JSON already exists
        json_filename = pdf_file.stem + '.json'
        json_path = output_dir / json_filename
        
        if json_path.exists():
            print(f"JSON already exists for {pdf_file.name}, skipping...")
            continue
        
        if process_pdf_file(pdf_file, output_dir):
            successful += 1
    
    print(f"Successfully processed {successful} PDF files")


def main():
    # If no arguments provided, run batch processing
    if len(sys.argv) == 1:
        batch_process()
        return
    
    # Original single file processing
    if len(sys.argv) != 3:
        sys.exit("Usage: pdf_to_chapters.py <input.pdf> <output.json> OR run without args for batch processing")

    pdf_path, json_path = map(Path, sys.argv[1:3])

    if not pdf_path.exists():
        sys.exit(f"File not found: {pdf_path}")

    chapters = extract_chapters(pdf_path)

    with json_path.open("w", encoding="utf-8") as f:
        json.dump(chapters, f, ensure_ascii=False, indent=2)

    print(f"✔  Extracted {len(chapters)} chapters to {json_path}")


if __name__ == "__main__":
    main()

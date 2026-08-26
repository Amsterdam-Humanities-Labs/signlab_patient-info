#!/usr/bin/env python3
import sys as _sys
from pathlib import Path as _Path
ROOT = _Path(__file__).resolve().parents[2]   # repository root (/web/hh)
DATA = ROOT / 'data'
_sys.path.insert(0, str(ROOT))                 # so `import db_credentials` works from tools/
"""
Create Deel3.docx (Bijwerkingen) and Deel4.docx (Waarschuwingen)
from extracted_data.json
"""

import json
from docx import Document
from docx.shared import Pt, Inches
from docx.enum.text import WD_ALIGN_PARAGRAPH

# Load extracted data
print("Loading extracted_data.json...")
with open(str(DATA / 'extracted_data.json'), 'r', encoding='utf-8') as f:
    data = json.load(f)

print(f"Found {len(data)} medications")

# Create Deel3.docx (Bijwerkingen)
print("\nCreating Deel3.docx (Bijwerkingen)...")
doc = Document()
first = True

for med_name in sorted(data.keys()):
    content = data[med_name]
    bijwerkingen = content.get('bijwerkingen', [])

    if not bijwerkingen:
        continue

    # Add page break before each medication (except first)
    if not first:
        doc.add_page_break()
    first = False

    # Add medication name as heading
    heading = doc.add_heading(med_name, level=1)
    heading.alignment = WD_ALIGN_PARAGRAPH.LEFT

    # Create table with 2 columns
    table = doc.add_table(rows=1, cols=2)
    table.style = 'Table Grid'

    # Set column widths
    table.columns[0].width = Inches(5.5)
    table.columns[1].width = Inches(1.0)

    # Header row
    header_cells = table.rows[0].cells
    header_cells[0].text = 'Paragraaf'
    header_cells[1].text = 'ID'

    # Make header bold
    for cell in header_cells:
        for paragraph in cell.paragraphs:
            for run in paragraph.runs:
                run.bold = True

    # Add bijwerkingen rows
    for row_text in bijwerkingen:
        row_cells = table.add_row().cells
        row_cells[0].text = row_text
        row_cells[1].text = ''

doc.save(str(DATA / 'Deel3.docx'))
print("✓ Saved Deel3.docx")

# Create Deel4.docx (Waarschuwingen)
print("\nCreating Deel4.docx (Waarschuwingen)...")
doc = Document()
first = True

for med_name in sorted(data.keys()):
    content = data[med_name]
    waarschuwingen = content.get('waarschuwingen', [])

    if not waarschuwingen:
        continue

    # Add page break before each medication (except first)
    if not first:
        doc.add_page_break()
    first = False

    # Add medication name as heading
    heading = doc.add_heading(med_name, level=1)
    heading.alignment = WD_ALIGN_PARAGRAPH.LEFT

    # Create table with 2 columns
    table = doc.add_table(rows=1, cols=2)
    table.style = 'Table Grid'

    # Set column widths
    table.columns[0].width = Inches(5.5)
    table.columns[1].width = Inches(1.0)

    # Header row
    header_cells = table.rows[0].cells
    header_cells[0].text = 'Paragraaf'
    header_cells[1].text = 'ID'

    # Make header bold
    for cell in header_cells:
        for paragraph in cell.paragraphs:
            for run in paragraph.runs:
                run.bold = True

    # Add waarschuwingen rows
    for row_text in waarschuwingen:
        row_cells = table.add_row().cells
        row_cells[0].text = row_text
        row_cells[1].text = ''

doc.save(str(DATA / 'Deel4.docx'))
print("✓ Saved Deel4.docx")

print("\n" + "="*50)
print("SUMMARY")
print("="*50)
print(f"Total medications: {len(data)}")
print("\nDocuments created:")
print("  • Deel3.docx - Bijwerkingen (side effects)")
print("  • Deel4.docx - Waarschuwingen (warnings)")
print("\nEach medication has its own page with a table.")
print("The ID column is empty for manual annotation.")

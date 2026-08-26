# PDF Extraction Project - Current Status

## Goal
Extract "Bijwerkingen" (side effects) and "Waarschuwingen" (warnings) sections from ~202 medication PDFs in `/web/hh/pdfs/` and create two Word documents:
- **Deel3.docx**: All Bijwerkingen
- **Deel4.docx**: All Waarschuwingen

## Current Progress
- **Completed**: 16 medications extracted manually
- **Remaining**: ~186 PDFs

## Files Created

### 1. `/web/hh/extracted_data.json`
Contains the manually extracted data in JSON format:
```json
{
  "medication_name": {
    "bijwerkingen": ["row1", "row2", ...],
    "waarschuwingen": ["row1", "row2", ...]
  }
}
```

**Medications already extracted:**
1. methylfenidaat (8848.pdf)
2. oxazepam (8855.pdf)
3. macrogol (8856.pdf)
4. Amoxicilline (8857.pdf)
5. naproxen (8858.pdf)
6. ibuprofen (8859.pdf)
7. diclofenac (8860.pdf)
8. pregabaline (8861.pdf)
9. oxycodon (8862.pdf)
10. Omeprazol (8863.pdf)
11. tramadol (8864.pdf)
12. citalopram (8865.pdf)
13. nitrofurantoïne (8866.pdf)
14. paracetamol (8867.pdf)
15. amitriptyline (8868.pdf)

### 2. `/web/hh/extract_pdf_sections.py`
Python script (initial version - had parsing issues). Can be used later to generate Word docs from JSON.

## Row Structure Rules

### For Bijwerkingen:
1. "U kunt last krijgen van:" + all bullet points = **1 row** (with line breaks `\n` between bullets)
2. Each "Let op!" warning within Bijwerkingen = **separate row**
3. Other paragraphs (e.g., "Heeft u veel last van...") = **separate rows**

### For Waarschuwingen:
1. Each "Let op!" section = **1 row**
2. Sub-bullets under "Let op!" = **separate rows**
3. Statements like "U mag autorijden..." = **separate rows**

## How to Continue

### Step 1: Continue extracting PDFs
Ask Claude to read more PDFs:
```
Read /web/hh/pdfs/8869.pdf and continue extracting to extracted_data.json
```

Next PDFs to process (starting from 8869.pdf):
- 8869.pdf, 8870.pdf, 8871.pdf, 8872.pdf, 8873.pdf...

### Step 2: After all PDFs are extracted
Run this to create the Word documents from the JSON:

```python
import json
from docx import Document
from docx.shared import Inches

# Load extracted data
with open('/web/hh/extracted_data.json', 'r') as f:
    data = json.load(f)

# Create Deel3.docx (Bijwerkingen)
doc = Document()
first = True
for med_name, content in sorted(data.items()):
    if not first:
        doc.add_page_break()
    first = False
    doc.add_heading(med_name, level=1)
    table = doc.add_table(rows=1, cols=2)
    table.style = 'Table Grid'
    table.rows[0].cells[0].text = 'Paragraaf'
    table.rows[0].cells[1].text = 'ID'
    for row_text in content.get('bijwerkingen', []):
        row = table.add_row()
        row.cells[0].text = row_text
        row.cells[1].text = ''
doc.save('/web/hh/Deel3.docx')

# Create Deel4.docx (Waarschuwingen)
doc = Document()
first = True
for med_name, content in sorted(data.items()):
    if not first:
        doc.add_page_break()
    first = False
    doc.add_heading(med_name, level=1)
    table = doc.add_table(rows=1, cols=2)
    table.style = 'Table Grid'
    table.rows[0].cells[0].text = 'Paragraaf'
    table.rows[0].cells[1].text = 'ID'
    for row_text in content.get('waarschuwingen', []):
        row = table.add_row()
        row.cells[0].text = row_text
        row.cells[1].text = ''
doc.save('/web/hh/Deel4.docx')
```

## Output Format (per medication page)

| Paragraaf | ID |
|-----------|-----|
| U kunt last krijgen van:\n• bullet1\n• bullet2 | |
| Soms helpt het om... | |
| Heeft u veel last... | |

## Dependencies
```bash
pip3 install python-docx PyMuPDF
```

## Notes
- Claude reads PDFs visually and extracts text accurately
- Each PDF is a medication summary from Apotheek.nl
- Medication name comes from "Samenvatting van [name]" in the PDF

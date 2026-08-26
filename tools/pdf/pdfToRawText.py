import sys as _sys
from pathlib import Path as _Path
ROOT = _Path(__file__).resolve().parents[2]   # repository root (/web/hh)
DATA = ROOT / 'data'
_sys.path.insert(0, str(ROOT))                 # so `import db_credentials` works from tools/
import PyPDF2
import os

def extract_raw_text_from_pdf(pdf_path):
    """Extract raw text from PDF file without any processing"""
    try:
        with open(pdf_path, 'rb') as file:
            pdf_reader = PyPDF2.PdfReader(file)
            text = ""
            for page_num, page in enumerate(pdf_reader.pages):
                page_text = page.extract_text()
                text += f"\n--- PAGE {page_num + 1} ---\n"
                text += page_text + "\n"
        return text
    except Exception as e:
        print(f"Error reading PDF {pdf_path}: {e}")
        return None

def process_pdf_to_raw_text(pdf_path, output_dir):
    """Process a single PDF file and save raw text"""
    # Extract raw text from PDF
    text = extract_raw_text_from_pdf(pdf_path)
    if not text:
        return False
    
    # Create output filename
    pdf_filename = os.path.basename(pdf_path)
    txt_filename = pdf_filename.replace('.pdf', '_raw.txt')
    txt_path = os.path.join(output_dir, txt_filename)
    
    # Save as text file
    try:
        with open(txt_path, 'w', encoding='utf-8') as f:
            f.write(text)
        print(f"Processed: {pdf_filename} -> {txt_filename}")
        return True
    except Exception as e:
        print(f"Error saving text for {pdf_filename}: {e}")
        return False

def main():
    """Main function to process all PDFs in the pdfs directory"""
    # Define directories
    script_dir = os.path.dirname(__file__)
    pdfs_dir = str(DATA / 'pdfs')
    output_dir = str(DATA / 'raw_texts')
    
    # Create output directory
    os.makedirs(output_dir, exist_ok=True)
    
    # Check if pdfs directory exists
    if not os.path.exists(pdfs_dir):
        print(f"PDFs directory not found: {pdfs_dir}")
        return
    
    # Process all PDF files
    pdf_files = [f for f in os.listdir(pdfs_dir) if f.endswith('.pdf')]
    
    if not pdf_files:
        print("No PDF files found in pdfs directory")
        return
    
    print(f"Found {len(pdf_files)} PDF files to process")
    
    successful = 0
    for pdf_file in pdf_files:
        pdf_path = os.path.join(pdfs_dir, pdf_file)
        
        # Check if text file already exists
        txt_filename = pdf_file.replace('.pdf', '_raw.txt')
        txt_path = os.path.join(output_dir, txt_filename)
        
        if os.path.exists(txt_path):
            print(f"Raw text already exists for {pdf_file}, skipping...")
            continue
        
        if process_pdf_to_raw_text(pdf_path, output_dir):
            successful += 1
    
    print(f"Successfully processed {successful} PDF files to raw text")

if __name__ == "__main__":
    main()

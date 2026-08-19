from db_credentials import DB_PASSWORD
import mysql.connector
import requests
from bs4 import BeautifulSoup
import os
from urllib.parse import urljoin, urlparse
import time

db_config = {
    'host': 'localhost',
    'user': 'user',
    'password': DB_PASSWORD,
    'database': 'admin_gebarenoverleg',
    'charset': 'utf8mb4',
    'use_unicode': True
}

def create_pdfs_directory():
    """Create pdfs directory if it doesn't exist"""
    pdf_dir = os.path.join(os.path.dirname(__file__), 'pdfs')
    os.makedirs(pdf_dir, exist_ok=True)
    return pdf_dir

def fetch_urls_from_db():
    """Fetch URLs from hh_index table with priority >= 100"""
    connection = mysql.connector.connect(**db_config)
    cursor = connection.cursor()
    
    query = "SELECT id, url FROM hh_index WHERE priority >= 100"
    cursor.execute(query)
    rows = cursor.fetchall()
    
    cursor.close()
    connection.close()
    
    return rows

def find_pdf_link(url):
    """Scrape webpage to find PDF download link"""
    try:
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        }
        response = requests.get(url, headers=headers, timeout=10)
        response.raise_for_status()
        
        soup = BeautifulSoup(response.content, 'html.parser')
        
        # Look for PDF links
        pdf_links = soup.find_all('a', href=lambda x: x and x.endswith('.pdf'))
        
        if pdf_links:
            pdf_url = pdf_links[0]['href']
            # Convert relative URLs to absolute
            if not pdf_url.startswith('http'):
                pdf_url = urljoin(url, pdf_url)
            return pdf_url
        
        return None
        
    except Exception as e:
        print(f"Error scraping {url}: {e}")
        return None

def download_pdf(pdf_url, filename, pdf_dir):
    """Download PDF and save with given filename"""
    try:
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        }
        response = requests.get(pdf_url, headers=headers, timeout=30)
        response.raise_for_status()
        
        filepath = os.path.join(pdf_dir, filename)
        with open(filepath, 'wb') as f:
            f.write(response.content)
        
        print(f"Downloaded: {filename}")
        return True
        
    except Exception as e:
        print(f"Error downloading {pdf_url}: {e}")
        return False

def main():
    """Main function to process URLs and download PDFs"""
    # Create pdfs directory
    pdf_dir = create_pdfs_directory()
    
    # Fetch URLs from database
    rows = fetch_urls_from_db()
    print(f"Found {len(rows)} URLs with priority >= 100")
    
    for row_id, url in rows:
        print(f"Processing ID {row_id}: {url}")
        
        # Check if PDF already exists
        pdf_filename = f"{row_id}.pdf"
        pdf_path = os.path.join(pdf_dir, pdf_filename)
        
        if os.path.exists(pdf_path):
            print(f"PDF already exists for ID {row_id}, skipping...")
            continue
        
        # Find PDF link on the webpage
        pdf_url = find_pdf_link(url)
        
        if pdf_url:
            print(f"Found PDF URL: {pdf_url}")
            
            # Download the PDF
            if download_pdf(pdf_url, pdf_filename, pdf_dir):
                print(f"Successfully saved {pdf_filename}")
            else:
                print(f"Failed to download PDF for ID {row_id}")
        else:
            print(f"No PDF link found for ID {row_id}")
        
        # Add small delay to be respectful to the server
        time.sleep(1)

if __name__ == "__main__":
    main()

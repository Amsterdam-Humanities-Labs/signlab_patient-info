import sys as _sys
from pathlib import Path as _Path
ROOT = _Path(__file__).resolve().parents[2]   # repository root (/web/hh)
DATA = ROOT / 'data'
_sys.path.insert(0, str(ROOT))                 # so `import db_credentials` works from tools/
from db_credentials import DB_PASSWORD
import os
import csv
import requests
import mysql.connector
from bs4 import BeautifulSoup

# ...existing mysql config...
db_config = {
    'host': 'signlab-db',
    'user': 'user',
    'password': DB_PASSWORD,
    'database': 'admin_gebarenoverleg',
    'charset': 'utf8mb4',
    'use_unicode': True
}

CSV_FILE = str(DATA / 'test.csv')
HTML_SAVE_DIR = './saved_html'

def extract_text(html):
    # Convert HTML to plain text using BeautifulSoup.
    soup = BeautifulSoup(html, "html.parser")
    full_text = soup.get_text(separator=' ', strip=True)
    start_marker = "belangrijk om te weten"
    end_marker = "download de samenvatting"
    start_idx = full_text.lower().find(start_marker)
    end_idx = full_text.lower().find(end_marker, start_idx)
    if start_idx != -1 and end_idx != -1:
        return full_text[start_idx + len(start_marker):end_idx].strip()
    return ""

def save_html(content, filename):
    if not os.path.exists(HTML_SAVE_DIR):
        os.makedirs(HTML_SAVE_DIR)
    file_path = os.path.join(HTML_SAVE_DIR, filename)
    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)

def main():
    # Connect to MySQL database.
    conn = mysql.connector.connect(**db_config)
    cursor = conn.cursor()

    # Using URL as unique key, update plain_text and prioriteit on duplicate.
    insert_query = ("INSERT INTO medicijnen (url, plain_text, prioriteit) VALUES (%s, %s, %s) "
                    "ON DUPLICATE KEY UPDATE plain_text=VALUES(plain_text), prioriteit=VALUES(prioriteit)")

    with open(CSV_FILE, newline='', encoding='utf-8') as csvfile:
        reader = csv.reader(csvfile, delimiter=';')
        header = next(reader)  # Skip header
        for row in reader:
            if not row or len(row) < 2:
                continue
            url_part = row[0].strip()
            # Remove any leading slash to avoid double slashes in 'full_url'
            url_part = url_part.lstrip('/')
            prioriteit = row[1].strip()
            full_url = f"https://www.apotheek.nl/medicijnen/{url_part}"
            try:
                response = requests.get(full_url, timeout=10)
                response.raise_for_status()
                html_content = response.text
            except Exception as e:
                print(f"Error fetching {full_url}: {e}")
                continue

            # Save HTML locally.
            html_filename = f"{url_part.replace('/', '_')}.html"
            save_html(html_content, html_filename)

            # Extract text between markers.
            plain_text = extract_text(html_content)
            try:
                cursor.execute(insert_query, (full_url, plain_text, prioriteit))
                conn.commit()
                print(f"Inserted data for {url_part}")
            except mysql.connector.Error as e:
                if e.errno == 1062:
                    update_query = "UPDATE medicijnen SET plain_text=%s, prioriteit=%s WHERE url=%s"
                    cursor.execute(update_query, (plain_text, prioriteit, full_url))
                    conn.commit()
                    print(f"Updated data for {url_part}")
                else:
                    print(f"Error inserting data for {url_part}: {e}")
                    conn.rollback()

    cursor.close()
    conn.close()

if __name__ == '__main__':
    main()

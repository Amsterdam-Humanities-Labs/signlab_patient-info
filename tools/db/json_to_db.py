import sys as _sys
from pathlib import Path as _Path
ROOT = _Path(__file__).resolve().parents[2]   # repository root (/web/hh)
DATA = ROOT / 'data'
_sys.path.insert(0, str(ROOT))                 # so `import db_credentials` works from tools/
from db_credentials import DB_PASSWORD
import os
import json
import mysql.connector

db_config = {
    'host': 'signlab-db',
    'user': 'user',
    'password': DB_PASSWORD,
    'database': 'admin_gebarenoverleg',
    'charset': 'utf8mb4',
    'use_unicode': True
}

conn = mysql.connector.connect(**db_config)
cursor = conn.cursor()

# Create tables with proper charset for text columns
cursor.execute("""
    CREATE TABLE IF NOT EXISTS hh_index (
        id INT AUTO_INCREMENT PRIMARY KEY,
        url VARCHAR(255) UNIQUE,
        plain_text TEXT CHARACTER SET utf8mb4
    )
""")
cursor.execute("""
    CREATE TABLE IF NOT EXISTS hh_sentences (
        sentence TEXT CHARACTER SET utf8mb4,
        sentence_id INT,
        FOREIGN KEY (sentence_id) REFERENCES hh_index(id) ON DELETE CASCADE
    )
""")
cursor.execute("""
    CREATE TABLE IF NOT EXISTS hh_words (
        word VARCHAR(255) CHARACTER SET utf8mb4,
        word_id INT,
        FOREIGN KEY (word_id) REFERENCES hh_index(id) ON DELETE CASCADE
    )
""")
conn.commit()

json_folder = str(DATA / "pages")
for filename in os.listdir(json_folder):
    if not filename.endswith(".json"):
        continue
    path = os.path.join(json_folder, filename)
    # Use utf-8-sig to remove BOM from the file
    with open(path, "r", encoding="utf-8-sig") as f:
        data = json.load(f)
    
    url = data.get("url", "")
    # Remove any BOM characters from the plain text explicitly
    plain_text = data.get("full_text", "").lstrip('\ufeff')
    sentences = data.get("sentences", [])
    words = data.get("words", [])
    
    cursor.execute("SELECT id FROM hh_index WHERE url = %s", (url,))
    row = cursor.fetchone()
    if row:
        index_id = row[0]
        cursor.execute("UPDATE hh_index SET plain_text = %s WHERE id = %s", (plain_text, index_id))
        cursor.execute("DELETE FROM hh_sentences WHERE sentence_id = %s", (index_id,))
        cursor.execute("DELETE FROM hh_words WHERE word_id = %s", (index_id,))
    else:
        cursor.execute("INSERT INTO hh_index (url, plain_text) VALUES (%s, %s)", (url, plain_text))
        index_id = cursor.lastrowid

    for sentence in sentences:
        cursor.execute("INSERT INTO hh_sentences (sentence, sentence_id) VALUES (%s, %s)", (sentence, index_id))
    for word in words:
        cursor.execute("INSERT INTO hh_words (word, word_id) VALUES (%s, %s)", (word, index_id))
    
    print(f"Processed {filename} with index id {index_id}")
    conn.commit()

cursor.close()
conn.close()

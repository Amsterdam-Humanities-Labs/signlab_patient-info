from db_credentials import DB_PASSWORD
import json
import re
import mysql.connector

# --- Configuration (adapt as needed) ---
db_config = {
    'host': 'signlab-db',
    'user': 'user',
    'password': DB_PASSWORD,
    'database': 'admin_gebarenoverleg',
    'charset': 'utf8mb4',
    'use_unicode': True
}
JSON_FILE = '/web/hh/topics.json'
BASE_URL = 'https://www.thuisarts.nl/'

# --- Helper function to extract plain keywords from topic field ---
def extract_keywords(topic_str):
    # Remove wrapping markdown code block if present e.g. ```json ... ```
    pattern = r"```json\s*(.*?)\s*```"
    match = re.search(pattern, topic_str, re.DOTALL)
    if match:
        content = match.group(1).strip()
    else:
        # if no markdown formatting found, use the raw string
        content = topic_str.strip()
    # Parse JSON array
    try:
        keywords = json.loads(content)
    except json.JSONDecodeError:
        keywords = []
    return keywords

def process_topics():
    # Load JSON file
    with open(JSON_FILE, 'r', encoding='utf-8') as f:
        topics_data = json.load(f)
    
    # Connect to MySQL
    cnx = mysql.connector.connect(**db_config)
    cursor = cnx.cursor()
    
    for record in topics_data:
        sentence = record.get("sentence")  # e.g. "mijn-kind-heeft-roodvonk"
        topic_str = record.get("topic")      # contains markdown-wrapped JSON array string
        if not sentence or not topic_str:
            continue
        keywords = extract_keywords(topic_str)
        # Convert back to plain json text (e.g. '["kind", "roodvonk", "mijn"]')
        keywords_text = json.dumps(keywords, ensure_ascii=False)
        
        # Derive url key by removing the base URL. Assume URL ends with the sentence.
        # e.g. if url is https://www.thuisarts.nl/roodvonk/mijn-kind-heeft-roodvonk,
        # we check if it ends with the sentence.
        update_query = """
            UPDATE hh_index
            SET keywords = %s
            WHERE url LIKE %s
        """
        url_key = '%' + sentence  # we use LIKE so that any preceding path is accepted.
        cursor.execute(update_query, (keywords_text, url_key))
        print(f"Updated record with sentence: {sentence} using keywords: {keywords_text}")
    
    cnx.commit()
    cursor.close()
    cnx.close()

if __name__ == '__main__':
    process_topics()

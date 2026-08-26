import sys as _sys
from pathlib import Path as _Path
ROOT = _Path(__file__).resolve().parents[2]   # repository root (/web/hh)
DATA = ROOT / 'data'
_sys.path.insert(0, str(ROOT))                 # so `import db_credentials` works from tools/
from db_credentials import DB_PASSWORD
import spacy
import mysql.connector
import re  # added for regex
import unicodedata  # added for normalization

# MySQL Configuration
db_config = {
    'host': 'signlab-db',
    'user': 'user',
    'password': DB_PASSWORD,
    'database': 'admin_gebarenoverleg',
    'charset': 'utf8mb4',
    'use_unicode': True
}

# Load Dutch spaCy model
nlp = spacy.load("nl_core_news_lg")

# Load OpenTaal word list
def load_opentaal_wordlist(filename=DATA / "wordlist.txt"):
    """Load Dutch words from OpenTaal."""
    with open(filename, "r", encoding="utf-8") as f:
        return set(word.strip().lower() for word in f)

dutch_words = load_opentaal_wordlist()

# Function to get lemma
def get_lemma(word):
    """Returns the lemma of a word using spaCy."""
    doc = nlp(word)
    return doc[0].lemma_  # Extract lemma from first token

# Function to check if a lemma exists in the OpenTaal dictionary
def check_lemma_validity(lemma):
    """Check if the lemma exists in OpenTaal word list."""
    return lemma in dutch_words

# Function to process a word and find its correct lemma
def process_word(word):
    """Get lemma and check its validity, retry if necessary."""
    lemma = get_lemma(word)
    # Normalize lemma to remove unsupported characters
    lemma = unicodedata.normalize('NFKC', lemma)
    if check_lemma_validity(lemma):
        return lemma
    
    return lemma  # Return the best guess

# New helper: check word contains only letters
def is_valid_word(word):
    return bool(re.match(r'^[^\W\d_]+$', word, re.UNICODE))

# Function to update the hh_words table
def update_hh_words():
    """Fetch words from hh_words, process them, and update the lemma field."""
    try:
        # Connect to the database
        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor()

        # Fetch words from hh_words table where lemma is NULL
        cursor.execute("SELECT id, word FROM hh_words WHERE lemma IS NULL")
        words = cursor.fetchall()

        if not words:
            print("No words to process.")
            return

        # Process words and update the database
        processed_count = 0  # added counter for commits
        for word_id, word in words:
            # Skip words if numeric or containing special characters
            if not is_valid_word(word):
                print(f"Skipping ID: {word_id} | Word: {word} | Invalid format")
                continue

            corrected_lemma = process_word(word)
            try:
                # Update hh_words table
                cursor.execute("UPDATE hh_words SET lemma = %s WHERE id = %s", (corrected_lemma, word_id))
                print(f"Updated ID: {word_id} | Word: {word} | Lemma: {corrected_lemma}")

                # Check if the lemma exists in hh_lemma, if not insert it
                cursor.execute("SELECT COUNT(*) FROM hh_lemma WHERE lemma = %s", (corrected_lemma,))
                if cursor.fetchone()[0] == 0:
                    cursor.execute("INSERT INTO hh_lemma (lemma) VALUES (%s)", (corrected_lemma,))
                
                processed_count += 1  # increment counter
                if processed_count % 1000 == 0:
                    conn.commit()
                    print(f"Committed after processing {processed_count} rows.")
            except mysql.connector.Error as e:
                if e.errno == 1366:
                    print(f"Skipping ID: {word_id} | Word: {word} | DB error: {e}")
                    continue
                else:
                    raise e

        # Final commit for remaining rows
        conn.commit()
        cursor.close()
        conn.close()

        print("Database update complete.")

    except mysql.connector.Error as e:
        print(f"Database error: {e}")

# Run the update function
update_hh_words()

import os
import sys as _sys
from pathlib import Path as _Path
ROOT = _Path(__file__).resolve().parents[2]   # repository root (/web/hh)
DATA = ROOT / 'data'
_sys.path.insert(0, str(ROOT))                 # so `import db_credentials` works from tools/
from db_credentials import DB_PASSWORD
import json
import mysql.connector

# MySQL Configuration
db_config = {
    'host': 'signlab-db',
    'user': 'user',
    'password': DB_PASSWORD,
    'database': 'admin_gebarenoverleg',
    'charset': 'utf8mb4',
    'use_unicode': True
}

# Load JSON file and build a mapping from glosses and senses
# The Signbank dump lives in the connector's directory, which is where it is
# rebuilt. The docroot-root path is the pre-connector layout, kept as a
# fallback so this still runs on a host that has not moved it.
_CANDIDATES = ("/web/signbank_data/glosses_transformed.json",
               "/web/glosses_transformed.json")
input_file = next((p for p in _CANDIDATES if os.path.exists(p)), _CANDIDATES[0])

with open(input_file, "r", encoding="utf-8") as f:
    input_data = json.load(f)

# Dictionary to store senses and their corresponding signbank data
senses_to_signbank = {}

for entry in input_data:
    for signbank_id, content in entry.items():
        senses = content.get("Senses: Dutch", {})
        nme_videos = content.get("NME Videos", [])
        main_video = content.get("Video")  # Fallback video

        if senses:
            for sense_key, sense_value in senses.items():
                # Split sense values on ", " to separate words
                sense_values = [s.strip().lower() for s in sense_value.split(",")]

                for single_sense in sense_values:
                    if single_sense:  # Avoid empty values
                        # Determine video source
                        if nme_videos:
                            video_link = nme_videos[0]["Link"]
                            video_id = nme_videos[0]["ID"]
                            origin = "signbank"
                        else:
                            video_link = main_video
                            video_id = signbank_id
                            origin = "signbank_glos"

                        # Store in dictionary
                        senses_to_signbank[single_sense] = {
                            "signbank_id": signbank_id,
                            "video": video_link,
                            "video_id": video_id,
                            "origin": origin,
                        }

                        print(f"Added sense: {single_sense} | Origin: {origin}")

# Connect to MySQL and update hh_words
try:
    conn = mysql.connector.connect(**db_config)
    cursor = conn.cursor()

    # Fetch words from hh_words table where lemma is NOT NULL
    cursor.execute("SELECT id, lemma FROM hh_lemma WHERE video IS NULL")
    words = cursor.fetchall()

    if not words:
        print("No words with lemmas to process.")
    else:
        for word_id, lemma in words:
            lemma_clean = lemma.strip().lower()
            print(f"Checking lemma: {lemma_clean}")

            if lemma_clean in senses_to_signbank:
                print("Found lemma in Signbank data.")
                signbank_data = senses_to_signbank[lemma_clean]
                video_link = signbank_data["video"]
                video_id = signbank_data["video_id"]
                origin = signbank_data["origin"]

                # Update hh_words table with the found values
                cursor.execute(
                    "UPDATE hh_lemma SET video = %s, video_id = %s, origin = %s WHERE id = %s",
                    (video_link, video_id, origin, word_id)
                )

                print(f"Updated ID: {word_id} | Lemma: {lemma} | Video: {video_link} | Video ID: {video_id} | Origin: {origin}")

            else:
                print(f"WARNING: Lemma '{lemma}' not found in Signbank senses.")

        # Commit changes
        conn.commit()

    # Close connection
    cursor.close()
    conn.close()
    print("Database update complete.")

except mysql.connector.Error as e:
    print(f"Database error: {e}")

import sys as _sys
from pathlib import Path as _Path
ROOT = _Path(__file__).resolve().parents[2]   # repository root (/web/hh)
DATA = ROOT / 'data'
_sys.path.insert(0, str(ROOT))                 # so `import db_credentials` works from tools/
from db_credentials import DB_PASSWORD
import mysql.connector
import json
import os
from pathlib import Path

db_config = {
    'host': 'localhost',
    'user': 'user',
    'password': DB_PASSWORD,
    'database': 'admin_gebarenoverleg',
    'charset': 'utf8mb4',
    'use_unicode': True
}

def get_row_id_from_filename(filename):
    """Extract row ID from JSON filename"""
    return int(Path(filename).stem)

def combine_title_and_text(title, text):
    """Combine title and text into one value"""
    if not text.strip():
        return title
    return f"{title}\n\n{text}"

def update_database_row(row_id, chapters):
    """Update hh_index table with extracted chapter data"""
    connection = mysql.connector.connect(**db_config)
    cursor = connection.cursor()
    
    # Map chapter titles to database fields
    field_mapping = {
        "Waarom gebruikt u dit medicijn?": "ngt_text",
        "Gebruik": "ngt_text2", 
        "Bijwerkingen": "ngt_text3",
        "Waarschuwingen": "ngt_text4"
    }
    
    # Prepare update data
    update_fields = []
    update_values = []
    
    for chapter in chapters:
        title = chapter.get('title', '')
        text = chapter.get('text', '')
        
        if title in field_mapping:
            field_name = field_mapping[title]
            combined_value = combine_title_and_text(title, text)
            update_fields.append(f"{field_name} = %s")
            update_values.append(combined_value)
    
    if update_fields:
        # Build and execute update query
        query = f"UPDATE hh_index SET {', '.join(update_fields)} WHERE id = %s"
        update_values.append(row_id)
        
        try:
            cursor.execute(query, update_values)
            connection.commit()
            print(f"✔  Updated row {row_id} with {len(update_fields)} fields")
            return True
        except Exception as e:
            print(f"✗  Error updating row {row_id}: {e}")
            connection.rollback()
            return False
    else:
        print(f"⚠  No valid chapters found for row {row_id}")
        return False
    
    cursor.close()
    connection.close()

def process_json_file(json_path):
    """Process a single JSON file and update database"""
    try:
        # Get row ID from filename
        row_id = get_row_id_from_filename(json_path.name)
        
        # Load JSON data
        with open(json_path, 'r', encoding='utf-8') as f:
            chapters = json.load(f)
        
        # Update database
        return update_database_row(row_id, chapters)
        
    except Exception as e:
        print(f"✗  Error processing {json_path.name}: {e}")
        return False

def main():
    """Main function to process all JSON files in json_texts directory"""
    # Define directories
    script_dir = Path(__file__).parent
    json_dir = DATA / 'json_texts'
    
    # Check if json_texts directory exists
    if not json_dir.exists():
        print(f"JSON directory not found: {json_dir}")
        return
    
    # Process all JSON files
    json_files = list(json_dir.glob('*.json'))
    
    if not json_files:
        print("No JSON files found in json_texts directory")
        return
    
    print(f"Found {len(json_files)} JSON files to process")
    
    successful = 0
    for json_file in json_files:
        if process_json_file(json_file):
            successful += 1
    
    print(f"Successfully processed {successful} JSON files")

if __name__ == "__main__":
    main()

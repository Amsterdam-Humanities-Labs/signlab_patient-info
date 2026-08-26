import sys as _sys
from pathlib import Path as _Path
ROOT = _Path(__file__).resolve().parents[2]   # repository root (/web/hh)
DATA = ROOT / 'data'
_sys.path.insert(0, str(ROOT))                 # so `import db_credentials` works from tools/
from db_credentials import DB_PASSWORD
import mysql.connector
import time

# Fix the configuration by removing the unsupported parameter
db_config = {
    'host': 'signlab-db',
    'user': 'user',
    'password': DB_PASSWORD,
    'database': 'admin_gebarenoverleg',
    'charset': 'utf8mb4',
    'use_unicode': True,
    'connection_timeout': 60
}

conn = mysql.connector.connect(**db_config)
cursor = conn.cursor()

# Set the session variable for lock timeout after connection is established
try:
    cursor.execute("SET SESSION innodb_lock_wait_timeout = 50")
    conn.commit()
except Exception as e:
    print(f"Could not set innodb_lock_wait_timeout: {e}")

# Smaller batch size to avoid lock timeouts
batch_size = 20
deleted_count = 0
max_retries = 5
commit_frequency = 10  # Commit more frequently

while True:
    try:
        # Get a batch of row IDs from highest to lowest
        cursor.execute("SELECT id FROM hh_words ORDER BY id DESC LIMIT %s", (batch_size,))
        rows = cursor.fetchall()
        if not rows:
            break

        for (row_id,) in rows:
            retry_count = 0
            while retry_count < max_retries:
                try:
                    print(f"Deleting row {row_id}...")
                    cursor.execute("DELETE FROM hh_words WHERE id = %s", (row_id,))
                    deleted_count += 1
                    # Commit more frequently
                    if deleted_count % commit_frequency == 0:
                        conn.commit()
                        print(f"Committed after deleting {deleted_count} rows.")
                    break  # Success, break the retry loop
                except mysql.connector.errors.DatabaseError as err:
                    if err.errno == 1205:  # Lock wait timeout
                        retry_count += 1
                        print(f"Lock timeout for row {row_id}, retrying ({retry_count}/{max_retries})...")
                        time.sleep(2)  # Wait before retrying
                        # If connection was lost, reconnect
                        if not conn.is_connected():
                            conn = mysql.connector.connect(**db_config)
                            cursor = conn.cursor()
                    else:
                        raise  # Re-raise if it's a different error
            
            if retry_count == max_retries:
                print(f"Failed to delete row {row_id} after {max_retries} retries")
                
    except mysql.connector.Error as err:
        print(f"Database error: {err}")
        # Reconnect if possible
        if not conn.is_connected():
            conn = mysql.connector.connect(**db_config)
            cursor = conn.cursor()
        time.sleep(5)  # Wait a bit before continuing

# Commit any remaining deletions
try:
    conn.commit()
    print(f"Finished deleting. Total rows deleted: {deleted_count}")
except Exception as err:
    print(f"Final commit error: {err}")

cursor.close()
conn.close()

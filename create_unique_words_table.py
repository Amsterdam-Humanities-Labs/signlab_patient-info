from db_credentials import DB_PASSWORD
import mysql.connector

db_config = {
    'host': 'signlab-db',
    'user': 'user',
    'password': DB_PASSWORD,
    'database': 'admin_gebarenoverleg',
    'charset': 'utf8mb4',
    'use_unicode': True
}

# Create two separate connections - one for reading, one for writing
read_conn = mysql.connector.connect(**db_config)
write_conn = mysql.connector.connect(**db_config)

read_cursor = read_conn.cursor(dictionary=True)
write_cursor = write_conn.cursor(dictionary=True)

# Increase lock timeout on write connection
write_cursor.execute("SET innodb_lock_wait_timeout = 300")

# Create the new unique words table with the same structure as the original
print("Creating hh_words_unique table...")
write_cursor.execute("""
    CREATE TABLE IF NOT EXISTS hh_words_unique LIKE hh_words
""")
write_conn.commit()

# Clear the table if it already had data
write_cursor.execute("TRUNCATE TABLE hh_words_unique")
write_conn.commit()
print("Table created and cleared.")

# Get all columns from hh_words table
read_cursor.execute("SHOW COLUMNS FROM hh_words")
columns = [column['Field'] for column in read_cursor.fetchall()]
columns_str = ", ".join(columns)
placeholders = ', '.join(['%s'] * len(columns))

# Process records one by one
print("Processing and inserting unique words...")
processed_lemmas = set()
unique_count = 0
skip_count = 0

# Query to fetch rows
read_cursor.execute(f"SELECT {columns_str} FROM hh_words ORDER BY id")

# Set batch size for commit
batch_size = 1000
batch_counter = 0

# Process each row
for row in read_cursor:
    lemma = row['word']
    
    if lemma not in processed_lemmas:
        # Insert the row into unique table
        values = [row[column] for column in columns]
        write_cursor.execute(f"INSERT INTO hh_words_unique ({columns_str}) VALUES ({placeholders})", values)
        processed_lemmas.add(lemma)
        unique_count += 1
        batch_counter += 1
        
        # Commit in batches to avoid excessive commits
        if batch_counter >= batch_size:
            write_conn.commit()
            batch_counter = 0
            print(f"Progress: {unique_count} unique words processed")
    else:
        skip_count += 1

# Final commit for any remaining records
write_conn.commit()

# Count total rows in original table
write_cursor.execute("SELECT COUNT(*) as count FROM hh_words")
original_count = write_cursor.fetchone()['count']

print(f"Original table: {original_count} rows")
print(f"Unique table: {unique_count} rows")
print(f"Duplicates skipped: {skip_count}")

read_cursor.close()
write_cursor.close()
read_conn.close()
write_conn.close()

print("Done! The hh_words_unique table now contains only unique words.")

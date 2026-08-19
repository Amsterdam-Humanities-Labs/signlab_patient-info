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

conn = mysql.connector.connect(**db_config)
cursor = conn.cursor(dictionary=True)

# Increase the lock wait timeout for this session (value in seconds)
cursor.execute("SET innodb_lock_wait_timeout = 120")  # Increase to 120 seconds (default is usually 50)

batch_size = 1000

while True:
    # Fetch a batch of duplicate words
    cursor.execute("""
        SELECT word, MIN(id) AS keep_id
        FROM hh_words
        GROUP BY word
        HAVING COUNT(*) > 1
        LIMIT %s
    """, (batch_size,))
    groups = cursor.fetchall()
    
    if not groups:
        break

    processed_count = 0
    for group in groups:
        
        word = group['word']
        print(word)
        keep_id = group['keep_id']
        try:
            cursor.execute("""
                DELETE FROM hh_words
                WHERE word = %s AND id <> %s
            """, (word, keep_id))
            
            # Commit after each group deletion
            conn.commit()
            processed_count += 1
        except mysql.connector.errors.DatabaseError as e:
            if "Lock wait timeout exceeded" in str(e):
                print(f"Lock timeout for word '{word}', skipping and continuing...")
                # Roll back the transaction to clear the error state
                conn.rollback()
                continue
            else:
                # Re-raise other database errors
                raise
    
    print(f"Processed batch of {processed_count} duplicate groups.")

cursor.close()
conn.close()

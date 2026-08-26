#!/usr/bin/env python3
import sys as _sys
from pathlib import Path as _Path
ROOT = _Path(__file__).resolve().parents[2]   # repository root (/web/hh)
DATA = ROOT / 'data'
_sys.path.insert(0, str(ROOT))                 # so `import db_credentials` works from tools/
"""
Test database operations with a single URL.
"""

import sys
import os
sys.path.insert(0, str(ROOT))

from crawl_in_het_kort import (
    extract_in_het_kort, save_to_database, db_config
)
import requests
import mysql.connector

TEST_URL = "https://www.thuisarts.nl/aambeien"

def main():
    print("Testing database operations with single URL")
    print("=" * 60)
    print(f"URL: {TEST_URL}")
    print()

    # Step 1: Fetch and extract
    print("1. Fetching page...")
    try:
        resp = requests.get(TEST_URL, timeout=30)
        resp.raise_for_status()
        print("   ✓ Page fetched")
    except requests.RequestException as e:
        print(f"   ✗ Failed: {e}")
        return

    print("2. Extracting 'In het kort' section...")
    text = extract_in_het_kort(resp.text)
    if text:
        print(f"   ✓ Extracted {len(text)} characters")
        print(f"   First 100 chars: {text[:100]}...")
    else:
        print("   ✗ Failed to extract")
        return

    # Step 2: Connect to database
    print("3. Connecting to database...")
    try:
        conn = mysql.connector.connect(**db_config)
        print("   ✓ Connected")
    except mysql.connector.Error as e:
        print(f"   ✗ Failed: {e}")
        return

    # Step 3: Check if record exists before
    cursor = conn.cursor()
    cursor.execute("SELECT id, url, LEFT(plain_text, 50) as plain_snippet, LEFT(ngt_text, 50) as ngt_snippet FROM hh_index WHERE url = %s", (TEST_URL,))
    row = cursor.fetchone()
    cursor.close()

    if row:
        print(f"4. Record exists (id={row[0]})")
        print(f"   Current plain_text: {row[2]}...")
        print(f"   Current ngt_text: {row[3]}...")
        print("   This will be an UPDATE operation")
    else:
        print("4. Record does NOT exist")
        print("   This will be an INSERT operation")

    # Step 4: Save to database
    print("5. Saving to database...")
    if save_to_database(conn, TEST_URL, text):
        print("   ✓ Saved successfully")
    else:
        print("   ✗ Save failed")
        conn.close()
        return

    # Step 5: Verify the save
    print("6. Verifying saved data...")
    cursor = conn.cursor()
    cursor.execute("SELECT id, url, plain_text, ngt_text FROM hh_index WHERE url = %s", (TEST_URL,))
    row = cursor.fetchone()
    cursor.close()

    if row:
        record_id, url, plain_text, ngt_text = row
        print(f"   ✓ Record found (id={record_id})")
        print(f"   URL: {url}")
        print(f"   plain_text length: {len(plain_text) if plain_text else 0}")
        print(f"   ngt_text length: {len(ngt_text) if ngt_text else 0}")
        print(f"   plain_text == ngt_text: {plain_text == ngt_text}")
        print()
        print("   plain_text content:")
        print("   " + "=" * 56)
        print("   " + plain_text.replace('\n', '\n   ') if plain_text else "   (empty)")
        print("   " + "=" * 56)
    else:
        print("   ✗ Record not found after save!")

    conn.close()
    print()
    print("✓ Test completed successfully!")

if __name__ == "__main__":
    main()

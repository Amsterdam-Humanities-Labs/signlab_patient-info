import sys as _sys
from pathlib import Path as _Path
ROOT = _Path(__file__).resolve().parents[2]   # repository root (/web/hh)
DATA = ROOT / 'data'
_sys.path.insert(0, str(ROOT))                 # so `import db_credentials` works from tools/
import os
import re
import json
import time
import requests
from bs4 import BeautifulSoup
from urllib.parse import urljoin, urlparse

base_url = "https://www.thuisarts.nl"
films_url = urljoin(base_url, "overzicht/films")
srt_folder = str(DATA / "srts")
json_folder = str(DATA / "json")

os.makedirs(srt_folder, exist_ok=True)
os.makedirs(json_folder, exist_ok=True)

def video_url_to_filename(url):
    parsed = urlparse(url)
    path = parsed.path.rstrip("/")
    name = os.path.basename(path) if path else "index"
    return name

def extract_text_from_srt(srt_content):
    lines = srt_content.splitlines()
    text_lines = []
    for line in lines:
        line = line.strip()
        if not line:
            continue
        if line.isdigit() or "-->" in line:
            continue
        text_lines.append(line)
    return " ".join(text_lines)

def split_sentences(text):
    return re.split(r'(?<=[.!?])\s+', text)

def split_words(text):
    return re.findall(r'\w+', text)

# Fetch the films overview page
resp = requests.get(films_url)
if resp.status_code != 200:
    print("Failed to retrieve films overview page.")
    exit(1)

soup = BeautifulSoup(resp.text, 'html.parser')

# Find all <a> tags that contain a descendant with class "node__video--container"
video_links = []
for a_tag in soup.find_all("a", href=True):
    if a_tag.find(class_="node__video--container"):
        video_links.append(urljoin(base_url, a_tag['href']))

for video_url in video_links:
    print("Processing video page:", video_url)
    video_resp = requests.get(video_url)
    if video_resp.status_code != 200:
        print("Failed to retrieve video page:", video_url)
        continue
    video_soup = BeautifulSoup(video_resp.text, 'html.parser')

    # Locate the SRT download link (checks for ".srt" and "download=1" in href)
    srt_link_tag = video_soup.find("a", href=lambda x: x and ".srt" in x and "download=1" in x)
    if not srt_link_tag:
        print("No SRT link found on:", video_url)
        continue
    srt_url = urljoin(base_url, srt_link_tag['href'])
    print("Downloading SRT from:", srt_url)
    srt_resp = requests.get(srt_url)
    if srt_resp.status_code != 200:
        print("Failed to download SRT:", srt_url)
        continue
    # Save the SRT file using the basename from the URL
    srt_filename = os.path.basename(urlparse(srt_url).path)
    srt_path = os.path.join(srt_folder, srt_filename)
    with open(srt_path, "wb") as f:
        f.write(srt_resp.content)
    print("Saved SRT file to:", srt_path)
    
    # Extract text from the SRT file
    srt_text = srt_resp.content.decode("utf-8", errors="ignore")
    full_text = extract_text_from_srt(srt_text)
    sentences = split_sentences(full_text)
    words = split_words(full_text)
    
    json_filename = video_url_to_filename(video_url) + ".json"
    json_path = os.path.join(json_folder, json_filename)
    with open(json_path, "w", encoding="utf-8") as f:
        json.dump({
            "url": video_url,
            "full_text": full_text,
            "sentences": sentences,
            "words": words
        }, f, ensure_ascii=False, indent=2)
    print("Saved JSON output to:", json_path)
    time.sleep(5)

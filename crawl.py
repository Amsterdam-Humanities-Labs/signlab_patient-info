import os, re, json, time, requests
from bs4 import BeautifulSoup
from urllib.parse import urljoin, urlparse

base_url = "https://www.thuisarts.nl"
visited = set()
to_visit = [base_url]

os.makedirs("pages", exist_ok=True)

def url_to_filename(url):
    parsed = urlparse(url)
    path = parsed.path.rstrip('/')
    name = os.path.basename(path) if path else "index"
    return os.path.join("pages", f"{name}.json")

def extract_text_hierarchies(html):
    soup = BeautifulSoup(html, 'html.parser')
    for tag in soup(["script", "style"]):
        tag.decompose()
    plain_text = soup.get_text(separator=" ", strip=True)
    # Split into sentences (simple regex based on punctuation)
    sentences = re.split(r'(?<=[.!?])\s+', plain_text)
    # Split into words (alphanumeric sequences)
    words = re.findall(r'\w+', plain_text)
    return plain_text, sentences, words

def save_page(url, plain_text, sentences, words):
    filename = url_to_filename(url)
    data = {"url": url, "plain_text": plain_text, "sentences": sentences, "words": words}
    with open(filename, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=2)

def crawl(url):
    filename = url_to_filename(url)
    if os.path.exists(filename):
        print(f"Skipping {url} (already saved)")
        return None
    try:
        response = requests.get(url)
        if response.status_code == 200:
            plain_text, sentences, words = extract_text_hierarchies(response.text)
            save_page(url, plain_text, sentences, words)
            return BeautifulSoup(response.text, 'html.parser')
    except Exception as e:
        print(e)
    return None

while to_visit:
    current_url = to_visit.pop(0)
    if current_url in visited:
        continue
    print("Visiting:", current_url)
    visited.add(current_url)
    soup = crawl(current_url)
    if not soup:
        continue
    for link in soup.find_all('a', href=True):
        new_url = urljoin(base_url, link['href'])
        if urlparse(new_url).netloc == urlparse(base_url).netloc and new_url not in visited:
            to_visit.append(new_url)
    time.sleep(5)

print("Total pages crawled:", len(visited))

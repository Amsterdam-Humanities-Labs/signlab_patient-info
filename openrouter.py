import requests
import json

API_KEY = "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"
API_URL = "https://openrouter.ai/api/v1/chat/completions"
HEADERS = {
    "Authorization": f"Bearer {API_KEY}",
    "HTTP-Referer": "https://your-site-url.com",  # Optional. Replace with your site URL.
    "X-Title": "YourSiteName",                    # Optional. Replace with your site name.
    "Content-Type": "application/json"
}

# Read each non-empty line from topics.txt
with open("topics.txt", "r", encoding="utf-8") as f:
    sentences = [line.strip() for line in f if line.strip()]

results = []

for sentence in sentences:
    payload = {
        "model": "google/gemini-2.0-flash-lite-001",  # Use Google Gemini 2.0 model
        "messages": [
            {
                "role": "user",
                "content": f"Given the sentence: \"{sentence}\", what is its keyword? Output max three medical keywords in dutch that also must appear in the sentence. We only accept the format in form of JSON array for example [keyword1,keyword2,keyword3]."
            }
        ]
    }
    response = requests.post(API_URL, headers=HEADERS, data=json.dumps(payload))
    if response.ok:
        result = response.json()["choices"][0]["message"]["content"].strip()
    else:
        result = "API error"
    results.append({"sentence": sentence, "topic": result})
    print(f"Sentence: {sentence}\nTopic: {result}\n")

# Save the results in topics.json
with open("topics.json", "w", encoding="utf-8") as outfile:
    json.dump(results, outfile, ensure_ascii=False, indent=2)

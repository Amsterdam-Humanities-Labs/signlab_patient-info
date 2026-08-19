# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Dutch health information indexing system that crawls, processes, and presents medical content from thuisarts.nl. The project combines web crawling, natural language processing (using OpenDutchWordnet), and a web interface for browsing and searching health-related content.

## Commands

### OpenDutchWordnet Setup
```bash
cd OpenDutchWordnet
bash install.sh  # Creates Python 3.4+ virtual environment
```

### Data Processing Pipeline
```bash
# 1. Crawl new content from thuisarts.nl
python crawl.py

# 2. Import crawled data to database
python json_to_db.py

# 3. Process unique words
python create_unique_words_table.py

# 4. Load lemmatized words
python lemma_load.py
```

### Testing
```bash
cd OpenDutchWordnet
bash unit_test.sh  # Run OpenDutchWordnet unit tests
```

## Architecture

### Core Components

1. **Data Collection Layer**
   - `crawl.py`: Web scraper for thuisarts.nl with 5-second request delays
   - Stores raw data in `pages/` directory as JSON files

2. **Data Processing Layer**
   - Multiple Python scripts for importing and processing data
   - Database schema with tables: `hh_index`, `hh_sentences`, `hh_words`, `hh_lemma`, `hh_unique_words`
   - OpenDutchWordnet integration for linguistic analysis

3. **API Layer**
   - `api.php`: RESTful endpoints for data access
   - Key endpoints: `dashboard`, `keywords`, `contents`, `words`, `sentences`, `search_glosses`

4. **Presentation Layer**
   - HTML/JavaScript frontend using React and Mantine UI
   - Main pages: `index.html` (dashboard), `contents.html`, `words.html`, `sentences.html`

### Database Configuration
- Host: `localhost` (or `signlab-db`)
- User: `user`
- Password: `$DB_PASSWORD`
- Database: `admin_gebarenoverleg`
- Charset: `utf8mb4`

### Directory Structure
- `/json/`: Medical condition data files
- `/json_texts/`: Numbered JSON text files
- `/pages/`: Crawled webpage data
- `/odwn/`: OpenDutch WordNet XML data
- `/OpenDutchWordnet/`: Dutch WordNet Python module

## Key Development Patterns

1. **Database Connections**: All scripts use MySQLdb or mysql.connector with utf8mb4 charset
2. **Error Handling**: Scripts typically use try-except blocks for database operations
3. **Data Format**: JSON files follow structure: `{url, title, plain_text, sentences[], words[]}`
4. **API Responses**: PHP API returns JSON with consistent structure
5. **Frontend**: Uses ES6 modules, React components, and Mantine UI library
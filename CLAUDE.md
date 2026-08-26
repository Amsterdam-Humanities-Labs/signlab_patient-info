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

### Key Database Tables
- `hh_index`: Health topics/items (~3485 rows). Each row is a topic from thuisarts.nl. NOT sentences — the variable `total_items` reflects this.
- `hh_sentences`, `hh_words`, `hh_lemma`, `hh_unique_words`: NLP processing tables
- `matched_transcriptions`: Links videos to hh_index items. Key fields: `m_file` (.wav filename), `m_transcription` (hh_index.id), `zOg` ('tekst'), `added` ('1')
- `hh_segments`: Video segments table. Fields: `base_filename`, `segment_number`, `filename`, `location` ('post'). UNIQUE on `(base_filename, segment_number)`. Only `location='post'` entries are kept.

### Directory Structure
- `/json/`: Medical condition data files
- `/json_texts/`: Numbered JSON text files
- `/pages/`: Crawled webpage data
- `/odwn/`: OpenDutch WordNet XML data
- `/OpenDutchWordnet/`: Dutch WordNet Python module

### Video/Segment File Locations
- **Raw videos**: `/web/gebarenoverleg_media/studioFilesMini/raw/` — original full videos (e.g. `M20250826_3368.mp4`)
- **Post segments**: `/web/gebarenoverleg_media/studioFilesMini/post/` — processed/smaller segment files (e.g. `M20250826_3368_1.mp4`). Primary location for segments.
- Segment naming: `{base_filename}_{N}.mp4` where N is 1-indexed
- `base_filename` = `matched_transcriptions.m_file` minus `.wav`

## Key Files

### Overview & Annotation
- **`overview_hh.html`**: Main overview page. Shows badges with counts, supports filtering (label, status, video, segments). Links to `/hh/subBeta4.html` for annotation. Pagination preserves all filters via state variables (`withVideoFilter`, `withoutVideoFilter`, `segmentsFilter`, etc.). When `withVideo` filter is active, items with segments are prioritized in results.
- **`subBeta4.html`**: Video annotation/subtitle editor (copied from `/web/zin/`, runs at **24fps** to reduce memory pressure). Loads videos from `post/` first, falls back to `raw/`.

### API Files
- **`getZinnen.php`**: Main API for overview page. Key actions: `fetchSentences` (paginated with filters), `countZinnen` (badge counts, cached 1hr), `fetchSegments`, `fetchSegmentsByRow`, `syncSegments` (DB counts)
- **`segment_api.php`**: External segmentation service API:
  - `GET ?action=list_unsegmented` — videos without segments
  - `POST ?action=upload_segments` — receives .mp4 files, saves to `post/`, upserts `hh_segments`
  - `GET ?action=status&base_filename=X` — segment status
- **`api.php`**: General data API (dashboard, keywords, contents, words, sentences)
- **`getMT.php`**: Fetches matched_transcription data for a specific item

### Outdated Files (moved, not deleted)
- `scanVideoSegments.php.outdated`, `populateSegments.php.outdated`, `listVideos.html.outdated`

## Key Development Patterns

1. **Database Connections**: PHP files use `include '../mysql_config.php'` with `new mysqli()` and `utf8mb4`
2. **Error Handling**: Scripts typically use try-except blocks for database operations
3. **Data Format**: JSON files follow structure: `{url, title, plain_text, sentences[], words[]}`
4. **API Responses**: PHP API returns JSON with consistent structure
5. **Frontend**: overview_hh.html uses Bootstrap 5 + vanilla JS. Other pages use React/Mantine.
6. **Segment workflow**: External service polls `list_unsegmented`, downloads videos, segments them, uploads via `upload_segments`. DB (`hh_segments`) is single source of truth.
7. **Filter state**: All filters tracked as JS globals, passed through every `loadSentences()` call to persist across pagination.
# HH — Dutch health information index (thuisarts.nl → NGT)

Web application and data pipeline for indexing Dutch patient-information texts from thuisarts.nl and apotheek.nl, linking them to sign-language (NGT) recordings, and annotating those recordings. Served at `https://signcollect.nl/hh/`.

## Repository layout

Everything the web server serves lives in the repository root, so URLs under `/hh/` never change. Everything else is grouped by purpose.

```
/                       Web pages and PHP endpoints (served as https://signcollect.nl/hh/…)
├── index.html          Dashboard — entry point
├── contents.html       Browse indexed topics (hh_index) and their sentences
├── words.html          Word statistics                 sentences.html   Sentence statistics
├── begrippenlijst.html Glossary editor                 content-selector.html  Pick topics for recording
├── overview_hh.html    Recording overview: which topics have video / segments / annotations
├── subBeta8.html       Subtitle/annotation editor for recordings (subBeta4 = older 24 fps variant)
├── autocue2.html       Autocue for the studio
├── action_stats.html   Activity statistics            ngt_comparison.html   apotheek.nl NGT text comparison
├── api.php             General JSON API (dashboard, contents, keywords, sentences, glossary, NGT text)
├── getZinnen.php       Overview/annotation API (fetchSentences, countZinnen, segments, EAF/SRT handling)
├── segment_api.php     API used by the external video-segmentation service
├── getMT.php, getGlosses.php, getGlossVideo.php, getHandshapes.php, get_begrippen.php, save_subtitle.php
├── auth.php            Session-cookie / API-token checks used by every endpoint above
├── syncEafToDatabase.php, SignSegmentationClient.php   (libraries included by getZinnen.php)
├── styles.css, VideoDrawer.js, mod.js
├── split_client/       viewer.php / segment_checker.php for reviewing NGT text segments
│
│                       (tools/ was removed - see "The data pipeline is gone")
│
├── data/               Crawled and derived data (tracked; regenerate with tools/)
│   ├── pages/          Raw crawl output, one JSON per thuisarts.nl page (source for json_to_db.py)
│   ├── json/           Per-topic sentence/word JSON      srts/        Subtitles per topic video
│   ├── pdfs/           apotheek.nl medication PDFs        raw_texts/, json_texts/  Extracted text per PDF
│   ├── saved_html/     Saved medication pages
│   ├── topics.txt / topics.json, wordlist.txt, medicijnen.csv, extracted_data.json, …
│
├── db/schema.sql       Structure of the MySQL tables this app uses (no data)
├── docs/               Notes (pdf-extraction-notes.md)
├── cache/, eaf/, subtitles/   Runtime directories written by the web app — not tracked
└── CLAUDE.md           Conventions and gotchas for AI-assisted work
```

Not tracked (but needed on the server): `OpenDutchWordnet/` (third-party package, see below), `odwn/` (WordNet XML), `glosses_transformed.json` (Signbank export, rebuilt by the connector at `/web/signbank_data/glosses_transformed.json`), `eaf/` (annotation files written by the editor), `cache/`, `split_client/output/`.

## Requirements

- Apache + PHP 8 with `mysqli`, served with this directory mounted at `/hh/`
- MySQL database `admin_gebarenoverleg` (shared with the signCollect suite)
- Media on disk: `/web/gebarenoverleg_media/studioFilesMini/{raw,post}/` for videos and segments
- Login cookie from the signcollect.nl portal: pages load `/userProtect.js`, and every PHP endpoint calls `requireAuthApi()` from `auth.php` (401 without the `sessionObject` cookie)

## Setup

```bash
git clone git@github.com:Amsterdam-Humanities-Labs/signlab_hh.git /web/hh
cd /web/hh

# 1. Database credentials. The PHP endpoints read them through
#    signcollect-lib: db_config.php resolves ../lib (i.e. /web/lib) or
#    /web/lib and takes host/user/password/database from /web/.env. Nothing
#    to copy here — deploy signlab_signcollect-lib and write /web/.env.
#    getZinnen.php, getMT.php, getGlossVideo.php, segment_api.php and
#    syncEafToDatabase.php still include ../mysql_config.php, which on a
#    migrated host is a shim over the same source.
cp db_credentials.example.py db_credentials.py     # tools/ only; still gitignored

# 2. Tables (structure only; data comes from the pipeline or from production)
mysql -u user -p admin_gebarenoverleg < db/schema.sql

# 3. Runtime directories, writable by the web server
mkdir -p cache eaf subtitles && chown www-data cache eaf subtitles

# 4. API token for the external segmentation service (segment_api.php):
#    put HH_API_TOKEN in db_credentials.php (gitignored, optional, loaded by
#    db_config.php when present) and configure the client to send X-Api-Token.
#    Until it is set, segment_api.php accepts unauthenticated calls and logs a warning.

# 5. WordNet (only for tools/nlp)
git clone https://github.com/cltl/OpenDutchWordnet && cd OpenDutchWordnet && bash install.sh
```

## The data pipeline is gone

`tools/` held the only written record of how the hh database is built:
crawl thuisarts.nl into `data/pages/`, load that into `hh_index` /
`hh_sentences` / `hh_words`, lemmatise, label topics, extract the apotheek.nl
PDFs. Thirty-four Python files, four stages.

They were removed because this repository is deployed into an Apache document
root that has no Python handler, so every one of them was served as source to
anyone who asked for the URL. They were also, individually, one-off scripts:
run once, against a data set that no longer changes, by one person.

Together they were not one-off at all - they were the reproduction recipe -
and that is what left. `data/` is still tracked, so the *output* of the
pipeline survives in full and the database can still be rebuilt from it by
`db/schema.sql` plus a loader someone writes again. The crawl itself is what
would have to be redone from scratch.

The files are recoverable: they are in this repository's history, and on the
production server, which keeps its own copy. If they come back they belong
somewhere outside the docroot.

## Video segments and annotation

- Recordings are matched to topics through `matched_transcriptions` (`m_file` = wav filename, `m_transcription` = `hh_index.id`, `zOg = 'tekst'`).
- An external segmentation service polls `segment_api.php?action=list_unsegmented`, cuts the video, and uploads segments via `action=upload_segments`; `hh_segments` is the single source of truth.
- Segments live in `…/studioFilesMini/post/` as `{base}_{N}.mp4`; the editor (`subBeta8.html`) loads from `post/` first and falls back to `raw/`.
- Annotations are saved as ELAN `.eaf` plus `.srt` sidecars in `eaf/`, and synced to the database with `syncEafToDatabase.php`.

See `CLAUDE.md` for table semantics and the traps that have bitten before.

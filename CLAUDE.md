# CLAUDE.md

Guidance for Claude Code in this repository. Setup, layout and the pipeline are in `README.md`; this file holds conventions and gotchas.

## Layout rules

- **Web root = repository root.** Anything the browser or Apache must reach (`*.html`, `*.php`, `*.js`, `*.css`, `split_client/`, runtime `eaf/` `cache/` `subtitles/`) stays at the top level so `/hh/…` URLs keep working. Do not move these into subdirectories.
- **Scripts go in `tools/<purpose>/`** (`crawl`, `db`, `nlp`, `pdf`, `ngt`). Every script starts with the `ROOT` / `DATA` header and `_sys.path.insert(0, str(ROOT))`; use `DATA / 'pages'` etc., never a relative `"pages"` or an absolute `/web/hh/...`.
- **Data goes in `data/`.** Regenerable crawl/extraction output is tracked there so the pipeline is reproducible; runtime state (`cache/`, `eaf/`, `subtitles/`, `video_segments_cache.json`) is gitignored.
- No `.bak`/`.backup`/`.outdated` copies in git — history has them. `.gitignore` blocks them.
- Never commit credentials. `db_credentials.php` / `db_credentials.py` and `../mysql_config.php` are outside version control; only the `.example` files are tracked.

## Database (`admin_gebarenoverleg`, shared)

Structure in `db/schema.sql`. Two credential mechanisms coexist: `db_credentials.php`/`.py` (api.php, tools) and `../mysql_config.php` (getZinnen.php, getMT.php, getGlossVideo.php, syncEafToDatabase.php). Don't add a third.

- `hh_index` — one row per thuisarts.nl **topic** (~3,485), not per sentence; `total_items` counts topics.
- `hh_sentences`, `hh_words`, `hh_lemma`, `hh_index_glos` — NLP tables filled by `tools/`.
- `matched_transcriptions` — links recordings to topics: `m_file` (wav name), `m_transcription` (= `hh_index.id`), `zOg = 'tekst'`, `added = '1'`.
- `hh_segments` — video segments: `base_filename`, `segment_number`, `filename`, `location`; UNIQUE `(base_filename, segment_number)`; only `location = 'post'` rows are kept. The DB is the source of truth, not the filesystem.
- `hh_unique_words` and `medicijnen` are referenced by old scripts but no longer exist in the database.

## Media paths

- Raw videos: `/web/gebarenoverleg_media/studioFilesMini/raw/` (`M20250826_3368.mp4`)
- Segments: `/web/gebarenoverleg_media/studioFilesMini/post/` (`M20250826_3368_1.mp4`, 1-indexed)
- `base_filename` = `matched_transcriptions.m_file` without `.wav`
- Annotation files: `eaf/` in this repo, served at `https://signcollect.nl/hh/eaf/`

## Key files

- `overview_hh.html` + `getZinnen.php` — overview with badge counts and filters (label, status, video, segments). Filter state lives in JS globals and must be passed through every `loadSentences()` call or pagination loses it. `countZinnen` is cached 1 h in `cache/countZinnen.json`.
- `subBeta8.html` — current annotation editor (copied from `/web/zin/`, 24 fps to limit memory). `subBeta4.html` is the previous version; `overview_hh.html` still links to it.
- `segment_api.php` — `list_unsegmented`, `upload_segments` (POST mp4 → `post/` + upsert `hh_segments`), `status`.
- `api.php` — dashboard/contents/keywords/sentences plus glossary CRUD and NGT-text endpoints. `ngt_comparison_stats` reads `data/ngt_comparison_results.json`, produced by `tools/ngt/compare_ngt_texts.py`.
- `overview_hh.html` uses Bootstrap 5 + vanilla JS; the older pages use React + Mantine from CDN.

## Working on it

- PHP: `php -l file.php`. Python: `python3 -m py_compile tools/*/*.py`. There is no test suite.
- Scripts hit the production database; read before you write, and prefer `--dry-run`-style checks when adding new ones.
- Requests to thuisarts.nl must keep the 5-second delay in `crawl.py`.

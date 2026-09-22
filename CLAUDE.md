# CLAUDE.md

Guidance for Claude Code in this repository. Setup, layout and the pipeline are in `README.md`; this file holds conventions and gotchas.

## Layout rules

- **Web root = repository root.** Anything the browser or Apache must reach (`*.html`, `*.php`, `*.js`, `*.css`, `split_client/`, runtime `eaf/` `cache/` `subtitles/`) stays at the top level so `/hh/…` URLs keep working. Do not move these into subdirectories.
- The Python pipeline (`tools/`) and its output (`data/`) were removed; both are in git history. Only `data/ngt_comparison_results.json` remains (read by `api.php`). Runtime state (`cache/`, `eaf/`, `subtitles/`, `video_segments_cache.json`) is gitignored.
- No `.bak`/`.backup`/`.outdated` copies in git — history has them. `.gitignore` blocks them.
- **Every PHP endpoint authenticates.** JSON endpoints start with `require_once __DIR__ . '/auth.php'; $currentUser = requireAuthApi();`; HTML-producing PHP uses `requireAuth()`; machine endpoints (`segment_api.php`) use `requireApiToken()`. New endpoints must do the same. Validate every client-supplied filename with `basename()` + a strict regex before touching the filesystem.
- Never commit credentials. They live in `/web/.env` on the host and are read at runtime by signcollect-lib; `../mysql_config.php` is outside version control, and only the `.example` files are tracked.

## Database (`admin_gebarenoverleg`, shared)

Structure in `db/schema.sql`. PHP endpoints get their credentials from `db_config.php`, which loads signcollect-lib (`/web/lib`) and exposes both shapes this repo already used — `$db_config[...]` and `DB_HOST`/`DB_USER`/`DB_PASS`/`DB_NAME` — from the one source, `/web/.env`. The `../mysql_config.php` includes (getZinnen.php, getMT.php, segment_api.php, syncEafToDatabase.php) are unmigrated and still work: on a migrated host that file is a shim over the same source. Don't add a third — new endpoints require `db_config.php`.

- `hh_index` — one row per thuisarts.nl **topic** (~3,485), not per sentence; `total_items` counts topics.
- `hh_sentences`, `hh_words`, `hh_lemma`, `hh_index_glos` — NLP tables filled by the removed pipeline.
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
- The annotation editor is not in this repo: `overview_hh.html` links to `/annotation-editors/subBeta8/zin/subBeta8.html`.
- `segment_api.php` — `list_unsegmented`, `upload_segments` (POST mp4 → `post/` + upsert `hh_segments`), `status`.
- `api.php` — dashboard/contents/keywords/sentences plus glossary CRUD and NGT-text endpoints. `ngt_comparison_stats` reads `data/ngt_comparison_results.json` (a pipeline output; the script that made it is gone).
- `overview_hh.html` uses Bootstrap 5 + vanilla JS; the older pages use React + Mantine from CDN.

## Working on it

- PHP: `php -l file.php`. There is no test suite.
- Endpoints hit the production database; read before you write.

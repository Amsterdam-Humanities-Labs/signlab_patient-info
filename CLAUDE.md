# CLAUDE.md

Conventions and gotchas. Setup, layout and deploy are in `README.md`.

## Rules
- **Web root = repository root.** Anything the browser or Apache must reach (`*.html`, `*.php`, `*.js`, `*.css`, `split_client/`, runtime `eaf/` `cache/` `subtitles/`) stays at the top level so `/hh/…` URLs keep working. Do not move these into subdirectories.
- **Every PHP endpoint authenticates.** JSON endpoints start with `require_once __DIR__ . '/auth.php'; $currentUser = requireAuthApi();`; HTML-producing PHP uses `requireAuth()`; machine endpoints (`segment_api.php`) use `requireApiToken()`. New endpoints must do the same. Validate every client-supplied filename with `basename()` + a strict regex before touching the filesystem.
- Never commit credentials. They live in `/web/.env` on the host and are read at runtime by signcollect-lib; `../mysql_config.php` is outside version control, and only the `.example` files are tracked.
- New endpoints require `db_config.php` (loads signcollect-lib, reads `/web/.env`). The `../mysql_config.php` includes in older endpoints still work; don't add a third way.
- Endpoints hit the production database: read before you write. Check PHP with `php -l`; there is no test suite.

## Database (`admin_gebarenoverleg`, shared; structure in `db/schema.sql`)
- `hh_index` — one row per thuisarts.nl **topic** (~3,485), not per sentence; `total_items` counts topics.
- `hh_sentences`, `hh_words`, `hh_lemma`, `hh_index_glos` — NLP tables filled by the removed pipeline.
- `matched_transcriptions` — links recordings to topics: `m_file` (wav name), `m_transcription` (= `hh_index.id`), `zOg = 'tekst'`, `added = '1'`.
- `hh_segments` — video segments: `base_filename`, `segment_number`, `filename`, `location`; UNIQUE `(base_filename, segment_number)`; only `location = 'post'` rows are kept. The DB is the source of truth, not the filesystem.
- `hh_unique_words` and `medicijnen` are referenced by old scripts but no longer exist in the database.
- `base_filename` = `matched_transcriptions.m_file` without `.wav`; segment files are `<base>_<n>.mp4` (1-indexed) in `studioFilesMini/post/`.

## Front end
- `overview_hh.html` (Bootstrap 5 + vanilla JS): filter state lives in JS globals and must be passed through every `loadSentences()` call or pagination loses it. `countZinnen` is cached 1 h in `cache/countZinnen.json`. Older pages use React + Mantine from CDN.

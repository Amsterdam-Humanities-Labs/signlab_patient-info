# HH — Dutch health information index (thuisarts.nl → NGT)

Web application and data pipeline for indexing Dutch patient-information texts from thuisarts.nl and apotheek.nl, linking them to sign-language (NGT) recordings, and annotating those recordings. Served at `https://signcollect.nl/hh/`.

## Where it runs

- **Production:** the signcollect core server (production VPS), served from `/web/hh` at <https://signcollect.nl/hh/>.
- **Demo hosts:** dev2 under `/web/hh`, dev-1 under `/srv/signcollect/web/hh`.

The deployed directory name is fixed: the menu in `signlab_signCollect-v2` links to `/hh/` by absolute path, and this repository's own pages link out to `/annotation-editors/…` and `/userProtect.js` at the docroot root.

**This repository's default branch is `master`, not `main`** — the only one in the estate that is, and what the deploy checks out.

## Status

**Dormant, but live and deployed.** Both halves of that matter:

- *Dormant.* Every commit of the original upload lands on a single day, 2025-07-04. Nothing since has been feature work: the 2026 commits are estate-wide maintenance — session-cookie verification, credential and path migration, removing the Python pipeline from the document root. The crawl it is built on is of a data set that no longer changes, and nothing in the live capture-to-API path depends on this repository.
- *Live.* It is nonetheless deployed like every other component (`repos.tsv`, below), its pages are reachable, and the stack's deployment tests assert that `hh/api.php`, `hh/getGlosses.php` and `hh/get_begrippen.php` return real rows. Do not treat "dormant" as "safe to break".

Read it for the crawler and lemmatisation approach; do not expect the pipeline to run as-is — see "The data pipeline is gone".

## Repository layout

Everything the web server serves lives in the repository root, so URLs under `/hh/` never change. Everything else is grouped by purpose.

```
/                       Web pages and PHP endpoints (served as https://signcollect.nl/hh/…)
├── index.html          Dashboard — entry point
├── contents.html       Browse indexed topics (hh_index) and their sentences
├── words.html          Word statistics                 sentences.html   Sentence statistics
├── begrippenlijst.html Glossary editor                 content-selector.html  Pick topics for recording
├── overview_hh.html    Recording overview: which topics have video / segments / annotations
│                       (the annotation editor it opens is NOT here: it links out to
│                        /annotation-editors/subBeta8/zin/subBeta8.html)
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
├── tools/              What is left of the pipeline: pdf/monitor_progress.sh only.
│                       The 34 Python scripts were removed - see "The data pipeline is gone"
│
├── data/               Crawled and derived data (tracked; the pipeline that made it is gone)
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
#    db_credentials.example.php is the stub for the one thing that is NOT a
#    database credential: see step 4.

# 2. Tables (structure only; data comes from the pipeline or from production)
mysql -u user -p admin_gebarenoverleg < db/schema.sql

# 3. Runtime directories, writable by the web server
mkdir -p cache eaf subtitles && chown www-data cache eaf subtitles

# 4. API token for the external segmentation service (segment_api.php):
#    put HH_API_TOKEN in db_credentials.php (gitignored, optional, loaded by
#    db_config.php when present) and configure the client to send X-Api-Token.
#    Until it is set, segment_api.php accepts unauthenticated calls and logs a warning.

# 5. WordNet - only needed by the lemmatisation scripts, which are no longer
#    in this repository. Skip it unless you are reviving them from history.
git clone https://github.com/cltl/OpenDutchWordnet && cd OpenDutchWordnet && bash install.sh
```

## Deploying it

There is no build step — PHP, HTML and static assets, served as-is.

Deployment is driven by `interface_deploy/scripts/repos.tsv` in
[signlab_signcollect-stack](https://github.com/Amsterdam-Humanities-Labs/signlab_signcollect-stack), which lists this repo as:

```
hh	signlab_hh	master
```

The host clones the repo itself and the checkout *is* the docroot directory; each deploy is `fetch → reset --hard → clean → rewrite-urls.sh`, which rewrites production URLs to same-origin paths on a demo host. That leaves the checkout permanently dirty by design — nobody commits from a docroot.

## Configuration

Everything below is per-host and deliberately absent from git.

| File | Where | What needs it |
|---|---|---|
| `/web/.env` | beside the install root; written once by the stack's `provision.sh` | the real source of the database credentials. Read through signcollect-lib, never directly |
| `/web/lib/` (`signlab_signcollect-lib`) | docroot root | `db_config.php` resolves `../lib/db_config.php` or `/web/lib/db_config.php`. Without it every endpoint that includes `db_config.php` returns 500 before reading the request |
| `../mysql_config.php` | **one level ABOVE this directory** — `/web/mysql_config.php`, not `/web/hh/` | `getZinnen.php`, `getMT.php`, `getGlossVideo.php`, `segment_api.php`, `syncEafToDatabase.php` and `auth.php`'s session check still include it. On a migrated host it is a shim over `/web/.env`. Putting a copy *in* this directory does nothing |
| `db_credentials.php` | this directory, gitignored, optional | `HH_API_TOKEN` for `segment_api.php`. Included first, and only if present, so a legacy host's constants keep working. Stub: `db_credentials.example.php` |
| `cache/`, `eaf/`, `subtitles/` | this directory, gitignored | runtime output; must be writable by `www-data` |

Do not add a fourth credential path. New endpoints `require` `db_config.php`.

## Dependencies

- **`signlab_signcollect-lib`** at `/web/lib` — credentials and the install-root resolver (`sc_paths.php`, vendored here, is byte-identical across repos: edit it in the library and re-copy).
- **MySQL `admin_gebarenoverleg`**, shared with the rest of the signCollect suite. Tables in `db/schema.sql`; semantics in `CLAUDE.md`.
- **`signlab_annotation-editors`** at `/web/annotation-editors` — `overview_hh.html` opens `subBeta8` there. Without it the editor links 404.
- **The media tree** `gebarenoverleg_media/studioFilesMini/{raw,post}/` for videos and segments.
- **The Signbank connector**, which publishes `glosses_transformed.json` to `/web/signbank_data/`.
- **The external video-segmentation service**, which polls `segment_api.php?action=list_unsegmented` and posts segments back. It is not in this estate; the shared secret is `HH_API_TOKEN`.
- **The portal session cookie** on `.signcollect.nl` (`/userProtect.js` plus `auth.php`).

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

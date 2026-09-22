# signlab_hh
Dutch patient-information texts (thuisarts.nl, apotheek.nl), linked to NGT recordings.

## What it does
- Browse pages for topics, sentences, words and the glossary: `index.html`, `contents.html`, `words.html`, `sentences.html`, `begrippenlijst.html`.
- `overview_hh.html` shows which topics have video, segments and annotations. Its editor button opens `/annotation-editors/subBeta8/zin/subBeta8.html`.
- JSON endpoints, all behind `auth.php`: `api.php`, `getZinnen.php`, `segment_api.php` (for an external segmentation service), `getMT.php`, `getGlosses.php`, `get_begrippen.php`, `save_subtitle.php`.
- New glosses go to `/menu_beta/batch_add.php` in [signlab_signCollect-v2](https://github.com/Amsterdam-Humanities-Labs/signlab_signCollect-v2).
- The crawl and extraction pipeline is gone (see git history). `api.php` still reads `data/ngt_comparison_results.json`.

## Where it runs
- Production: core server, `/web/hh`, <https://signcollect.nl/hh/>.
- Demo hosts: `/web/hh` or `/srv/signcollect/web/hh`.
- The path `/hh/` is fixed, because the SignCollect menu links to it.

## Status
Dormant but deployed. The stack's tests expect `api.php`, `getGlosses.php` and `get_begrippen.php` to return rows.

## How to run / deploy
There is no build step. The default branch is `master`.
The stack deploys it (`repos.tsv` line `hh	signlab_hh	master`); see
[signlab_signcollect-stack](https://github.com/Amsterdam-Humanities-Labs/signlab_signcollect-stack).
```bash
mysql -u user -p admin_gebarenoverleg < db/schema.sql   # structure only
mkdir -p cache eaf subtitles && chown www-data cache eaf subtitles
```

## Configuration
| File | Where | Used by |
|---|---|---|
| `/web/.env` | install root | DB credentials, read through signcollect-lib (`db_config.php`) |
| `SC_LEGACY_WEB_ROOT`, `SC_LEGACY_BASE_URL` | env or `/web/.env`, optional | `getMT.php` turns SRT paths under the first (default `/var/www/html`) into URLs under the second (default `https://leffe.science.uva.nl:8043`) |
| `../mysql_config.php` | one level above this directory | `getZinnen.php`, `getMT.php`, `segment_api.php`, `syncEafToDatabase.php`, `auth.php` |
| `db_credentials.php` | here, gitignored, optional | `HH_API_TOKEN` for `segment_api.php`. Unset means open, with a warning. Template: `db_credentials.example.php` |
| `cache/`, `eaf/`, `subtitles/` | here, gitignored | runtime output, writable by `www-data` |

## Dependencies
- [signlab_signcollect-lib](https://github.com/Amsterdam-Humanities-Labs/signlab_signcollect-lib) at `/web/lib`.
- MySQL `admin_gebarenoverleg`. Schema in `db/schema.sql`, meaning of the tables in `CLAUDE.md`.
- [signlab_annotation-editors](https://github.com/Amsterdam-Humanities-Labs/signlab_annotation-editors) (subBeta8) and signlab_signCollect-v2 (`/menu_beta/batch_add.php`).
- Media: `/web/gebarenoverleg_media/studioFilesMini/{raw,post}/`. Signbank export: `/web/signbank_data/glosses_transformed.json`.
- An external segmentation service polls `segment_api.php`. It is not in any of our repos.
- Portal session cookie (`/userProtect.js` and `auth.php`).

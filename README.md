# signlab_hh
Index of Dutch patient-information texts (thuisarts.nl, apotheek.nl) linked to NGT sign-language recordings.

## What it does
- Pages to browse topics, sentences, words and the glossary (`index.html`, `contents.html`, `words.html`, `sentences.html`, `begrippenlijst.html`).
- `overview_hh.html`: which topics have video, segments, annotations. Its editor links out to `/annotation-editors/subBeta8/zin/subBeta8.html`.
- JSON endpoints: `api.php` (general), `getZinnen.php` (overview/annotation), `segment_api.php` (external segmentation service), `getMT.php`, `getGlosses.php`, `get_begrippen.php`, `save_subtitle.php`. All go through `auth.php`.
- New glosses are posted to signCollect-v2's `/menu_beta/batch_add.php`.
- The crawl/extraction pipeline and its output (`data/`) are gone; both are in git history. Only `data/ngt_comparison_results.json` is still read (`api.php`).

## Where it runs
- Production: core server, `/web/hh`, <https://signcollect.nl/hh/>. Demo hosts: `/web/hh` or `/srv/signcollect/web/hh`.
- The path `/hh/` is fixed: the signCollect-v2 menu links to it.

## Status
Dormant but deployed; the stack's tests expect `api.php`, `getGlosses.php` and `get_begrippen.php` to return rows.

## How to run / deploy
No build step. Default branch is **`master`**. Deployed by the stack (`repos.tsv` line `hh	signlab_hh	master`); see
[signlab_signcollect-stack](https://github.com/Amsterdam-Humanities-Labs/signlab_signcollect-stack).
```bash
mysql -u user -p admin_gebarenoverleg < db/schema.sql   # structure only
mkdir -p cache eaf subtitles && chown www-data cache eaf subtitles
```

## Configuration
| File | Where | Used by |
|---|---|---|
| `/web/.env` | install root | DB credentials, read via signcollect-lib (`db_config.php`) |
| `../mysql_config.php` | one level ABOVE this dir | `getZinnen.php`, `getMT.php`, `segment_api.php`, `syncEafToDatabase.php`, `auth.php` |
| `db_credentials.php` | here, gitignored, optional | `HH_API_TOKEN` for `segment_api.php` (unset = open + warning). Stub: `db_credentials.example.php` |
| `cache/`, `eaf/`, `subtitles/` | here, gitignored | runtime output, writable by `www-data` |

## Dependencies
- `signlab_signcollect-lib` at `/web/lib`; MySQL `admin_gebarenoverleg` (schema in `db/schema.sql`, semantics in `CLAUDE.md`).
- `signlab_annotation-editors` (subBeta8) and `signlab_signCollect-v2` (`/menu_beta/batch_add.php`).
- Media: `/web/gebarenoverleg_media/studioFilesMini/{raw,post}/`; Signbank export `/web/signbank_data/glosses_transformed.json`.
- External segmentation service polling `segment_api.php` (not in this estate).
- Portal session cookie (`/userProtect.js` + `auth.php`).

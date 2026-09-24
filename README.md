# signlab_patient-info
Dutch patient-information texts (thuisarts.nl, apotheek.nl), linked to NGT recordings.

## What it does
- Browse pages for topics, sentences, words and the glossary: `index.html`, `contents.html`, `words.html`, `sentences.html`, `begrippenlijst.html`.
- More pages: `ngt_comparison.html` (reads `data/ngt_comparison_results.json` through `api.php`), `action_stats.html`, `autocue.html`, `content-selector.html`.
- `overview_hh.html` shows which topics have video, segments and annotations. Its editor button opens `/annotation-editors/subBeta8/zin/subBeta8.html`.
- JSON endpoints, all behind `auth.php`: `api.php`, `getZinnen.php`, `segment_api.php` (for an external segmentation service), `getMT.php`, `getGlosses.php`, `get_begrippen.php`, `save_subtitle.php`.
- New glosses go to `/menu_beta/batch_add.php` in [signlab_signCollect-v2](https://github.com/Amsterdam-Humanities-Labs/signlab_signCollect-v2).
- `syncEafToDatabase.php` copies the Nederlands tier of the EAF files in `eaf/` to `sentences.zinStringEAF`. Run it with `php syncEafToDatabase.php [--limit=N] [--force] [--sentence_id=ID]`.
- The crawl and extraction pipeline is gone (see git history).

## Where it runs
- Production: core server, `/web/hh`, <https://signcollect.nl/hh/>.
- Demo hosts: `/web/hh` or `/srv/signcollect/web/hh`.
- The path `/hh/` is fixed, because the SignCollect menu links to it.

## Status
Dormant but deployed. The stack's tests expect `api.php`, `getGlosses.php` and `get_begrippen.php` to return rows.

## How to run / deploy
There is no build step. The default branch is `master`.
The stack deploys it (`repos.tsv` line `hh	signlab_patient-info	master`); see
[signlab_signcollect-stack](https://github.com/Amsterdam-Humanities-Labs/signlab_signcollect-stack).
```bash
mysql -u user -p admin_gebarenoverleg < db/schema.sql   # structure only
mkdir -p cache eaf && chown www-data cache eaf
```

## Configuration
| File | Where | Used by |
|---|---|---|
| `/web/.env` | install root | DB credentials, read through signcollect-lib (`db_config.php`) |
| `../mysql_config.php` | one level above this directory | `getZinnen.php`, `getMT.php`, `segment_api.php`, `syncEafToDatabase.php`, `auth.php` |
| `db_credentials.php` | here, gitignored, optional | `HH_API_TOKEN` for `segment_api.php`. Unset means open, with a warning. Template: `db_credentials.example.php` |
| `cache/`, `eaf/` | here, gitignored | runtime output (`getZinnen.php`), writable by `www-data` |

## Dependencies
- [signlab_signcollect-lib](https://github.com/Amsterdam-Humanities-Labs/signlab_signcollect-lib) at `/web/lib`.
- MySQL `admin_gebarenoverleg`. Schema in `db/schema.sql`, meaning of the tables in `CLAUDE.md`.
- [signlab_annotation-editors](https://github.com/Amsterdam-Humanities-Labs/signlab_annotation-editors) (subBeta8) and signlab_signCollect-v2 (`/menu_beta/batch_add.php`).
- Media: `/web/gebarenoverleg_media/studioFilesMini/{raw,post}/`. Video uploads through `api.php` go to `/web/uploads/`. Signbank export: `/web/signbank_data/glosses_transformed.json`.
- An external segmentation service polls `segment_api.php`. It is not in any of our repos.
- Portal session cookie (`/userProtect.js` and `auth.php`).

## License and citation

Apache License 2.0, copyright University of Amsterdam: see [LICENSE](LICENSE) and
[NOTICE](NOTICE). You may use it, also commercially, as long as you credit
Gomer Otterspeer / University of Amsterdam as the source. To cite it, use
[CITATION.cff](CITATION.cff) (the *Cite this repository* button on GitHub) or the DOI [10.21942/uva.33980359](https://doi.org/10.21942/uva.33980359).

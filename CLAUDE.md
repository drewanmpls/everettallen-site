# Everett Allen Photography site: rules for Claude sessions

## Standing rules

1. Never use em dashes anywhere: site copy, admin labels, code comments, commit messages, docs. Use commas, periods, colons, semicolons or parentheses.
2. Never commit photos, originals, passwords, tokens, keys, or anything from the server's private folder. Font files (WOFF2) and the existing `static/assets/og-default.jpg` are the only binary files allowed.
3. Never invent facts, quotes, dates, prices or contact details. If copy is missing, leave a clearly marked placeholder and say so in the pull request.
4. No frameworks, no build tools, no npm runtime dependencies. Plain HTML, CSS, vanilla JavaScript (ES2017), Python 3 standard library for `build.py` (it must also run on Python 3.9, the version on Andrew's Mac), PHP 8.2+ with PDO SQLite, GD and curl.
5. Make the smallest change that solves the ticket. Match the existing dense code style. Do not reformat lines you are not changing.
6. Before opening the pull request run `python3 build.py` and `php -l` on every changed PHP file. Say in the pull request what could only be tested locally. For admin and client area changes, run `bash dev/run.sh` (fake data, email goes to a log file, nothing is sent) and check the changed screens locally. Public photos are not in Git, so public image tiles look empty locally; that is expected.
7. Do not touch payments, downloads, free codes, privacy wording or model release wording unless the ticket says so. If a ticket seems to require it, stop and ask Andrew.
8. Gallery photo IDs and cover IDs are strings (`"153"`, `"001"`). New or rewritten gallery JSON is indented 4 spaces.
9. Do not turn on GitHub branch protection that requires pull requests on `main`. The admin Publish page, the NAS watcher and Pages CMS commit gallery files straight to `main` through the GitHub API, and that must keep working.
10. Never re-run an old GitHub Actions run (it republishes old content). Use Run workflow.
11. Admin screens follow Section 3.7 (Admin design). Use the admin only at `www.everettallenphotography.com`; the `new.everettallenphotography.com` address has its own separate database.

## Where things are

- Full map, schemas and endpoints: IMPLEMENTATION-PLAN.md, Section 3.
- Public site: build.py reads content/ and writes dist/. Styles and scripts are in static/.
- Client area and admin: static/client/ (PHP + SQLite). Private data lives on the server outside the web root.
- Check before finishing: python3 build.py, and php -l on each changed PHP file.

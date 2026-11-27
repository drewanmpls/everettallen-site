# Everett Allen Photography: Site Evaluation, Design Audit and Remote Execution Blueprint

- **Site:** Everett Allen Photography, https://www.everettallenphotography.com
- **Repo:** `drewanmpls/everettallen-site` (branch `main`), inspected at commit `57a3692` on 2026-11-15
- **Updated 2026-11-27:** the admin rework (Clubhouse look, Sessions structure) is merged into this plan: new Section 3.7, new tickets T-37 to T-46, T-20 and T-31 to T-33 rewritten, roadmap reordered, deploy folder notes corrected (H-03, Section 3.2).
- **Hosting:** DreamHost shared (user `dh_dbvmkc`, host `iad1-shared-b7-19.dreamhost.com`, PHP 8.3, SSH)
- **Purpose and audience:** Portrait and event photography site for Andrew Bertke. Visitors are portrait clients, people looking for themselves in community event galleries, and press or organizations licensing photos.
- **This document is planning only.** No site code was changed to produce it.

## How to run a ticket (read this first)

1. Open a new Claude session on `drewanmpls/everettallen-site` (web or phone). Use the model named on the ticket.
2. Paste: `Open IMPLEMENTATION-PLAN.md. Read "Standing rules" and Section 3. Then do Ticket #T-NN only. Work on a new branch named t-NN-short-name and open a pull request. Do not push to main.`
3. Wait for the green check on the pull request (after T-02 exists), then merge. Merging to `main` deploys in a minute or two.
4. Run the ticket's Done Checks on your phone. If something is wrong, open the merged pull request on GitHub and tap **Revert**, then merge the revert.

One ticket = one branch = one pull request. Never combine tickets.

## Standing rules (apply to every ticket)

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

---

# Section 1: Short Summary and Phased Roadmap

## What exists today

A working, live, non-WordPress site:

- **Public site (static).** `build.py` (zero-dependency Python) reads `content/` and writes `dist/`: home (portraits face), Portraits A to Z, Events (newest, by year, by series), series pages, tag pages, search, one page per gallery, one page per photo (for share previews), Services, Contact, About, Press and licensing, Photo removal, Privacy, 404, sitemap, robots, `.htaccess` (HTTPS redirect, hotlink protection, old WordPress redirects). 86 galleries (59 portrait, 27 event), 4,662 photos, 105 indexed pages plus 4,662 photo pages.
- **Client area (PHP + SQLite)** in `static/client/`: private client galleries with favorites, comments, included picks and paid downloads; Stripe Checkout with a manual backup flow; public photo shop with multi-photo discount; named free-download codes; anonymous photo likes; cookie-free stats; new-client intake form with digital model release, PDF and Google Drive filing.
- **Admin** at `/client/admin/`: Dashboard (new), Galleries (client galleries), Clients, Publish (browser upload), Library (originals), Codes, Short links, Stats, Settings.
- **Local testing:** `bash dev/run.sh` runs the site and admin on a Mac with a fake database (`dev/README.md`).
- **Publishing:** Admin > Publish, or the NAS folder watcher (`nas/watcher.py`), or `scripts/eap-publish.py`. All three write photos to the server and the gallery JSON to GitHub.
- **Content editing:** Pages CMS (`.pages.yml`) commits to `content/`.
- **Deploy:** every push to `main` runs `.github/workflows/deploy.yml` (build, then rsync `dist/` over SSH; adds and updates only) into the `new.everettallenphotography.com` folder, which is the folder the live domain serves. There is no staging copy: a merge is live in about two minutes.

## Important: where this plan differs from the standard template

The blueprint template assumes a flat-file PHP + JSON backend under `site/data/` with a `private-[domain]/config.php`. This site is already live on a different, sound design, with live Stripe orders and signed releases in SQLite. Rebuilding it to fit the template would be all risk and no benefit, so this plan **keeps the current architecture** and applies the template's workflow and hardening standards to it:

| Template standard | What this plan does instead |
|---|---|
| Hand-written static HTML under `site/` | Keep `build.py` generating `dist/` from `content/` |
| JSON data files in `site/data/` with file locks | Keep SQLite at `~/everettallen-private/<host>/data.sqlite`; public content stays as JSON in Git under `content/` |
| `data-seed/` plus `_app/cli/ensure-data.php` | SQLite schema is created and migrated in `lib.php`; T-16 adds a `schema_version` so this runs once, not on every request |
| Secrets in `private-[domain]/config.php` | Secrets stay in the SQLite `settings` table inside the private folder (outside the web root, never in Git), entered through Admin > Settings |
| Dated backups in the private folder | Missing today. Added by T-14 |
| Strict CSP, no inline code | Missing today. Added in stages by T-18 to T-22 |
| `throttle.json` login rate limit | Missing today (only a one second delay). Added by T-15 as a SQLite table |
| Cache-busting on admin CSS/JS | Public assets have it; admin assets do not. Added by T-20 |
| One task = one branch = one pull request | Not how the repo has been worked (direct pushes to `main`). Adopted from T-01 on |
| Deploy excludes `/data/`, `/uploads/`, `/assets/images/` | Not needed: photos and private data are never in `dist/`, and rsync only adds and updates. T-03 adds a narrow, safe cleanup of stale generated pages |

## What is missing or broken (the short list)

1. **Removed photos are not really removed.** There is no admin tool to take down a public photo. Editing the gallery JSON hides it from the gallery page, but the deploy never deletes anything, so `/photo/<gallery>/<id>/`, the two JPEG files and the private original all stay on the server. The Photo removal page promises prompt removal. (T-03, T-26)
2. **No backup of the SQLite database** that holds orders, clients, signed release records, codes and settings. (T-14)
3. **No login throttling** on the admin or on password-protected client galleries. (T-15)
4. **No security headers and no Content-Security-Policy** on the public site; the admin relies on about 130 inline style attributes, 8 inline event handlers and several inline scripts. (T-18 to T-22)
5. **Home page does not say what or where** above the fold, and the booking call to action is at the very bottom. (T-08)
6. **Accessibility gaps:** no visible keyboard focus styles, lightbox does not trap or restore focus, no reduced-motion handling, small tap targets, photo pages have no `h1`. Color contrast passes AA everywhere it was measured. (T-05, T-06, T-07, T-11, T-13)
7. **Mobile:** the Services page scrolls sideways at 320px (the "Just ask a question" button). (T-04)
8. **Performance:** fonts load from Google Fonts (third party, render blocking); the first home images and the Events lead tile are lazy-loaded even though they are the largest visible content. (T-09, T-10)
9. **Contact form** messages are email only; they never reach the dashboard "New inquiries" tile, and there is no rate limit. (T-17)
10. **Pages CMS is still required** for gallery details, series, pages and services. The roadmap decision is to replace it with the custom admin. (T-23 to T-30)
11. **The admin is hard to use on a phone and is not organized around the work.** One row of top links with no current-page mark, no dark mode, wide tables that scroll sideways, a 600-line client gallery page with settings above the photos, and a shoot's details split between the client record and the client gallery. (T-20, T-37, T-38, T-41 to T-45)
12. **Two databases.** `everettallenphotography.com` and `new.everettallenphotography.com` serve the same files, but the private folder is picked by host name, so the admin on `new.` shows a different, separate database. (T-46)

## Phased roadmap

H = something Andrew does by hand. T = a ticket prompt for a remote Claude session. Do phases in order; inside a phase, follow the ticket numbers unless "Depends on" says otherwise.

| Phase | Step | Type | Model | Size | What |
|---|---|---|---|---|---|
| 0. Setup | H-01 | Hand | | | Merge the `implementation-plan` pull request (done) |
| | H-02 | Hand | | | GitHub > Settings > General: turn on "Automatically delete head branches". Do not add branch protection (rule 9) |
| | H-03 | Hand | | | Leave the `DEPLOY_DIR` variable as `new.everettallenphotography.com`. The live domain's web directory in the DreamHost panel is that folder, so changing it would deploy to a folder nobody sees |
| 1. Foundations | T-01 | Task | Haiku | S | Add `CLAUDE.md` with the standing rules |
| | T-02 | Task | Sonnet | S | Pull request check workflow (build, PHP lint, JSON, em dash scan) |
| | T-03 | Task | Sonnet | M | Deploy cleans up stale gallery and photo pages |
| | H-10 | Hand | | | Stripe dashboard > Webhooks: confirm the endpoint address starts with `https://www.everettallenphotography.com` (or the bare domain), not `new.` |
| | T-46 | Task | Sonnet | S | Send the `new.` address to the main domain (needs OK; after H-10) |
| 2. Safety first | T-14 | Task | Sonnet | M | Nightly database backup script |
| | H-04 | Hand | | | DreamHost panel > Cron Jobs: add the nightly backup command from T-14 |
| | T-15 | Task | Sonnet | M | Login throttling (admin and client galleries) |
| | T-16 | Task | Sonnet | M | Database schema version |
| 3. Admin shell | T-20 | Task | Sonnet | L | Admin shell and `admin.css` in the Clubhouse style (menu, light and dark, phone and tablet layouts) |
| | T-37 | Task | Sonnet | L | Restyle the admin pages and remove inline styles |
| | T-38 | Task | Sonnet | M | Settings in sections, hidden secret fields, change password (needs OK) |
| | T-21 | Task | Sonnet | L | Admin and client pages: move inline scripts and handlers to files |
| 4. Portfolio (replaces Pages CMS) | T-23 | Task | Sonnet | M | Shared "save content to GitHub" helper and gallery index |
| | T-24 | Task | Sonnet | M | Public galleries list in the admin, with series chips |
| | T-25 | Task | Sonnet | L | Gallery editor (details, cover picker, hide) |
| | T-26 | Task | Sonnet | L | Reorder photos and remove photos for real |
| | T-39 | Task | Sonnet | M | Publish page: drag and drop, series at upload, live check |
| | T-40 | Task | Sonnet | M | NAS watcher status in the admin (needs OK) |
| | H-11 | Hand | | | Copy the new `nas/watcher.py` to the NAS and restart the `eap-watcher` container |
| | T-27 | Task | Sonnet | M | Event series editor |
| | T-28 | Task | Sonnet | M | Pages and site settings editor |
| | T-29 | Task | Sonnet | M | Services and rates editor |
| | T-30 | Task | Haiku | S | Point the dashboard and docs at the new editors |
| | H-05 | Hand | | | After a week without needing Pages CMS: remove the Pages CMS app from the repo (GitHub > Settings > Integrations) |
| 5. Studio: sessions, clients, sales | T-41 | Task | Sonnet | M | Sessions: client galleries become sessions |
| | T-42 | Task | Sonnet | L | Session page: tabs, share sheet and activity |
| | T-43 | Task | Sonnet | M | Select several photos at once in a session |
| | T-17 | Task | Sonnet | M | Contact form saves an inquiry and is rate limited |
| | T-31 | Task | Sonnet | M | Client list: pipeline chips, search and filters |
| | T-32 | Task | Sonnet | M | Client record: Overview cards and timeline |
| | T-33 | Task | Sonnet | M | Sales: orders page with totals (approved by Andrew 2026-11-15) |
| 6. Today and stats | T-44 | Task | Sonnet | M | Today: the admin home becomes a "Needs you" list |
| | T-45 | Task | Sonnet | S | Stats page restyle |
| 7. Public site fixes and CSP | T-04 | Task | Haiku | S | Fix sideways scroll at 320px |
| | T-05 | Task | Haiku | S | Keyboard focus styles and reduced motion |
| | T-06 | Task | Haiku | S | Photo page heading, copyright year, dead code |
| | T-07 | Task | Sonnet | M | Lightbox accessibility |
| | T-08 | Task | Sonnet | M | Home page: say what and where, booking button up top, About in the menu |
| | T-09 | Task | Haiku | S | Load the first visible images eagerly |
| | T-10 | Task | Sonnet | M | Self-host the fonts |
| | T-11 | Task | Haiku | S | Search page accessibility |
| | T-12 | Task | Haiku | S | Structured data on the home page |
| | T-13 | Task | Sonnet | S | Tap target sizes |
| | T-18 | Task | Sonnet | S | Security headers on the public site |
| | T-19 | Task | Sonnet | M | Public pages: remove inline code, then enforce CSP |
| | T-22 | Task | Sonnet | S | Enforce CSP on `/client/` |
| 8. Email clients | T-34 | Task | Sonnet | M | Outbox queue and sender |
| | H-06 | Hand | | | DreamHost panel > Cron Jobs: add the outbox command from T-34 |
| | T-35 | Task | Sonnet | M | Email templates and "Email this client" |
| 9. Portfolio polish | T-36 | Task | Sonnet | M | Portraits page: sharper tiles and a newest-first view |
| Parked | H-07 | Hand | | | Lawyer review of release wording and of privacy wording for code and download tracking |
| | H-08 | Hand | | | Decide the SES sender domain, limits, bounce and unsubscribe handling with the Leather Websites project |
| | H-09 | Hand | | | Do not delete the `new.everettallenphotography.com` folder: it is the live web folder. Once T-46 has run for a month, the `new.` host name itself can be removed in the DreamHost panel only if the live domain's web directory is moved first |

Why this order: safety items that protect clients and the removal promise come first; the admin shell comes before any new admin screen so every later ticket is built once in the new style; the public site fixes (Phase 7) do not depend on the admin work and can be done in between at any time.

**Tickets marked "needs OK"** change login, the NAS key's permissions, or a host name. Do not start them until Andrew says yes in the session.

**Parked, not ticketed yet (missing facts, do not start):** switching the outbox sender to the shared AWS SES setup (needs H-08), and a newsletter signup (depends on the shared email system's list and unsubscribe design). Write tickets for these once H-08 is decided.

---

# Section 2: Executive Design and UX Audit (top 5 priorities)

Method: read all templates in `build.py`, `static/css/style.css`, the four public scripts and the client area; built the site locally and measured every main page type in a headless browser at 320, 375 and 1280 pixels wide; computed color contrast from the stylesheet values. Limits: photos are not in the repo and Google Fonts was blocked in the test sandbox, so measurements used fallback fonts and empty image boxes. The live site was not load tested.

## Priority 1: A removal request must actually remove the photo (trust and privacy)

The site photographs people at leather and kink community events, and the Photo removal page says "I'll remove the photo promptly." Today that takes a manual JSON edit, and even then the photo page, both JPEG files and the original remain reachable by direct link because the deploy only adds and updates. This is the highest priority because it is a promise to the community, not a polish item. **Fix:** T-03 (deploy removes stale generated pages) and T-26 (one "Remove" button that deletes the web copies, the photo page and the likes, and moves the original out of the sellable library).

## Priority 2: Above the fold, the home page has personality but no plain statement or action

The giant "EVERETT ALLEN / PHOTOGRAPHY" wordmark and the tagline ("There is art in your body. Be sexy, get ugly, it's all beautiful.") are distinctive and should stay. But a first-time visitor is not told this is a Minneapolis portrait and event photographer, and the only booking button ("Contact me") sits at the bottom of the page under the Events band. The menu has Events, Search, Services, Contact but no About, which is where the trust-building story lives. The meta line ("59 portrait sessions / 2012 to 2025 / Scroll") is charming but is doing the job a descriptor should do. **Fix:** T-08 adds one plain descriptor line using copy that already exists on the About page ("Portraits and community events, Minneapolis, Minnesota."), a primary "Book a portrait session" button beside it linking to Services, and About in the menu. T-12 adds matching structured data for search engines.

## Priority 3: Keyboard and screen reader users hit real walls

- **No visible focus style** on links, cards, tiles or buttons (only the search box has one). Hover only changes color.
- **Lightbox** opens as `role="dialog"` but has no accessible name, does not move focus into itself, does not trap focus, and does not return focus to the thumbnail on close. Keyboard users end up tabbing through the page behind it.
- **`html{scroll-behavior:smooth}`** has no `prefers-reduced-motion` override.
- **Photo pages have no `h1`.**
- **Search:** filter chips are `<a href="#">` (should be buttons) and the result count is not announced.
- **Tap targets:** 8 to 17 controls per page are under 24px tall (menu links, footer links, year jump links, tag chips), below the WCAG 2.2 AA minimum.
- **Good news:** contrast passes AA on every pairing measured (body ink 15.0:1, muted text 5.7:1, blue on paper 5.8:1, white on blue 7.3:1, muted on striped rate rows 5.4:1). Skip link, `lang`, landmarks, labelled form fields and image dimensions in galleries are already in place.

**Fix:** T-05, T-06, T-07, T-11, T-13.

## Priority 4: Mobile is close; three specific defects

- **Services at 320px scrolls sideways by 43px** because "Just ask a question" plus its left margin is wider than the screen. Events is 1px over from the year links.
- **The menu wraps to two rows on phones.** It works, but with About added it will be three; T-08 handles this with a tighter phone layout.
- Everything else measured (home, portraits, gallery, photo, contact, search, about, series) fits at 320 and 375 with no overflow. The justified gallery grid, the cart bar and the checkout box behave.

**Fix:** T-04, T-08.

## Priority 5: Speed and Core Web Vitals

- **Fonts:** Archivo and Archivo Narrow come from Google Fonts with a render-blocking stylesheet and two extra connections, on the public site and on every client and admin page. Self-hosted WOFF2 with `font-display:swap` and one preload removes the third party (which also fits the Privacy page's "no tracking services" promise) and lets a strict CSP work.
- **Largest image is lazy-loaded.** On the home page the featured strip is visible without scrolling on desktop, yet every image has `loading="lazy"`. Same for the Events lead tile. That delays the largest contentful paint.
- **Layout shift is already well handled:** gallery thumbnails carry width and height, cards and tiles reserve space with `aspect-ratio`.
- **Portrait tiles** use the 400px thumbnail in a tile up to about 400px wide, so they are soft on high-density phone screens. T-36 addresses this.

**Fix:** T-09, T-10, T-36.

## Smaller findings (ticketed where noted)

- Footer says "Copyright 2026" as fixed text (T-06).
- `site.js` carries dead code for a menu button and a slideshow that no longer exist (T-06).
- `content/facebook-events.json` and `.csv` contain em dashes in imported Facebook text. `build.py` does not read these files, so nothing reaches the site. Leave them alone; T-02's scan skips them.
- The deploy workflow's fallback folder is `new.everettallenphotography.com`. That is correct: it is the folder the live domain serves (H-03, corrected 2026-11-27).
- Contact form errors return a bare text page (T-17).

---

# Section 3: Technical Reference and Data Schemas

## 3.1 Repository layout (as it exists)

```
build.py                      Static site generator. Reads content/, copies static/ to dist/, writes pages.
content/
  site.json                   Site title, tagline, links, contact intro, usage note, featured portraits
  series.json                 Recurring event series
  services.json               Services page plus model release wording
  pages/*.json                About, Press and licensing, Photo removal, Privacy
  galleries/*.json            One file per public gallery (86 files)
  start-here.json             Pages CMS help screen (empty values; labels live in .pages.yml)
  facebook-events.json/.csv   Imported archive, not read by build.py. Do not edit.
static/                       Copied as-is into dist/
  css/style.css               Public stylesheet
  js/site.js                  Stats beacon, share buttons, lightbox
  js/shop.js                  Buying from public galleries, cart, free codes
  js/likes.js                 Photo likes
  js/search.js                Search page
  assets/og-default.jpg       Default share image (the only image in Git)
  contact/send.php            Contact form mailer
  client/                     PHP client area (see 3.5)
    .htaccess                 Friendly URLs, noindex header, blocks .sqlite/.db/.log
    lib.php                   Shared helpers, SQLite schema, sessions, CSRF, images, release PDF, Drive
    index.php                 Client gallery page          client.css / client.js   its assets
    api.php                   Client gallery JSON endpoints
    img.php  dl.php           Watermarked previews / unlocked downloads
    new.php                   New client intake form and model release
    shop.php                  Public gallery checkout
    payments.php              Stripe Checkout and manual fallback
    stripe-webhook.php        Stripe webhook
    likes.php  hit.php        Photo likes / stats counter
    pdf.php                   Minimal PDF writer
    admin/index.php           Admin: auth, client galleries, clients, codes, stats, settings
    admin/dashboard.php       Admin home
    admin/publish.php         Browser publish (also used by the NAS watcher)
    admin/library.php         Download originals
    admin/links.php           Short links
dev/                          Local test copy: run.sh, router.php, seed.php (fake data), README.md. Never deployed
nas/                          NAS folder watcher (runs on the NAS from its own copy)
scripts/                      eap-publish.py (Mac publish), sync-series.py, drive-release-receiver.gs, one-time import scripts
.pages.yml                    Pages CMS configuration
.github/workflows/deploy.yml        Build and rsync on push to main
.github/workflows/sync-series.yml   Keeps the Series dropdown in .pages.yml in sync
```

`dist/`, `photos/`, `__pycache__/`, `.env` and `dev/.local/` are git-ignored.

## 3.2 Server layout (DreamHost, user `dh_dbvmkc`)

```
~/new.everettallenphotography.com/        Web root for the live domain (the DEPLOY_DIR variable). Everything from dist/, plus:
    photos/<slug>/<id>-t.jpg              400px thumbnail            (server only, never in Git)
    photos/<slug>/<id>-l.jpg              1000px with corner logo    (server only)
    assets/logo-white.png, icon.png       (server only)
~/everettallen-private/
    masters/<slug>/<id>.jpg               Full-size originals for public galleries (shared by all host names)
    masters/<slug>/labels.json            {"title": "...", "files": {"<id>": "<original file name>"}}
    masters/<slug>/.lock                  Upload lock file
    everettallenphotography.com/          Private folder for the live host (host name without www)
        data.sqlite                       The database
        setup-code.txt                    Exists only before the admin password is first set
        galleries/<id>/orig|display|thumb|web|sdisplay|sthumb/   Client gallery files
        releases/<clientId>.pdf, <clientId>-model.jpg, <clientId>-id.jpg, signatures
        backups/                          NEW in T-14
    new.everettallenphotography.com/      Separate private folder used when the site is opened at the new. address. Leave it; T-46 stops it being used
    masters/_removed/<slug>/<id>.jpg      NEW in T-26
```

The private folder is found as `DOCUMENT_ROOT/../everettallen-private/<host>` (or the `EA_PRIVATE` environment variable for local tests). The old WordPress folder is still on the server as a rollback; no ticket touches it.

## 3.3 Content JSON schemas (in Git under `content/`)

None of these files carries a `schema_version` field. Treat that as version 1. **Do not add a version field:** four writers (Pages CMS, `publish.php`, `nas/watcher.py` through `publish.php`, `scripts/eap-publish.py`) produce these files and all would need changing. New optional fields must be safe when absent.

### `content/galleries/<slug>.json`

File name is the slug: lowercase letters, digits and single hyphens, `^[a-z0-9]+(-[a-z0-9]+)*$`, 120 characters max.

| Field | Type | Required | Rules |
|---|---|---|---|
| `title` | string | yes | 1 to 200 characters, plain text. Event titles keep the year ("2026 TCLF Friday") |
| `category` | string | yes | `"portraits"` or `"events"` |
| `year` | string | yes | 4 digits. Groups the Events page |
| `date` | string | yes | `YYYY-MM-DD` |
| `cover` | string | yes | Must equal the `id` of one entry in `images`. Drives tiles and share previews |
| `description` | string | no | Plain text, blank line = new paragraph. Only `<strong>` and `<em>` survive. 2,000 characters max |
| `series` | string | no | A `slug` from `content/series.json`, or absent |
| `tags` | array of strings | no | Each 1 to 40 characters, 12 tags max |
| `hidden` | boolean | no | **NEW in T-25.** `true` leaves the gallery out of the built site. Absent means shown |
| `images` | array | yes | At least 1 entry. Order is display order |
| `images[].id` | string | yes | `^\d{3,6}$` (for example `"001"`). Must be a string |
| `images[].ratio` | number | yes | Width divided by height, 4 decimals, greater than 0.1 and less than 10 |

### `content/series.json`

`{"series": [ {slug, name, short, desc} ]}`. `slug`: same pattern as gallery slugs, 40 max, never changed once galleries use it. `name`: 1 to 120. `short`: 1 to 40 (shown on chips). `desc`: 1 to 300, plain text.

### `content/site.json`

| Field | Type | Rules |
|---|---|---|
| `title` | string | Site name, 80 max |
| `tagline` | string | 200 max. Split into lines at sentence ends on the home page |
| `base_url` | string | `https://www.everettallenphotography.com`, no trailing slash. Do not change |
| `facebook`, `instagram` | string | Full `https://` addresses |
| `hero_count` | number | Unused leftover. Leave it |
| `contact_intro` | string | 600 max |
| `usage_note` | string | 400 max |
| `featured_portraits` | array of strings | Gallery slugs, in order. Unknown slugs are skipped |
| `home_descriptor` | string | **NEW in T-08.** 120 max |

### `content/pages/<slug>.json`

`title` (string, 80 max, required), `lede` (string, 200 max, required), `body` (string, required; blank line = paragraph; `**bold**`, `*italic*`, `[text](/internal-path/)`, and blocks where every line starts `1. ` become numbered lists), `desc` (string, 160 max, optional), `nav_label` (string, optional, unused by the build). The file name becomes the address (`about.json` is `/about/`). The footer links are fixed text in `build.py`, so adding a page file does not add a link.

### `content/services.json`

`title`, `lede`, `delivery`, `callout`, `cta` (strings); `cards[]` with `title`, `price` (display text such as `"$175"`), `unit`, `meta`, `badge` (strings), `wide` (boolean, unused), `items` (array of strings), `included` (integer: free picks pre-filled when a client gallery is created); `share_discount_percent` (integer 0 to 100), `share_note` (string, `{percent}` placeholder); and the model release fields `release_title`, `release_version`, `release_terms` (array of strings), `release_definitions` (array of `{term, text}`), `release_gallery_question`, `release_consideration`. **The release fields are legal wording. Any change must also change `release_version`.**

## 3.4 SQLite database (`data.sqlite`, created by `db()` in `lib.php`)

Tables today: `settings (k, v)`; `galleries` (client and order galleries: `token`, `title`, `client_name`, `client_email`, `password_hash`, `default_price_cents`, `default_web_cents`, `included_count`, `note`, `active`, `client_id`, `code_id`, `kind` where `'shop'` marks an order gallery, `created`); `images`; `favorites`; `comments`; `claims`; `orders` (`gallery_id`, `image_id`, `email`, `status` = `requested`, `paid` or `declined`, `provider_ref`, `tier` = `web` or `full`, `created`); `downloads`; `clients` (intake and release fields, `status` = `new`, `contacted`, `booked`, `shot`, `delivered`, `archived`; `source`; `ip_hash`); `codes`; `photo_likes`; `like_rate`; `stat_hits`, `stat_days`, `stat_seen`.

Settings keys in use: `admin_hash`, `nas_token_hash`, `github_token`, `github_repo`, `stripe_enabled`, `stripe_secret_key`, `stripe_webhook_secret`, `manual_pay_text`, `shop_enabled`, `shop_price_web_cents`, `shop_price_full_cents`, `shop_discount_min`, `shop_discount_pct`, `shop_code_fails`, `drive_webhook_url`, `drive_webhook_secret`, `stats_salt`.

Planned additions (each owned by one ticket):

| Addition | Ticket | Shape |
|---|---|---|
| `settings.schema_version` | T-16 | Integer as text. Migrations run only when it is lower than the number in code |
| `login_fails` | T-15 | `(scope TEXT, h TEXT, t INTEGER)` with an index on `(scope, h, t)` |
| `orders.price_cents` | T-33 | `INTEGER` nullable. Null means "not recorded" (orders made before T-33) |
| `outbox` | T-34 | `(id INTEGER PRIMARY KEY AUTOINCREMENT, client_id INTEGER, to_email TEXT NOT NULL, subject TEXT NOT NULL, body TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'queued', attempts INTEGER NOT NULL DEFAULT 0, last_error TEXT DEFAULT '', created INTEGER NOT NULL, sent INTEGER)`; `status` = `queued`, `sent`, `failed` |
| `email_templates` | T-35 | `(k TEXT PRIMARY KEY, name TEXT NOT NULL, subject TEXT NOT NULL, body TEXT NOT NULL)` |
| `clients.source = 'contact'` | T-17 | New value in the existing column, no schema change |
| `galleries.shoot_date`, `galleries.shoot_location` | T-41 | `TEXT DEFAULT ''`. Shoot date is YYYY-MM-DD. Empty means use the linked client's values |
| `settings.nas_status` | T-40 | JSON text: `{t, version, folders:[{name, state, photos, error}]}` |

## 3.5 Roles and endpoints

| Role | How it is proven | Can do |
|---|---|---|
| Public | Nothing | Read the static site; `likes.php`; `hit.php`; `shop.php`; `contact/send.php`; `client/new/` |
| Client | 24-hex gallery token in the link, plus the gallery password if one is set (session flag) | View one gallery, favorite, comment, claim picks, request or buy, download what is unlocked |
| NAS watcher | `X-EA-Token` header; its SHA-256 must equal `settings.nas_token_hash` | Only `pubfile` and `pubdone` (plus `nasstatus` after T-40) |
| Admin (Andrew, the only admin) | Password checked against `settings.admin_hash`; session cookie `eapc` (path `/client`, HttpOnly, SameSite Lax, Secure) | Everything under `/client/admin/` |

Every admin POST requires the CSRF token (`csrf` field or `X-CSRF` header, compared with `hash_equals`). There is one admin role; do not add users or roles.

**Existing admin actions** (`/client/admin/?a=<action>`): views `dashboard`, `list`, `gallery`, `clients`, `client`, `publish`, `library`, `codes`, `stats`, `settings`; files `thumb`, `releasepdf`, `releasephoto`, `libfile`, `libzip`, `codescsv`; POST actions in `index.php` (`create`, `update`, `upload`, client and code saves, settings saves, deletes), plus `pubfile` (one JPEG: `slug`, `title`, file `photo`; returns `{ok, id, ratio, name}`) and `pubdone` (`payload` JSON `{slug, title, date, category, images:[{id, ratio}]}`; returns `{ok, url, count, created}`).

**Existing public and client endpoints:** `api.php?t=<token>&a=comments|fav|comment|claim|unclaim|request|done`; `img.php?t=&i=&s=thumb|display`; `dl.php?t=&i=&size=web|full`; `shop.php?a=info|checkout`; `likes.php` (GET `g`, POST `a=like g id on`); `hit.php` (POST JSON `{p, r, w}`); `stripe-webhook.php`.

**New admin actions added by this plan** (all admin only, POSTs need CSRF; JSON actions return `{ok:true,...}` or `{error:"plain sentence"}` with a 4xx status):

| Action | Method | Ticket | Purpose |
|---|---|---|---|
| `pgalleries` | GET | T-24 | List of public galleries |
| `pgallery&slug=` | GET | T-25 | Edit one public gallery |
| `pgsave` | POST | T-25 | Save gallery details. Fields: `slug`, `title`, `date`, `year`, `category`, `series`, `tags` (one per line), `description`, `cover`, `hidden` |
| `pgorder` | POST | T-26 | Save photo order. Fields: `slug`, `order` (comma-separated ids; must be exactly the current set) |
| `pgremove` | POST | T-26 | Remove photos. Fields: `slug`, `ids` (comma-separated), `confirm=REMOVE` |
| `series`, `seriessave` | GET, POST | T-27 | Edit `content/series.json` |
| `pages`, `pagesave`, `sitesave` | GET, POST | T-28 | Edit `content/pages/*.json` and `content/site.json` |
| `services`, `servicessave` | GET, POST | T-29 | Edit `content/services.json` |
| `orders`, `orderscsv` | GET | T-33 | Orders list with totals, CSV export |
| `outbox`, `mailsend`, `mailretry` | GET, POST | T-34, T-35 | Queue, send and retry client email |
| `templates`, `templatesave` | GET, POST | T-35 | Edit email templates |
| `settings&tab=`, `pwchange` | GET, POST | T-38 | Settings tabs; change the admin password |
| `nasstatus` | POST (NAS key) | T-40 | Watcher status report |
| `sessions` | GET | T-41 | Sessions list (`list` redirects here) |
| `gallery&id=&tab=` | GET | T-42 | Session page tabs: `photos`, `activity`, `settings` |
| `bulkphotos` | POST | T-43 | Prices or delete for several photos. Fields: `id`, `images`, `do`, `price` |

## 3.6 Target security headers (reached with T-22)

Public site (`dist/.htaccess`, written by `build.py`):

```
Header always set X-Content-Type-Options "nosniff"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set X-Frame-Options "DENY"
Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
Header always set Strict-Transport-Security "max-age=15552000"
Header always set Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'none'; object-src 'none'"
```

`/client/` (`static/client/.htaccess`): the same, and it keeps `X-Robots-Tag "noindex, nofollow, noarchive"`. Stripe Checkout is a full-page redirect, so it needs no CSP allowance.

## 3.7 Admin design (Clubhouse style, photographer structure)

Added 2026-11-27. The admin takes its look from the Clubhouse admin Andrew uses on his club sites (reference: `docs/clubhouse.html` and `public/admin/admin.css` in the private `community-admin` repo), but its structure is built around a photography business. The styles are **copied** into `static/client/admin/admin.css`, not shared: this repo never loads files from `community-admin`. The top of `admin.css` says which `community-admin` commit the tokens came from, so later Clubhouse changes can be copied over on purpose.

**Menu (in this order).** Section labels are small grey capitals and are not links.

| Label | Action | Built by |
|---|---|---|
| Today | `dashboard` | exists; reworked in T-44 |
| *Studio* | | |
| Clients | `clients` | exists; T-31, T-32 |
| Sessions | `sessions` (client galleries) | T-41 (until then: "Client galleries", `list`) |
| Sales | `orders` | T-33 |
| Codes | `codes` | exists |
| *Portfolio* | | |
| Galleries | `pgalleries` | T-24 |
| Publish | `publish` | exists; T-39, T-40 |
| Event series | `series` | T-27 |
| Library | `library` | exists |
| *Website* | | |
| Pages | `pages` | T-28 |
| Services | `services` | T-29 |
| Short links | `links` | exists |
| Stats | `stats` | exists; T-45 |
| Settings | `settings` | exists; T-38 |

On phones the bottom tab bar shows Today, Clients, Sessions, Portfolio (opens Galleries) and More (the full menu).

**Shell.** Sidebar (240px) at 1024px and wider. Below 1024px a one-row top bar with a Menu button (`aria-expanded`; when closed its links cannot be reached; Escape closes it). Under 640px also the bottom tab bar, with page content padded so nothing hides behind it. The top bar always shows "Everett Allen" and a View site link; Log out sits at the bottom of the menu. Landmarks `<header>`, `<nav>`, `<main>` and a skip link.

**Color.** Every color is a CSS variable; no view sets its own colors. Light: warm grey page `#f2f2ef`, white panels, ink `#1c1d1c`, muted `#6e716d`, lines `#ebebe7`. Dark: near-black page `#0e0f0e`, panels `#1c1d1b`, ink `#f1f2f0`, muted `#9a9e98`, lines `#2a2c29`. Accent is EAP blue `#2b2bff` in light and a lighter blue (about `#8c8cff`, checked to 4.5:1 on the dark panel) in dark. Status colors (green paid or done, amber waiting, red failed or declined, blue information) each have a soft background tint. Behind photos use neutral grey, never a tinted color, so photos are judged correctly in both modes. Mode: Auto (device), Light or Dark, saved per browser (T-20).

**Type and spacing.** System font stack (SF on Apple devices). 15px body, 13px small text, page titles 28px (22px on phones). 8px spacing scale; content max width 1120px; 28px side padding (14px on phones). Panels have 12px corners.

**Page layout.** Back link (when inside a list), title, status tag, one main action on the right; then a message (only when there is one), tabs (only when the page has views), a toolbar (lists) or form sections, then content. Long forms end with a save bar that stays on screen.

**Components.** Buttons (main, secondary, danger; at least 44px tall), fields with visible labels above, status tags with words (never color alone), chips for filters, cards, tables that become stacked cards under 640px, stat tiles, a "Needs you" list (row with count and arrow), photo tiles (fixed ratio box, lazy-loaded, marks for favorite, picked, comment, sold), confirm steps for anything that deletes, unlocks or emails.

**Words.** Plain and short. Buttons say what happens ("Save settings", "Mark paid and send download"). Messages: "Saved.", "Couldn't save: <reason>." No em dashes. Dates like "Nov 27, 2026" and times like "3:05 pm", America/Chicago.

**Phones and tablets.** Every tap target 44px or more with 8px between neighbors. No page scrolls sideways at 390px. Uploads accept the photo library on iPad and iPhone. Nothing depends on hover.

**Accessibility.** WCAG 2.1 AA: text contrast 4.5:1, control edges 3:1, focus always visible, one `h1` per page, messages announced (`role="status"` or `role="alert"`), every field labeled, everything works by keyboard, motion respects reduced motion.

**No inline code.** Nothing written for the admin from T-20 on uses `style=""`, `<style>`, inline `<script>` with code, or `on...=` handlers, so the strict CSP in T-22 can be turned on without rework.

**Testing.** `bash dev/run.sh` runs the site and admin locally with fake data (see `dev/README.md`). Check each changed screen at 390px, 768px and 1280px, in light and dark, and with the keyboard alone, before opening the pull request.

---

# Section 4: Self-Contained Execution Tickets

## Phase 1: Foundations

### Ticket #T-01: Add CLAUDE.md so every session loads the standing rules
* **Assigned Model:** Haiku
* **Size:** S
* **Target File(s):** `CLAUDE.md` (new, repo root)
* **The Problem / Goal:** Remote sessions start with no memory. A `CLAUDE.md` makes the rules load automatically so tickets cannot drift from them.
* **Exact Instructions:**
  1. Create `CLAUDE.md` with a title line `# Everett Allen Photography site: rules for Claude sessions`.
  2. Under a heading `## Standing rules`, copy the eleven numbered rules from the "Standing rules" section of `IMPLEMENTATION-PLAN.md` word for word.
  3. Under a heading `## Where things are`, add exactly these lines: `- Full map, schemas and endpoints: IMPLEMENTATION-PLAN.md, Section 3.` / `- Public site: build.py reads content/ and writes dist/. Styles and scripts are in static/.` / `- Client area and admin: static/client/ (PHP + SQLite). Private data lives on the server outside the web root.` / `- Check before finishing: python3 build.py, and php -l on each changed PHP file.`
  4. Do not add anything else.
* **Guardrails (Do Not Touch):** Every other file. `README.md` stays as is.
* **Verification Checklist (Done Checks):**
  - [ ] On GitHub mobile, `CLAUDE.md` shows at the repo root with eleven rules and no em dashes.
  - [ ] The live site is unchanged.
* **Undo:** Revert the pull request.

### Ticket #T-02: Add a pull request check (build, PHP lint, JSON, em dash scan)
* **Assigned Model:** Sonnet
* **Size:** S
* **Target File(s):** `.github/workflows/check.yml` (new)
* **The Problem / Goal:** Nothing verifies a pull request before it is merged and deployed. A green or red mark makes phone review safe.
* **Exact Instructions:**
  1. New workflow named `Check`, triggered on `pull_request` only. One job on `ubuntu-latest`, `permissions: contents: read`.
  2. Steps: checkout (`actions/checkout@v4`); `python3 build.py`; fail unless `dist/index.html`, `dist/sitemap.xml` and `dist/.htaccess` exist.
  3. PHP lint: run `php -l` on every `*.php` under `static/` and fail on the first error (the runner has PHP preinstalled; do not add a setup action).
  4. JSON check: `python3 -c` loop that loads every `content/**/*.json` and fails with the file name on a parse error. Also fail if any `content/galleries/*.json` has a `cover` or an `images[].id` that is not a string, or a `cover` not present in `images`.
  5. Em dash scan: fail if the character U+2014 appears in any tracked file except `content/facebook-events.json` and `content/facebook-events.csv`. Print the file and line.
  6. Do not deploy, do not upload artifacts, do not use secrets.
* **Guardrails (Do Not Touch):** `deploy.yml`, `sync-series.yml`. No branch protection settings.
* **Verification Checklist (Done Checks):**
  - [ ] The pull request for this ticket shows a green "Check" mark.
  - [ ] After merging, the Actions tab shows the normal "Deploy to DreamHost" run succeeded.
* **Undo:** Revert the pull request.

### Ticket #T-03: Make the deploy remove stale gallery, photo, tag and series pages
* **Assigned Model:** Sonnet
* **Size:** M
* **Target File(s):** `.github/workflows/deploy.yml`
* **The Problem / Goal:** rsync only adds and updates, so a page for a removed photo or gallery stays live forever. Removal requests need the page gone.
* **Exact Instructions:**
  1. Keep the existing "Upload" step exactly as is.
  2. Add a step before the SSH setup: `php -l` on every `*.php` under `static/`; fail the deploy on an error.
  3. Add a step after "Upload" named `Remove stale generated pages`. Safety gate first: count the directories in `dist/gallery/`; if fewer than 50, print a warning and skip the step (a broken build must never wipe the site).
  4. For each of `gallery`, `photo`, `tag`, `series`: run `rsync -a --delete -e "ssh -i ~/.ssh/id_deploy -o IdentitiesOnly=yes" dist/<dir>/ "dh_dbvmkc@iad1-shared-b7-19.dreamhost.com:~/${DEPLOY_DIR}/<dir>/"` with `-v` and `--itemize-changes` so deletions show in the log. These four folders contain only files generated by `build.py`.
  5. Never use `--delete` on the web root or on any other folder.
  6. Update the README sentence "adds and updates files only, never deletes" to say that stale pages under `gallery/`, `photo/`, `tag/` and `series/` are removed, and nothing else is ever deleted.
* **Guardrails (Do Not Touch):** `photos/`, `assets/`, `client/`, the private folder, the `DEPLOY_DIR` default, the `concurrency` setting.
* **Verification Checklist (Done Checks):**
  - [ ] After merging, the deploy run is green and its log shows the new step.
  - [ ] Home, Events, one gallery and one photo page still load on the live site.
  - [ ] Photos still show in a gallery (proves `photos/` was not touched).
* **Undo:** Revert the pull request. Nothing deleted by this step is lost for good: the next build regenerates any page that should exist.

### Ticket #T-46: Send the new. address to the main domain
* **Assigned Model:** Sonnet
* **Size:** S
* **Needs Andrew's OK before starting:** yes. First do H-10 (confirm the Stripe webhook address uses the main domain), because a redirect would break webhooks sent to `new.`.
* **Target File(s):** `build.py` (the generated root `.htaccess`)
* **The Problem / Goal:** `everettallenphotography.com` and `new.everettallenphotography.com` serve the same folder, but the private folder is chosen by host name (`host_key()` in `lib.php`), so the admin and client links on `new.` use a second, separate database. Logging in there shows different clients, galleries and settings.
* **Exact Instructions:**
  1. In the generated root `.htaccess`, before the existing HTTPS redirect, add a 301 redirect for any request whose host is `new.everettallenphotography.com` to the same path and query on `https://www.everettallenphotography.com`.
  2. Do not change `host_key()`, `priv_dir()` or anything in `static/client/`.
* **Guardrails (Do Not Touch):** The private folders on the server (the `new.everettallenphotography.com` private folder stays as it is), the HTTPS and old WordPress redirects, the deploy workflow.
* **Verification Checklist (Done Checks):**
  - [ ] `https://new.everettallenphotography.com/events/` lands on `https://www.everettallenphotography.com/events/`.
  - [ ] `https://new.everettallenphotography.com/client/admin/` lands on the main domain admin.
  - [ ] A Stripe test event (Stripe dashboard > Webhooks > Send test event) still shows a 200 response.
* **Undo:** Revert the pull request.

## Phase 2: Safety first

### Ticket #T-14: Nightly database backup script
* **Assigned Model:** Sonnet
* **Size:** M
* **Target File(s):** `static/client/cli/backup.php` (new), `static/client/cli/.htaccess` (new), `README.md`
* **The Problem / Goal:** Orders, clients, signed release records, codes and settings live in one SQLite file with no backup.
* **Exact Instructions:**
  1. `cli/.htaccess`: `Require all denied`.
  2. `backup.php`: first line of logic `if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }`. It takes the private folder path as its first argument (for example `/home/dh_dbvmkc/everettallen-private/everettallenphotography.com`); exit with a message if `data.sqlite` is not there. Do not `require lib.php` (it depends on web variables).
  3. Open the database with PDO and run `VACUUM INTO '<private>/backups/data-YYYY-MM-DD.sqlite'` (create `backups/` with mode 0700; if today's file exists, delete it first). Set the file mode to 0600.
  4. Keep the newest 30 daily files plus the first file of each month for 12 months; delete older ones. Only ever delete files matching `data-\d{4}-\d{2}-\d{2}\.sqlite` inside `backups/`.
  5. Print one line: file name and size. Exit non-zero on failure.
  6. README: add a "Backups" section with the cron command for H-04: `php8.3 /home/dh_dbvmkc/new.everettallenphotography.com/client/cli/backup.php /home/dh_dbvmkc/everettallen-private/everettallenphotography.com`, daily, and a note that the PHP binary name should be confirmed in the DreamHost panel.
  7. Test locally with a throwaway SQLite file.
* **Guardrails (Do Not Touch):** `lib.php`, the live database schema. Photos and originals are not part of this backup (they are large and already on the NAS).
* **Verification Checklist (Done Checks):**
  - [ ] Visiting `https://www.everettallenphotography.com/client/cli/backup.php` in a browser shows an error page, not output.
  - [ ] After H-04, the next morning the cron email (or an SSH `ls` of `backups/`) shows a dated file.
* **Undo:** Revert the pull request and delete the cron job in the DreamHost panel.

### Ticket #T-15: Throttle password guessing (admin login and client gallery passwords)
* **Assigned Model:** Sonnet
* **Size:** M
* **Target File(s):** `static/client/lib.php`, `static/client/admin/index.php`, `static/client/index.php`
* **The Problem / Goal:** A wrong password only costs a one second delay; nothing stops unlimited guessing.
* **Exact Instructions:**
  1. `lib.php`: add table `login_fails (scope TEXT NOT NULL, h TEXT NOT NULL, t INTEGER NOT NULL)` with an index on `(scope, h, t)`. Add `throttle_blocked(string $scope): bool`, `throttle_fail(string $scope): void`, `throttle_clear(string $scope): void`. The visitor key `h` is `hash('sha256', 'thr|' . stats_salt . '|' . REMOTE_ADDR)` (never store the address).
  2. Limits: blocked when the same visitor has 5 failures in the last 15 minutes for that scope, or when a scope has 30 failures from anyone in the last 15 minutes. Delete rows older than one day on each failure.
  3. Admin login: scope `admin`. Check before `password_verify`; when blocked show "Too many tries. Wait 15 minutes and try again." and do not check the password. Clear on success. Apply the same to the first-time setup code form (scope `setup`).
  4. Client gallery password: scope `g<id>`, same behavior, message "Too many tries. Please wait 15 minutes."
  5. Keep the existing `usleep` delays.
* **Guardrails (Do Not Touch):** The NAS token check, session and CSRF functions, `password_hash` storage, `shop_find_code` (it has its own limiter).
* **Verification Checklist (Done Checks):**
  - [ ] In a private browser window, enter a wrong admin password 5 times: the sixth try shows the "Too many tries" message.
  - [ ] Your already signed-in browser still works. After 15 minutes the private window can log in with the right password.
* **Undo:** Revert the pull request. If you are locked out before then, wait 15 minutes.

### Ticket #T-16: Give the database a schema version
* **Assigned Model:** Sonnet
* **Size:** M
* **Target File(s):** `static/client/lib.php`
* **The Problem / Goal:** `db()` runs every `CREATE TABLE IF NOT EXISTS` and about 24 `PRAGMA table_info` checks on every request, including each like and page-view beacon.
* **Exact Instructions:**
  1. Add `const SCHEMA_VERSION = 1;` (use the next number if T-15 already merged; one number per schema change from now on).
  2. In `db()`: after connecting, read `settings.schema_version` inside a try/catch (the table may not exist). If it equals `SCHEMA_VERSION`, skip all schema work. Otherwise run the existing create and add-column code unchanged, then store the version.
  3. Add a comment (no em dashes) telling future tickets: add the table or column to the migration block and raise `SCHEMA_VERSION` by one.
  4. Test locally: fresh database gets all tables; an existing database missing a column gains it once.
* **Guardrails (Do Not Touch):** Table and column definitions, `PRAGMA foreign_keys = ON` (must still run on every connection).
* **Verification Checklist (Done Checks):**
  - [ ] Admin dashboard, Stats and a client gallery link all load.
  - [ ] Liking a photo on a public gallery still works.
* **Undo:** Revert the pull request (the stored version number is harmless).

## Phase 3: Admin shell

### Ticket #T-20: Admin shell and admin.css in the Clubhouse style
* **Assigned Model:** Sonnet
* **Size:** L
* **Target File(s):** `static/client/admin/admin.css` (new), `static/client/admin/admin.js` (new), `static/client/admin/index.php` (`ahead()` and `afoot()` only), `static/client/admin/dashboard.php` (its `<style>` block only), `static/client/lib.php` (one helper)
* **The Problem / Goal:** The admin borrows the public site's look (beige, uppercase Archivo), has a single row of top links with no current-page mark, no dark mode, and wraps badly on phones. This ticket builds the new frame every admin page sits in, following Section 3.7. Page contents stay as they are; T-37 restyles them.
* **Exact Instructions:**
  1. Create `admin.css` with the tokens, shell and components from Section 3.7 (colors as variables, light, dark and auto, EAP blue accent). Move the `<style>` block from `ahead()` and the one in `dashboard.php` into it, adjusted to use the variables.
  2. Add `asset(string $path): string` to `lib.php`: the path plus `?v=` and the first 8 characters of `md5_file` for a file under the document root. Use it for every admin CSS and JS file.
  3. Rewrite `ahead()` and `afoot()` to output the shell from Section 3.7: skip link, `<header>` top bar, `<nav>` menu with the exact menu from 3.7 (items whose ticket is not built yet are left out; the menu is one PHP array so later tickets add one line), `<main>`. Mark the current page with `aria-current="page"`. Keep the existing `<title>` format.
  4. Sidebar at 1024px and wider; a menu button with `aria-expanded` that opens the same menu as a panel below 1024px; a bottom tab bar under 640px with Today, Clients, Sessions, Portfolio and More (More opens the full menu).
  5. Appearance: Auto (follows the device), Light, Dark. Stored in `localStorage` key `eaTheme` and applied by `admin.js` from a `data-mode` attribute on `<html>`. With no JavaScript the page follows the device.
  6. Move the `localStorage.setItem("eaSkip","1")` line from `ahead()` into `admin.js`. No inline script or style in anything this ticket writes.
  7. System font stack inside the admin. Stop loading Google Fonts in `ahead()`. The login and setup screens use the same shell without the menu.
* **Guardrails (Do Not Touch):** PHP logic, form field names, action names, the bodies of the views (T-37), `client.css` rules used by client pages, `publish.php` actions `pubfile` and `pubdone`.
* **Verification Checklist (Done Checks):**
  - [ ] Locally (`bash dev/run.sh`) every admin page opens in the new frame at 390px, 768px and 1280px wide, in light and dark, with no sideways scrolling.
  - [ ] The menu opens and closes with the keyboard; Escape closes it; the current page is marked.
  - [ ] On the live site: Dashboard, one client gallery, Clients, Publish and Settings open and work on a phone and a computer.
* **Undo:** Revert the pull request.

### Ticket #T-37: Restyle the admin pages and remove inline styles
* **Assigned Model:** Sonnet
* **Size:** L
* **Depends on:** T-20
* **Target File(s):** `static/client/admin/admin.css`, `static/client/admin/index.php`, `dashboard.php`, `publish.php`, `library.php`, `links.php`
* **The Problem / Goal:** The admin has about 130 `style=""` attributes, which block a strict CSP, and the page bodies still use the old look. This is the rest of the old T-20 goal, done with the Section 3.7 components.
* **Exact Instructions:**
  1. Replace every `style=""` attribute in the listed files with classes from `admin.css`. Use the components in 3.7 (page header, cards, tags, toolbars, tables that become cards on phones, form sections, messages) and a few utility classes. Do not create one class per element.
  2. Page header on every view: back link where the page sits inside a list, title, status tag where the thing has a status, one main action on the right.
  3. "Saved." and error lines become the message component with `role="status"` or `role="alert"`.
  4. Wide tables (client galleries, clients, orders, stats tables) become stacked cards under 640px.
  5. Finish with a search: no `style="` and no `<style` left in `static/client/admin/`.
* **Guardrails (Do Not Touch):** PHP logic, form field names, action names, inline scripts and handlers (T-21), the order of sections on each page (later tickets change those).
* **Verification Checklist (Done Checks):**
  - [ ] Locally, every admin page looks right at 390px, 768px and 1280px in light and dark, and keyboard focus is always visible.
  - [ ] On the live site, saving a setting and creating a code still work.
* **Undo:** Revert the pull request.

### Ticket #T-38: Settings in sections, hidden secret fields, change password
* **Assigned Model:** Sonnet
* **Size:** M
* **Depends on:** T-37
* **Needs Andrew's OK before starting:** yes, because "Change password" touches the admin login.
* **Target File(s):** `static/client/admin/index.php` (the `settings` view and one new POST action)
* **The Problem / Goal:** Settings is one long page mixing Google Drive, GitHub, the NAS key, Stripe and shop prices. Secret fields are plain text boxes, so keys show on screen while typing. There is no way to change the admin password.
* **Exact Instructions:**
  1. Split the view into tabs through `?a=settings&tab=`: Payments (Stripe and manual payment text), Selling (shop prices and discount), Publishing (GitHub token and repo, NAS key), Google Drive, Account (change password, appearance). Default tab: Payments. The existing POST actions and field names stay the same; each form only moves to its tab.
  2. Secret inputs (`stripe_secret_key`, `stripe_webhook_secret`, `github_token`, `drive_webhook_secret`) become `type="password"` with `autocomplete="off"` and a "Show" toggle in `admin.js`.
  3. New POST `pwchange` on the Account tab: current password, new password (10 or more characters), new password again. Check the current password with `password_verify` (and the T-15 throttle if merged), then save with `password_hash`, call `session_regenerate_id(true)`, and show "Password changed."
  4. The Appearance control (Auto, Light, Dark) from T-20 also shows on this tab.
* **Guardrails (Do Not Touch):** Setting key names, how secrets are stored, the existing save actions' behavior, the NAS key format.
* **Verification Checklist (Done Checks):**
  - [ ] Locally: each tab saves; secrets show dots until "Show" is pressed; a wrong current password is refused; a good change logs in with the new password afterwards.
  - [ ] Live: open each tab and confirm saved values are still there. Do not change the live password as a test unless you mean to.
* **Undo:** Revert the pull request. A password changed with it stays changed.

### Ticket #T-21: Admin and client pages: move inline scripts and handlers into files
* **Assigned Model:** Sonnet
* **Size:** L
* **Depends on:** T-20
* **Target File(s):** `static/client/admin/admin.js` (new), `static/client/admin/index.php`, `publish.php`, `static/client/index.php`, `static/client/new.php`, `static/client/new.js` (new), `static/client/client.js`
* **The Problem / Goal:** Inline scripts and `onclick`/`onsubmit` handlers block a strict CSP on `/client/`.
* **Exact Instructions:**
  1. Admin: replace the 8 inline handlers (7 in `index.php`, 1 in `publish.php`) with data attributes handled in `admin.js`: `data-confirm="question"` on forms (uses `confirm()`), `data-copy="#id"` or `data-copy-text` on copy buttons, and a `submit` listener for the Publish form. Move the three inline `<script>` blocks in `index.php` and the one in `publish.php` into `admin.js` (or `publish.js` if clearer). Pass PHP values through `data-` attributes or a `<script type="application/json" id="...">` block, never through inline script.
  2. The `localStorage.setItem("eaSkip","1")` line in `ahead()` moves into `admin.js`.
  3. Client gallery page: replace `<script>window.EA=...</script>` with `<script type="application/json" id="ea-cfg">` and have `client.js` read it first thing.
  4. `new.php`: move its inline script to `new.js` and its `<style>` block and 4 style attributes into `client.css`.
  5. Load all scripts through the `asset()` helper from T-20 for cache-busting.
  6. Finish with a search across `static/client/`: no inline `<script>` with code, no `on...=` attributes, no `style="`, no `<style`.
* **Guardrails (Do Not Touch):** Endpoint names and parameters, CSRF handling, the signature pad behavior and release wording in `new.php`, payment and download logic, `nas/watcher.py`.
* **Verification Checklist (Done Checks):**
  - [ ] Admin: copy-link buttons copy; deleting a test photo asks for confirmation; Publish uploads a test photo to a test gallery.
  - [ ] A client gallery link: favorite, comment and download a Free photo.
  - [ ] `/client/new/`: the form loads, the signature box draws, and the release section toggles (do not submit a real release).
* **Undo:** Revert the pull request.

## Phase 4: Portfolio (replaces Pages CMS)

### Ticket #T-23: Shared "save content to GitHub" helper and a gallery index
* **Assigned Model:** Sonnet
* **Size:** M
* **Target File(s):** `static/client/admin/content.php` (new), `static/client/admin/publish.php`, `build.py`
* **The Problem / Goal:** Every editor in this phase needs to read and save `content/` files through the GitHub API, and the admin needs a list of public galleries without calling GitHub 86 times.
* **Exact Instructions:**
  1. `content.php`: move `pub_gh()` here from `publish.php` (publish.php then requires it). Add `content_get(string $path): array` returning `[data, sha]` for a JSON file on `main`, and `content_put(string $path, array $data, string $sha, string $message): array` that writes pretty-printed JSON (4 spaces, unescaped unicode and slashes, trailing newline), retries once on a 409 or 422 by re-reading, and returns `[ok, errorSentence]`. Paths must match `^content/[a-z0-9/_-]+\.json$`.
  2. Add `validate_gallery(array $g): array` returning a list of plain-sentence problems, enforcing the gallery table in Section 3.3.
  3. `build.py`: write `dist/client/galleries-index.json`, a list of `{slug, title, date, year, category, series, cover, count, hidden}` for **all** galleries including hidden ones, newest first.
  4. No user-visible change in this ticket.
* **Guardrails (Do Not Touch):** `pubfile` and `pubdone` behavior and responses (the NAS watcher depends on them), the GitHub token setting names.
* **Verification Checklist (Done Checks):**
  - [ ] Admin > Publish still uploads and publishes a test photo.
  - [ ] `https://www.everettallenphotography.com/client/galleries-index.json` loads and lists galleries.
* **Undo:** Revert the pull request.

### Ticket #T-24: Public galleries list in the admin
* **Assigned Model:** Sonnet
* **Size:** M
* **Depends on:** T-23
* **Target File(s):** `static/client/admin/pgalleries.php` (new), `static/client/admin/index.php` (one `require` line and one menu link)
* **The Problem / Goal:** There is no place in the admin to see all public galleries.
* **Exact Instructions:**
  1. New view `?a=pgalleries`, required from `index.php` after the login check like `dashboard.php`.
  2. Read `galleries-index.json` from the document root. Show a table (cards on phones): cover thumbnail (`/photos/<slug>/<cover>-t.jpg`, fixed 3:2 box), title, date, category, series short name, photo count, Shown or Hidden, links "Edit" (`?a=pgallery&slug=`, built in T-25) and "View".
  3. Filters at the top as chips (Section 3.7): All, Portraits, Events, Hidden, Needs info (an event gallery with no series, or any gallery with no description), and one chip per event series using its short label; and a text filter box that narrows rows as you type (external script).
  4. Add the Galleries line under Portfolio in the admin menu (Section 3.7).
  5. Note at the top: "Changes take a minute or two to appear here after saving."
* **Guardrails (Do Not Touch):** The existing `?a=list` view and its `#orders` anchor (the dashboard links to it).
* **Verification Checklist (Done Checks):**
  - [ ] Admin > Portfolio > Galleries lists 86 galleries with cover photos; the Events filter shows 27.
  - [ ] Sessions (or Client galleries, before T-41) still opens the client gallery list.
* **Undo:** Revert the pull request.

### Ticket #T-25: Gallery editor (details, cover picker, hide)
* **Assigned Model:** Sonnet
* **Size:** L
* **Depends on:** T-24
* **Target File(s):** `static/client/admin/pgalleries.php`, `build.py`, `.pages.yml`
* **The Problem / Goal:** Editing a gallery's title, date, series, tags, description and cover requires Pages CMS and typing a photo ID by hand.
* **Exact Instructions:**
  1. View `?a=pgallery&slug=`: load the file with `content_get`. Form fields: Title, Date, Year (pre-filled from the date, editable), Category (select), Series (select from `content/series.json` plus "None"), Tags (one per line), Description (textarea with a note that a blank line starts a new paragraph), Hidden (checkbox: "Hide this gallery from the site").
  2. Cover picker: a grid of the gallery's thumbnails as radio buttons; the current cover is selected. Lazy-load the images.
  3. POST `pgsave`: build the new gallery array from the loaded file (keep `images` untouched and keep any unknown fields), run `validate_gallery`, show problems above the form in plain sentences, otherwise `content_put` with message `Gallery details: <slug>`. Omit `series`, `tags` and `hidden` when empty or false.
  4. After saving show: "Saved. The site updates in a minute or two."
  5. `build.py`: change the filter to leave out galleries where `hidden` is true (they still go into `galleries-index.json`).
  6. `.pages.yml`: add `{ name: hidden, label: Hide this gallery from the site, type: boolean }` to the galleries fields so Pages CMS does not drop the field.
  7. State in the page help text: hiding removes the gallery's pages; the photo files stay on the server. To take a photo down for good use Remove (T-26).
* **Guardrails (Do Not Touch):** `images` order and content in this ticket, slugs (never rename a file), `featured_portraits`.
* **Verification Checklist (Done Checks):**
  - [ ] Edit a test gallery's description and cover; within two minutes the public gallery and its Events or Portraits tile show the change.
  - [ ] Tick Hidden on a test gallery: after the deploy it is gone from Events, Search and its own address (needs T-03). Untick: it returns.
* **Undo:** Revert the pull request. To undo a content change, edit again or revert that single "Gallery details" commit on GitHub.

### Ticket #T-26: Reorder photos and remove photos for real
* **Assigned Model:** Sonnet
* **Size:** L
* **Depends on:** T-25, T-03
* **Approved by Andrew (2026-11-15):** yes, as written. Web copies are deleted and the original is moved to the private removed folder, not deleted.
* **Target File(s):** `static/client/admin/pgalleries.php`, `static/client/admin/pgalleries.js` (new)
* **The Problem / Goal:** A removal request today leaves the photo page, both web copies and the original on the server. There is also no way to reorder photos.
* **Exact Instructions:**
  1. On the gallery editor add a "Photos" section: thumbnail grid with drag to reorder on computers and "Move earlier" / "Move later" buttons on every tile for phones and keyboards. "Save order" posts `pgorder`; the server rejects the request unless the id list is exactly the current set, then saves with `content_put` (message `Photo order: <slug>`).
  2. Each tile has a checkbox. "Remove selected photos" opens a confirmation step that shows the count and requires typing `REMOVE`.
  3. POST `pgremove`, for each id (validated `^\d{3,6}$`, slug validated, and the id must be in the gallery): delete `photos/<slug>/<id>-t.jpg` and `-l.jpg`; delete the folder `photo/<slug>/<id>/` in the document root (its `index.html`); move `masters/<slug>/<id>.jpg` to `masters/_removed/<slug>/<id>.jpg` (create folders 0700; overwrite if present); remove the id from `labels.json` under the existing lock file; `DELETE FROM photo_likes WHERE g = ? AND id = ?`.
  4. Then save the gallery JSON without those images. If the cover was removed, set the cover to the first remaining image. If no images remain, set `hidden` to true and keep one-line help text explaining the gallery is now empty and hidden.
  5. If the GitHub save fails after files were deleted, show: "The photo files are removed, but the gallery list could not be saved. Press Remove again." and make the action safe to repeat.
  6. Resolve every path with `realpath` checks so nothing outside `photos/<slug>/`, `photo/<slug>/` or `masters/<slug>/` can be touched.
  7. Help text on the page: "Removed photos disappear from the site right away. Copies already delivered to a buyer are not affected. The original is kept in a private removed folder in case it was a mistake."
* **Guardrails (Do Not Touch):** Client galleries and order galleries (`galleries`, `images` tables and `~/everettallen-private/<host>/galleries/`), `pubfile`, `pubdone`, payment code, the Photo removal page wording.
* **Verification Checklist (Done Checks):**
  - [ ] Publish a throwaway test gallery with 3 photos. Remove one: immediately its `/photo/<slug>/<id>/` address and its `-l.jpg` address both show Not found.
  - [ ] After the deploy the gallery shows 2 photos and the right count.
  - [ ] Reorder the 2 photos and save: the public gallery shows the new order after the deploy.
  - [ ] Admin > Library no longer offers the removed photo.
* **Undo:** Revert the pull request to remove the feature. A removed photo can be restored by hand from `masters/_removed/` by publishing it again.

### Ticket #T-39: Publish page: drag and drop, series at upload, live check
* **Assigned Model:** Sonnet
* **Size:** M
* **Depends on:** T-23, T-37
* **Target File(s):** `static/client/admin/publish.php`, `static/client/admin/publish.js` (new)
* **The Problem / Goal:** Publishing uses a plain file picker, the series and tags have to be set afterwards in another tool, and "rebuilds in a minute or two" is a guess.
* **Exact Instructions:**
  1. Drop zone for files (drag and drop on computers; tapping it opens the picker, which offers the photo library on iPad and iPhone). Per-photo progress rows with a thumbnail, file name and status.
  2. For a new gallery add Series (select from `content/series.json` through `content_get`, plus "None") and Tags (one per line). Send them as optional `series` and `tags` in the `pubdone` payload; `pubdone` stores them only when creating a new gallery file and validates them with `validate_gallery`. A payload without them must behave exactly as today (the NAS watcher sends none).
  3. After a successful publish, `publish.js` checks `/gallery/<slug>/` every 15 seconds for up to 6 minutes and shows "Live now" with a link once it returns 200 and contains the new photo count, or "Still building. Check the gallery in a few minutes." when time runs out.
  4. Move the page's inline script into `publish.js`.
* **Guardrails (Do Not Touch):** `pubfile` request and response, the `pubdone` response, the NAS key check, image sizes, the corner logo.
* **Verification Checklist (Done Checks):**
  - [ ] Publish a throwaway test gallery with 2 photos and a series: the page reports "Live now" and the public gallery shows the series.
  - [ ] The NAS watcher still publishes a test folder.
* **Undo:** Revert the pull request.

### Ticket #T-40: NAS watcher status in the admin
* **Assigned Model:** Sonnet
* **Size:** M
* **Depends on:** T-39
* **Needs Andrew's OK before starting:** yes, because it lets the NAS key use one more action.
* **Target File(s):** `nas/watcher.py`, `static/client/admin/index.php` (key check), `static/client/admin/publish.php`, `README.md`
* **The Problem / Goal:** The server cannot see the NAS, so nobody knows from the admin whether the watcher is running, what it is publishing, or why a folder failed.
* **Exact Instructions:**
  1. Allow the NAS key (`X-EA-Token`) to use one more POST action, `nasstatus`, and nothing else new.
  2. `nasstatus` accepts JSON `{version, folders:[{name, state, photos, error}]}` where `state` is `ready`, `working`, `published` or `failed`, at most 50 folders, names and errors trimmed to 200 characters. Store it with the time in setting `nas_status`.
  3. `watcher.py`: send `nasstatus` once a minute and right after each folder changes state. Failures to send are logged and never stop publishing.
  4. Publish page: a "NAS folder watcher" card: last heard from (with "not heard from in over 10 minutes" in the warning style), folders working now, the last 5 published, and failed folders with their error.
  5. README: one line saying to copy the new `watcher.py` to the NAS and restart the container (H-11).
* **Guardrails (Do Not Touch):** `pubfile` and `pubdone`, folder renaming rules (`-READY`, `-PUBLISHED`, `-FAILED`), the `.env` format.
* **Verification Checklist (Done Checks):**
  - [ ] After H-11, the card shows "last heard from" within a minute.
  - [ ] Rename a test folder to `-READY`: the card shows it working, then published.
* **Undo:** Revert the pull request and put the previous `watcher.py` back on the NAS.

### Ticket #T-27: Event series editor
* **Assigned Model:** Sonnet
* **Size:** M
* **Depends on:** T-23
* **Target File(s):** `static/client/admin/content-editors.php` (new), `static/client/admin/index.php` (require and menu)
* **The Problem / Goal:** Adding or editing a recurring event series requires Pages CMS.
* **Exact Instructions:**
  1. View `?a=series`: list the series with Name, Short label, Description fields per row, "Move up" / "Move down", and an "Add a series" form (Name, Short label, Description; the slug is generated from the name and shown before saving).
  2. POST `seriessave`: validate per Section 3.3. Slugs of existing series cannot be changed. A series cannot be deleted while any gallery in `galleries-index.json` uses it; say which galleries.
  3. Save with `content_put` (message `Event series`). The existing `sync-series` workflow then updates `.pages.yml` on its own.
* **Guardrails (Do Not Touch):** `scripts/sync-series.py`, `.github/workflows/sync-series.yml`, existing slugs.
* **Verification Checklist (Done Checks):**
  - [ ] Change one series description; after the deploy its series page shows the new text.
  - [ ] Trying to delete a series that has galleries shows a clear refusal.
* **Undo:** Revert the pull request; content changes are separate commits that can be reverted on GitHub.

### Ticket #T-28: Pages and site settings editor
* **Assigned Model:** Sonnet
* **Size:** M
* **Depends on:** T-27
* **Target File(s):** `static/client/admin/content-editors.php`
* **The Problem / Goal:** About, Press and licensing, Photo removal, Privacy and the site settings (tagline, links, featured portraits) require Pages CMS.
* **Exact Instructions:**
  1. View `?a=pages`: list the four page files. Editing one shows Title, Lede, Search description, Body (textarea) and a short formatting key: blank line for a new paragraph, `**bold**`, `*italic*`, `[text](/path/)`, numbered lines for a list.
  2. Above the Privacy and Photo removal editors show: "This wording is a promise to visitors. Have changes to it reviewed before saving."
  3. Same view, section "Site settings": Tagline, Home descriptor, Contact intro, Usage note, Facebook and Instagram addresses (must start with `https://`), and Featured portraits as an ordered list chosen from portrait galleries in `galleries-index.json` (add, remove, move up, move down).
  4. POST `pagesave` and `sitesave`: validate per Section 3.3, keep unknown fields, save with `content_put`. `title` and `base_url` in `site.json` are shown read-only.
  5. No creating or deleting pages (the footer links are fixed in `build.py`).
* **Guardrails (Do Not Touch):** `base_url`, the wording of existing pages (this ticket builds the editor only), `content/services.json`.
* **Verification Checklist (Done Checks):**
  - [ ] Change the order of two featured portraits; after the deploy the home strip shows the new order.
  - [ ] Edit and restore one word on the About page; both saves appear on the site.
* **Undo:** Revert the pull request.

### Ticket #T-29: Services and rates editor
* **Assigned Model:** Sonnet
* **Size:** M
* **Depends on:** T-28
* **Target File(s):** `static/client/admin/content-editors.php`
* **The Problem / Goal:** Prices and service cards require Pages CMS.
* **Exact Instructions:**
  1. View `?a=services`: page text fields (Title, Lede, Delivery, Callout, Call to action), share discount percent and note, and one block per card: Title, Price (display text), Unit, Meta line, Badge, Included photos (number), Items (one per line). Cards can be added, removed and moved.
  2. Model release fields (`release_*`) are shown in a separate, collapsed section with the warning: "Legal wording. People who already signed keep the version they signed. Changing anything here requires a new version label." The server refuses to save any change to a `release_*` field unless `release_version` also changed.
  3. POST `servicessave`: validate per Section 3.3, keep unknown fields (including `wide`), save with `content_put` (message `Services and rates`).
  4. Do not change any price, wording or release text as part of this ticket.
* **Guardrails (Do Not Touch):** `share.json` and `services.json` generation in `build.py`, `new.php`, `lib.php` release functions.
* **Verification Checklist (Done Checks):**
  - [ ] Edit and restore one item line on a card; both saves appear on `/services/`.
  - [ ] Changing release text without changing the version label is refused with a clear message.
  - [ ] `/client/new/` still shows the services list.
* **Undo:** Revert the pull request.

### Ticket #T-30: Point the dashboard and docs at the new editors
* **Assigned Model:** Haiku
* **Size:** S
* **Depends on:** T-29
* **Target File(s):** `static/client/admin/dashboard.php`, `README.md`
* **The Problem / Goal:** The dashboard still sends Andrew to Pages CMS for site content.
* **Exact Instructions:**
  0. The editors are also in the menu (Section 3.7). If T-44 has already replaced the "Jump to" section, put the four links in Today's shortcuts instead.
  1. In `dashboard.php`, replace the one link whose `href` is `https://app.pagescms.org` with these four links in the same style: `?a=pgalleries` "Galleries" / small text "Titles, covers, photos"; `?a=series` "Event series" / "Names and descriptions"; `?a=pages` "Pages and settings" / "About, home page, links"; `?a=services` "Services and rates" / "Prices and cards". Use the existing `<?= $base ?>` prefix like the neighboring links.
  2. In `README.md`, under "Admin and Pages CMS quick guide", add one paragraph: gallery details, series, pages and services are now edited in the admin; Pages CMS still works as a fallback until it is removed.
  3. Leave `.pages.yml` in place.
* **Guardrails (Do Not Touch):** The tiles, queries and other links in `dashboard.php`.
* **Verification Checklist (Done Checks):**
  - [ ] The dashboard "Jump to" section shows the four new links and each opens the right editor.
* **Undo:** Revert the pull request.

## Phase 5: Studio: sessions, clients, sales

### Ticket #T-41: Sessions: client galleries become sessions
* **Assigned Model:** Sonnet
* **Size:** M
* **Depends on:** T-37, T-16
* **Target File(s):** `static/client/lib.php` (migration), `static/client/admin/index.php` (`list` view, `create` and `update` actions)
* **The Problem / Goal:** A shoot's information is split between the client record and the client gallery. A session (one shoot) should be the hub: client, date, location, release, proofing gallery, picks and orders in one place. In the database a session is a client gallery (`galleries` where `kind` is not `'shop'`).
* **Exact Instructions:**
  1. Add `galleries.shoot_date TEXT DEFAULT ''` (YYYY-MM-DD) and `galleries.shoot_location TEXT DEFAULT ''` through the migration (raise `SCHEMA_VERSION`). When empty, show the linked client's `shoot_date` and `shoot_location` instead.
  2. New view `?a=sessions`: cards with the first photo as a thumbnail, title, client, shoot date, status tag (Off, Proofing, Picks made, Delivered) and counts (photos, favorites, open requests, unread comments). Filters as chips: All, Upcoming, Proofing, Needs reply, Off. Search by title or client. "New session" is the main action.
  3. `?a=list` redirects to `?a=sessions`, keeping `#orders` working until T-33 is merged.
  4. The create and settings forms get Shoot date and Shoot location fields.
  5. Order galleries (`kind = 'shop'`) are not sessions; they stay reachable from the orders list.
* **Guardrails (Do Not Touch):** Tokens and client links, prices, picks, payments, downloads, the client gallery page.
* **Verification Checklist (Done Checks):**
  - [ ] Locally and live: Sessions lists every client gallery with the right counts; an old bookmark to `?a=list` still opens.
  - [ ] Set a shoot date on a test session: it shows on the card.
* **Undo:** Revert the pull request (the two columns are harmless if left).

### Ticket #T-42: Session page: tabs, share sheet and activity
* **Assigned Model:** Sonnet
* **Size:** L
* **Depends on:** T-41
* **Target File(s):** `static/client/admin/index.php` (the `gallery` view), `static/client/admin/admin.js`
* **The Problem / Goal:** The client gallery page is one long scroll with settings before photos, favorites shown as a list of file names, and orders, comments and settings mixed together.
* **Exact Instructions:**
  1. Page header: title, status tag, client name linking to the client record, shoot date and location, and a "Share" main action.
  2. Share panel: the link with Copy, "Email the link" (the existing mailto text), "Preview as client", and whether a password is set.
  3. Tabs through `?a=gallery&id=&tab=`: Photos (default), Activity, Settings. Photos: upload drop zone and the photo grid with favorite, picked, comment and download marks on each tile. Activity: favorites as thumbnails, picks used out of included, comments with reply, and the photo requests for this session. Settings: the existing settings form, the price form, Reset picks and Delete.
  4. "Mark paid" in Activity asks for confirmation with `data-confirm` saying it unlocks the download and emails the client. No change to what it does.
* **Guardrails (Do Not Touch):** Every POST action and field name, `mark_order_paid`, upload handling, prices, the client gallery page.
* **Verification Checklist (Done Checks):**
  - [ ] Locally: each tab works at 390px and 1280px; Copy copies the link; replying to a comment works.
  - [ ] Live: open a real session, check the counts match before and after.
* **Undo:** Revert the pull request.

### Ticket #T-43: Select several photos at once in a session
* **Assigned Model:** Sonnet
* **Size:** M
* **Depends on:** T-42
* **Target File(s):** `static/client/admin/index.php` (one new POST action), `static/client/admin/admin.js`
* **The Problem / Goal:** Setting prices or deleting photos means editing small boxes one photo at a time.
* **Exact Instructions:**
  1. On the Photos tab add "Select" mode: tapping tiles selects them (checkbox on each tile for keyboards), with Select all, Select favorites and Clear.
  2. Actions bar for the selection: Set web price, Set full price (blank means gallery default, 0 means Free), and Delete (confirmation that states the count).
  3. New POST `bulkphotos` with `id` (gallery), `images` (comma-separated ids, each must belong to that gallery), `do` (`webprice`, `fullprice`, `delete`), `price`. Delete removes the same files and rows as `delimg`.
  4. The single-photo price boxes stay for one-off changes.
* **Guardrails (Do Not Touch):** `delimg`, `price`, `priceall` behavior, picks, orders.
* **Verification Checklist (Done Checks):**
  - [ ] Locally: select 3 photos, set a web price, check the three tiles; delete 2, the count drops by 2.
* **Undo:** Revert the pull request.

### Ticket #T-17: Contact form saves an inquiry and is rate limited
* **Assigned Model:** Sonnet
* **Size:** M
* **Target File(s):** `static/contact/send.php`, `build.py`, `static/js/contact.js` (new, small)
* **The Problem / Goal:** Contact messages are email only: they never appear under "New inquiries" on the dashboard, a lost email is a lost lead, and the form has no rate limit.
* **Exact Instructions:**
  1. `send.php` requires `../client/lib.php` and uses its `OWNER_EMAIL`, `OWNER_BCC`, `MAIL_FROM` constants in place of the hard-coded addresses (same values).
  2. Keep the honeypot. Add: at most 5 messages per visitor per hour using the same hashed key and `clients.ip_hash` approach as `new.php`; over the limit, redirect to `/thanks/` quietly.
  3. On a valid message insert a `clients` row: `name` (first plus last), `email`, `message` (2,000 characters max), `status` `'new'`, `source` `'contact'`, `ip_hash`, `created`. Then send the same email as today.
  4. Replace the bare 400 text with a redirect to `/contact/?e=1`; `build.py` adds a hidden paragraph the page shows when `?e=1` is present. Do this with a small external script file, not inline code.
  5. Length limits: first and last 80 each, message 2,000.
  6. In the pull request description, tell Andrew: the Privacy page says form messages are received and used only to reply; they will now also be stored in the admin. Ask him whether the Privacy wording needs a sentence. Do not edit `content/pages/privacy.json`.
* **Guardrails (Do Not Touch):** `new.php`, the `clients` table definition, privacy wording, the form's field names in `build.py`.
* **Verification Checklist (Done Checks):**
  - [ ] Send a test message from `/contact/`: you land on Thanks, the email arrives, and the dashboard "New inquiries" count goes up by one with your test name.
  - [ ] Submitting with an invalid email shows a friendly message on the contact page.
* **Undo:** Revert the pull request. Rows already saved stay in Clients and can be deleted there.

### Ticket #T-31: Client list: pipeline chips, search and filters
* **Assigned Model:** Sonnet
* **Size:** M
* **Target File(s):** `static/client/admin/index.php` (the `clients` view)
* **The Problem / Goal:** The client list has a basic search; finding people by source, release status or date needs scrolling.
* **Exact Instructions:**
  1. Keep the status filter and text search. Extend the search to also match phone and handle.
  2. Add filters as plain links or selects: Source (Form, Contact, any other values present), Release (Signed, Not signed), and Sort (Newest, Oldest, Name).
  3. Show a count line ("14 clients"). Keep archived clients hidden unless the Archived status is chosen.
  4. All filters work through the query string so a filtered list can be bookmarked. Use prepared statements only.
  5. On phones the table becomes stacked cards.
  6. Show the statuses as chips in pipeline order (New, Contacted, Booked, Shot, Delivered, then Archived) with a count on each, using the Section 3.7 chip component.
* **Guardrails (Do Not Touch):** The single-client view, release data, `CLIENT_STATUSES`.
* **Verification Checklist (Done Checks):**
  - [ ] Filtering by Contact shows only contact-form inquiries (after T-17); searching part of a name finds the client.
* **Undo:** Revert the pull request.

### Ticket #T-32: Client record: Overview cards and timeline
* **Assigned Model:** Sonnet
* **Size:** M
* **Target File(s):** `static/client/admin/index.php` (the `client` view)
* **The Problem / Goal:** A client's history is spread across the client record, their galleries and their orders.
* **Exact Instructions:**
  1. Add a "Timeline" section to the single-client view, newest first, built from existing data only: inquiry received (`clients.created`, with source), release signed (`release_at`), each linked gallery created, each order on those galleries (status and tier), each download on those galleries (grouped per day), and each email in `outbox` for this client once T-34 exists (check that the table exists before querying).
  2. Each row: date and time in America/Chicago, one plain sentence, and a link where there is a page to open.
  3. Read only. No new tables.
  4. The client view opens on an Overview tab with cards in two columns (one on phones): Contact (email, phone, pronouns, handle, with Email and Call buttons), Release (signed or not, ID checked, Drive saved, View PDF), Sessions (from T-41, with "New session"), Notes, and the Timeline. The existing details form moves to a Details tab and the release update form to a Release tab, through `?a=client&id=&tab=`. Delete stays at the bottom of Details.
* **Guardrails (Do Not Touch):** Release PDF and Drive actions, the notes and status form fields and actions.
* **Verification Checklist (Done Checks):**
  - [ ] Open a client who has a gallery: the timeline lists the inquiry, the gallery and any orders in date order.
* **Undo:** Revert the pull request.

### Ticket #T-33: Sales: orders page with totals
* **Assigned Model:** Sonnet
* **Size:** M
* **Approved by Andrew (2026-11-15):** yes. Adding the price column and the two checkout lines is OK. Anything beyond that in payment code still needs a new OK.
* **Target File(s):** `static/client/lib.php`, `static/client/api.php`, `static/client/shop.php`, `static/client/admin/orders.php` (new), `static/client/admin/index.php` (require and menu)
* **The Problem / Goal:** There is no single orders page, and orders do not record the price charged, so totals cannot be shown.
* **Exact Instructions:**
  1. `lib.php`: add column `orders.price_cents INTEGER` through the migration block (raise `SCHEMA_VERSION` if T-16 is merged).
  2. In the two `INSERT INTO orders` statements (`api.php` and `shop.php`), also store the price already computed at that point for that order. Change nothing else in those files. If the price variable at that point is not obvious, stop and ask.
  3. View `?a=orders`: table of all orders, newest first: date, buyer email, gallery, photo label, size, status, price (or "not recorded" for older orders), and a link to the gallery. Filters: status, date range (this month, last month, this year, all).
  4. Totals line for the filtered set: number of paid orders and the sum of recorded paid prices, with the note "Totals only include orders made after this page was added."
  5. `?a=orderscsv`: the filtered set as CSV.
  6. Change the dashboard "Orders needing action" tile to link to `?a=orders&status=requested`.
  7. The menu label is "Sales" (Section 3.7). Use the 3.7 table component, which becomes cards on phones.
* **Guardrails (Do Not Touch):** Stripe calls, the webhook, `mark_orders_paid`, download checks, prices and discounts.
* **Verification Checklist (Done Checks):**
  - [ ] Admin > Orders lists existing orders with "not recorded" prices.
  - [ ] Make one small real or code-free test purchase: it appears with its price, and the total updates once paid.
* **Undo:** Revert the pull request (the extra column is harmless if left).

## Phase 6: Today and stats

### Ticket #T-44: Today: the admin home becomes a "Needs you" list
* **Assigned Model:** Sonnet
* **Size:** M
* **Depends on:** T-33, T-40, T-41
* **Target File(s):** `static/client/admin/dashboard.php`
* **The Problem / Goal:** The dashboard leads with numbers. A one-person studio needs to see what is waiting on them first.
* **Exact Instructions:**
  1. Rename the menu item and page title to "Today" (the action stays `dashboard`).
  2. First card, "Needs you", one row per item with a count and a link: photo requests not yet paid, new inquiries, client comments with no reply, signed releases with no ID check, sessions with a shoot date in the next 14 days, public galleries with no series or no description (from `galleries-index.json`), failed NAS folders. Hide rows with nothing in them; when all are empty show "Nothing waiting. Nice."
  3. Below: Upcoming sessions, Latest public galleries (cover thumbnails), Recent sales (from T-33), and the existing visitor, download and like tiles.
  4. Keep the existing queries that still apply; add the new ones as prepared statements.
* **Guardrails (Do Not Touch):** Data and actions; this ticket only reads.
* **Verification Checklist (Done Checks):**
  - [ ] Locally with fake data every row of "Needs you" appears and links to the right filtered page.
  - [ ] Live: the counts match the Sessions, Clients and Sales pages.
* **Undo:** Revert the pull request.

### Ticket #T-45: Stats page restyle
* **Assigned Model:** Sonnet
* **Size:** S
* **Depends on:** T-37
* **Target File(s):** `static/client/admin/index.php` (the `stats` view), `static/client/admin/admin.css`
* **The Problem / Goal:** The stats chart is built from inline-styled bars and does not suit dark mode or phones.
* **Exact Instructions:**
  1. Tiles at the top: page views, visitor-days and likes for the chosen range, with the range as chips (7, 30, 90, 365 days).
  2. The daily chart as an inline SVG drawn with CSS variables so it works in light and dark, with an accessible table of the same numbers in a `<details>` below it.
  3. Top pages, sources and devices as the 3.7 table component (cards on phones).
* **Guardrails (Do Not Touch):** How stats are collected and stored.
* **Verification Checklist (Done Checks):**
  - [ ] Locally the chart and tables read well at 390px and 1280px in light and dark.
* **Undo:** Revert the pull request.

## Phase 7: Public site fixes and CSP

### Ticket #T-04: Fix sideways scrolling at 320px (Services and Events)
* **Assigned Model:** Haiku
* **Size:** S
* **Target File(s):** `static/css/style.css`
* **The Problem / Goal:** At 320px wide the Services page scrolls sideways by 43px (the "Just ask a question" button), and Events by 1px.
* **Exact Instructions:**
  1. Append these lines to the very end of the file, exactly:
     ```css
     /* T-04: keep the call-to-action buttons and year links inside a 320px screen */
     @media(max-width:480px){.cta .btn,.cta .btn.alt{width:100%;margin-left:0;padding:14px 16px;text-align:center}}
     .yj a:last-child{margin-right:0}
     ```
  2. Change nothing else.
* **Guardrails (Do Not Touch):** Existing rules, `build.py`, any class names.
* **Verification Checklist (Done Checks):**
  - [ ] On a phone, `/services/` does not scroll sideways; the two buttons at the bottom are full width, stacked.
  - [ ] `/events/` does not scroll sideways.
  - [ ] On a computer, the Services buttons look as before.
* **Undo:** Revert the pull request.

### Ticket #T-05: Add visible keyboard focus and respect reduced motion
* **Assigned Model:** Haiku
* **Size:** S
* **Target File(s):** `static/css/style.css`
* **The Problem / Goal:** Keyboard users cannot see where they are, and smooth scrolling ignores the reduced-motion setting.
* **Exact Instructions:**
  1. Append to the end of the file, exactly:
     ```css
     /* T-05: keyboard focus and reduced motion */
     a:focus-visible,button:focus-visible,input:focus-visible,textarea:focus-visible,.strip:focus-visible{outline:3px solid var(--blue);outline-offset:3px}
     .fcard:focus-visible{outline-offset:-3px}
     .band:focus-visible,.lb button:focus-visible,.lb a:focus-visible,.cartbar a:focus-visible,.cartbar button:focus-visible{outline-color:#fff;outline-offset:-5px}
     @media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}*,*::before,*::after{transition:none!important;animation:none!important}}
     ```
  2. Do not remove the existing `.sform input:focus` rule.
* **Guardrails (Do Not Touch):** Colors, the `--blue` value, any script.
* **Verification Checklist (Done Checks):**
  - [ ] On a computer, press Tab on the home page: a blue outline moves through the menu, the photo strip cards, the Events band (white outline) and the footer.
  - [ ] Clicking with a mouse shows no outline.
* **Undo:** Revert the pull request.

### Ticket #T-06: Photo page heading, automatic copyright year, remove dead script code
* **Assigned Model:** Haiku
* **Size:** S
* **Target File(s):** `build.py`, `static/css/style.css`, `static/js/site.js`
* **The Problem / Goal:** Photo pages have no `h1`; the footer year is fixed text; `site.js` has code for a menu button and slideshow that no longer exist.
* **Exact Instructions:**
  1. `build.py` line 3: change `import json, os, shutil, html, re, glob, hashlib` to `import json, os, shutil, html, re, glob, hashlib, datetime`.
  2. `build.py`, in the footer inside `page()`: change `<span>Copyright 2026 Everett Allen Photography</span>` to `<span>Copyright {datetime.date.today().year} Everett Allen Photography</span>`.
  3. `build.py`, in the photo page loop: change `f'<img class="pimg" src="/photos/{s}/{pid}-l.jpg"` to `f'<h1 class="ptitle">{e(title)}</h1><img class="pimg" src="/photos/{s}/{pid}-l.jpg"` (only the start of that one string changes).
  4. `static/css/style.css`: append `.ptitle{margin:8px 0 0;font:500 15px/1.5 "Archivo Narrow",Archivo,sans-serif;text-transform:uppercase;letter-spacing:.08em}`.
  5. `static/js/site.js`: delete the two lines that start with `var b=document.querySelector('.menu-btn')` and `if(b)b.addEventListener('click'`. Delete the two lines that start with `var s=document.querySelectorAll('.slide');` and `if(s.length>1){var i=0;setInterval(`.
  6. Run `python3 build.py`; confirm `dist/photo/tommy/004/index.html` contains `<h1 class="ptitle">Tommy, photo 4</h1>`.
* **Guardrails (Do Not Touch):** The stats beacon line (starts with `try{if(!localStorage.getItem('eaSkip')`), share and lightbox code, `content/site.json` (leave `hero_count`).
* **Verification Checklist (Done Checks):**
  - [ ] Open any photo page: a small uppercase title shows above the photo.
  - [ ] The footer shows the current year.
  - [ ] Share buttons and the photo viewer still work in a gallery.
* **Undo:** Revert the pull request.

### Ticket #T-07: Make the photo viewer (lightbox) keyboard and screen reader friendly
* **Assigned Model:** Sonnet
* **Size:** M
* **Target File(s):** `static/js/site.js`, `static/css/style.css`
* **The Problem / Goal:** The lightbox is announced as a dialog but has no name, does not take or trap focus, and does not return focus on close.
* **Exact Instructions:**
  1. On the `.lb` element add `aria-label="Photo viewer"` and `tabindex="-1"`.
  2. On open: remember `document.activeElement`, set the `inert` attribute on `header.top`, `main` and `footer`, then focus the Close button. On close: remove `inert`, return focus to the remembered element.
  3. Trap Tab and Shift+Tab inside `.lb` among its visible buttons and links (include `.lbbuy` and `.lblike`, which `shop.js` and `likes.js` add later; query the focusable elements each time, do not cache them).
  4. Add a visually hidden live region inside `.lb` (`aria-live="polite"`) and write "Photo N of M" to it on each change. Keep the visible counter.
  5. Give Previous, Next and Close a minimum 44 by 44 pixel hit area in CSS without changing their look.
  6. Keep Escape, arrow keys, swipe, backdrop click and next-image preloading working exactly as now.
  7. Add a `.vh` visually hidden utility class to the stylesheet if you need one.
* **Guardrails (Do Not Touch):** Class names `.lb`, `.x`, `.p`, `.n`, `.sh`, `.lbshare`, `.c` and the `img` inside `.lb` (`shop.js` and `likes.js` depend on them, and `likes.js` watches that image's `src`). The `.ph` anchors and their `href` format. `shop.js`, `likes.js`.
* **Verification Checklist (Done Checks):**
  - [ ] On a computer: Tab to a gallery photo, press Enter, the viewer opens; Tab cycles only inside the viewer; Escape closes it and focus is back on the same photo.
  - [ ] On a phone: swipe, Share, Like and Buy still work in the viewer.
* **Undo:** Revert the pull request.

### Ticket #T-08: Home page says what and where, with a booking button up top; About in the menu
* **Assigned Model:** Sonnet
* **Size:** M
* **Target File(s):** `build.py`, `content/site.json`, `static/css/style.css`, `.pages.yml`
* **The Problem / Goal:** A first-time visitor sees a name and a tagline but not that this is a Minneapolis portrait and event photographer, and the only booking button is at the bottom.
* **Exact Instructions:**
  1. `content/site.json`: add `"home_descriptor": "Portraits and community events, Minneapolis, Minnesota."` (this sentence already exists as the About page lede; do not write new copy).
  2. `.pages.yml`: in the `site` file's fields add `{ name: home_descriptor, label: Home page descriptor line (what you do and where), type: string }`.
  3. `build.py` home section: inside `.sub`, above the tagline paragraph, output `<p class="what">` with the descriptor (escaped; skip the element if the field is empty). After the tagline paragraph add `<a class="btn" href="/services/">Book a portrait session</a>`. Keep the existing meta line and the existing bottom call to action.
  4. Menu: add `<a href="/about/">About</a>` before Services in all three `nav` variants in `page()`.
  5. CSS: `.what` uses the Archivo Narrow uppercase style at 16 to 18px. On screens up to 480px keep the header to at most two rows of links (reduce the gap and font size slightly; do not hide links, do not add a hamburger menu).
  6. The `h1` wordmark, tagline text and featured strip stay exactly as they are.
* **Guardrails (Do Not Touch):** The tagline wording, `featured_portraits`, the Events band, footer links, the inline `code` capture script in the head.
* **Verification Checklist (Done Checks):**
  - [ ] On a phone, the home page shows "Portraits and community events, Minneapolis, Minnesota." and a "Book a portrait session" button without scrolling past the first photo.
  - [ ] The button opens Services. About is in the menu on every page and opens `/about/`.
  - [ ] No sideways scrolling on the home page at phone width.
* **Undo:** Revert the pull request.

### Ticket #T-09: Load the first visible images right away
* **Assigned Model:** Haiku
* **Size:** S
* **Target File(s):** `build.py`
* **The Problem / Goal:** The first home page photos and the Events lead tile are lazy-loaded, which delays the largest visible content.
* **Exact Instructions:**
  1. Add this helper on a new line directly above the line that starts `strip=''.join(`:
     `def lz(n): return 'fetchpriority="high"' if n==0 else ('' if n<3 else 'loading="lazy"')`
  2. Replace the `strip=` line with:
     `strip=''.join(f'<a class="fcard" href="/gallery/{g["slug"]}/"><img {lz(n)} src="{img(g,"l")}" alt=""><span>{e(g["title"])} / {g["year"]}</span></a>' for n,g in enumerate(feat))`
  3. In `def tile(g,big=False):` change the returned string's `<img loading="lazy" src=` to `<img {"fetchpriority=high" if big else "loading=lazy"} src=`.
  4. Run `python3 build.py`. In `dist/index.html` the first strip image must have `fetchpriority="high"`, the next two no loading attribute, the rest `loading="lazy"`.
* **Guardrails (Do Not Touch):** Every other `loading="lazy"` in the file (cards, gallery thumbnails).
* **Verification Checklist (Done Checks):**
  - [ ] Home and Events look the same as before.
  - [ ] The first photos on the home page appear without a visible delay on a phone.
* **Undo:** Revert the pull request.

### Ticket #T-10: Self-host the Archivo fonts
* **Assigned Model:** Sonnet
* **Size:** M
* **Target File(s):** `static/fonts/` (new: WOFF2 files and `OFL.txt`), `static/css/style.css`, `static/client/client.css`, `build.py`, `static/client/index.php`, `static/client/new.php`, `static/client/admin/index.php`
* **The Problem / Goal:** Fonts load from Google Fonts: a third-party request on every page, render blocking, and incompatible with a strict CSP.
* **Exact Instructions:**
  1. In a temporary folder outside the repo run `npm pack @fontsource/archivo @fontsource/archivo-narrow` and unpack. Copy only these into `static/fonts/`: Archivo weight 500 and 800, Archivo Narrow weight 500, normal style, `latin` and `latin-ext` subsets, WOFF2 only (six files). Copy the license as `static/fonts/OFL.txt`. Do not add `package.json` or `node_modules` to the repo. If the registry cannot be reached, stop and report; do not substitute another font.
  2. Add `@font-face` rules at the top of `static/css/style.css` and `static/client/client.css`: family names `Archivo` and `Archivo Narrow`, `font-display:swap`, `src:url(/fonts/<file>) format("woff2")`, and the `unicode-range` values copied from the fontsource CSS for each subset.
  3. `build.py` `page()`: remove the two `preconnect` links and the Google Fonts stylesheet link. Add `<link rel="preload" href="/fonts/<archivo 800 latin file>" as="font" type="font/woff2" crossorigin>`.
  4. Remove the Google Fonts `<link>` tags (and preconnects) from the three PHP files.
  5. Confirm with a search that `fonts.googleapis.com` and `fonts.gstatic.com` appear nowhere in the repo.
* **Guardrails (Do Not Touch):** Font family names and weights used in existing CSS rules. No other fonts or weights.
* **Verification Checklist (Done Checks):**
  - [ ] Home page headline still shows in the heavy Archivo face; menu and labels in Archivo Narrow.
  - [ ] Admin login page and a client gallery link show the same fonts as before.
  - [ ] In a desktop browser's Network panel there are no requests to Google.
* **Undo:** Revert the pull request.

### Ticket #T-11: Search page accessibility
* **Assigned Model:** Haiku
* **Size:** S
* **Target File(s):** `build.py`, `static/js/search.js`, `static/css/style.css`
* **The Problem / Goal:** Search chips are links to `#` and the result count is not announced to screen readers.
* **Exact Instructions:**
  1. `build.py`, the line that starts `chips=''.join(`: in all three generator strings change `<a href="#" data-q="` to `<button type="button" data-q="` and each closing `</a>` to `</button>`.
  2. `build.py`, the search page line: change `<p class="meta" id="rc"></p>` to `<p class="meta" id="rc" aria-live="polite"></p>`.
  3. `static/js/search.js`: change `document.querySelectorAll('.chips a')` to `document.querySelectorAll('.chips button')`.
  4. `static/css/style.css`: in the two rules `.chips a,.tags a{...}` and `.chips a:hover,.tags a:hover{...}` add the selectors `.chips button` and `.chips button:hover` respectively. Then append `.chips button{background:transparent;color:var(--ink);cursor:pointer;border-radius:0}`.
* **Guardrails (Do Not Touch):** The `onsubmit="return false"` on the form (T-19 handles it), the `#q` and `#res` ids, `.tags a` on gallery pages.
* **Verification Checklist (Done Checks):**
  - [ ] On `/search/`, tapping a chip (for example TCLFW) fills the box and filters the results. Chips look the same as before.
* **Undo:** Revert the pull request.

### Ticket #T-12: Structured data on the home page
* **Assigned Model:** Haiku
* **Size:** S
* **Target File(s):** `build.py`
* **The Problem / Goal:** Search engines get no machine-readable statement of what the business is.
* **Exact Instructions:**
  1. Directly above the line `w('/',page('',home,'/','Portraits'))` add:
     `ld='<script type="application/ld+json">'+json.dumps({"@context":"https://schema.org","@type":"ProfessionalService","name":site["title"],"url":site["base_url"]+"/","description":site.get("home_descriptor") or site["tagline"],"areaServed":"Minneapolis, Minnesota","sameAs":[site["facebook"],site["instagram"]]}).replace("</","<\\/")+'</script>'`
  2. Change that `w(` line to `w('/',page('',home,'/','Portraits').replace('</head>',ld+'</head>',1))`.
  3. Do not add an address, phone number, prices or opening hours.
* **Guardrails (Do Not Touch):** Every other page. A JSON-LD block is data, not script, so it is allowed under the CSP in T-19.
* **Verification Checklist (Done Checks):**
  - [ ] Home page looks unchanged.
  - [ ] Paste the home address into Google's Rich Results Test: it reports valid structured data with no errors.
* **Undo:** Revert the pull request.

### Ticket #T-13: Tap target sizes
* **Assigned Model:** Sonnet
* **Size:** S
* **Target File(s):** `static/css/style.css`
* **The Problem / Goal:** Between 8 and 17 controls per page are under 24px tall (menu links, footer links, year links, tag chips), below the WCAG 2.2 AA target size.
* **Exact Instructions:**
  1. CSS only. Every link and button must have a hit area at least 24 by 24px; standalone controls (menu links, chips, tags, share buttons, "By event" buttons, year jump links, footer links, Previous and Next on photo pages) should reach 44px tall on touch screens where it does not change the layout's look.
  2. Prefer padding (with negative margin where needed to keep visual spacing) over changing font sizes.
  3. Keep the underline on `.yj a` and `.allp a` visually attached to the text.
  4. Check at 320, 375 and 1280px that nothing wraps differently enough to cause sideways scrolling.
* **Guardrails (Do Not Touch):** HTML, scripts, colors, font sizes of headings.
* **Verification Checklist (Done Checks):**
  - [ ] On a phone, menu links, footer links and tag chips are easy to tap without hitting a neighbor.
  - [ ] Pages look the same at a glance; no sideways scrolling on home, Events, a gallery, Services.
* **Undo:** Revert the pull request.

### Ticket #T-18: Security headers on the public site
* **Assigned Model:** Sonnet
* **Size:** S
* **Target File(s):** `build.py` (the `ht=[...]` list)
* **The Problem / Goal:** The site sends no `nosniff`, referrer, framing or HSTS headers.
* **Exact Instructions:**
  1. Add to the generated `.htaccess`, wrapped in `<IfModule mod_headers.c>`: the first five `Header always set` lines from Section 3.6 (everything except Content-Security-Policy).
  2. Do not add `includeSubDomains` or `preload` to HSTS.
  3. Leave the redirect and hotlink rules in the same order.
* **Guardrails (Do Not Touch):** `static/client/.htaccess`, the hotlink rules, the old WordPress redirects.
* **Verification Checklist (Done Checks):**
  - [ ] Site loads normally; a Facebook share preview of a gallery still shows its photo.
  - [ ] securityheaders.com for the home address lists the five headers as present.
* **Undo:** Revert the pull request.

### Ticket #T-19: Public pages: remove inline code, then enforce a strict CSP
* **Assigned Model:** Sonnet
* **Size:** M
* **Depends on:** T-10, T-18
* **Target File(s):** `build.py`, `static/js/code.js` (new), `static/js/search.js`, `static/css/style.css`, `static/client/.htaccess`
* **The Problem / Goal:** A strict CSP is impossible while pages carry an inline script, an inline `onsubmit`, and `style=""` attributes.
* **Exact Instructions:**
  1. Move the inline free-code capture script from the head into `static/js/code.js`, loaded in the head with a normal (not deferred) `<script src="/js/code.js?v=...">` so it still runs before anything else. Same behavior: store `?code=` in `localStorage` key `eaCode` and strip it from the address.
  2. Remove `onsubmit="return false"` from the search form; add the equivalent `submit` listener in `search.js`.
  3. Gallery grid: replace `style="--r:{ratio}"` with a class `r<N>` where N is the ratio times 100, rounded (for example `r67`, `r150`). After all galleries are loaded, write `dist/css/style.css` as the static file plus one generated rule per used value (`.r67{--r:.67}`), and compute the CSS cache-busting hash from that final text.
  4. Photo pages: replace `style="aspect-ratio:..."` on `.pimg` with `width` and `height` attributes (long edge 1000) and keep it responsive in CSS.
  5. Search the built `dist/` for ` style="`, ` onclick=`, ` onsubmit=` and inline `<script>` (other than `application/ld+json`) outside `dist/client/`: there must be none. Check that `site.js`, `shop.js` and `likes.js` do not build HTML strings containing `style=` attributes (setting `element.style` from script is fine).
  6. Add the Content-Security-Policy line from Section 3.6 to the generated root `.htaccess`.
  7. In `static/client/.htaccess` add a temporary override so the admin keeps working until T-22: the same policy but with `script-src 'self' 'unsafe-inline'` and `style-src 'self' 'unsafe-inline'`.
* **Guardrails (Do Not Touch):** The `eaCode` key name and behavior (free codes depend on it), `.ph` anchor `href` and `data-n`, the `img` width and height attributes in the grid, PHP files.
* **Verification Checklist (Done Checks):**
  - [ ] Home, Events, a gallery (grid looks the same), a photo page, Search, Services, Contact all work.
  - [ ] Photo viewer, Share, Like, and the Buy flow up to the Stripe page work.
  - [ ] Open a free code link (`/events/?code=...` from Admin > Codes): the code is still recognized.
  - [ ] Admin and a client gallery still work.
  - [ ] In a desktop browser console there are no "Content Security Policy" errors on those pages.
* **Undo:** Revert the pull request.

### Ticket #T-22: Enforce the strict CSP on /client/
* **Assigned Model:** Sonnet
* **Size:** S
* **Depends on:** T-21
* **Target File(s):** `static/client/.htaccess`
* **The Problem / Goal:** After T-20 and T-21 the temporary `'unsafe-inline'` override from T-19 can go.
* **Exact Instructions:**
  1. Replace the temporary override with the strict policy from Section 3.6 (or delete the override so the root policy applies; keep `X-Robots-Tag`).
  2. Before opening the pull request, run the client pages locally with PHP's built-in server and confirm no CSP console errors on admin login, dashboard, publish, a client gallery and the intake form.
* **Guardrails (Do Not Touch):** Rewrite rules, the `FilesMatch` block.
* **Verification Checklist (Done Checks):**
  - [ ] Repeat the three checks from T-21. Everything still works.
  - [ ] A test purchase flow reaches the Stripe page and returns (use a Free code or cancel at Stripe).
* **Undo:** Revert the pull request (this restores the relaxed policy).

## Phase 8: Email clients

### Ticket #T-34: Outbox queue and sender
* **Assigned Model:** Sonnet
* **Size:** M
* **Depends on:** T-14 (for the `cli/` folder)
* **Target File(s):** `static/client/lib.php`, `static/client/cli/send-outbox.php` (new), `static/client/admin/outbox.php` (new), `static/client/admin/index.php`, `README.md`
* **The Problem / Goal:** Client email needs a queue so nothing is lost when mail fails, and so the sender can be swapped to the shared AWS setup later without touching the screens.
* **Exact Instructions:**
  1. Add the `outbox` table from Section 3.4.
  2. `lib.php`: `outbox_queue(?int $clientId, string $to, string $subject, string $body): int` (validates the address; subject 200 max, single line; body 10,000 max, plain text) and `outbox_send_due(int $max = 20): array`, which sends queued rows oldest first through one function `mail_transport(string $to, string $subject, string $body): bool`. Today that function calls PHP `mail()` with the same From and Reply-To as `send_client_mail`. It is the only place a later ticket changes for SES.
  3. On failure raise `attempts`, store `last_error`; after 5 attempts mark `failed`.
  4. `cli/send-outbox.php`: CLI only, takes the private folder path as its argument, sets `EA_PRIVATE` and requires `lib.php`, calls `outbox_send_due`. README gets the cron line for H-06 (every 5 minutes).
  5. Admin view `?a=outbox`: queued, sent and failed lists with recipient, subject, time and error; "Send now" (runs `outbox_send_due`) and "Retry" on failed rows.
  6. Do not move existing order and inquiry emails into the queue in this ticket.
* **Guardrails (Do Not Touch):** `send_owner_mail`, `send_client_mail`, payment and release emails.
* **Verification Checklist (Done Checks):**
  - [ ] Admin > Outbox opens and is empty. (T-35 adds the way to create an email.)
* **Undo:** Revert the pull request and remove the cron job.

### Ticket #T-35: Email templates and "Email this client"
* **Assigned Model:** Sonnet
* **Size:** M
* **Depends on:** T-34
* **Target File(s):** `static/client/lib.php`, `static/client/admin/outbox.php`, `static/client/admin/index.php` (client view link)
* **The Problem / Goal:** Andrew wants to email clients from the admin with reusable templates (gallery ready, thanks, release reminder).
* **Exact Instructions:**
  1. Add the `email_templates` table. Seed three rows on first use with keys `gallery_ready`, `thanks`, `release_reminder`, each with a subject and a body that is exactly: `[Draft wording needed from Andrew]` followed by the placeholders that template supports. Do not write the email copy; Andrew writes it in the Templates screen.
  2. Placeholders replaced at send time: `{name}`, `{first_name}`, `{gallery_title}`, `{gallery_link}`, `{release_link}` (the intake form address). Unknown placeholders are left as typed and flagged in the preview.
  3. View `?a=templates`: edit name, subject and body of each template.
  4. On the single-client view add "Email this client": choose a template (and a gallery when the client has more than one), see an editable preview with placeholders filled, then "Queue email". Refuse to queue while the text still contains `[Draft wording needed`.
  5. Queued emails go through `outbox_queue` and appear in the client timeline (T-32).
* **Guardrails (Do Not Touch):** Automatic emails (orders, intake), release wording.
* **Verification Checklist (Done Checks):**
  - [ ] Write real wording into the "thanks" template, email a test client record that uses your own address, press Send now in Outbox: the email arrives with your name filled in.
  - [ ] A template still containing the draft marker cannot be queued.
* **Undo:** Revert the pull request.

## Phase 9: Portfolio polish

### Ticket #T-36: Portraits page: sharper tiles and a newest-first view
* **Assigned Model:** Sonnet
* **Size:** M
* **Target File(s):** `build.py`, `static/css/style.css`, `static/js/sort.js` (new, small)
* **The Problem / Goal:** Portrait tiles use the 400px thumbnail in boxes up to about 400px wide, so they look soft on high-density screens, and the page only offers A to Z.
* **Exact Instructions:**
  1. On `/portraits/`, series pages and tag pages give card images `srcset="<t.jpg> 400w, <l.jpg> 1000w"` and a `sizes` value that matches the card grid (about 50vw on phones, 33vw on tablets, 300px on wide screens). Keep `src` as the thumbnail and keep `loading="lazy"`.
  2. On `/portraits/` add two buttons above the grid, "A to Z" and "Newest", as a toggle (`aria-pressed`). Each card gets `data-title` and `data-date`. `sort.js` reorders the existing cards in place; A to Z is the default and works without JavaScript.
  3. No inline script or style (CSP).
* **Guardrails (Do Not Touch):** The home strip, Events tiles, gallery grids, the search page's own card rendering.
* **Verification Checklist (Done Checks):**
  - [ ] On a phone, portrait tiles look sharp.
  - [ ] "Newest" puts the most recent session first; "A to Z" restores the alphabetical order.
* **Undo:** Revert the pull request.

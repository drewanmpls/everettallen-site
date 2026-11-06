# Everett Allen Photography site

Static site (no WordPress). `python3 build.py` writes the finished site to `dist/`.

- `content/galleries/*.json`: one file per gallery (title, category, date, cover, description, photo list)
- `content/site.json`: tagline, contact text, social links
- `static/`: CSS, JS, `contact/send.php` (contact form mailer), copied as-is into `dist/`
- `scripts/extract_wp.py`: one-time import from the WordPress export (already run)
- `scripts/copy-images.sh`: run on the DreamHost server to copy the existing photos into `photos/`

Photos are NOT in this repo (about 2,700 files). They live on the server in `photos/<gallery>/<id>-t.jpg` (400px thumb) and `<id>-l.jpg` (1000px, shown in the lightbox).

## First deploy to new.everettallenphotography.com
1. Build: `python3 build.py`, then upload the contents of `dist/` (including the hidden `.htaccess`) to the new subdomain's folder.
2. On the server (SSH), from that folder: `bash copy-images.sh ~/everettallenphotography.com/wp-content/uploads` (upload `scripts/copy-images.sh` first). It copies photos, hero slideshow images and the logo into `photos/` and `assets/`.
3. Check the site, then go live the same way as the other sites (back up old WordPress files and database first).

Old WordPress URLs (`/client-galleries/gallery/...`) are redirected by `.htaccess`.

## Automatic deploys
Every push to `main` runs `.github/workflows/deploy.yml`: it builds the site and uploads it to DreamHost over SSH (adds and updates files only, never deletes).
- Secret `DEPLOY_SSH_KEY`: private key whose public half is in the DreamHost user's `~/.ssh/authorized_keys`.
- Repository variable `DEPLOY_DIR` (optional): folder to upload to. Default is `new.everettallenphotography.com` (staging). Set it to `everettallenphotography.com` to deploy to the live site.

Last deploy test: 2026-10-29

## Private client galleries

Lives in `static/client/` (PHP + SQLite, no WordPress). Private data is stored **outside** the public web folder at `~/everettallen-private/<hostname>/` (database, untouched originals, watermarked copies).

- Admin: `/client/admin/`. First visit asks for a setup code (the file `setup-code.txt` in the private folder) and lets you choose a password.
- Client link: `/client/g/<token>/`, optionally password protected. Clients see watermarked previews, can favorite, comment, and download Free photos.
- Each gallery has an **included photos** count (free picks), and two prices for extra photos: **web size** (2048 px, for social) and **full resolution** (original, for printing). Per-photo overrides are in the admin (blank = gallery default, 0 = Free). Included picks get both sizes. A client can undo a pick until it has been downloaded. Paid photos unlock when an order is marked paid in the admin (a paid web order unlocks web only; a paid full order unlocks both).
- Payments are not wired to a processor yet. `static/client/payments.php` is the single place to add one; until then "Request this photo" emails Andrew.
- The white logo at `/assets/logo-white.png` is used for the watermark; without it a text watermark is used.

### Clients and intake
- New-client form (public, share the link): `/client/new/`. It saves into the private database, emails Andrew, and has a photo-release option that gives a discount. The release wording, discount percent and services come from the Pages CMS (Services and rates).
- Admin: `/client/admin/?a=clients` lists clients with status, private notes, the signed release (exact wording, signer, date), and their galleries. "Create gallery" pre-fills the package size from the service they chose.

### Model releases and Google Drive
- Signing the release on `/client/new/` stores the signature, the exact wording, the date and the signer privately, builds a PDF, and sends it to Google Drive through a small Apps Script (`scripts/drive-release-receiver.gs`). Deploy that script as a web app, then paste its address and secret into Admin > Settings.
- Files land in `EAP / Model Releases` named like `ER20261101-Name-00002.pdf`.
- In the admin, a client's page shows the PDF, the Drive status, and fields for shoot date, location and "ID checked in person". Updating them rebuilds the PDF (and re-saves it to Drive, replacing the earlier copy).
- Release wording, discount, and the gallery question are edited in the Pages CMS under "Services and rates".

## Payments (Stripe, with manual backup)
Admin > Settings > Payments holds the Stripe secret key, the webhook signing secret and the "use Stripe" switch. The webhook address is `/client/stripe-webhook.php` (event `checkout.session.completed`). If Stripe is off, missing, under $0.50 or errors, requests fall back to the manual flow: Andrew gets an email, the client gets the "how to pay manually" text from Settings, and "Mark paid" in the gallery unlocks the download and emails the client.

## Publishing photos from Lightroom
`scripts/eap-publish.py` turns a folder of exported JPEGs into a gallery: it makes the 400px and 1000px copies, uploads them to the server over SSH, writes `content/galleries/<name>.json`, then commits and pushes. Run it with `python3 scripts/eap-publish.py ~/EAP-Publish/<gallery-name>` (use `--dry-run` to preview). Re-running updates the gallery: new or edited photos are redone, photos removed from the folder drop out of the gallery. Title, date, series and description are filled in afterwards in Pages CMS. Settings (server, repo location) can be overridden in `~/.eap-publish.json`.

In Lightroom Classic, create a Publish Service of type Hard Drive pointing at `~/EAP-Publish/`, one collection per gallery, JPEG, sRGB, long edge 2000 px, metadata "Copyright & Contact Info Only" (keeps GPS and camera data off the web).

### Full-size originals (for paid downloads)

`eap-publish.py` also uploads your full-size exports to a private folder on the server (`~/everettallen-private/masters/<gallery>/`, outside the web root, never public). Re-running the script on a folder only sends new or changed files. Buyers of a photo get an emailed link to a small private "order gallery" with their files; set prices and switch selling on in Admin > Settings > Payments.

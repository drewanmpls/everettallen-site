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

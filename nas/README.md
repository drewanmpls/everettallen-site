# NAS folder watcher

Runs in Docker on the NAS. Watches the EAP-Publish folder; rename a finished export folder so it ends in `-READY`
and the photos are published to the site (resized, corner logo, private originals kept) within about 20 seconds
per folder plus upload time. The folder is then renamed `-PUBLISHED` (or `-FAILED` with an ERROR.txt).

Setup: copy this `nas` folder to the NAS, copy `.env.example` to `.env`, fill it in, then `docker compose up -d`.
Key: Admin > Settings > "Folder watcher key (NAS)". Needs the GitHub token saved in Settings too (Publish page setup).

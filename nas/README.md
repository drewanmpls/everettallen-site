# NAS folder watcher

Runs in Docker on the Ugreen NAS. Watches the EAP-Publish folder; rename a finished export folder so it ends in `-READY`
and the photos are published to the site (resized, corner logo, private originals kept) within about 20 seconds
per folder plus upload time. The folder is then renamed `-PUBLISHED` (or `-FAILED` with an ERROR.txt).
It also reports to the "NAS folder watcher" card at the bottom of Admin > Publish once a minute.

## How it runs on the NAS

The NAS does not keep a copy of `watcher.py`. In the Ugreen Docker app it is a **Project** named `eap-watcher`
(its compose file is `Shared folder/docker/eap-watcher/docker-compose.yaml`). Every time the container starts it
downloads the current `nas/watcher.py` from the `main` branch on GitHub and runs it. This only works because the
repository is public; making it private would stop the watcher.

`docker-compose.yml` in this folder matches that project, with placeholders where the NAS file has the real
values: the folder watcher key (`EA_TOKEN`) and the NAS user name in the watch folder path. Never commit the real key.

## Update the watcher after changing `watcher.py`

1. Merge the change to `main`.
2. On the NAS: Docker app > Project > eap-watcher > **Stop**, then **Start**.
3. Logs tab: the first line ends with `(watcher <VERSION>)`, matching `VERSION` in `watcher.py`.
4. Within a minute the card on Admin > Publish says "Last heard from" with the new version.

## Set it up from scratch

1. Admin > Settings > Publishing > "Folder watcher key (NAS)" > Make a key. Copy it right away (it is shown once).
   The GitHub token must be saved there too.
2. Ugreen Docker app > Project > Create. Name it `eap-watcher` and paste `docker-compose.yml` from this folder.
3. Replace `paste-your-key-here` with the key, and the watch folder path with the NAS's `EAP-Publish` folder.
4. Start the project and check the Logs tab and the card as above.

To change the key later: make a new one in Settings, paste it into the project's compose file on the NAS, then Stop and Start.

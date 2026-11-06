#!/usr/bin/env python3
"""Folder watcher for the NAS. Publishes finished Lightroom export folders to everettallenphotography.com.

How it works: every few seconds it looks in WATCH_DIR for sub-folders (one per gallery) that contain a file
named READY. It uploads each JPEG to the site's Publish page (the server makes the web copies, the corner logo and
keeps the full-size original private), then saves the gallery. Safe to run again on the same folder: photos with the
same file name are replaced, new ones are added.

To publish: finish the export, then rename the folder so it ends with -READY (for example
"2026-11-05 TCLF Thursday Atons Kickoff-READY"). Or leave the name alone and drop an empty file named READY inside it.
Folder name = gallery. "2026-11-05 TCLF Thursday Atons Kickoff" becomes the title "TCLF Thursday Atons Kickoff"
(the leading date is used as the date) and the web address 2026-11-05-tclf-thursday-atons-kickoff.
To file it under Portraits, end the name with -PORTRAITS-READY (default is Events).

When it finishes the folder is renamed to end with -PUBLISHED. If something fails it is renamed to end with -FAILED and
ERROR.txt inside explains why. To add more photos later, put them in the folder and rename it back to -READY.
Only the Python standard library is used.
"""
import os, re, sys, time, json, uuid, datetime, urllib.request, urllib.error

SITE = os.environ.get("SITE_URL", "https://www.everettallenphotography.com").rstrip("/")
TOKEN = os.environ.get("EA_TOKEN", "").strip()
WATCH = os.environ.get("WATCH_DIR", "/watch")
EVERY = int(os.environ.get("POLL_SECONDS", "20"))
ENDPOINT = SITE + "/client/admin/"


def log(*a):
    print(time.strftime("%Y-%m-%d %H:%M:%S"), *a, flush=True)


def slugify(t):
    return re.sub(r"-+", "-", re.sub(r"[^a-z0-9]+", "-", t.lower())).strip("-")


def post(fields, filepath=None, timeout=900):
    b = uuid.uuid4().hex
    parts = []
    for k, v in fields.items():
        parts.append(f'--{b}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
    if filepath:
        name = os.path.basename(filepath).replace('"', "")
        parts.append(f'--{b}\r\nContent-Disposition: form-data; name="photo"; filename="{name}"\r\nContent-Type: image/jpeg\r\n\r\n'.encode())
        with open(filepath, "rb") as f:
            parts.append(f.read())
        parts.append(b"\r\n")
    parts.append(f"--{b}--\r\n".encode())
    body = b"".join(parts)
    req = urllib.request.Request(ENDPOINT, data=body, method="POST", headers={
        "Content-Type": f"multipart/form-data; boundary={b}", "X-EA-Token": TOKEN, "User-Agent": "eap-nas-watcher"})
    try:
        with urllib.request.urlopen(req, timeout=timeout) as r:
            return json.loads(r.read().decode() or "{}")
    except urllib.error.HTTPError as e:
        try:
            return json.loads(e.read().decode())
        except Exception:
            return {"error": f"HTTP {e.code}"}
    except Exception as e:
        return {"error": f"network problem: {e}"}


READY_RE = re.compile(r"^(.*?)[ _-]*(-portraits)?-ready$", re.I)


def publish(folder, via_file):
    path = os.path.join(WATCH, folder)
    m0 = READY_RE.match(folder)
    base = m0.group(1).strip() if m0 else folder
    cat = "portraits" if (m0 and m0.group(2)) or (via_file and "portraits" in open(os.path.join(path, "READY"), errors="ignore").read().lower()) else "events"
    m = re.match(r"^(\d{4}-\d{2}-\d{2})[ _-]+(.*)$", base)
    date = m.group(1) if m else datetime.date.today().isoformat()
    title = (m.group(2) if m else base).strip() or base
    slug = slugify(base)
    files = sorted((f for f in os.listdir(path) if f.lower().endswith((".jpg", ".jpeg")) and not f.startswith(".")),
                   key=lambda s: [int(x) if x.isdigit() else x.lower() for x in re.split(r"(\d+)", s)])
    if not files:
        raise RuntimeError("The folder is marked ready but has no JPEGs in it")
    log(f"{folder}: publishing {len(files)} photos as {slug}")
    done, bad = [], []
    for i, f in enumerate(files, 1):
        res = {}
        for attempt in range(3):
            res = post({"a": "pubfile", "slug": slug, "title": title}, os.path.join(path, f))
            if res.get("ok"):
                break
            if "Bad key" in str(res.get("error")):
                raise RuntimeError("The site did not accept the key (EA_TOKEN). Make a new one in Admin > Settings.")
            time.sleep(3)
        if res.get("ok"):
            done.append({"id": res["id"], "ratio": res["ratio"]})
            log(f"  {i}/{len(files)} {f} ok")
        else:
            bad.append(f"{f}: {res.get('error')}")
            log(f"  {i}/{len(files)} {f} FAILED: {res.get('error')}")
    if not done:
        raise RuntimeError("No photos could be uploaded. " + "; ".join(bad[:3]))
    res = post({"a": "pubdone", "payload": json.dumps({"slug": slug, "title": title, "date": date, "category": cat, "images": done})})
    if not res.get("ok"):
        raise RuntimeError("Photos uploaded but the gallery was not saved: " + str(res.get("error")))
    note = f"Published {len(done)} photos to {SITE}{res['url']} on {time.strftime('%Y-%m-%d %H:%M')}.\n"
    if bad:
        note += "Skipped:\n" + "\n".join(bad) + "\n"
    with open(os.path.join(path, "PUBLISHED.txt"), "w") as f:
        f.write(note)
    for junk in ("READY", "ERROR.txt"):
        if os.path.exists(os.path.join(path, junk)):
            os.remove(os.path.join(path, junk))
    rename(folder, base + "-PUBLISHED")
    log(f"{folder}: done. {note.strip()}")


def rename(folder, new):
    src = os.path.join(WATCH, folder)
    dst = os.path.join(WATCH, new)
    n = 2
    while os.path.exists(dst):
        dst = os.path.join(WATCH, f"{new} {n}"); n += 1
    os.rename(src, dst)


def main():
    if not TOKEN:
        sys.exit("EA_TOKEN is not set. Make a key in Admin > Settings and put it in the .env file.")
    log(f"Watching {WATCH} and publishing to {SITE}")
    while True:
        try:
            for folder in sorted(os.listdir(WATCH)):
                full = os.path.join(WATCH, folder)
                if folder.startswith(".") or not os.path.isdir(full):
                    continue
                by_name = bool(READY_RE.match(folder))
                by_file = os.path.isfile(os.path.join(full, "READY"))
                if not (by_name or by_file):
                    continue
                try:
                    publish(folder, by_file and not by_name)
                except Exception as e:
                    log(f"{folder}: ERROR {e}")
                    with open(os.path.join(full, "ERROR.txt"), "w") as f:
                        f.write(f"{time.strftime('%Y-%m-%d %H:%M')}\n{e}\nFix the problem, then rename the folder back to end with -READY to try again.\n")
                    if os.path.exists(os.path.join(full, "READY")):
                        os.remove(os.path.join(full, "READY"))
                    m0 = READY_RE.match(folder)
                    rename(folder, (m0.group(1).strip() if m0 else folder) + "-FAILED")
        except FileNotFoundError:
            log(f"{WATCH} is not there yet")
        time.sleep(EVERY)


if __name__ == "__main__":
    main()

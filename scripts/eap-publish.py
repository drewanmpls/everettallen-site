#!/usr/bin/env python3
"""Publish a folder of exported Lightroom JPEGs as a website gallery.

    python3 scripts/eap-publish.py ~/EAP-Publish/2026-tcl-friday

What it does (safe to re-run; it only adds and updates):
  1. Makes a 400px thumbnail (-t.jpg) and a 1000px lightbox photo (-l.jpg) of each JPEG.
  2. Uploads them to the server at photos/<gallery>/ over SSH (rsync).
  3. Creates or updates content/galleries/<gallery>.json (photo list, ratios, cover).
  4. Commits and pushes, so the site rebuilds. Title, date, series and description
     are filled in afterwards in Pages CMS (new galleries get placeholders).

The folder name is the gallery name (2026-tcl-friday becomes the slug).
Zero dependencies on a Mac: it uses the built-in `sips` and `rsync`.
Settings can be overridden in ~/.eap-publish.json (keys: host, remote_dir, repo, ssh_key).
"""
import argparse, json, os, re, shutil, subprocess, sys, tempfile, datetime

DEFAULTS = {
    "host": "dh_dbvmkc@iad1-shared-b7-19.dreamhost.com",
    "remote_dir": "~/new.everettallenphotography.com/photos",
    "repo": "~/everettallen-site",
    "ssh_key": "",  # e.g. ~/.ssh/everettallen_deploy (skips the password prompt)
}
THUMB, LARGE = 400, 1000
STATE = ".eap-publish.json"  # remembers which photo got which number, inside the folder


def cfg():
    c = dict(DEFAULTS)
    p = os.path.expanduser("~/.eap-publish.json")
    if os.path.exists(p):
        c.update(json.load(open(p)))
    return c


def slugify(t):
    return re.sub(r"[^a-z0-9]+", "-", t.lower()).strip("-")


def run(cmd, **kw):
    return subprocess.run(cmd, check=True, text=True, capture_output=True, **kw).stdout


def size_of(path):
    """(width, height) honoring orientation."""
    try:
        from PIL import Image, ImageOps
        with Image.open(path) as im:
            return ImageOps.exif_transpose(im).size
    except ImportError:
        out = run(["sips", "-g", "pixelWidth", "-g", "pixelHeight", path])
        w = int(re.search(r"pixelWidth: (\d+)", out).group(1))
        h = int(re.search(r"pixelHeight: (\d+)", out).group(1))
        return w, h


def make(src, dst, px, quality):
    try:
        from PIL import Image, ImageOps
        with Image.open(src) as im:
            im = ImageOps.exif_transpose(im).convert("RGB")  # drops EXIF/GPS
            im.thumbnail((px, px), Image.LANCZOS)
            im.save(dst, "JPEG", quality=quality, optimize=True)
    except ImportError:
        run(["sips", "-Z", str(px), "-s", "format", "jpeg", "-s", "formatOptions", str(quality), src, "--out", dst])


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("folder", help="folder of exported JPEGs")
    ap.add_argument("--slug", help="gallery name in the URL (default: folder name)")
    ap.add_argument("--title", help="title for a NEW gallery (default: made from the folder name)")
    ap.add_argument("--category", choices=["events", "portraits"], default="events", help="for a NEW gallery")
    ap.add_argument("--date", help="YYYY-MM-DD for a NEW gallery (default: today)")
    ap.add_argument("--no-upload", action="store_true", help="skip the server upload")
    ap.add_argument("--no-push", action="store_true", help="write the gallery file but do not commit or push")
    ap.add_argument("--local-dest", help="copy to this folder instead of the server (testing)")
    ap.add_argument("--dry-run", action="store_true", help="show what would happen, change nothing")
    a = ap.parse_args()
    c = cfg()

    folder = os.path.abspath(os.path.expanduser(a.folder))
    if not os.path.isdir(folder):
        sys.exit(f"Folder not found: {folder}")
    slug = slugify(a.slug or os.path.basename(folder))
    repo = os.path.expanduser(c["repo"])
    gpath = os.path.join(repo, "content", "galleries", slug + ".json")
    if not os.path.isdir(os.path.join(repo, "content", "galleries")):
        sys.exit(f"Can't find the website repo at {repo}. Set \"repo\" in ~/.eap-publish.json")

    files = sorted(f for f in os.listdir(folder) if f.lower().endswith((".jpg", ".jpeg")) and not f.startswith("."))
    if not files:
        sys.exit("No JPEGs in that folder.")

    spath = os.path.join(folder, STATE)
    state = json.load(open(spath)) if os.path.exists(spath) else {}
    used = {v["id"] for v in state.values()}
    nxt = max([int(i) for i in used] + [0]) + 1
    # a gallery that already exists on the site keeps its numbers too
    existing = json.load(open(gpath)) if os.path.exists(gpath) else None
    if existing and not state:
        nxt = max([int(i["id"]) for i in existing["images"] if i["id"].isdigit()] + [0]) + 1

    todo, images = [], []
    for f in files:
        p = os.path.join(folder, f)
        sig = [os.path.getsize(p), int(os.path.getmtime(p))]
        e = state.get(f)
        if e is None:
            e = {"id": "%03d" % nxt}
            nxt += 1
        if e.get("sig") != sig:
            todo.append((f, e["id"], sig))
        state[f] = e
        images.append((f, e))
    gone = [f for f in list(state) if f not in files]
    for f in gone:
        del state[f]

    print(f"Gallery: {slug}  ({'update' if existing else 'NEW'})")
    print(f"{len(files)} photos in folder, {len(todo)} new or changed, {len(gone)} removed from the gallery")
    if a.dry_run:
        return

    if todo:
        tmp = tempfile.mkdtemp(prefix="eap-")
        out = os.path.join(tmp, slug)
        os.makedirs(out)
        for n, (f, pid, sig) in enumerate(todo, 1):
            p = os.path.join(folder, f)
            w, h = size_of(p)
            state[f].update(ratio=round(w / h, 4), sig=sig)
            make(p, os.path.join(out, pid + "-t.jpg"), THUMB, 80)
            make(p, os.path.join(out, pid + "-l.jpg"), LARGE, 84)
            print(f"\r  resized {n}/{len(todo)}", end="", flush=True)
        print()
        if a.local_dest:
            os.makedirs(a.local_dest, exist_ok=True)
            shutil.copytree(out, os.path.join(a.local_dest, slug), dirs_exist_ok=True)
            print("Copied to", a.local_dest)
        elif not a.no_upload:
            dest = f"{c['host']}:{c['remote_dir']}/{slug}/"
            sshc = ["ssh"]
            if c.get("ssh_key"):
                sshc += ["-i", os.path.expanduser(c["ssh_key"]), "-o", "IdentitiesOnly=yes"]
            subprocess.run(sshc + [c["host"], f"mkdir -p {c['remote_dir']}/{slug}"], check=True)
            subprocess.run(["rsync", "-a", "--chmod=D755,F644", "-e", " ".join(sshc), out + "/", dest], check=True)
            print("Uploaded to the server.")
        shutil.rmtree(tmp, ignore_errors=True)

    imgs = [{"id": e["id"], "ratio": e["ratio"]} for f, e in images if "ratio" in e]
    if len(imgs) != len(images):  # state from an older run without ratios: read them now
        for f, e in images:
            if "ratio" not in e:
                w, h = size_of(os.path.join(folder, f))
                e["ratio"] = round(w / h, 4)
        imgs = [{"id": e["id"], "ratio": e["ratio"]} for f, e in images]

    if existing:
        g = existing
        g["images"] = imgs
        if g.get("cover") not in {i["id"] for i in imgs}:
            g["cover"] = imgs[0]["id"]
    else:
        d = a.date or datetime.date.today().isoformat()
        title = a.title or " ".join(w.capitalize() if not w.isdigit() else w for w in slug.split("-"))
        g = {"title": title, "category": a.category, "year": d[:4], "date": d,
             "cover": imgs[0]["id"], "description": "", "images": imgs}
    os.makedirs(os.path.dirname(gpath), exist_ok=True)
    with open(gpath, "w") as fh:
        json.dump(g, fh, indent=1, ensure_ascii=False)
        fh.write("\n")
    json.dump(state, open(spath, "w"), indent=1)
    print("Wrote", gpath)

    if a.no_push:
        return
    git = ["git", "-C", repo]
    run(git + ["pull", "--rebase", "--autostash"])
    run(git + ["add", gpath])
    if run(git + ["status", "--porcelain", gpath]).strip():
        run(git + ["commit", "-m", f"Gallery: {slug} ({len(imgs)} photos)"])
        run(git + ["push"])
        print("Pushed. The site rebuilds in a minute or two.")
    else:
        print("Gallery file unchanged; nothing to push.")
    if not existing:
        print("Next: open Pages CMS > Galleries and fill in the title, date, series and description.")


if __name__ == "__main__":
    main()

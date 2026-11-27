# Local test copy of the admin

Runs the whole site and the admin on this Mac with fake data, so admin changes can be checked before they go live.
Nothing in `dev/` is uploaded: `build.py` only copies `static/`, and the fake data lives in `dev/.local/` (ignored by git).

```bash
bash dev/run.sh
```

Then open http://127.0.0.1:8090/client/admin/ and log in with the local practice password `local-practice-only`.
That password only exists in the fake local database.

- `bash dev/run.sh reset` wipes the fake data and starts over.
- `PORT=8091 bash dev/run.sh` uses another port.
- Stop it with Control-C.

## What's in the fake data
8 clients (one or more in every status, two with signed releases), 4 client galleries with numbered sample photos,
favorites, comments, package picks, photo requests (open, paid, declined), 2 public shop orders and 120 days of visitor stats.
All names are made up and every email ends in `example.test`, which can never be delivered.

## Safety
- Email is written to `dev/.local/mail.log` instead of being sent.
- GitHub and Stripe calls go to a dead local address, so nothing leaves the Mac even if a real key is typed into Settings.
- The seed script refuses to run unless the private folder is inside `dev/`.

## Not included
Public gallery photos are not in the repo, so public gallery pages show blank tiles locally. The admin does not need them.

#!/bin/bash
# Runs the site and admin on this Mac with fake data. Nothing here is uploaded: build.py only copies static/.
#   bash dev/run.sh          build, add fake data the first time, start at http://127.0.0.1:8090/client/admin/
#   bash dev/run.sh reset    same, but wipe the local fake data first
# Safety: email is written to dev/.local/mail.log instead of being sent, and GitHub and Stripe calls go to a dead
# local address, so nothing leaves this Mac even if you type a real key into Settings.
set -e
cd "$(dirname "$0")/.."
LOCAL="$PWD/dev/.local"
PORT="${PORT:-8090}"

if [ "$1" = "reset" ]; then rm -rf "$LOCAL"; echo "Local fake data wiped."; fi
mkdir -p "$LOCAL/data" "$LOCAL/masters"

export EA_PRIVATE="$LOCAL/data"
export EA_MASTERS="$LOCAL/masters"
export EA_GITHUB_API="http://127.0.0.1:9"
export EA_STRIPE_BASE="http://127.0.0.1:9/"
export EA_ANTHROPIC_BASE="http://127.0.0.1:9/"   # Claude API calls go nowhere locally
PHPOPTS=(-d "sendmail_path=cat >> '$LOCAL/mail.log'" -d upload_max_filesize=64M -d post_max_size=64M -d display_errors=1 -d "error_reporting=E_ALL & ~E_DEPRECATED")

python3 build.py
if [ ! -f "$LOCAL/data/data.sqlite" ]; then php "${PHPOPTS[@]}" dev/seed.php; fi

echo
echo "Admin:   http://127.0.0.1:$PORT/client/admin/   (password is in dev/README.md)"
echo "Emails:  $LOCAL/mail.log"
echo "Stop with Control-C."
exec php "${PHPOPTS[@]}" -S "127.0.0.1:$PORT" -t dist dev/router.php

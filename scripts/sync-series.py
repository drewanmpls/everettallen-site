#!/usr/bin/env python3
"""Keep the Series dropdown in .pages.yml in sync with content/series.json (Pages CMS needs a fixed list)."""
import json, re
slugs = [s["slug"] for s in json.load(open("content/series.json"))["series"] if s.get("slug")]
y = open(".pages.yml").read()
new = re.sub(r"(name: series, label: Recurring event series[^\n]*?values: \[)[^\]]*(\])", lambda m: m.group(1) + ", ".join(slugs) + m.group(2), y, count=1)
if new != y:
    open(".pages.yml", "w").write(new)
    print("updated:", ", ".join(slugs))
else:
    print("already in sync")

# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""Reject missing, stale, or foreign coverage before a Sonar scan."""
import argparse
import fnmatch
import hashlib
import json
from pathlib import Path
import subprocess
import xml.etree.ElementTree as ET


def snapshot(root):
    properties = (root / "sonar-project.properties").read_text()
    excluded = next(line.split("=", 1)[1].split(",") for line in properties.splitlines()
                    if line.startswith("sonar.exclusions="))
    tracked = subprocess.check_output(["git", "ls-files", "-z"], cwd=root).decode().split("\0")
    sources = {name: hashlib.sha256((root / name).read_bytes()).hexdigest()
               for name in tracked if name.endswith((".php", ".js", ".mjs"))
               and not any(fnmatch.fnmatchcase(name, pattern) for pattern in excluded)}
    revision = subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=root, text=True).strip()
    return {"revision": revision, "sources": sources}


def source_file(root, name, sources):
    path = Path(name)
    path = path if path.is_absolute() else root / path
    try:
        relative = path.resolve().relative_to(root.resolve()).as_posix()
    except ValueError as exc:
        raise ValueError("Coverage path is outside the checkout") from exc
    if relative not in sources:
        raise ValueError(f"Coverage source is not in the scanned manifest: {relative}")
    return path


def validate(root, manifest):
    if manifest != snapshot(root):
        raise ValueError("Coverage revision or production source hashes changed during measurement")
    sources = manifest["sources"]
    php_files = [file for name in ["php.xml", "php-browser.xml"]
                 for file in ET.parse(root / "coverage" / name).iter("file")]
    if not php_files:
        raise ValueError("PHP coverage report contains no source files")
    covered = 0
    for file in php_files:
        path = source_file(root, file.attrib["name"], sources)
        limit = len(path.read_text().splitlines())
        for line in file.findall("line"):
            number, count = int(line.attrib["num"]), int(line.attrib["count"])
            if not 1 <= number <= limit or count < 0:
                raise ValueError("PHP coverage has an invalid line or execution count")
            covered += count > 0
    if not covered:
        raise ValueError("PHP coverage contains no executed production lines")
    javascript_files = 0
    javascript_hits = 0
    limit = None
    for line in (root / "coverage/lcov.info").read_text().splitlines():
        if line.startswith("SF:"):
            path = source_file(root, line[3:], sources)
            limit = len(path.read_text().splitlines())
            javascript_files += 1
        elif line.startswith("DA:"):
            number, count = (int(value) for value in line[3:].split(",")[:2])
            if limit is None or not 1 <= number <= limit or count < 0:
                raise ValueError("JavaScript coverage has an invalid line or execution count")
            javascript_hits += count > 0
        elif line == "end_of_record":
            limit = None
    if not javascript_files or not javascript_hits:
        raise ValueError("JavaScript coverage contains no executed production lines")
    return len(php_files), javascript_files


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--root", type=Path, default=Path.cwd())
    parser.add_argument("--snapshot", action="store_true")
    args = parser.parse_args()
    root = args.root.resolve()
    manifest = root / "coverage/revision.json"
    if args.snapshot:
        manifest.parent.mkdir(parents=True, exist_ok=True)
        manifest.write_text(json.dumps(snapshot(root), indent=2) + "\n")
    else:
        php, javascript = validate(root, json.loads(manifest.read_text()))
        print(f"Coverage matches checkout: {php} PHP files, {javascript} JavaScript files")


if __name__ == "__main__":
    main()

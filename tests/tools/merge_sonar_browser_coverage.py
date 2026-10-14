# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""Merge hash-verified disposable HTTP Clover observations for Sonar."""
import argparse
import json
from pathlib import Path, PurePosixPath
import xml.etree.ElementTree as ET
from check_sonar_coverage import snapshot, source_file


def merge(root):
    manifest = json.loads((root / "coverage/revision.json").read_text())
    if manifest != snapshot(root):
        raise ValueError("The scanned checkout changed during coverage collection")
    reports = list((root / "coverage/php-browser").glob("*.xml"))
    if not reports:
        raise ValueError("No native PHP request coverage was collected")
    observations = {}
    for report in reports:
        attestation = json.loads(report.with_suffix(".json").read_text())
        if attestation["revision"] != manifest["revision"]:
            raise ValueError("Native PHP coverage belongs to a different revision")
        for file in ET.parse(report).iter("file"):
            relative = PurePosixPath(file.attrib["name"]).relative_to(PurePosixPath(attestation["root"]))
            if ".." in relative.parts:
                raise ValueError("Native PHP coverage escapes its runtime root")
            name = relative.as_posix()
            if attestation["sources"].get(name) != manifest["sources"].get(name) or name not in manifest["sources"]:
                raise ValueError("Native PHP coverage source hash differs from the checkout")
            path = source_file(root, name, manifest["sources"])
            limit = len(path.read_text().splitlines())
            rows = observations.setdefault(str(path.resolve()), {})
            for line in file.findall("line"):
                number, count = int(line.attrib["num"]), int(line.attrib["count"])
                if not 1 <= number <= limit or count < 0:
                    raise ValueError("Native PHP coverage contains invalid execution counts")
                key = number, line.attrib.get("type", "stmt")
                rows[key] = rows.get(key, 0) + count
    if not any(count for rows in observations.values() for count in rows.values()):
        raise ValueError("Native PHP reports contain no executed production lines")
    document = ET.Element("coverage")
    project = ET.SubElement(document, "project")
    for path, rows in sorted(observations.items()):
        file = ET.SubElement(project, "file", name=path)
        for (number, kind), count in sorted(rows.items()):
            ET.SubElement(file, "line", num=str(number), type=kind, count=str(count))
    ET.ElementTree(document).write(root / "coverage/php-browser.xml", encoding="utf-8", xml_declaration=True)
    return len(reports), len(observations)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--root", type=Path, default=Path.cwd())
    args = parser.parse_args()
    requests, files = merge(args.root.resolve())
    print(f"Native PHP coverage: {requests} requests, {files} source files")


if __name__ == "__main__":
    main()

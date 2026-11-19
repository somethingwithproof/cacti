#!/usr/bin/env python3
"""Combine additions to Cacti's unreleased 1.2.x CHANGELOG during a Git conflict.

Run inside the conflicted checkout, or pass --repo PATH. By default the complete
result goes to stdout. --write replaces only a pristine conflicted working copy.
Inspect the result with git diff before staging the file yourself.
"""

import argparse
from graphlib import CycleError, TopologicalSorter
import os
from pathlib import Path
import re
import stat
import subprocess
import sys
import tempfile


ENTRY = re.compile(
    rb"-(security|issue|feature)#([1-9][0-9]*|GHSA-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}): [^\s].*"
)
KINDS = {b"security": 0, b"issue": 1, b"feature": 2}


def entry_key(line):
    match = ENTRY.fullmatch(line.rstrip(b"\r\n"))
    if match is None:
        return None
    kind, reference = match.groups()
    if reference.isdigit():
        return KINDS[kind], 1, int(reference)
    if kind == b"security":
        return KINDS[kind], 0, reference
    return None


def split_release(contents):
    lines = contents.splitlines(keepends=True)
    if [line.rstrip(b"\r\n") for line in lines[:3]] != [b"Cacti CHANGELOG", b"", b"1.2.x"]:
        raise ValueError("expected the root Cacti CHANGELOG with 1.2.x as its first release")
    for end, line in enumerate(lines[3:], start=3):
        if re.fullmatch(rb"1\.2\.\d+\r?\n", line):
            return lines[:3], lines[3:end], lines[end:]
    raise ValueError("could not identify the first historical release")


def additions(base, side):
    prefix, body, history = side
    if prefix != base[0] or history != base[2]:
        raise ValueError("a heading or historical release changed; resolve manually")
    original = base[1]
    original_lines = set(original)
    position = 0
    added = []
    for line in body:
        if position < len(original) and line == original[position]:
            position += 1
        elif line in original_lines or line in added or entry_key(line) is None:
            raise ValueError("expected only new numbered entries or security advisories; resolve manually")
        else:
            added.append(line)
    if position != len(original):
        raise ValueError("a pre-existing 1.2.x entry changed or was removed; resolve manually")
    return added


def combine(base_blob, ours_blob, theirs_blob):
    base = split_release(base_blob)
    sides = [additions(base, split_release(blob)) for blob in (ours_blob, theirs_blob)]
    if not all(sides):
        raise ValueError("both sides must add release notes")
    # Each side can contribute multiple notes for the same issue. Preserve
    # their relative order, including when the two sides share a note.
    order = TopologicalSorter()
    for side in sorted(sides):
        previous = {}
        for line in side:
            key = entry_key(line)
            if key in previous:
                order.add(line, previous[key])
            else:
                order.add(line)
            previous[key] = line
    try:
        new = sorted(order.static_order(), key=entry_key)
    except CycleError as error:
        raise ValueError("the two sides disagree on the order of notes for one reference") from error
    body = base[1].copy()
    for line in new:
        key = entry_key(line)
        peers = [(i, other) for i, item in enumerate(body) if (other := entry_key(item)) is not None]
        same = [i for i, other in peers if other == key]
        lower = [i for i, other in peers if other < key]
        if same or lower:
            position = (same or lower)[-1] + 1
        elif peers:
            position = peers[0][0]
        else:
            raise ValueError("no numbered release-note group to use as an ordering reference")
        body.insert(position, line)
    return b"".join(base[0] + body + base[2])


def git(repo, *args):
    command = subprocess.run(["git", "-C", repo, *args], capture_output=True)
    if command.returncode:
        raise ValueError(command.stderr.decode(errors="replace").strip() or f"git {args[0]} failed")
    return command.stdout


def resolve(repo, write):
    root = Path(os.fsdecode(git(repo, "rev-parse", "--show-toplevel").rstrip(b"\n")))
    stages = git(root, "ls-files", "--unmerged", "--", "CHANGELOG")
    blobs = {}
    modes = set()
    for record in stages.splitlines():
        metadata, name = record.split(b"\t")
        mode, oid, stage = metadata.split()
        if name != b"CHANGELOG" or mode not in (b"100644", b"100755"):
            raise ValueError("expected a regular root CHANGELOG")
        modes.add(mode)
        blobs[stage] = git(root, "cat-file", "blob", oid)
    if set(blobs) != {b"1", b"2", b"3"} or len(modes) != 1:
        raise ValueError("expected three unmerged Git stages with the same mode for root CHANGELOG")
    result = combine(blobs[b"1"], blobs[b"2"], blobs[b"3"])
    if not write:
        sys.stdout.buffer.write(result)
        return

    # Git's ort strategy snapshots the untouched worktree conflict, including
    # any automatically merged hunks. Never discard manual conflict edits.
    try:
        pristine = git(root, "cat-file", "blob", "AUTO_MERGE:CHANGELOG")
    except ValueError as error:
        raise ValueError("no AUTO_MERGE snapshot; omit --write to inspect the staged result") from error
    target = root / "CHANGELOG"
    mode = target.lstat().st_mode
    if not stat.S_ISREG(mode) or target.read_bytes() != pristine:
        raise ValueError("working CHANGELOG differs from Git's conflict snapshot; omit --write to inspect")
    handle = tempfile.NamedTemporaryFile(dir=root, prefix=".changelog-", delete=False)
    temporary = Path(handle.name)
    try:
        with handle:
            handle.write(result)
            os.fchmod(handle.fileno(), stat.S_IMODE(mode))
        if git(root, "ls-files", "--unmerged", "--", "CHANGELOG") != stages or target.read_bytes() != pristine:
            raise ValueError("CHANGELOG or its index stages changed during resolution")
        os.replace(temporary, target)
    finally:
        temporary.unlink(missing_ok=True)
    print("Updated working CHANGELOG. Review it before staging.", file=sys.stderr)


def main():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--repo", default=".", help="conflicted checkout (default: current directory)")
    parser.add_argument("--write", action="store_true", help="write only the untouched working conflict")
    args = parser.parse_args()
    try:
        resolve(args.repo, args.write)
    except (OSError, ValueError) as error:
        parser.exit(1, f"{parser.prog}: {error}\n")


if __name__ == "__main__":
    main()

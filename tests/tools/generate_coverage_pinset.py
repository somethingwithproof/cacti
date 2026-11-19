#!/usr/bin/env python3
"""Generate a compact replay pinset for explicitly selected verified preview rows."""

import argparse
from collections import Counter
import json
import os
from pathlib import Path
import sys
import tempfile

from apply_coverage_pinset import (COMMON, CONTRACT, FORMAT, checked_json,
                                   feature_paths, foundation, replay, require, scratch)


def recipe(g, path, target, origins, features):
    if target is None:
        return {'path': path, 'delete': True}
    mode, blob = target
    item = {'path': path, 'mode': mode, 'blob': blob}
    candidates = {}
    for name, tree in origins.items():
        old = g.entry(tree, path)
        if old is None:
            continue
        if old[1] == blob:
            return {**item, 'from': name}
        candidates.setdefault(old[1], (name, old))
    if path in features:
        raw = g.git('cat-file', 'blob', blob)
        checked_json(raw)
        return {**item, 'text': raw.decode()}
    require(candidates, f'non-feature path has no existing content for a delta: {path}')
    choices = []
    for old_blob, (name, old) in candidates.items():
        hunks = g.hunks(old_blob, blob)
        g.apply_zero_context(path, old, blob, hunks)
        choices.append((len(hunks.encode()), name, old_blob, hunks))
    _, name, old_blob, hunks = min(choices)
    return {**item, 'from': name, 'before': old_blob, 'hunks': hunks}


def share_entries(rows):
    def key(entry):
        return json.dumps(entry, sort_keys=True, separators=(',', ':'))
    counts = Counter(key(entry) for row in rows.values() for entry in row['entries'])
    shared = {str(i): json.loads(value) for i, value in enumerate(sorted(
        key for key, count in counts.items() if count > 1), 1)}
    aliases = {key(entry): name for name, entry in shared.items()}
    for row in rows.values():
        row['shared'] = [aliases[key(entry)] for entry in row['entries'] if key(entry) in aliases]
        row['entries'] = [entry for entry in row['entries'] if key(entry) not in aliases]
    return shared


def generate(g, preview, directory, selected):
    require(len(selected) == len(set(selected)) and selected, 'select distinct PR numbers')
    base, published, extended = (preview[k] for k in ('base', 'original_foundation', 'foundation'))
    base_tree = g.commit_tree(base)
    published_tree = g.commit_tree(published)
    shared_tree = g.commit_tree(extended)
    require(g.line('rev-list', '--parents', '-1', extended).split() == [extended, published],
            'generic loader is not a direct child of fork foundation')
    shared_changes = g.changed(published_tree, shared_tree)
    require(set(shared_changes) <= {CONTRACT}, 'generic loader changed paths outside the dispatch contract')
    g.check_patch(published_tree, (directory / 'foundation-contracts.patch').read_bytes(), shared_tree)
    pins = {'format': FORMAT, 'object_format': g.fmt, 'base': base, 'fork_foundation': published,
            'fork_foundation_tree': published_tree, 'foundation_tree': shared_tree,
            'loader': [recipe(g, p, g.entry(shared_tree, p), {'published': published_tree}, set())
                       for p in shared_changes], 'shared': {}, 'prs': {}}
    foundation(g, pins)

    numbers = [row['number'] for row in preview['prs']]
    require(len(numbers) == len(set(numbers)), 'duplicate PR in preview')
    lookup = {row['number']: row for row in preview['prs']}
    for n in sorted(selected):
        features = feature_paths(n)
        require(n in lookup, f'PR #{n} absent from preview')
        row = lookup[n]
        require(row['status'] in ('complete', 'partial') and row['base'] == base
                and row['foundation'] == extended, f'PR #{n} has no verified preview at these pins')
        head, mb, commit, tree = (row[k] for k in ('head', 'merge_base', 'commit', 'tree'))
        require(g.commit_tree(commit) == tree, f'PR #{n} candidate tree mismatch')
        require(g.line('rev-list', '--parents', '-1', commit).split() == [commit, extended],
                f'PR #{n} candidate does not belong to the pinned foundation')
        ordinary = g.ordinary(base, head, mb)
        changed = g.changed(ordinary, tree)
        require(set(changed) <= COMMON | features and sorted(row['changed_paths']) == sorted(changed),
                f'PR #{n} modifies paths outside its integration inventory')
        require(changed, f'PR #{n} has an empty verified migration')
        # Independent evidence supplied by the migration tool: its exported patch must
        # apply to the recomputed ordinary merge and reproduce the supplied candidate.
        g.check_patch(ordinary, (directory / f'{n}.patch').read_bytes(), tree)
        origins = {'source': ordinary, 'head': g.commit_tree(head), 'base': base_tree,
                   'merge_base': g.commit_tree(mb), 'foundation': shared_tree, 'published': published_tree}
        pins['prs'][str(n)] = {
            'status': row['status'], 'head': head, 'merge_base': mb, 'source_tree': ordinary, 'tree': tree,
            'entries': [recipe(g, p, g.entry(tree, p), origins, features) for p in changed],
        }
    pins['shared'] = share_entries(pins['prs'])
    for n in sorted(selected):
        replay(g, pins, n)
    return pins


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--repo', required=True, type=Path)
    parser.add_argument('--preview', required=True, type=Path, help='verified preview.json next to exported patches')
    parser.add_argument('--output', required=True, type=Path, help='destination pinset JSON file')
    parser.add_argument('--pr', required=True, nargs='+', action='extend', type=int)
    args = parser.parse_args()
    try:
        with scratch(args.repo) as g:
            pins = generate(g, checked_json(args.preview.read_bytes()), args.preview.parent, args.pr)
        data = (json.dumps(pins, indent=2, ensure_ascii=False) + '\n').encode()
        fd, name = tempfile.mkstemp(dir=args.output.parent, prefix='.pinset-')
        try:
            with os.fdopen(fd, 'wb') as stream:
                stream.write(data)
                stream.flush()
                os.fsync(stream.fileno())
            os.replace(name, args.output)
        finally:
            Path(name).unlink(missing_ok=True)
        print(f'Verified {len(args.pr)} migrations in {len(data)} bytes.', file=sys.stderr)
    except (OSError, ValueError, TypeError, KeyError, AttributeError) as error:
        print(f'Pinset generation failed: {error}', file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    sys.exit(main())

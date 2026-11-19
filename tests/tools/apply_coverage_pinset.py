#!/usr/bin/env python3
"""Emit a verified migration patch from pinned Git inputs, entirely offline."""

import argparse
from contextlib import contextmanager
import functools
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile

CONTRACT = 'tests/tools/test_request_coverage_dispatch.py'
COMMON = {
    '.github/workflows/pr-changed-coverage.yml', '.github/workflows/pr-php-coverage.yml',
    'tests/Coverage/features/README.md', 'tests/Coverage/features/constant-time-guards.json',
    'tests/tools/run_feature_coverage.py', 'tests/tools/test_feature_coverage.py',
    'tests/tools/run_pr_change_tests.sh', 'tests/tools/run_request_coverage.sh', CONTRACT,
}
FORMAT = 'cacti-coverage-pinset-v1'


class PinError(ValueError):
    pass


def require(condition, message):
    if not condition:
        raise PinError(message)


def feature_paths(number):
    require(type(number) is int and number > 0, 'invalid PR number')
    return {f'tests/Coverage/{kind}/pr-{number}.json' for kind in ('features', 'dispatch')}


def checked_json(data):
    def unique(pairs):
        result = {}
        for key, value in pairs:
            require(key not in result, 'duplicate JSON key')
            result[key] = value
        return result
    return json.loads(data, object_pairs_hook=unique)


def git_command(directory, env, *args, data=None):
    result = subprocess.run(['git', '-C', str(directory), '-c', 'core.hooksPath=/dev/null',
                             '-c', 'core.autocrlf=false', *args],
                            env=env, input=data, capture_output=True)
    if result.returncode:
        raise PinError(f'git {args[0]} failed: {result.stderr.decode(errors="replace").strip()[:400]}')
    return result.stdout


class ScratchGit:
    def __init__(self, directory, env, fmt):
        self.directory = directory
        self.env = env
        self.fmt = fmt
        self.length = {'sha1': 40, 'sha256': 64}[fmt]

    def git(self, *args, data=None):
        return git_command(self.directory, self.env, *args, data=data)

    def line(self, *args, data=None):
        return self.git(*args, data=data).decode('ascii').strip()

    def oid(self, oid):
        require(isinstance(oid, str) and re.fullmatch('[0-9a-f]{%d}' % self.length, oid),
                'invalid full Git object pin')
        return oid

    @functools.lru_cache(maxsize=512)
    def object(self, oid, kind):
        self.oid(oid)
        require(self.line('cat-file', '-t', oid) == kind, f'expected {kind} {oid}')
        return oid

    @functools.lru_cache(maxsize=256)
    def commit_tree(self, commit):
        self.object(commit, 'commit')
        return self.line('rev-parse', commit + '^{tree}')

    @functools.lru_cache(maxsize=2048)
    def entry(self, tree, path):
        record = self.git('ls-tree', '-z', tree, '--', path)
        if not record:
            return None
        meta, filename = record.rstrip(b'\0').split(b'\t', 1)
        mode, kind, oid = meta.decode('ascii').split()
        require(filename.decode() == path and kind == 'blob' and mode in ('100644', '100755'),
                f'unsupported entry at {path}')
        return mode, oid

    def changed(self, before, after):
        data = self.git('diff', '--no-ext-diff', '--no-textconv', '--no-renames',
                        '--name-only', '-z', before, after, '--')
        return [p.decode() for p in data.split(b'\0') if p]

    def ordinary(self, base, head, merge_base):
        for commit in (base, head, merge_base):
            self.object(commit, 'commit')
        require(self.line('merge-base', '--all', base, head) == merge_base,
                'upstream and PR head do not have the pinned unique merge base')
        return self.oid(self.line('merge-tree', '--write-tree', '--merge-base=' + merge_base,
                                  base, head))

    def update(self, path, entry):
        mode, oid = entry if entry else ('0', '0' * self.length)
        self.git('update-index', '-z', '--index-info', data=f'{mode} {oid}\t{path}\0'.encode())

    def hunks(self, before, after):
        diff = self.git('diff', '--no-ext-diff', '--no-textconv', '--no-color',
                        '--minimal', '--unified=0', before, after)
        start = diff.find(b'\n@@ ')
        require(start >= 0, 'expected a nonempty unified text diff')
        return diff[start + 1:].decode()

    def apply_zero_context(self, path, source, target_blob, hunks):
        require(isinstance(hunks, str) and hunks.startswith('@@ '), 'missing integration hunks')
        require(all(line.startswith(('@@ ', ' ', '-', '+', '\\ No newline at end of file'))
                    for line in hunks.splitlines()), 'unexpected integration patch header')
        mode, before = source
        header = (f'diff --git a/{path} b/{path}\nindex {before}..{target_blob} {mode}\n'
                  f'--- a/{path}\n+++ b/{path}\n')
        # Isolate each reconstruction, even when other paths were already materialized.
        self.git('read-tree', '--empty')
        self.update(path, source)
        self.git('apply', '--cached', '--unidiff-zero', '--whitespace=nowarn', '-',
                 data=(header + hunks).encode())
        require(self.line('rev-parse', ':' + path) == target_blob, f'patched blob mismatch at {path}')
        require(self.hunks(before, target_blob) == hunks, f'noncanonical or unused hunk data at {path}')
        changed = self.git('ls-files', '-z').split(b'\0')
        require(changed == [path.encode(), b''], 'integration patch affected an additional path')

    def patch(self, before, after):
        return self.git('diff', '--no-ext-diff', '--no-textconv', '--no-renames', '--no-color',
                        '--full-index', '--src-prefix=a/', '--dst-prefix=b/', before, after, '--')

    def check_patch(self, starting_tree, patch, target_tree):
        self.git('read-tree', starting_tree)
        if patch:
            self.git('apply', '--cached', '--whitespace=nowarn', '-', data=patch)
        require(self.line('write-tree') == target_tree, 'patch does not produce the pinned tree')


@contextmanager
def scratch(repo):
    env = {k: v for k, v in os.environ.items() if not k.startswith('GIT_')}
    env.update(GIT_CONFIG_NOSYSTEM='1', GIT_CONFIG_GLOBAL=os.devnull, GIT_ATTR_NOSYSTEM='1',
               GIT_NO_REPLACE_OBJECTS='1', GIT_NO_LAZY_FETCH='1', GIT_TERMINAL_PROMPT='0',
               GIT_ALLOW_PROTOCOL='', LC_ALL='C')
    objects = git_command(repo, env, 'rev-parse', '--path-format=absolute', '--git-path', 'objects').rstrip(b'\n')
    require(b'\n' not in objects, 'unsupported object directory')
    fmt = git_command(repo, env, 'rev-parse', '--show-object-format').decode().strip()
    require(fmt in ('sha1', 'sha256'), 'unsupported Git object format')
    with tempfile.TemporaryDirectory(prefix='coverage-pinset-') as temp:
        directory = Path(temp)
        git_command(directory, env, 'init', '--bare', '--quiet', '--template=', '--object-format=' + fmt)
        (directory / 'objects/info/alternates').write_bytes(objects + b'\n')
        yield ScratchGit(directory, env, fmt)


def materialize(g, starting_tree, origins, entries, allowed, features):
    updates = {}
    for entry in entries:
        p = entry['path']
        require(p in allowed and p not in updates, f'disallowed or duplicate overlay path: {p}')
        if entry.get('delete') is True:
            require(set(entry) == {'path', 'delete'} and g.entry(starting_tree, p),
                    f'invalid deletion at {p}')
            updates[p] = None
            continue
        blob = g.oid(entry['blob'])
        mode = entry['mode']
        require(mode in ('100644', '100755'), 'unsupported migration file mode')
        if 'text' in entry:
            require(set(entry) == {'path', 'mode', 'blob', 'text'} and p in features and mode == '100644',
                    'only PR-specific JSON can be embedded in full')
            raw = entry['text'].encode()
            value = checked_json(raw)
            require(isinstance(value, list if '/features/' in p else dict), 'invalid feature JSON')
            require(g.line('hash-object', '-w', '--stdin', data=raw) == blob, f'JSON blob mismatch at {p}')
        else:
            origin = entry['from']
            require(origin in origins, f'unknown blob source at {p}')
            old = g.entry(origins[origin], p)
            require(old is not None, f'missing source blob at {p}')
            keys = {'path', 'mode', 'blob', 'from'}
            if 'hunks' in entry:
                require(set(entry) == keys | {'before', 'hunks'} and old[1] == g.oid(entry['before']),
                        f'invalid patch source or fields at {p}')
                g.apply_zero_context(p, old, blob, entry['hunks'])
            else:
                require(set(entry) == keys and old[1] == blob, f'invalid reuse recipe at {p}')
        updates[p] = (mode, blob)
    g.git('read-tree', starting_tree)
    for p, pair in updates.items():
        g.update(p, pair)
    return g.line('write-tree')


def foundation(g, pins):
    require(pins['format'] == FORMAT and pins['object_format'] == g.fmt, 'unsupported pinset format')
    commit = pins['fork_foundation']
    tree = g.commit_tree(commit)
    require(tree == pins['fork_foundation_tree'], 'fork foundation tree mismatch')
    shared = materialize(g, tree, {'published': tree}, pins['loader'], {CONTRACT}, set())
    require(shared == g.oid(pins['foundation_tree']), 'shared loader tree mismatch')
    return tree, shared


def replay(g, pins, number, source_pin=None, result_pin=None):
    published, shared = foundation(g, pins)
    require(str(number) in pins['prs'], 'selected PR is absent from the pinset')
    row = pins['prs'][str(number)]
    base, head, mb = pins['base'], row['head'], row['merge_base']
    ordinary = g.ordinary(base, head, mb)
    require(ordinary == row['source_tree'] and (source_pin is None or source_pin == ordinary),
            'ordinary upstream-plus-PR squash does not match its pin')
    origins = {'source': ordinary, 'head': g.commit_tree(head), 'base': g.commit_tree(base),
               'merge_base': g.commit_tree(mb), 'published': published, 'foundation': shared}
    features = feature_paths(number)
    entries = [pins['shared'][key] for key in row['shared']] + row['entries']
    final = materialize(g, ordinary, origins, entries, COMMON | features, features)
    require(final == g.oid(row['tree']) and (result_pin is None or result_pin == final),
            'final migration tree does not match its pin')
    changed = set(g.changed(ordinary, final))
    require(changed == {entry['path'] for entry in entries}, 'changed-path inventory mismatch')
    patch = g.patch(ordinary, final)
    g.check_patch(ordinary, patch, final)
    return patch


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--repo', required=True, type=Path, help='local clone containing the pinned input commits')
    parser.add_argument('--pinset', required=True, type=Path)
    parser.add_argument('--pr', required=True, type=int)
    parser.add_argument('--source-tree', help='also require this ordinary squash tree')
    parser.add_argument('--expected-tree', help='also require this final tree')
    args = parser.parse_args()
    try:
        with scratch(args.repo) as g:
            patch = replay(g, checked_json(args.pinset.read_bytes()), args.pr, args.source_tree, args.expected_tree)
    except (OSError, KeyError, ValueError, TypeError, AttributeError) as error:
        print(f'Pinset replay failed: {error}', file=sys.stderr)
        return 1
    sys.stdout.buffer.write(patch)
    return 0


if __name__ == '__main__':
    sys.exit(main())

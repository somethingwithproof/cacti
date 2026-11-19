#!/usr/bin/env python3
"""Run a Git merge or rehearsal with Cacti's contributor-side merge drivers."""

import argparse
import os
from pathlib import Path
import shlex
import subprocess
import sys
import tempfile

from resolve_cacti_changelog import combine


COVERAGE_FILES = (
    'tests/tools/run_pr_change_tests.sh',
    'tests/tools/run_request_coverage.sh',
    'tests/tools/test_request_coverage_dispatch.py',
)
DRIVER = 'cacti-registrations'


def merge_file(path: str, base: Path, ours: Path, theirs: Path,
               marker_size: str) -> int:
    """Leave standard conflict markers when a specialized merge is ambiguous."""
    if path not in ('CHANGELOG', *COVERAGE_FILES):
        raise ValueError(f'unsupported merge path: {path}')
    ordinary = subprocess.run(
        ['git', 'merge-file', '-p', '--marker-size', marker_size,
         '-L', 'current', '-L', 'base', '-L', 'incoming',
         '--', ours, base, theirs], stdout=subprocess.PIPE)
    if ordinary.returncode < 0 or ordinary.returncode > 127:
        return 1
    if ordinary.returncode == 0:
        ours.write_bytes(ordinary.stdout)
        return 0
    if path == 'CHANGELOG':
        try:
            result = combine(base.read_bytes(), ours.read_bytes(),
                             theirs.read_bytes())
        except ValueError:
            result = None
    else:
        resolver = Path(__file__).with_name('resolve_coverage_conflict.py')
        specialized = subprocess.run(
            [sys.executable, resolver, path, base, ours, theirs],
            stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        result = specialized.stdout if specialized.returncode == 0 else None
    ours.write_bytes(ordinary.stdout if result is None else result)
    return int(result is None)


def global_attributes(repo: Path) -> bytes:
    """Retain the user's attributes for all other paths during this invocation."""
    configured = subprocess.run(
        ['git', '-C', repo, 'config', '--path', '--get', 'core.attributesFile'],
        stdout=subprocess.PIPE)
    if configured.returncode == 0:
        path = repo / os.fsdecode(configured.stdout.rstrip(b'\n'))
    elif configured.returncode == 1:
        xdg = os.environ.get('XDG_CONFIG_HOME') or Path.home() / '.config'
        path = Path(xdg) / 'git' / 'attributes'
    else:
        raise ValueError('cannot read Git attributes configuration')
    return path.read_bytes() if path.is_file() else b''


def run_git(repo: Path, command: str, args: list[str]) -> int:
    with tempfile.TemporaryDirectory(prefix='cacti-merge-') as directory:
        attributes = Path(directory) / 'attributes'
        rules = ''.join(f'/{p} merge={DRIVER}\n'
                        for p in ('CHANGELOG', *COVERAGE_FILES))
        attributes.write_bytes(global_attributes(repo) + b'\n' + rules.encode())
        driver = shlex.join([sys.executable, str(Path(__file__).resolve()),
                             '--driver']) + ' %P %O %A %B %L'
        return subprocess.run(
            ['git', '-C', repo, '-c', f'core.attributesFile={attributes}',
             '-c', f'merge.{DRIVER}.driver={driver}',
             '-c', f'merge.{DRIVER}.recursive=binary',
             command, *args]).returncode


def main() -> int:
    if sys.argv[1:2] == ['--driver']:
        path, base, ours, theirs, size = sys.argv[2:]
        return merge_file(path, Path(base), Path(ours), Path(theirs), size)
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--repo', type=Path, default=Path.cwd())
    parser.add_argument('command', choices=('merge-tree', 'merge', 'rebase',
                                            'cherry-pick', 'check-attr'))
    parser.add_argument('arguments', nargs=argparse.REMAINDER)
    options = parser.parse_args()
    return run_git(options.repo.resolve(), options.command, options.arguments)


if __name__ == '__main__':
    try:
        sys.exit(main())
    except (OSError, ValueError) as error:
        sys.exit(str(error))

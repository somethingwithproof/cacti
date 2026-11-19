#!/usr/bin/env python3
"""Run the PHP coverage fixtures registered by each feature.

Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""
import argparse
from fnmatch import fnmatchcase
import json
import os
from pathlib import Path
import subprocess
import sys


def string_list(value):
    return isinstance(value, list) and all(isinstance(item, str) and '\0' not in item for item in value)


def registrations(manifest):
    entries = json.loads(manifest.read_text(encoding='utf-8'))
    if not isinstance(entries, list) or not entries:
        raise ValueError(f'{manifest} must contain a nonempty array of fixture registrations')
    for index, entry in enumerate(entries):
        location = f'{manifest}, entry {index + 1}'
        if not isinstance(entry, dict) or entry.keys() - {'fixture', 'native', 'request_paths', 'cases'}:
            raise ValueError(f'{location}: expected a fixture registration with supported keys')
        fixture = entry.get('fixture')
        if not isinstance(fixture, str) or not fixture.startswith('tests/') or not fixture.endswith('.php'):
            raise ValueError(f'{location}: expected a repository-relative PHP fixture under tests/')
        path = Path(fixture)
        if '..' in path.parts or not path.resolve().is_relative_to(Path('tests').resolve()) or not path.is_file():
            raise ValueError(f'{location}: fixture is missing or outside tests/: {fixture}')
        native = entry.get('native', False)
        paths = entry.get('request_paths')
        if not isinstance(native, bool) or ('request_paths' in entry and not string_list(paths)):
            raise ValueError(f'{location}: native must be boolean and request_paths must be an array of globs')
        if not native and paths is None:
            raise ValueError(f'{location}: select native, request_paths, or both')
        cases = entry.get('cases', [[]])
        if not isinstance(cases, list) or not cases or not all(string_list(case) for case in cases):
            raise ValueError(f'{location}: cases must contain arrays of string arguments')
        yield fixture, native, paths, cases


def run(mode, directory, php, changed):
    for manifest in sorted(Path('tests/Coverage/features').glob('*.json')):
        for index, (fixture, native, paths, cases) in enumerate(registrations(manifest)):
            if mode == 'native':
                if not native:
                    continue
            elif paths is None or not (fixture in changed or manifest.as_posix() in changed
                                       or any(fnmatchcase(name, glob) for name in changed for glob in paths)):
                continue

            for case, args in enumerate(cases):
                if mode == 'native':
                    output = directory / f'{manifest.stem}-{index + 1}-{case + 1}.json'
                    options = ['-d', 'memory_limit=1G', '-d', 'xdebug.mode=coverage']
                    env = dict(os.environ, CACTI_COVERAGE_FILE=str(output))
                else:
                    options = ['-d', 'memory_limit=2G']
                    if extension := os.environ.get('CACTI_PR_XDEBUG_EXTENSION'):
                        options.extend(['-d', f'zend_extension={extension}'])
                    env = dict(os.environ, XDEBUG_MODE='coverage', CACTI_PR_RAW_COVERAGE=str(directory))
                subprocess.run([php, *options, fixture, *args], env=env, check=True)
                if mode == 'native' and not output.is_file():
                    raise ValueError(f'{fixture} did not produce its native coverage report {output}')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('mode', choices=['native', 'request'])
    parser.add_argument('directory', type=Path)
    parser.add_argument('--php', default='php')
    args = parser.parse_args()
    changed = set(os.fsdecode(sys.stdin.buffer.read()).split('\0')) if args.mode == 'request' else set()
    changed.discard('')
    try:
        run(args.mode, args.directory, args.php, changed)
    except (ValueError, OSError, subprocess.CalledProcessError) as error:
        sys.exit(str(error))

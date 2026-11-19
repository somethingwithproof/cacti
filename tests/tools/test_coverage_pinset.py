"""Offline materialization contracts using Git objects, including a dirty user index."""

import copy
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

from apply_coverage_pinset import CONTRACT, PinError, checked_json, replay, scratch
from generate_coverage_pinset import generate

RUNNER = 'tests/tools/run_pr_change_tests.sh'
GENERIC = '.github/workflows/pr-changed-coverage.yml'
APP = 'app.php'


def git(repo, *args, data=None, check=True):
    return subprocess.run(['git', '-C', str(repo), '-c', 'user.name=Test',
                           '-c', 'user.email=fixture@example.invalid', '-c', 'commit.gpgsign=false',
                           '-c', 'core.hooksPath=/dev/null', *args],
                          input=data, capture_output=True, check=check)


def line(repo, *args, **kwargs):
    return git(repo, *args, **kwargs).stdout.decode().strip()


class PinsetTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.repo = self.root / 'source'
        self.repo.mkdir()
        git(self.repo, 'init', '-q', '-b', 'fixture-base', '--template=')
        self.old = self.commit(None, {APP:'old app\n', CONTRACT:'dispatch\n',
                                      RUNNER:'legacy branch\n' * 30})
        self.base = self.commit(self.old, {'upstream.txt':'upstream only\n'})
        self.published = self.commit(self.base, {GENERIC:'shared action\n', RUNNER:'upgraded branch\n' * 30})
        self.shared = self.commit(self.published, {CONTRACT:'dispatch\nload contracts\n'})
        self.patch(self.published, self.shared, 'foundation-contracts.patch')
        self.rows = []
        for number in (9, 10):
            head = self.commit(self.old, {APP:f'APPLICATION-ONLY-{number}\n'})
            ordinary = line(self.repo, 'merge-tree', '--write-tree', self.base, head)
            feature = f'tests/Coverage/features/pr-{number}.json'
            candidate = self.commit(ordinary, {GENERIC:'shared action\n', CONTRACT:'dispatch\nload contracts\n',
                                    RUNNER:'upgraded branch\n' * 30 + 'additional integration line\n',
                                    feature:'[{"fixture":"test"}]\n'}, parent=self.shared)
            tree = line(self.repo, 'rev-parse', candidate + '^{tree}')
            self.patch(ordinary, tree, f'{number}.patch')
            changed = line(self.repo, 'diff', '--name-only', ordinary, tree).splitlines()
            self.rows.append(dict(number=number, head=head, merge_base=self.old, base=self.base,
                                  foundation=self.shared, status='complete', commit=candidate,
                                  tree=tree, changed_paths=changed))
        self.preview = dict(base=self.base, original_foundation=self.published,
                            foundation=self.shared, prs=self.rows)
        self.preview_file = self.root / 'preview.json'
        self.preview_file.write_text(json.dumps(self.preview))

    def commit(self, prior, files, parent=None):
        git(self.repo, 'read-tree', prior) if prior else git(self.repo, 'read-tree', '--empty')
        for path, data in files.items():
            oid = line(self.repo, 'hash-object', '-w', '--stdin', data=data.encode())
            git(self.repo, 'update-index', '--add', '--cacheinfo', '100644,' + oid + ',' + path)
        tree = line(self.repo, 'write-tree')
        parents = ['-p', parent or prior] if prior or parent else []
        return line(self.repo, 'commit-tree', tree, *parents, '-m', 'test')

    def patch(self, base, final, name):
        data = git(self.repo, 'diff', '--no-ext-diff', '--no-textconv', '--no-renames', base, final).stdout
        (self.root / name).write_bytes(data)

    def generate(self):
        with scratch(self.repo) as g:
            return generate(g, self.preview, self.root, [9, 10])

    def offline_clone(self):
        """Copy public inputs only. Neither preview commits nor candidate feature blobs are supplied."""
        other = self.root / 'offline'
        other.mkdir()
        git(other, 'init', '-q', '-b', 'user-work', '--template=')
        inputs = [self.base, self.published] + [r['head'] for r in self.rows]
        pack = git(self.repo, 'pack-objects', '--stdout', '--revs', data=('\n'.join(inputs)+'\n').encode()).stdout
        git(other, 'index-pack', '--stdin', data=pack)
        git(other, 'update-ref', 'refs/heads/user-work', self.base)
        git(other, 'read-tree', self.base)
        (other / 'dirty.txt').write_text('pre-existing work\n')
        git(other, 'add', 'dirty.txt')
        return other

    def test_fresh_clone_round_trip_and_existing_user_state(self):
        pins = self.generate()
        offline = self.offline_clone()
        self.assertTrue(git(offline, 'cat-file', '-e', self.shared, check=False).returncode)
        self.assertTrue(git(offline, 'cat-file', '-e', self.rows[0]['tree'], check=False).returncode)
        def state():
            return (git(offline, 'show-ref').stdout, (offline / '.git/index').read_bytes(),
                    (offline / 'dirty.txt').read_bytes(),
                    sorted(str(p) for p in (offline / '.git/objects').rglob('*')))
        previous = state()
        with scratch(offline) as g:
            patch = replay(g, pins, 9)
            row = pins['prs']['9']
            self.assertEqual(row['tree'], self.rows[0]['tree'])
            self.assertNotIn(APP.encode(), patch)
            self.assertNotIn('APPLICATION-ONLY-', json.dumps(pins))
            self.assertEqual(g.line('rev-parse', row['source_tree'] + ':upstream.txt'),
                             g.line('rev-parse', self.base + ':upstream.txt'))
            self.assertEqual(g.line('rev-parse', row['tree'] + ':' + APP),
                             g.line('rev-parse', self.rows[0]['head'] + ':' + APP))
            self.assertEqual(patch, replay(g, pins, 9, row['source_tree'], row['tree']))
        self.assertEqual(state(), previous)

    def test_shared_recipes_and_smallest_known_applicable_delta(self):
        pins = self.generate()
        self.assertEqual(pins['prs']['9']['shared'], pins['prs']['10']['shared'])
        runner = next(e for e in pins['shared'].values() if e['path'] == RUNNER)
        self.assertIn(runner['from'], ('foundation', 'published'))
        self.assertIn('+additional integration line', runner['hunks'])
        self.assertNotIn('legacy branch', runner['hunks'])
        self.assertTrue(all('text' not in e for e in pins['shared'].values()))
        self.assertTrue(all('text' in e for e in pins['prs']['9']['entries']))

    def test_tampered_pins_and_paths_refuse(self):
        pins = self.generate()
        for what in ('ordinary', 'final', 'loader', 'path', 'json', 'origin', 'hunk', 'extra_field'):
            bad = copy.deepcopy(pins)
            row = bad['prs']['9']
            if what == 'ordinary':
                row['source_tree'] = self.rows[0]['tree']
            elif what == 'final':
                row['tree'] = self.rows[1]['tree']
            elif what == 'loader':
                bad['loader'][0]['hunks'] += '+changed body\n'
            elif what == 'path':
                row['entries'][0]['path'] = APP
            elif what == 'json':
                row['entries'][0]['text'] += '\n'
            elif what == 'origin':
                next(iter(bad['shared'].values()))['from'] = 'elsewhere'
            elif what == 'hunk':
                e = next(e for e in bad['shared'].values() if 'hunks' in e)
                e['hunks'] = e['hunks'].replace('additional integration', 'different integration')
            else:
                row['entries'][0]['arbitrary'] = True
            with self.subTest(what=what), scratch(self.repo) as g, self.assertRaises(PinError):
                replay(g, bad, 9)

    def test_conflicted_or_wrong_head_rejected(self):
        row = self.rows[0]
        row['head'] = self.commit(self.old, {'upstream.txt':'would conflict\n'})
        with scratch(self.repo) as g, self.assertRaises(PinError):
            generate(g, self.preview, self.root, [9])

    def test_exported_evidence_and_allowlist_required(self):
        path = self.root / '9.patch'
        original = path.read_bytes()
        path.write_bytes(b'')
        with scratch(self.repo) as g, self.assertRaises(PinError):
            generate(g, self.preview, self.root, [9])
        path.write_bytes(original)
        self.rows[0]['changed_paths'].append(APP)
        with scratch(self.repo) as g, self.assertRaises(PinError):
            generate(g, self.preview, self.root, [9])

    def test_cli_outputs_patch_only_after_complete_verification(self):
        here = Path(__file__).resolve().parent
        out = self.root / 'pinset.json'
        subprocess.run([sys.executable, here / 'generate_coverage_pinset.py', '--repo', self.repo,
                        '--preview', self.preview_file, '--output', out, '--pr', '9', '10'],
                       check=True, capture_output=True)
        args = [sys.executable, here / 'apply_coverage_pinset.py', '--repo', self.offline_clone(),
                '--pinset', out, '--pr', '9']
        good = subprocess.run(args, capture_output=True, check=True)
        self.assertTrue(good.stdout.startswith(b'diff --git '))
        self.assertEqual(good.stderr, b'')
        bad = subprocess.run([*args, '--source-tree', '0'*40], capture_output=True)
        self.assertNotEqual(bad.returncode, 0)
        self.assertEqual(bad.stdout, b'')
        self.assertIn(b'squash', bad.stderr)

    def test_duplicate_json_keys_refused(self):
        with self.assertRaises(PinError):
            checked_json('{"prs":{},"prs":{}}')


if __name__ == '__main__':
    unittest.main()

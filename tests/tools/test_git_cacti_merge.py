"""Check contributor merge drivers through Git in a disposable repository."""

import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest


TOOL = Path(__file__).with_name('git_cacti_merge.py')
RUNNER = 'tests/tools/run_pr_change_tests.sh'
LOOP = ('for fixture in Alpha; do\n'
        '\tif [[ -f "tests/fixtures/Core/$fixture.php" ]]; then\n'
        '\t\trun_probe "$fixture.php" "$fixture"\n\tfi\ndone\n')
LOG = ('Cacti CHANGELOG\n\n1.2.x\n-issue#5: Existing note\n'
       '\n1.2.31\n-issue#1: Historical note\n')


class ContributorMergeTest(unittest.TestCase):
    def setUp(self):
        temp = tempfile.TemporaryDirectory(prefix="cacti merge's ")
        self.addCleanup(temp.cleanup)
        self.repo = Path(temp.name)
        self.env = dict(os.environ, GIT_CONFIG_GLOBAL=os.devnull,
                        GIT_CONFIG_NOSYSTEM='1', GIT_AUTHOR_NAME='Fixture',
                        GIT_AUTHOR_EMAIL='fixture@example.invalid',
                        GIT_COMMITTER_NAME='Fixture',
                        GIT_COMMITTER_EMAIL='fixture@example.invalid')
        self.git('init', '-q')

    def git(self, *args):
        return subprocess.check_output(['git', '-C', self.repo, *args],
                                       env=self.env, text=True).strip()

    def commit(self, path, contents, parent=None):
        file = self.repo / path
        file.parent.mkdir(parents=True, exist_ok=True)
        file.write_text(contents)
        self.git('add', '--', path)
        parents = ['-p', parent] if parent else []
        return self.git('commit-tree', self.git('write-tree'), *parents,
                        '-m', 'Fixture commit')

    def simulate(self, path, base, ours, theirs):
        common = self.commit(path, base)
        left = self.commit(path, ours, common)
        right = self.commit(path, theirs, common)
        normal = subprocess.run(
            ['git', '-C', self.repo, 'merge-tree', left, right],
            env=self.env, capture_output=True)
        self.assertEqual(normal.returncode, 1)
        result = subprocess.run(
            [sys.executable, TOOL, '--repo', self.repo, 'merge-tree',
             '--name-only', '--no-messages', left, right],
            env=self.env, capture_output=True, text=True)
        tree = result.stdout.splitlines()[0]
        return result.returncode, self.git('show', tree + ':' + path)

    def test_maintenance_entries_survive_a_real_merge(self):
        def add(note):
            return LOG.replace('\n\n1.2.31', '\n' + note + '\n\n1.2.31')
        result, content = self.simulate('CHANGELOG', LOG,
                                        add('-issue#8: Incoming'),
                                        add('-issue#7: Current'))
        self.assertEqual(result, 0)
        self.assertEqual(content, add('-issue#7: Current\n-issue#8: Incoming').strip())

    def test_fixture_additions_survive_a_real_merge(self):
        result, content = self.simulate(
            RUNNER, LOOP, LOOP.replace('Alpha;', 'Alpha Bravo;'),
            LOOP.replace('Alpha;', 'Alpha Charlie;'))
        self.assertEqual(result, 0)
        self.assertEqual(content, LOOP.replace('Alpha;', 'Alpha Bravo Charlie;').strip())

    def test_competing_rewrites_keep_normal_conflict_markers(self):
        result, content = self.simulate(
            'CHANGELOG', LOG, LOG.replace('Existing note', 'One revision'),
            LOG.replace('Existing note', 'Different revision'))
        self.assertEqual(result, 1)
        for line in ['<<<<<<< current', 'One revision', 'Different revision',
                     '>>>>>>> incoming']:
            self.assertIn(line, content)

    def test_other_attributes_and_local_config_are_preserved(self):
        attributes = self.repo / 'user attributes'
        attributes.write_text('*.txt merge=union\n')
        self.git('config', 'core.attributesFile', str(attributes))
        result = subprocess.check_output(
            [sys.executable, TOOL, '--repo', self.repo, 'check-attr',
             'merge', '--', 'readme.txt', RUNNER], env=self.env, text=True)
        self.assertIn('readme.txt: merge: union', result)
        self.assertIn(f'{RUNNER}: merge: cacti-registrations', result)
        self.assertEqual(self.git('config', 'core.attributesFile'), str(attributes))
        self.assertIn('unspecified', self.git('check-attr', 'merge', '--', RUNNER))


if __name__ == '__main__':
    unittest.main()

"""Exercise both shell entrypoints and the evidence consumed by the changed-line gate.

Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
"""
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest


class FeatureCoverageTest(unittest.TestCase):
    def setUp(self):
        temporary = tempfile.TemporaryDirectory()
        self.addCleanup(temporary.cleanup)
        root = Path(temporary.name)
        self.repo = root / 'repository'
        self.repo.mkdir()
        self.out = root / 'coverage with spaces'
        self.out.mkdir()
        self.env = dict(os.environ, PATH=str(root) + os.pathsep + os.environ['PATH'],
                        CACTI_COVERAGE_DIR=str(self.out), CACTI_PR_COVERAGE_OUTPUT=str(self.out),
                        GIT_CONFIG_GLOBAL=os.devnull, GIT_CONFIG_NOSYSTEM='1')
        self.git('init', '--quiet')
        self.git('config', 'user.name', 'Coverage fixture')
        self.git('config', 'user.email', 'fixture@example.invalid')
        source = Path(__file__).resolve().parent
        scripts = self.repo / 'tests/tools'
        scripts.mkdir(parents=True)
        for name in ['run_pr_change_tests.sh', 'run_request_coverage.sh', 'run_feature_coverage.py',
                     'report_changed_coverage.py', 'test_report_changed_coverage.py']:
            shutil.copy(source / name, scripts / name)
        fixture = self.repo / 'tests/fixtures/Nested/Example.php'
        fixture.parent.mkdir(parents=True)
        fixture.write_text('<?php // fixture\n')
        self.fixture = str(fixture.relative_to(self.repo))
        self.manifest = self.repo / 'tests/Coverage/features/sample.json'
        self.manifest.parent.mkdir(parents=True)
        self.entries = [
            {'fixture': self.fixture, 'native': True, 'request_paths': ['lib/feature*.php'],
             'cases': [['', 'two words'], ['42']]},
            {'fixture': self.fixture, 'native': True, 'cases': [['native only']]},
            {'fixture': self.fixture, 'request_paths': ['cli/command.php'], 'cases': [['request only']]}]
        self.manifest.write_text(json.dumps(self.entries))
        php = root / 'php'
        php.write_text('#!' + sys.executable + '\n' + """
import json, os, sys
from pathlib import Path
index = next(i for i, arg in enumerate(sys.argv) if arg.startswith('tests/fixtures/'))
fixture, *args = sys.argv[index:]
native = os.environ.get('CACTI_COVERAGE_FILE')
if native:
    assert sys.argv[1:index] == ['-d', 'memory_limit=1G', '-d', 'xdebug.mode=coverage']
    if args == ['missing report']:
        sys.exit(0)
    Path(native).write_text(json.dumps({str(Path('lib/feature.php').resolve()): {'lines': {'2': 1}}}))
else:
    assert sys.argv[1:index] == ['-d', 'memory_limit=2G', '-d', 'zend_extension=fixture.so']
    assert os.environ['XDEBUG_MODE'] == 'coverage'
    assert os.environ['CACTI_PR_RAW_COVERAGE'] == os.environ['CACTI_PR_COVERAGE_OUTPUT']
with (Path(os.environ['CACTI_PR_COVERAGE_OUTPUT']) / 'invocations').open('a') as output:
    print(json.dumps([fixture, args]), file=output)
if args == ['fail']:
    sys.exit(17)
""")
        php.chmod(0o700)
        self.env['CACTI_PR_XDEBUG_EXTENSION'] = 'fixture.so'
        self.git('add', '--all')
        self.git('commit', '--quiet', '-m', 'registered fixtures')
        self.env['BASE_COMMIT'] = self.git('rev-parse', 'HEAD')

    def git(self, *args):
        return subprocess.check_output(['git', *args], cwd=self.repo, env=self.env, text=True).strip()

    def change(self, name, text):
        path = self.repo / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(text)
        self.git('add', '--all')
        self.git('commit', '--quiet', '-m', 'change fixture')

    def runner(self, mode, check=True):
        log = self.out / 'invocations'
        log.unlink(missing_ok=True)
        command = ['bash', 'tests/tools/run_request_coverage.sh'] if mode == 'request' else [
            'bash', 'tests/tools/run_pr_change_tests.sh', self.env['BASE_COMMIT']]
        result = subprocess.run(command, env=self.env, cwd=self.repo, text=True, capture_output=True)
        if not check:
            return result
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        return [json.loads(line) for line in log.read_text().splitlines()] if log.exists() else []

    def test_native_collects_current_evidence_and_request_selects_matching_cases(self):
        self.change('lib/feature.php', '<?php\nreturn 42;\n')
        cases = [[self.fixture, ['', 'two words']], [self.fixture, ['42']]]
        self.assertEqual(self.runner('request'), cases)
        stale = self.out / 'features.previous'
        stale.mkdir()
        (stale / 'old.json').write_text('invalid stale report')
        self.assertEqual(self.runner('native'), cases + [[self.fixture, ['native only']]])
        report = json.loads((self.out / 'report.json').read_text())
        self.assertEqual(report['lib/feature.php'], {
            'instrumented': True, 'covered': 1, 'executable': 1, 'uncovered': []})

    def test_request_ignores_unrelated_changes_and_selects_changed_fixture_or_manifest(self):
        self.manifest.write_text(json.dumps([dict(self.entries[0], request_paths=['*'])]))
        self.assertEqual(self.runner('request'), [])
        self.manifest.write_text(json.dumps(self.entries))
        self.change('README.md', 'documentation')
        self.assertEqual(self.runner('request'), [])
        for path, text in [(self.fixture, '<?php // updated'), (self.manifest, json.dumps(self.entries, indent=2))]:
            self.env['BASE_COMMIT'] = self.git('rev-parse', 'HEAD')
            self.change(path, text)
            self.assertEqual(self.runner('request'), [
                [self.fixture, ['', 'two words']], [self.fixture, ['42']], [self.fixture, ['request only']]])

    def test_bad_registrations_and_failed_or_missing_probes_stop_the_gate(self):
        for entry, message in [
            ({'fixture': self.fixture, 'request_path': []}, 'supported keys'),
            ({'fixture': self.fixture, 'native': True, 'cases': ['one']}, 'arrays of string arguments'),
            ({'fixture': 'tests/fixtures/missing.php', 'native': True}, 'missing'),
            ({'fixture': self.fixture, 'native': True, 'cases': [['fail']]}, '17'),
            ({'fixture': self.fixture, 'native': True, 'cases': [['missing report']]}, 'did not produce')]:
            with self.subTest(entry=entry):
                self.manifest.write_text(json.dumps([entry]))
                result = self.runner('native', check=False)
                self.assertNotEqual(result.returncode, 0)
                self.assertIn(message, result.stderr)


if __name__ == '__main__':
    unittest.main()

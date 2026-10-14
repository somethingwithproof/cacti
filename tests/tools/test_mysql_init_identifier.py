"""Execute the production identifier guard without connecting to a database."""
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]


class MysqlIdentifierTest(unittest.TestCase):
    def run_guard(self, value):
        source = (ROOT / 'docker/mysql-init.sh').read_text()
        # Execute the complete production case statement; schema import is
        # separately tested in the disposable database integration workflow.
        guard = source[:source.index('\nesac') + len('\nesac')]
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'guard.sh'
            path.write_text(guard + '\nprintf "accepted\\n"\n')
            environment = {**os.environ}
            environment.pop('MYSQL_DATABASE', None)
            if value is not None:
                environment['MYSQL_DATABASE'] = value
            return subprocess.run(['/bin/bash', '-c', 'source "$1"', 'test', str(path)], env=environment, capture_output=True, text=True, timeout=10)

    def test_valid_ascii_identifiers_are_accepted(self):
        for value in ['cacti', 'cacti_test_13', 'CACTI', '123', '_']:
            with self.subTest(value=value):
                result = self.run_guard(value)
                self.assertEqual(result.returncode, 0, result.stderr)
                self.assertEqual(result.stdout, 'accepted\n')
                self.assertEqual(result.stderr, '')

    def test_empty_unset_and_injection_shaped_names_are_rejected(self):
        for value in [None, '', 'cacti-test', 'cacti test', 'cacti;DROP DATABASE cacti', '`cacti`', 'cacti\nother', 'cactí']:
            with self.subTest(value=value):
                result = self.run_guard(value)
                self.assertEqual(result.returncode, 1)
                self.assertEqual(result.stdout, '')
                self.assertEqual(result.stderr, 'MYSQL_DATABASE must contain only letters, numbers, and underscores.\n')


if __name__ == '__main__':
    unittest.main()

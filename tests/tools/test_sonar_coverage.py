# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location("sonar_coverage", Path(__file__).with_name("check_sonar_coverage.py"))
coverage = importlib.util.module_from_spec(spec)
spec.loader.exec_module(coverage)


class CoverageBoundaryTests(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.root = Path(self.directory.name)
        (self.root / "app.php").write_text("<?php\nreturn 1;\n")
        (self.root / "client.js").write_text("const value = 1;\n")
        (self.root / "coverage").mkdir()
        (self.root / "sonar-project.properties").write_text("sonar.exclusions=tests/**,include/vendor/**\n")
        with patch.object(coverage.subprocess, "check_output", side_effect=[b"app.php\0client.js\0", "revision\n"]):
            self.manifest = coverage.snapshot(self.root)
        (self.root / "coverage/php.xml").write_text(f'<coverage><project><file name="{self.root}/app.php"><line num="2" count="1"/></file></project></coverage>')
        (self.root / "coverage/php-browser.xml").write_text((self.root / "coverage/php.xml").read_text())
        (self.root / "coverage/lcov.info").write_text("SF:client.js\nDA:1,1\nend_of_record\n")

    def check(self):
        with patch.object(coverage, "snapshot", return_value=self.manifest):
            return coverage.validate(self.root, self.manifest)

    def test_accepts_real_relative_and_absolute_checkout_paths(self):
        self.assertEqual(self.check(), (2, 1))

    def test_rejects_changed_revision(self):
        with patch.object(coverage, "snapshot", return_value={**self.manifest, "revision": "another"}):
            with self.assertRaisesRegex(ValueError, "revision or production source"):
                coverage.validate(self.root, self.manifest)

    def test_snapshot_detects_source_changes(self):
        (self.root / "app.php").write_text("<?php\nreturn 2;\n")
        with patch.object(coverage.subprocess, "check_output", side_effect=[b"app.php\0client.js\0", "revision\n"]):
            self.assertNotEqual(coverage.snapshot(self.root), self.manifest)

    def test_rejects_foreign_report_paths(self):
        with self.assertRaisesRegex(ValueError, "outside the checkout"):
            coverage.source_file(self.root, "../outside.php", self.manifest["sources"])

    def test_rejects_untracked_report_paths(self):
        with self.assertRaisesRegex(ValueError, "not in the scanned manifest"):
            coverage.source_file(self.root, "untracked.php", self.manifest["sources"])

    def test_rejects_empty_or_unexecuted_php_reports(self):
        (self.root / "coverage/php-browser.xml").write_text("<coverage/>")
        for content in ['<coverage/>', '<coverage><file name="app.php"><line num="2" count="0"/></file></coverage>']:
            (self.root / "coverage/php.xml").write_text(content)
            with self.assertRaises(ValueError):
                self.check()

    def test_rejects_invalid_php_line_and_count(self):
        for number, count in [(3, 1), (2, -1)]:
            (self.root / "coverage/php.xml").write_text(f'<coverage><file name="app.php"><line num="{number}" count="{count}"/></file></coverage>')
            with self.assertRaisesRegex(ValueError, "invalid line or execution count"):
                self.check()

    def test_rejects_invalid_or_empty_javascript_reports(self):
        for report in ['', 'SF:client.js\nDA:2,1\nend_of_record\n', 'SF:client.js\nDA:1,-1\nend_of_record\n', 'SF:client.js\nDA:1,0\nend_of_record\n']:
            (self.root / "coverage/lcov.info").write_text(report)
            with self.assertRaises(ValueError):
                self.check()


if __name__ == "__main__":
    unittest.main()

# Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch
import xml.etree.ElementTree as ET
import merge_sonar_browser_coverage as merger


class NativeCoverageTests(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.root = Path(self.directory.name)
        (self.root / "app.php").write_text("<?php\nreturn 1;\n")
        (self.root / "coverage/php-browser").mkdir(parents=True)
        self.manifest = {"revision": "revision", "sources": {"app.php": "hash"}}
        (self.root / "coverage/revision.json").write_text(json.dumps(self.manifest))
        self.report("a", 1)
        self.report("b", 0)

    def report(self, name, count, revision="revision", source_hash="hash", path="app.php"):
        target = self.root / "coverage/php-browser" / name
        target.with_suffix(".json").write_text(json.dumps({"root": "/runtime", "revision": revision, "sources": {"app.php": source_hash}}))
        target.with_suffix(".xml").write_text(f'<coverage><project><file name="/runtime/{path}"><line num="2" type="stmt" count="{count}"/></file></project></coverage>')

    def merge(self):
        with patch.object(merger, "snapshot", return_value=self.manifest):
            return merger.merge(self.root)

    def test_merges_real_line_observations_and_normalizes_container_paths(self):
        self.assertEqual(self.merge(), (2, 1))
        file = ET.parse(self.root / "coverage/php-browser.xml").find("project/file")
        self.assertEqual(file.attrib["name"], str((self.root / "app.php").resolve()))
        self.assertEqual(file.find("line").attrib["count"], "1")

    def test_rejects_another_revision(self):
        self.report("b", 1, revision="another")
        with self.assertRaisesRegex(ValueError, "different revision"):
            self.merge()

    def test_rejects_stale_source_hashes(self):
        self.report("b", 1, source_hash="stale")
        with self.assertRaisesRegex(ValueError, "source hash"):
            self.merge()

    def test_rejects_paths_escaping_runtime_root(self):
        self.report("b", 1, path="../outside.php")
        with self.assertRaisesRegex(ValueError, "escapes"):
            self.merge()

    def test_rejects_reports_without_executed_lines(self):
        self.report("a", 0)
        with self.assertRaisesRegex(ValueError, "no executed"):
            self.merge()

    def test_rejects_negative_execution_counts(self):
        self.report("b", -1)
        with self.assertRaisesRegex(ValueError, "invalid execution"):
            self.merge()


if __name__ == "__main__":
    unittest.main()

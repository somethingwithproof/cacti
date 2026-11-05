"""Exercise check-workflow-policy.py against copies of the repository's workflows."""

import re
import shutil
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
AUDIT = '.github/workflows/released-dependency-audit.yml'
PUBLISH_STEP = 'Checkout released branch for publication'


class WorkflowPolicyTest(unittest.TestCase):
    def setUp(self) -> None:
        self.tmp = Path(tempfile.mkdtemp(prefix='workflow-policy-'))
        self.addCleanup(shutil.rmtree, self.tmp, ignore_errors=True)
        shutil.copytree(ROOT / '.github', self.tmp / '.github')

    def run_checker(self) -> subprocess.CompletedProcess:
        return subprocess.run(
            [sys.executable, str(self.tmp / '.github/scripts/check-workflow-policy.py')],
            capture_output=True,
            text=True,
            check=False,
        )

    def edit_audit(self, old: str, new: str) -> None:
        path = self.tmp / AUDIT
        text = path.read_text()
        self.assertIn(old, text)
        path.write_text(text.replace(old, new, 1))

    def test_repository_workflows_satisfy_the_policy(self) -> None:
        result = self.run_checker()

        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)

    def test_the_publication_checkout_is_the_only_credential_persisting_step_allowed(self) -> None:
        text = (self.tmp / AUDIT).read_text()
        step = re.search(
            rf'- name: {re.escape(PUBLISH_STEP)}\n(?P<body>(?:        .*\n)+)', text
        )
        self.assertIsNotNone(step)
        self.assertIn('persist-credentials: true', step.group('body'))

    def test_a_renamed_publication_step_loses_the_exemption(self) -> None:
        self.edit_audit(PUBLISH_STEP, 'Checkout released branch')

        result = self.run_checker()

        self.assertEqual(result.returncode, 1)
        self.assertIn('read-only checkout must set persist-credentials: false', result.stdout + result.stderr)

    def test_a_read_only_checkout_in_the_same_file_stays_subject_to_the_rule(self) -> None:
        path = self.tmp / AUDIT
        text = path.read_text()
        self.assertIn('persist-credentials: false', text)
        path.write_text(text.replace('persist-credentials: false', 'persist-credentials: true', 1))

        result = self.run_checker()

        self.assertEqual(result.returncode, 1)
        self.assertIn('read-only checkout must set persist-credentials: false', result.stdout + result.stderr)


if __name__ == '__main__':
    unittest.main()

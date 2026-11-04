"""Exercise the released-branch audit and remediation contracts offline."""

from __future__ import annotations

import importlib.util
import json
import os
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path
from unittest import mock

import yaml

ROOT = Path(__file__).resolve().parents[2]
WORKFLOW = ROOT / ".github/workflows/released-dependency-audit.yml"
REMEDIATION_SCRIPT = ROOT / ".github/scripts/released_dependency_remediation.py"

sys.dont_write_bytecode = True
SPEC = importlib.util.spec_from_file_location(
    "released_dependency_remediation", REMEDIATION_SCRIPT
)
assert SPEC and SPEC.loader
REMEDIATION = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = REMEDIATION
SPEC.loader.exec_module(REMEDIATION)


class ReleasedDependencyAuditTest(unittest.TestCase):
    def setUp(self) -> None:
        self.workflow = yaml.safe_load(WORKFLOW.read_text(encoding="utf-8"))

    def audit(self, ecosystem, report, status=0):
        command = next(
            step["run"]
            for step in self.workflow["jobs"]["audit"]["steps"]
            if step["name"] == "Audit committed dependencies"
        )
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            executable = root / ecosystem
            executable.write_text(
                '#!/bin/sh\ncat "$FIXTURE_REPORT"\nexit "$FIXTURE_STATUS"\n',
                encoding="utf-8",
            )
            executable.chmod(0o755)
            fixture = root / "fixture.json"
            fixture.write_text(
                report if isinstance(report, str) else json.dumps(report),
                encoding="utf-8",
            )
            output = root / "github-output"
            result = subprocess.run(
                ["bash", "-c", command],
                check=False,
                text=True,
                capture_output=True,
                env={
                    **os.environ,
                    "PATH": f'{root}{os.pathsep}{os.environ["PATH"]}',
                    "PACKAGE_ECOSYSTEM": ecosystem,
                    "RUNNER_TEMP": directory,
                    "GITHUB_OUTPUT": str(output),
                    "FIXTURE_REPORT": str(fixture),
                    "FIXTURE_STATUS": str(status),
                },
            )
            audit_report = root / "dependency-audit.json"
            self.assertEqual(audit_report.read_text(), fixture.read_text())
            return result

    def test_clean_locks_pass(self):
        reports = [
            ("composer", {"advisories": [], "abandoned": {}}),
            ("npm", {"metadata": {"vulnerabilities": {"total": 0}}}),
        ]
        for ecosystem, report in reports:
            with self.subTest(ecosystem=ecosystem):
                result = self.audit(ecosystem, report)
                self.assertEqual(result.returncode, 0, result.stderr)
                self.assertNotIn("::warning", result.stdout)

    def test_composer_advisories_are_reported(self):
        report = {
            "advisories": {"vendor/package": [{"title": "fixture"}]},
            "abandoned": {},
        }
        result = self.audit("composer", report, 1)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn("1 advisory or abandoned-package records", result.stdout)

    def test_abandoned_packages_are_reported(self):
        report = {"advisories": [], "abandoned": {"vendor/package": None}}
        result = self.audit("composer", report, 2)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn("1 advisory or abandoned-package records", result.stdout)

    def test_npm_advisories_are_reported(self):
        report = {"metadata": {"vulnerabilities": {"total": 3}}}
        result = self.audit("npm", report, 1)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn("3 advisory or abandoned-package records", result.stdout)

    def test_invalid_reports_fail(self):
        for ecosystem in ["composer", "npm"]:
            for report in ["registry unavailable", {"error": {"message": "fixture"}}]:
                with self.subTest(ecosystem=ecosystem, report=report):
                    self.assertNotEqual(self.audit(ecosystem, report, 1).returncode, 0)

    def test_tool_failures_without_findings_fail(self):
        reports = [
            ("composer", {"advisories": [], "abandoned": {}}),
            ("npm", {"metadata": {"vulnerabilities": {"total": 0}}}),
        ]
        for ecosystem, report in reports:
            with self.subTest(ecosystem=ecosystem):
                self.assertEqual(self.audit(ecosystem, report, 42).returncode, 42)

    def test_workflow_is_daily_and_separates_write_permissions(self):
        triggers = self.workflow.get("on", self.workflow.get(True))
        self.assertEqual(triggers["schedule"], [{"cron": "17 6 * * *"}])
        self.assertEqual(self.workflow["permissions"], {"contents": "read"})
        self.assertEqual(
            self.workflow["jobs"]["audit"]["permissions"],
            {"contents": "read"},
        )
        self.assertEqual(
            self.workflow["jobs"]["publish-remediation"]["permissions"],
            {"contents": "write", "pull-requests": "write"},
        )
        publisher = self.workflow["jobs"]["publish-remediation"]
        self.assertIn("github.repository == 'Cacti/cacti'", publisher["if"])

    def test_cli_download_is_versioned_and_hash_pinned(self):
        environment = self.workflow["env"]
        self.assertRegex(environment["DEPENDABOT_CLI_VERSION"], r"^\d+\.\d+\.\d+$")
        self.assertRegex(environment["DEPENDABOT_CLI_SHA256"], r"^[0-9a-f]{64}$")


class ReleasedDependencyRemediationTest(unittest.TestCase):
    def test_builds_composer_security_job(self):
        report = {
            "advisories": {
                "vendor/package": [
                    {"affectedVersions": "<1.2.3"},
                    {"affectedVersions": ">=2.0,<2.1"},
                ]
            },
            "abandoned": {},
        }
        document = REMEDIATION.build_job_document(
            ecosystem="composer",
            directory="/tests",
            report=report,
            repository="Cacti/cacti",
            commit="a" * 40,
        )
        job = document["job"]
        self.assertEqual(job["package-manager"], "composer")
        self.assertEqual(job["dependencies"], ["vendor/package"])
        self.assertEqual(job["source"]["directory"], "/tests")
        self.assertTrue(job["security-updates-only"])
        self.assertEqual(
            job["security-advisories"][0]["affected-versions"],
            ["<1.2.3", ">=2.0,<2.1"],
        )

    def test_builds_npm_security_job_from_advisory_objects(self):
        report = {
            "vulnerabilities": {
                "advisory-package": {
                    "isDirect": False,
                    "range": "*",
                    "via": [
                        {
                            "source": 1,
                            "name": "advisory-package",
                            "url": "https://github.com/advisories/GHSA-test",
                            "range": ">=1.0.0 <1.2.4",
                        },
                        {"source": 2, "name": "advisory-package", "range": "<0.9.0"},
                    ],
                },
                "parent-package": {
                    "isDirect": True,
                    "range": "*",
                    "via": ["advisory-package"],
                },
            },
            "metadata": {"vulnerabilities": {"total": 2}},
        }
        document = REMEDIATION.build_job_document(
            ecosystem="npm",
            directory="/",
            report=report,
            repository="Cacti/cacti",
            commit="b" * 40,
        )
        job = document["job"]
        self.assertEqual(job["package-manager"], "npm_and_yarn")
        self.assertEqual(job["dependencies"], ["advisory-package"])
        self.assertEqual(
            job["security-advisories"][0]["affected-versions"],
            ["<0.9.0", ">=1.0.0 <1.2.4"],
        )

    def test_npm_pure_transitive_entries_are_skipped(self):
        report = {
            "vulnerabilities": {
                "parent": {"range": "*", "via": ["child"]},
                "mixed-string-only": {"range": ">=1", "via": ["a", "b"]},
            }
        }
        self.assertEqual(REMEDIATION.npm_security_advisories(report), [])

    def test_npm_advisory_name_comes_from_the_advisory_object(self):
        report = {
            "vulnerabilities": {
                "parent": {
                    "via": [
                        "child",
                        {"name": "child", "range": "<2.0.0"},
                    ]
                }
            }
        }
        advisories = REMEDIATION.npm_security_advisories(report)
        self.assertEqual([item["dependency-name"] for item in advisories], ["child"])
        self.assertEqual(advisories[0]["affected-versions"], ["<2.0.0"])

    def test_npm_advisory_without_name_uses_the_entry_key(self):
        report = {"vulnerabilities": {"pkg": {"via": [{"range": "<1.0.0"}]}}}
        advisories = REMEDIATION.npm_security_advisories(report)
        self.assertEqual(advisories[0]["dependency-name"], "pkg")

    def test_npm_empty_audit_yields_no_advisories(self):
        self.assertEqual(REMEDIATION.npm_security_advisories({"vulnerabilities": {}}), [])

    def test_rejects_malformed_npm_reports(self):
        reports = [
            {},
            {"vulnerabilities": []},
            {"vulnerabilities": {"package": []}},
            {"vulnerabilities": {"package": {"range": "*"}}},
            {"vulnerabilities": {"package": {"via": "string"}}},
            {"vulnerabilities": {"package": {"via": [{"name": "package", "range": ""}]}}},
            {"vulnerabilities": {"package": {"via": [{"name": "bad name", "range": "<1"}]}}},
            {"vulnerabilities": {"bad name": {"via": []}}},
        ]
        for report in reports:
            with self.subTest(report=report):
                with self.assertRaises(REMEDIATION.RemediationError):
                    REMEDIATION.build_job_document(
                        ecosystem="npm",
                        directory="/",
                        report=report,
                        repository="Cacti/cacti",
                        commit="c" * 40,
                    )

    def test_materializes_only_manifest_files(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory) / "repo"
            root.mkdir()
            self.git(root, "init", "--quiet")
            self.git(root, "config", "user.name", "Fixture")
            self.git(root, "config", "user.email", "fixture@example.com")
            self.git(root, "config", "commit.gpgsign", "false")
            manifest = root / "tests/e2e/package.json"
            lockfile = root / "tests/e2e/package-lock.json"
            manifest.parent.mkdir(parents=True)
            manifest.write_text('{"devDependencies":{"fixture":"1.0.0"}}\n')
            lockfile.write_text('{"lockfileVersion":3,"packages":{}}\n')
            self.git(root, "add", ".")
            self.git(root, "commit", "--quiet", "-m", "fixture")
            base_commit = self.git(root, "rev-parse", "HEAD").strip()

            event = self.create_event(
                directory="/tests/e2e",
                name="fixture",
                files={
                    "package.json": '{"devDependencies":{"fixture":"1.0.1"}}\n',
                    "package-lock.json": '{"lockfileVersion":3,"packages":{"fixture":"1.0.1"}}\n',
                },
            )
            results = Path(directory) / "results.jsonl"
            results.write_text(json.dumps(event) + "\n")
            output = Path(directory) / "output"

            count = REMEDIATION.materialize_results(
                results=results,
                repository_root=root,
                base_commit=base_commit,
                ecosystem="npm",
                directory="/tests/e2e",
                output_root=output,
            )

            self.assertEqual(count, 1)
            metadata_path = next(output.rglob("metadata.json"))
            metadata = json.loads(metadata_path.read_text())
            self.assertEqual(
                metadata["files"],
                ["tests/e2e/package-lock.json", "tests/e2e/package.json"],
            )
            patch = metadata_path.with_name("changes.patch")
            self.assertTrue(patch.read_bytes().startswith(b"diff --git "))
            self.git(root, "apply", "--check", str(patch))

    def test_rejects_generated_path_traversal(self):
        data = self.create_event(
            directory="/tests/e2e",
            name="fixture",
            files={"../../owned": "unsafe\n"},
        )["data"]
        with self.assertRaises(REMEDIATION.RemediationError):
            REMEDIATION.event_file_path(
                data["updated-dependency-files"][0],
                "/tests/e2e",
            )

    def test_root_npm_policy_includes_shipped_assets(self):
        _, policy = REMEDIATION.policy_for("npm", "/")
        self.assertIn("package-lock.json", policy.allowed_files)
        self.assertIn("include/js/purify.js", policy.allowed_files)
        self.assertIn("include/js/billboard.js", policy.allowed_files)
        self.assertNotIn("build/sync-js.mjs", policy.allowed_files)

    def test_draft_pr_copy_names_the_blind_spot_and_test_gate(self):
        metadata = {
            "ecosystem": "npm",
            "directory": "/",
            "base_commit": "d" * 40,
            "dependencies": ["fixture"],
        }
        title = REMEDIATION.pull_request_title(metadata)
        body = REMEDIATION.pull_request_body(
            metadata,
            issue=8577,
            run_url="https://github.example/actions/runs/1",
        )
        self.assertIn("security blind spot", title)
        self.assertIn("dependency-security blind spot", body)
        self.assertIn("Approve the draft's queued workflow runs", body)
        self.assertIn("include/js/", body)
        self.assertIn("#8577", body)

    @staticmethod
    def git(root: Path, *arguments: str) -> str:
        return subprocess.run(
            ["git", *arguments],
            cwd=root,
            check=True,
            capture_output=True,
            text=True,
        ).stdout

    @staticmethod
    def create_event(*, directory: str, name: str, files: dict[str, str]) -> dict:
        return {
            "type": "create_pull_request",
            "data": {
                "dependencies": [{"name": name}],
                "updated-dependency-files": [
                    {
                        "directory": directory,
                        "name": file_name,
                        "deleted": False,
                        "content": content,
                    }
                    for file_name, content in files.items()
                ],
            },
        }


BOT = REMEDIATION.BOT_EMAIL


def git(root: Path, *arguments: str, env: dict | None = None) -> str:
    return subprocess.run(
        ["git", *arguments],
        cwd=root,
        check=True,
        capture_output=True,
        text=True,
        env=env,
    ).stdout.strip()


class VerifyRemediationTest(unittest.TestCase):
    """The status gate behind the 'Require remediation' workflow step."""

    def setUp(self) -> None:
        self._tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self._tmp.cleanup)
        self.root = Path(self._tmp.name)
        self.output = self.root / "remediation"
        self.output.mkdir()
        self.job = self.root / "job.json"

    def write_job(self, dependencies):
        self.job.write_text(json.dumps({"job": {"dependencies": dependencies}}))

    def write_status(self, error_types=(), created=()):
        (self.output / "status.json").write_text(
            json.dumps({"schema": 1, "created": list(created), "error_types": list(error_types)})
        )

    def write_event(self, dependencies, directory="/"):
        identifier = REMEDIATION.event_id("composer", directory, sorted(dependencies))
        event = self.output / "events" / identifier
        event.mkdir(parents=True)
        (event / "metadata.json").write_text(
            json.dumps(
                {
                    "schema": 1,
                    "id": identifier,
                    "ecosystem": "composer",
                    "directory": directory,
                    "base_commit": "a" * 40,
                    "dependencies": sorted(dependencies),
                    "files": ["composer.lock"],
                }
            )
        )

    def verify(self):
        REMEDIATION.verify_remediation(output_root=self.output, job_path=self.job)

    def test_full_remediation_passes(self):
        self.write_job(["a/a", "b/b"])
        self.write_status()
        self.write_event(["a/a"])
        self.write_event(["b/b"], directory="/")
        self.verify()

    def test_one_event_may_cover_several_dependencies(self):
        self.write_job(["a/a", "b/b"])
        self.write_status()
        self.write_event(["a/a", "b/b"])
        self.verify()

    def test_error_types_fail_even_when_events_exist(self):
        self.write_job(["a/a"])
        self.write_status(error_types=["security_update_not_possible"])
        self.write_event(["a/a"])
        with self.assertRaisesRegex(REMEDIATION.RemediationError, "security_update_not_possible"):
            self.verify()

    def test_partial_remediation_fails(self):
        self.write_job(["a/a", "b/b"])
        self.write_status()
        self.write_event(["a/a"])
        with self.assertRaisesRegex(REMEDIATION.RemediationError, "b/b"):
            self.verify()

    def test_no_events_fails(self):
        self.write_job(["a/a"])
        self.write_status()
        with self.assertRaises(REMEDIATION.RemediationError):
            self.verify()

    def test_malformed_inputs_fail(self):
        self.write_job(["a/a"])
        with self.assertRaises(REMEDIATION.RemediationError):  # no status.json
            self.verify()
        (self.output / "status.json").write_text("not json")
        with self.assertRaises(REMEDIATION.RemediationError):
            self.verify()
        (self.output / "status.json").write_text(json.dumps({"error_types": "oops"}))
        with self.assertRaises(REMEDIATION.RemediationError):
            self.verify()
        self.write_status()
        self.write_job([])
        with self.assertRaises(REMEDIATION.RemediationError):
            self.verify()
        self.job.write_text(json.dumps({"job": []}))
        with self.assertRaises(REMEDIATION.RemediationError):
            self.verify()

    def test_cli_exit_codes(self):
        self.write_job(["a/a"])
        self.write_status()
        command = [
            sys.executable, "-I", str(REMEDIATION_SCRIPT), "verify",
            "--remediation", str(self.output), "--job", str(self.job),
        ]
        failed = subprocess.run(command, capture_output=True, text=True, check=False)
        self.assertNotEqual(failed.returncode, 0)
        self.assertIn("released dependency remediation failed", failed.stderr)
        self.write_event(["a/a"])
        passed = subprocess.run(command, capture_output=True, text=True, check=False)
        self.assertEqual(passed.returncode, 0, passed.stderr)


class TrackingIssueTest(unittest.TestCase):
    def test_precedence_and_validation(self):
        env = REMEDIATION.ISSUE_ENV
        with mock.patch.dict(os.environ, {}, clear=False):
            os.environ.pop(env, None)
            self.assertEqual(REMEDIATION.tracking_issue(None), REMEDIATION.DEFAULT_TRACKING_ISSUE)
            self.assertEqual(REMEDIATION.tracking_issue(12), 12)
            os.environ[env] = "  "
            self.assertEqual(REMEDIATION.tracking_issue(None), REMEDIATION.DEFAULT_TRACKING_ISSUE)
            os.environ[env] = "9001"
            self.assertEqual(REMEDIATION.tracking_issue(None), 9001)
            self.assertEqual(REMEDIATION.tracking_issue(5), 5)
            os.environ[env] = "abc"
            with self.assertRaises(REMEDIATION.RemediationError):
                REMEDIATION.tracking_issue(None)
        with self.assertRaises(REMEDIATION.RemediationError):
            REMEDIATION.tracking_issue(0)


class PublishBranchTest(unittest.TestCase):
    """Run the real publisher against throwaway local origin and clone repos."""

    BRANCH = None

    def setUp(self) -> None:
        # Ignore the developer's global hooks, signing and identity settings.
        isolation = mock.patch.dict(
            os.environ,
            {"GIT_CONFIG_GLOBAL": os.devnull, "GIT_CONFIG_NOSYSTEM": "1"},
        )
        isolation.start()
        self.addCleanup(isolation.stop)
        self._tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self._tmp.cleanup)
        self.root = Path(self._tmp.name)
        self.origin = self.root / "origin.git"
        git(self.root, "init", "--quiet", "--bare", "-b", "1.2.x", str(self.origin))
        self.work = self.root / "work"
        git(self.root, "clone", "--quiet", str(self.origin), str(self.work))
        git(self.work, "checkout", "--quiet", "-B", "1.2.x")
        for key, value in (("user.name", "Maintainer"), ("user.email", "m@example.com"), ("commit.gpgsign", "false")):
            git(self.work, "config", key, value)
        (self.work / "composer.lock").write_text('{"packages":[]}\n')
        (self.work / "README").write_text("one\n")
        git(self.work, "add", "composer.lock", "README")
        git(self.work, "commit", "--quiet", "-m", "base")
        git(self.work, "push", "--quiet", "origin", "1.2.x")
        self.base = git(self.work, "rev-parse", "HEAD")

        (self.work / "composer.lock").write_text('{"packages":["fixed"]}\n')
        patch = git(self.work, "diff", "--binary", "--full-index") + "\n"
        git(self.work, "checkout", "--quiet", "--", "composer.lock")
        dependencies = ["vendor/package"]
        identifier = REMEDIATION.event_id("composer", "/", dependencies)
        self.artifacts = self.root / "artifacts"
        event = self.artifacts / "events" / identifier
        event.mkdir(parents=True)
        (event / "changes.patch").write_text(patch)
        self.metadata_path = event / "metadata.json"
        self.metadata_path.write_text(
            json.dumps(
                {
                    "schema": 1, "id": identifier, "ecosystem": "composer",
                    "directory": "/", "base_commit": self.base,
                    "dependencies": dependencies, "files": ["composer.lock"],
                }
            )
        )
        self.branch = REMEDIATION.branch_name(REMEDIATION.validate_metadata(json.loads(self.metadata_path.read_text())))

        bin_dir = self.root / "bin"
        bin_dir.mkdir()
        gh = bin_dir / "gh"
        gh.write_text(f'#!/bin/sh\necho "$@" >> "{self.root}/gh.log"\n[ "$2" = list ] && echo "[]"\nexit 0\n')
        gh.chmod(0o755)
        patcher = mock.patch.dict(os.environ, {"PATH": f"{bin_dir}{os.pathsep}{os.environ['PATH']}"})
        patcher.start()
        self.addCleanup(patcher.stop)

    def publish(self) -> bool:
        git(self.work, "fetch", "--quiet", "origin")
        return REMEDIATION.publish_event(
            metadata_path=self.metadata_path,
            repository_root=self.work,
            repository="Cacti/cacti",
            base_branch="1.2.x",
            assignee="TheWitness",
            issue=1,
            run_url="https://example.invalid/run/1",
        )

    def remote_tip(self):
        result = subprocess.run(
            ["git", "rev-parse", "--verify", "--quiet", f"refs/heads/{self.branch}"],
            cwd=self.origin, capture_output=True, text=True, check=False,
        )
        return result.stdout.strip()

    def remote_authors(self):
        return git(self.origin, "log", "--format=%ae", f"1.2.x..refs/heads/{self.branch}").split()

    def test_first_publish_creates_a_bot_commit_and_pr(self):
        self.assertTrue(self.publish())
        self.assertEqual(self.remote_authors(), [BOT])
        self.assertIn("pr create", (self.root / "gh.log").read_text())

    def test_equivalent_patch_is_not_republished(self):
        self.assertTrue(self.publish())
        tip = self.remote_tip()
        (self.root / "gh.log").write_text("")
        self.assertFalse(self.publish())
        self.assertEqual(self.remote_tip(), tip)
        self.assertEqual((self.root / "gh.log").read_text(), "")

    def test_maintainer_commits_are_never_overwritten(self):
        self.assertTrue(self.publish())
        clone = self.root / "maintainer"
        git(self.root, "clone", "--quiet", "-b", self.branch, str(self.origin), str(clone))
        git(clone, "config", "user.email", "dev@example.com")
        git(clone, "config", "user.name", "Dev")
        git(clone, "config", "commit.gpgsign", "false")
        (clone / "extra").write_text("fix\n")
        git(clone, "add", "extra")
        git(clone, "commit", "--quiet", "-m", "maintainer fix")
        git(clone, "push", "--quiet", "origin", f"HEAD:refs/heads/{self.branch}")
        tip = self.remote_tip()
        with self.assertRaisesRegex(REMEDIATION.RemediationError, "dev@example.com"):
            self.publish()
        self.assertEqual(self.remote_tip(), tip)

    def test_stale_bot_branch_is_refreshed_with_a_lease(self):
        self.assertTrue(self.publish())
        old_tip = self.remote_tip()
        (self.work / "README").write_text("two\n")
        git(self.work, "checkout", "--quiet", "1.2.x")
        git(self.work, "add", "README")
        git(self.work, "commit", "--quiet", "-m", "advance base")
        git(self.work, "push", "--quiet", "origin", "1.2.x")
        self.assertTrue(self.publish())
        self.assertNotEqual(self.remote_tip(), old_tip)
        self.assertEqual(self.remote_authors(), [BOT])

    def test_lease_rejects_a_push_that_raced_the_fetch(self):
        self.assertTrue(self.publish())
        stale = self.remote_tip()
        (self.work / "README").write_text("two\n")
        git(self.work, "checkout", "--quiet", "1.2.x")
        git(self.work, "add", "README")
        git(self.work, "commit", "--quiet", "-m", "advance base")
        git(self.work, "push", "--quiet", "origin", "1.2.x")
        # Another bot-authored run lands after our fetch but before our push.
        racer = self.root / "racer"
        git(self.root, "clone", "--quiet", "-b", self.branch, str(self.origin), str(racer))
        for key, value in (("user.name", "bot"), ("user.email", BOT), ("commit.gpgsign", "false")):
            git(racer, "config", key, value)
        (racer / "race").write_text("x\n")
        git(racer, "add", "race")
        git(racer, "commit", "--quiet", "-m", "raced")
        git(racer, "push", "--quiet", "origin", f"HEAD:refs/heads/{self.branch}")
        raced_tip = self.remote_tip()
        git(self.work, "fetch", "--quiet", "origin")
        with mock.patch.object(REMEDIATION, "fetch_remote_branch", return_value=stale):
            with self.assertRaises(subprocess.CalledProcessError):
                REMEDIATION.publish_event(
                    metadata_path=self.metadata_path, repository_root=self.work,
                    repository="Cacti/cacti", base_branch="1.2.x",
                    assignee="TheWitness", issue=1, run_url="https://example.invalid/run/1",
                )
        self.assertEqual(self.remote_tip(), raced_tip)

    def test_lease_refuses_to_clobber_a_branch_created_after_the_query(self):
        racer = self.root / "racer"
        git(self.root, "clone", "--quiet", str(self.origin), str(racer))
        git(racer, "config", "user.email", BOT)
        git(racer, "config", "user.name", "bot")
        git(racer, "config", "commit.gpgsign", "false")
        (racer / "race").write_text("x\n")
        git(racer, "add", "race")
        git(racer, "commit", "--quiet", "-m", "raced")
        git(racer, "push", "--quiet", "origin", f"HEAD:refs/heads/{self.branch}")
        with mock.patch.object(REMEDIATION, "fetch_remote_branch", return_value=None):
            with self.assertRaises(subprocess.CalledProcessError):
                REMEDIATION.publish_event(
                    metadata_path=self.metadata_path, repository_root=self.work,
                    repository="Cacti/cacti", base_branch="1.2.x",
                    assignee="TheWitness", issue=1, run_url="https://example.invalid/run/1",
                )

    def test_fetch_remote_branch_reports_absent_and_unreachable(self):
        self.assertIsNone(REMEDIATION.fetch_remote_branch(self.work, "no-such-branch"))
        git(self.work, "remote", "set-url", "origin", str(self.root / "missing.git"))
        with self.assertRaises(REMEDIATION.RemediationError):
            REMEDIATION.fetch_remote_branch(self.work, "no-such-branch")

    def test_patch_that_no_longer_applies_is_rejected(self):
        (self.work / "composer.lock").write_text("diverged\n")
        git(self.work, "add", "composer.lock")
        git(self.work, "commit", "--quiet", "-m", "diverge")
        git(self.work, "push", "--quiet", "origin", "1.2.x")
        with self.assertRaises(REMEDIATION.RemediationError):
            self.publish()
        self.assertEqual(self.remote_tip(), "")


class WorkflowWiringTest(unittest.TestCase):
    def setUp(self) -> None:
        self.workflow = yaml.safe_load(WORKFLOW.read_text(encoding="utf-8"))
        self.text = WORKFLOW.read_text(encoding="utf-8")

    def checkout(self, job, name):
        return next(
            step for step in self.workflow["jobs"][job]["steps"]
            if step["name"] == name
        )

    def test_audit_job_uses_a_shallow_released_checkout(self):
        step = self.checkout("audit", "Checkout released branch")
        self.assertNotIn("fetch-depth", step["with"])
        self.assertIs(step["with"]["persist-credentials"], False)

    def test_publisher_keeps_full_history_and_credentials_for_the_push(self):
        step = self.checkout("publish-remediation", "Checkout released branch for publication")
        self.assertEqual(step["with"]["fetch-depth"], 0)
        self.assertIs(step["with"]["persist-credentials"], True)
        trusted = self.checkout("publish-remediation", "Checkout trusted publisher")
        self.assertIs(trusted["with"]["persist-credentials"], False)

    def test_every_job_declares_minimal_permissions(self):
        self.assertEqual(
            self.workflow["jobs"]["audit-contracts"]["permissions"],
            {"contents": "read"},
        )

    def test_actions_are_pinned_to_commit_shas_with_version_comments(self):
        for line in self.text.splitlines():
            if "uses: actions/" in line:
                self.assertRegex(line, r"@[0-9a-f]{40} # v\d")

    def test_tracking_issue_is_a_variable_not_a_literal(self):
        self.assertNotIn("8577", self.text)
        publish = self.checkout("publish-remediation", "Open or refresh draft remediation pull requests")
        self.assertEqual(
            publish["env"]["RELEASED_REMEDIATION_ISSUE"],
            "${{ vars.RELEASED_REMEDIATION_ISSUE }}",
        )
        self.assertNotIn("--issue", publish["run"])

    def run_gate(self, updater, materialize, remediation=None, job=None):
        step = self.checkout("audit", "Require remediation for security findings")
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / "automation").symlink_to(ROOT)
            temp = root / "tmp"
            output = temp / "remediation" / "x"
            output.mkdir(parents=True)
            (temp / "dependabot-job.json").write_text(
                json.dumps({"job": {"dependencies": job or ["a/a"]}})
            )
            if remediation is not None:
                (output / "status.json").write_text(json.dumps(remediation[0]))
                for dependencies in remediation[1]:
                    identifier = REMEDIATION.event_id("composer", "/", dependencies)
                    event = output / "events" / identifier
                    event.mkdir(parents=True)
                    (event / "metadata.json").write_text(json.dumps({
                        "schema": 1, "id": identifier, "ecosystem": "composer",
                        "directory": "/", "base_commit": "a" * 40,
                        "dependencies": dependencies, "files": ["composer.lock"],
                    }))
            return subprocess.run(
                ["bash", "-c", step["run"]], cwd=root, check=False, text=True,
                capture_output=True,
                env={
                    **os.environ, "RUNNER_TEMP": str(temp), "REMEDIATION_DIR": str(output),
                    "UPDATER_OUTCOME": updater, "MATERIALIZE_OUTCOME": materialize,
                    "PYTHONDONTWRITEBYTECODE": "1",
                },
            )

    def test_gate_passes_on_complete_remediation(self):
        status = {"schema": 1, "created": ["x"], "error_types": []}
        self.assertEqual(self.run_gate("success", "success", (status, [["a/a"]])).returncode, 0)

    def test_gate_fails_on_recorded_error_types(self):
        status = {"schema": 1, "created": ["x"], "error_types": ["update_not_possible"]}
        result = self.run_gate("success", "success", (status, [["a/a"]]))
        self.assertEqual(result.returncode, 1)
        self.assertIn("update_not_possible", result.stderr)

    def test_gate_fails_on_partial_remediation(self):
        status = {"schema": 1, "created": ["x"], "error_types": []}
        result = self.run_gate("success", "success", (status, [["a/a"]]), job=["a/a", "b/b"])
        self.assertEqual(result.returncode, 1)
        self.assertIn("b/b", result.stderr)

    def test_gate_fails_when_updater_or_materializer_failed(self):
        status = {"schema": 1, "created": [], "error_types": []}
        self.assertEqual(self.run_gate("failure", "success", (status, [])).returncode, 1)
        self.assertEqual(self.run_gate("success", "failure").returncode, 1)
        self.assertEqual(self.run_gate("success", "success").returncode, 1)  # no status.json


if __name__ == "__main__":
    unittest.main()

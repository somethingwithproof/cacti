"""Regression tests for read-only squash forecasting. Run with unittest discovery."""

import json
import os
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

import forecast_pr_conflicts as planner


class SquashForecastTest(unittest.TestCase):
    def setUp(self):
        temp = tempfile.TemporaryDirectory()
        self.addCleanup(temp.cleanup)
        self.parent = Path(temp.name)
        self.repo = self.parent / "source worktree"
        self.env = {
            **os.environ,
            "GIT_CONFIG_GLOBAL": os.devnull,
            "GIT_CONFIG_NOSYSTEM": "1",
            "GIT_AUTHOR_NAME": "Forecast tests",
            "GIT_AUTHOR_EMAIL": "test@example.org",
            "GIT_COMMITTER_NAME": "Forecast tests",
            "GIT_COMMITTER_EMAIL": "test@example.org",
        }
        for key in (
            planner.command(["git", "rev-parse", "--local-env-vars"])
            .decode()
            .splitlines()
        ):
            self.env.pop(key, None)
        self.git("init", "--quiet", "--template=", str(self.repo), cwd=self.parent)
        self.root = self.commit(None, {"value": "old"})

    def git(self, *args, cwd=None):
        return (
            planner.command(["git", *args], cwd=cwd or self.repo, env=self.env)
            .decode()
            .strip()
        )

    def commit(self, parent, files):
        if parent:
            self.git("checkout", "--quiet", "--detach", parent)
        for name, value in files.items():
            (self.repo / name).write_text(value + "\n")
        self.git("add", "--", *files)
        self.git("commit", "--quiet", "-m", "fixture")
        return self.git("rev-parse", "HEAD")

    def cli(self, base, heads, *, extra_env=None):
        args = [
            sys.executable,
            planner.__file__,
            "--local",
            str(self.repo),
            "--base",
            base,
            "--json",
        ]
        for number, head in heads:
            args.extend(["--pr", f"{number}={head}"])
        return subprocess.run(
            args,
            env={**self.env, **(extra_env or {})},
            capture_output=True,
            text=True,
            check=False,
        )

    def report(self, base, heads, **kwargs):
        proc = self.cli(base, heads.items(), **kwargs)
        self.assertEqual(proc.returncode, 0, proc.stderr)
        return json.loads(proc.stdout)

    def verify_batches_with_git_squash(self, report, heads):
        # Replay the returned orders with the ordinary index-based Git command.
        clone = self.parent / "independent replay"
        self.git(
            "clone",
            "--quiet",
            "--no-local",
            str(self.repo),
            str(clone),
            cwd=self.parent,
        )
        for batch in report["batches"]:
            self.git(
                "checkout", "--quiet", "--force", "--detach", report["base"], cwd=clone
            )
            for step in batch["order"]:
                self.git("merge", "--squash", heads[step["number"]], cwd=clone)
                self.assertEqual(self.git("write-tree", cwd=clone), step["tree"])
                if step["changes_tree"]:
                    self.git("commit", "--quiet", "-m", "real squash", cwd=clone)
            self.assertEqual(
                self.git("rev-parse", "HEAD^{tree}", cwd=clone), batch["final_tree"]
            )

    def pin(self, refs):
        # An ordinary clone should have all fixture commits, including side branches.
        for name, oid in refs.items():
            self.git("branch", str(name), oid)

    def test_asymmetric_order_real_squash_and_dirty_checkout_preservation(self):
        base = self.commit(self.root, {"value": "upstream"})
        heads = {
            1: self.commit(self.root, {"value": "upstream", "alpha": "first"}),
            2: self.commit(base, {"value": "second"}),
            3: self.commit(base, {"other": "third"}),
        }
        self.pin({"base": base, **{f"pr-{n}": oid for n, oid in heads.items()}})
        (self.repo / "value").write_text("staged\n")
        self.git("add", "value")
        (self.repo / "value").write_text("also unstaged\n")
        (self.repo / "untracked").write_text("untracked\n")

        def footprint():
            return (
                self.git("rev-parse", "HEAD"),
                self.git("show-ref"),
                (self.repo / ".git/index").read_bytes(),
                self.git("count-objects", "-v"),
                (self.repo / "value").read_bytes(),
                (self.repo / "untracked").read_bytes(),
            )

        before = footprint()
        report = self.report(
            "base",
            {n: f"pr-{n}" for n in heads},
            extra_env={
                "GIT_DIR": str(self.parent / "missing"),
                "GIT_INDEX_FILE": str(self.parent / "wrong index"),
            },
        )
        self.assertEqual(footprint(), before)
        self.assertEqual(report["base"], base)
        self.assertEqual(report["prs"]["1"]["head"], heads[1])
        pair = report["pairs"][0]
        self.assertEqual((pair["a"], pair["b"]), (1, 2))
        self.assertEqual(pair["a_then_b"], {"status": "clean", "paths": []})
        self.assertEqual(pair["b_then_a"], {"status": "conflict", "paths": ["value"]})
        self.assertEqual(report["risk"][0], {"number": 1, "peers": [2]})
        self.assertEqual(
            [s["number"] for s in report["batches"][0]["order"]], [1, 2, 3]
        )
        self.verify_batches_with_git_squash(report, heads)

    def test_independent_batches_preserve_conflict_paths_with_newlines(self):
        name = "release notes\nnext"
        base = self.commit(self.root, {name: "baseline"})
        heads = {
            1: self.commit(base, {name: "one"}),
            2: self.commit(base, {name: "two"}),
            3: self.commit(base, {"more": "independent"}),
        }
        self.pin({f"pr-{n}": oid for n, oid in heads.items()})
        report = self.report(base, heads)
        pair = report["pairs"][0]
        for direction in ("a_then_b", "b_then_a"):
            self.assertEqual(pair[direction], {"status": "conflict", "paths": [name]})
        self.assertEqual(
            [[s["number"] for s in b["order"]] for b in report["batches"]],
            [[3, 1], [2]],
        )
        self.assertEqual(
            report["batches"][0]["conflicts_at_tip"], [{"number": 2, "paths": [name]}]
        )
        self.assertEqual(report["unplaced"], [])
        self.verify_batches_with_git_squash(report, heads)

    def test_base_conflict_can_become_clean_after_another_squash(self):
        base = self.commit(self.root, {"value": "base"})
        heads = {
            1: self.commit(self.root, {"value": "same"}),
            2: self.commit(base, {"value": "same"}),
        }
        self.pin({f"pr-{n}": oid for n, oid in heads.items()})
        report = self.report(base, heads)
        self.assertFalse(report["prs"]["1"]["clean"])
        self.assertEqual(
            report["pairs"][0]["a_then_b"],
            {"status": "first_conflicts", "paths": ["value"]},
        )
        self.assertEqual(
            [(s["number"], s["changes_tree"]) for s in report["batches"][0]["order"]],
            [(2, True), (1, False)],
        )
        self.assertEqual(report["unplaced"], [])
        self.verify_batches_with_git_squash(report, heads)
        alone = self.report(base, {1: heads[1]})
        self.assertEqual(alone["unplaced"], [{"number": 1, "paths": ["value"]}])
        self.assertEqual(alone["batches"], [])

    def test_empty_group_and_invalid_inputs(self):
        report = self.report(self.root, {})
        self.assertEqual(report["pairs"], [])
        self.assertEqual(report["batches"], [])
        duplicate = self.cli(self.root, [(1, self.root), (1, self.root)])
        self.assertNotEqual(duplicate.returncode, 0)
        self.assertIn("unique positive PR number", duplicate.stderr)
        self.assertNotEqual(self.cli(self.root, [(1, "absent")]).returncode, 0)

    def test_discovery_uses_authenticated_owner_ready_prs_and_checks_fetched_hashes(
        self,
    ):
        responses = [b"", b"Owner\n", b"5\towner\tabc\tbase\n6\tother\tdef\tbase\n"]
        with (
            patch.object(planner, "command", side_effect=responses) as calls,
            patch.object(planner, "Forecast") as fake,
        ):
            git = fake.return_value.git
            git.side_effect = lambda *args: {
                "refs/forecast/base": "base",
                "refs/forecast/pr/5": "abc",
            }.get(args[-1], "")
            _, base, heads, owner = planner.github_refs(
                "org/repo", "release", self.parent
            )
            self.assertEqual((base, heads, owner), ("base", {5: "abc"}, "Owner"))
            listing = calls.call_args_list[2].args[0]
            for item in ["GET", "--paginate", "state=open", "base=release"]:
                self.assertIn(item, listing)
            self.assertIn(".draft == false", listing[-1])
            fetch = next(c.args for c in git.call_args_list if c.args[0] == "fetch")
            self.assertIn("refs/pull/5/head:refs/forecast/pr/5", fetch)
            self.assertNotIn("refs/pull/6/head:refs/forecast/pr/6", fetch)
        with (
            patch.object(planner, "command", side_effect=responses),
            patch.object(planner, "Forecast") as fake,
        ):
            fake.return_value.git.return_value = "moved"
            with self.assertRaisesRegex(ValueError, "moved"):
                planner.github_refs("org/repo", "release", self.parent)


if __name__ == "__main__":
    unittest.main()

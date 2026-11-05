"""Pin the least-privilege invariants of every GitHub Actions workflow.

The rules follow GitHub's "Security hardening for GitHub Actions" and
"Use GITHUB_TOKEN for authentication in workflows" guidance: declare a
restrictive top-level token, grant write scopes per job with a stated reason,
pin third-party actions to commit SHAs, keep credentials out of checkouts that
do not push, and never splice event data into shell scripts.

A file listed in PENDING is exempt from one rule while another change owns its fix.
"""

from __future__ import annotations

import re
import unittest
from pathlib import Path

try:
    import yaml
except ImportError:  # structural checks are skipped; text checks still run
    yaml = None

ROOT = Path(__file__).resolve().parents[2]
WORKFLOW_DIR = ROOT / ".github/workflows"
ACTION_DIR = ROOT / ".github/actions"

# rule -> {workflow path under .github: owner of a pending fix}; none on this branch.
PENDING: dict[str, dict[str, str]] = {}

# Verified with `gh api repos/<owner>/<repo>/git/ref/tags/<tag>` (annotated tags
# dereferenced): each SHA is the commit that the named release tag points at.
VERIFIED_PINS = {
    "3d3c42e5aac5ba805825da76410c181273ba90b1": "v7.0.1",  # actions/checkout
    "949feb2413d6458794dcd2491c4babbbce0c15c1": "v7.1.0",  # actions/setup-node
    "cf430e030ddbb5b0abf93d22962f4752f3646cd9": "v7.0.2",  # actions/upload-artifact
    "9000827ccba6bdab643e8b6fd33ac0654aef8333": "v8.0.2",  # actions/download-artifact
    "55cc8345863c7cc4c66a329aec7e433d2d1c52a9": "v6.1.0",  # actions/cache
    "eb7c497e18156a6bbabfef1d3a82760b9eda3962": "2.40.0",  # shivammathur/setup-php
    "f3e473d116dcccaddc5834248c87452386958240": "2.37.2",  # shivammathur/setup-php
    "4391f3da665fdf50b6810c1a66712fb9ba21aa93": "v11.0.0",  # actions/stale
    "9b3254085494b55a0f7c4c2b3d2d52cc712ce370": "v4.39.1",  # github/codeql-action
}

# Event fields that hold a commit SHA, a number or a fixed event name. Anything
# else under github.event, inputs or a ref name is attacker-influenced text.
SAFE_EVENT = re.compile(
    r"github\.event\.(?:pull_request\.(?:base|head)\.sha|pull_request\.number"
    r"|merge_group\.(?:base|head)_sha|before|after)$"
)
UNTRUSTED = re.compile(
    r"(?:github\.event\.|\binputs\.|github\.head_ref|github\.base_ref|github\.ref_name)"
)
EXPRESSION = re.compile(r"\$\{\{(.*?)\}\}", re.S)
USES_LINE = re.compile(
    r"^\s*(?:-\s+)?uses:\s+(?P<action>\S+?)@(?P<ref>\S+)(?:\s+#\s*(?P<comment>\S+))?\s*$"
)


def workflow_files() -> list[Path]:
    return sorted([*WORKFLOW_DIR.glob("*.y*ml"), *ACTION_DIR.glob("*/action.y*ml")])


def key(path: Path) -> str:
    return path.relative_to(ROOT / ".github").as_posix()


def pending(rule: str, path: Path) -> bool:
    return key(path) in PENDING.get(rule, {})


def load(path: Path):
    return yaml.safe_load(path.read_text(encoding="utf-8"))


def jobs_of(document) -> dict:
    return document.get("jobs") or {} if isinstance(document, dict) else {}


def steps_of(job) -> list[dict]:
    steps = job.get("steps") if isinstance(job, dict) else None
    return [step for step in steps or [] if isinstance(step, dict)]


def write_scopes(permissions) -> list[str]:
    if permissions == "write-all":
        return ["write-all"]
    if not isinstance(permissions, dict):
        return []
    return [scope for scope, level in permissions.items() if level == "write"]


class WorkflowTextTest(unittest.TestCase):
    """Rules that read the raw text, so they run without PyYAML."""

    def test_workflows_exist(self) -> None:
        self.assertTrue(list(WORKFLOW_DIR.glob("*.y*ml")))

    def test_no_privileged_untrusted_triggers(self) -> None:
        for path in workflow_files():
            with self.subTest(path=path.name):
                text = path.read_text(encoding="utf-8")
                self.assertNotRegex(text, r"(?m)^\s*(?:pull_request_target|workflow_run):")

    def test_no_unsecure_commands_or_pipe_to_shell(self) -> None:
        for path in workflow_files():
            with self.subTest(path=path.name):
                text = path.read_text(encoding="utf-8")
                self.assertNotIn("ACTIONS_ALLOW_UNSECURE_COMMANDS", text)
                self.assertNotRegex(
                    text, r"curl\b[^\n|]*\|\s*(?:sudo\s+)?(?:/(?:usr/)?bin/)?(?:sh|bash)\b"
                )

    def test_every_remote_action_is_pinned_to_a_sha_with_a_version_comment(self) -> None:
        for path in workflow_files():
            if pending("pin-comments", path):
                continue
            for number, line in enumerate(path.read_text(encoding="utf-8").splitlines(), 1):
                match = USES_LINE.match(line)
                if not match or match["action"].startswith(("./", "docker://")):
                    continue
                with self.subTest(path=path.name, line=number):
                    self.assertRegex(match["ref"], r"^[0-9a-f]{40}$", line.strip())
                    self.assertRegex(
                        match["comment"] or "", r"^v?\d+(?:\.\d+)*$", f"missing version comment: {line.strip()}"
                    )

    def test_verified_pins_carry_the_tag_they_resolve_to(self) -> None:
        for path in workflow_files():
            if pending("pin-comments", path):
                continue
            for number, line in enumerate(path.read_text(encoding="utf-8").splitlines(), 1):
                match = USES_LINE.match(line)
                if match and match["ref"] in VERIFIED_PINS:
                    with self.subTest(path=path.name, line=number):
                        self.assertEqual(match["comment"], VERIFIED_PINS[match["ref"]], line.strip())

    def test_untrusted_event_data_is_never_expanded_into_a_shell_script(self) -> None:
        # Pass such values through env: and quote the variable instead.
        for path in workflow_files():
            for scope, runs in self.run_scripts(path):
                for expression in (m.strip() for script in runs for m in EXPRESSION.findall(script)):
                    with self.subTest(path=path.name, scope=scope, expression=expression):
                        if UNTRUSTED.search(expression):
                            self.assertRegex(expression, SAFE_EVENT)

    def test_write_permissions_state_why_they_are_needed(self) -> None:
        for path in workflow_files():
            if pending("write-justified", path):
                continue
            lines = path.read_text(encoding="utf-8").splitlines()
            for number, line in enumerate(lines):
                if re.match(r"^\s+[a-z-]+:\s+write\s*(?:#.*)?$", line):
                    # A comment on the line, or in the permission block above it,
                    # counts as the explanation.
                    above = number - 1
                    while above >= 0 and re.match(r"^\s*(?:#.*|[a-z-]+:\s+(?:read|write|none))\s*$", lines[above]):
                        above -= 1
                    documented = "#" in line or any(
                        lines[i].lstrip().startswith("#") for i in range(above + 1, number)
                    )
                    with self.subTest(path=path.name, line=number + 1):
                        self.assertTrue(documented, f"undocumented write permission: {line.strip()}")

    @staticmethod
    def run_scripts(path: Path):
        """Yield (location, [run scripts]) without needing PyYAML."""
        if yaml is None:
            yield path.name, [path.read_text(encoding="utf-8")]
            return
        document = load(path)
        if path.parent == ACTION_DIR / path.parent.name and path.name.startswith("action"):
            steps = (document.get("runs") or {}).get("steps") or []
            yield "action", [s["run"] for s in steps if isinstance(s, dict) and "run" in s]
            return
        for name, job in jobs_of(document).items():
            yield name, [s["run"] for s in steps_of(job) if "run" in s]


@unittest.skipIf(yaml is None, "PyYAML is not importable")
class WorkflowStructureTest(unittest.TestCase):
    def workflows(self):
        for path in sorted(WORKFLOW_DIR.glob("*.y*ml")):
            yield path, load(path)

    def test_every_workflow_declares_a_top_level_token(self) -> None:
        for path, document in self.workflows():
            if not pending("top-level-permissions", path):
                with self.subTest(path=path.name):
                    self.assertIn("permissions", document)

    def test_top_level_token_is_never_writable(self) -> None:
        # Grant write scopes on the job that needs them, not on every job.
        for path, document in self.workflows():
            if not pending("top-level-write", path):
                with self.subTest(path=path.name):
                    self.assertEqual(write_scopes(document.get("permissions")), [])

    def test_every_job_has_a_timeout(self) -> None:
        for path, document in self.workflows():
            for name, job in jobs_of(document).items():
                if "uses" not in job:  # reusable-workflow calls cannot set it
                    with self.subTest(path=path.name, job=name):
                        self.assertIn("timeout-minutes", job)

    def test_checkout_drops_credentials_unless_the_job_pushes(self) -> None:
        pushes: set[tuple[str, str]] = set()  # no job on this branch pushes
        for path, document in self.workflows():
            for name, job in jobs_of(document).items():
                for step in steps_of(job):
                    if not str(step.get("uses", "")).startswith("actions/checkout@"):
                        continue
                    persisted = (step.get("with") or {}).get("persist-credentials")
                    with self.subTest(path=path.name, job=name, step=step.get("name")):
                        if persisted is True:
                            self.assertIn((path.name, name), pushes)
                        else:
                            self.assertIs(persisted, False)

    def test_secrets_are_not_set_at_workflow_or_job_scope(self) -> None:
        for path, document in self.workflows():
            scopes = [("workflow", document.get("env"))]
            scopes += [(name, job.get("env")) for name, job in jobs_of(document).items()]
            for scope, env in scopes:
                with self.subTest(path=path.name, scope=scope):
                    # Comparing a secret to '' only tests presence; it exposes nothing.
                    exposed = re.findall(r"secrets\.\w+(?![\w\s]*!=)", str(env or ""))
                    self.assertEqual(exposed, [])


if __name__ == "__main__":
    unittest.main()

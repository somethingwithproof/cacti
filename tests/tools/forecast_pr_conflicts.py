#!/usr/bin/env python3
"""Simulate PRs landing as squash commits on one base, without updating a checkout.

Git 2.38+ and Python 3.9+ are required. For GitHub discovery, authenticate gh.
Use --github OWNER/REPO --base BRANCH for the signed-in user's open, ready PRs.
Use --local CHECKOUT --base REF --pr NUMBER=REF ... to forecast local refs offline.
"""

import argparse
import json
import os
import re
import subprocess
import tempfile
from itertools import combinations
from pathlib import Path


def command(args, *, env=None, cwd=None):
    result = subprocess.run(args, cwd=cwd, env=env, capture_output=True, check=False)
    if result.returncode:
        raise ValueError(
            result.stderr.decode(errors="replace").strip()
            or f"{args[0]} exited {result.returncode}"
        )
    return result.stdout


class Forecast:
    """All objects created by fetch, merge-tree and commit-tree live in the temporary repository."""

    def __init__(self, directory, object_format="sha1"):
        self.directory = directory
        local_variables = os.fsdecode(
            command(["git", "rev-parse", "--local-env-vars"])
        ).splitlines()
        self.env = {
            key: value
            for key, value in os.environ.items()
            if key not in local_variables
        }
        self.env.update(
            GIT_AUTHOR_NAME="Local merge forecast",
            GIT_COMMITTER_NAME="Local merge forecast",
            GIT_AUTHOR_EMAIL="forecast@localhost",
            GIT_COMMITTER_EMAIL="forecast@localhost",
            GIT_OPTIONAL_LOCKS="0",
            GIT_CONFIG_NOSYSTEM="1",
            GIT_CONFIG_GLOBAL=os.devnull,
        )
        command(
            [
                "git",
                "init",
                "--quiet",
                "--bare",
                "--template=",
                f"--object-format={object_format}",
                directory,
            ],
            env=self.env,
        )
        self.tips = {}
        self.merges = {}

    def git(self, *args):
        return (
            command(["git", "-C", self.directory, *args], env=self.env).decode().strip()
        )

    def merge(self, tip, head):
        if (tip, head) not in self.merges:
            result = subprocess.run(
                [
                    "git",
                    "-C",
                    self.directory,
                    "merge-tree",
                    "--write-tree",
                    "--name-only",
                    "--no-messages",
                    "-z",
                    tip,
                    head,
                ],
                env=self.env,
                capture_output=True,
                check=False,
            )
            if result.returncode not in (0, 1):
                raise ValueError(
                    result.stderr.decode(errors="replace").strip()
                    or "git merge-tree failed"
                )
            fields = result.stdout.rstrip(b"\0").split(b"\0")
            tree = fields[0].decode("ascii")
            if not re.fullmatch(r"[0-9a-f]{40}|[0-9a-f]{64}", tree):
                raise ValueError("git merge-tree returned an invalid tree ID")
            paths = sorted(os.fsdecode(path) for path in fields[1:] if path)
            self.merges[tip, head] = (result.returncode == 0, tree, paths)
        return self.merges[tip, head]

    def squash(self, parent, tree):
        key = parent, tree
        if key not in self.tips:
            if tree == self.git("rev-parse", parent + "^{tree}"):
                self.tips[key] = parent
            else:
                self.tips[key] = self.git(
                    "-c",
                    "commit.gpgSign=false",
                    "commit-tree",
                    tree,
                    "-p",
                    parent,
                    "-m",
                    "Temporary squash for local merge forecast",
                )
        return self.tips[key]

    def run(self, base, heads):
        first = {}
        baseline = {}
        for number, head in sorted(heads.items()):
            clean, tree, paths = self.merge(base, head)
            baseline[number] = {"head": head, "clean": clean, "paths": paths}
            if clean:
                first[number] = self.squash(base, tree)

        def test_order(a, b):
            if a not in first:
                return {"status": "first_conflicts", "paths": baseline[a]["paths"]}
            clean, _, paths = self.merge(first[a], heads[b])
            return {"status": "clean" if clean else "conflict", "paths": paths}

        outgoing = {n: set() for n in heads}
        peers = {n: set() for n in heads}
        pairs = []
        for a, b in combinations(sorted(heads), 2):
            ab, ba = test_order(a, b), test_order(b, a)
            pairs.append({"a": a, "b": b, "a_then_b": ab, "b_then_a": ba})
            # Count new conflicts only between PRs that each start clean.
            if a in first and b in first:
                for before, after, outcome in ((a, b, ab), (b, a, ba)):
                    if outcome["status"] == "conflict":
                        outgoing[before].add(after)
                        peers[before].add(after)
                        peers[after].add(before)

        # Every batch starts at the same real base. Only batch 1 is a proposed
        # sequence to land now; the later batches are independent alternatives.
        batches = []
        remaining = set(heads)
        while remaining:
            tip = base
            order = []
            while remaining:
                priority = sorted(
                    remaining, key=lambda n: (len(outgoing[n] & remaining), n)
                )
                for n in priority:
                    clean, tree, _ = self.merge(tip, heads[n])
                    if clean:
                        next_tip = self.squash(tip, tree)
                        order.append(
                            {"number": n, "tree": tree, "changes_tree": next_tip != tip}
                        )
                        remaining.remove(n)
                        tip = next_tip
                        break
                else:
                    break
            if not order:
                break
            blocked = [
                {"number": n, "paths": self.merge(tip, heads[n])[2]}
                for n in sorted(remaining)
            ]
            batches.append(
                {
                    "order": order,
                    "final_tree": self.git("rev-parse", tip + "^{tree}"),
                    "conflicts_at_tip": blocked,
                }
            )
        unplaced = [
            {"number": n, "paths": baseline[n]["paths"]} for n in sorted(remaining)
        ]
        risk = [
            {"number": n, "peers": sorted(peers[n])}
            for n in sorted(heads, key=lambda n: (-len(peers[n]), n))
        ]
        return {
            "base": base,
            "prs": baseline,
            "pairs": pairs,
            "risk": risk,
            "batches": batches,
            "unplaced": unplaced,
        }


def local_refs(checkout, base, refs, scratch):
    # Resolve user refs in the named checkout, then borrow its objects read-only.
    # A partial clone with missing objects must use --github for a network run.
    local_env = os.environ.copy()
    for key in os.fsdecode(
        command(["git", "rev-parse", "--local-env-vars"])
    ).splitlines():
        local_env.pop(key, None)
    local_env.update(GIT_OPTIONAL_LOCKS="0", GIT_NO_LAZY_FETCH="1")

    def read(*args):
        return os.fsdecode(
            command(["git", "-C", checkout, *args], env=local_env)
        ).rstrip("\n")

    forecast = Forecast(scratch, read("rev-parse", "--show-object-format"))
    objects = read("rev-parse", "--path-format=absolute", "--git-path", "objects")
    (scratch / "objects/info/alternates").write_bytes(os.fsencode(objects) + b"\n")
    if read("rev-parse", "--is-shallow-repository") == "true":
        shallow = Path(
            read("rev-parse", "--path-format=absolute", "--git-path", "shallow")
        )
        (scratch / "shallow").write_bytes(shallow.read_bytes())
    forecast.env["GIT_NO_LAZY_FETCH"] = "1"
    heads = {}
    for ref in refs:
        number, separator, value = ref.partition("=")
        if (
            not separator
            or not value
            or not re.fullmatch(r"[1-9][0-9]*", number)
            or int(number) in heads
        ):
            raise ValueError(f"expected a unique positive PR number and ref: {ref}")
        heads[int(number)] = read(
            "rev-parse", "--verify", "--end-of-options", value + "^{commit}"
        )
    return (
        forecast,
        read("rev-parse", "--verify", "--end-of-options", base + "^{commit}"),
        heads,
    )


def github_refs(repo, base, scratch, owner=None):
    if not re.fullmatch(r"[\w.-]+/[\w.-]+", repo) or repo.startswith("-"):
        raise ValueError("--github expects OWNER/REPO")
    command(["git", "check-ref-format", "refs/heads/" + base])
    if owner is None:
        owner = command(["gh", "api", "user", "--jq", ".login"]).decode().strip()
    if not owner:
        raise ValueError("gh did not return the signed-in user")
    # The base SHA embedded in a PR may reflect its last refresh. Pin the branch itself.
    expected_base = command(
        ["gh", "api", f"repos/{repo}/git/ref/heads/{base}", "--jq", ".object.sha"]
    ).decode().strip()
    listing = command(
        [
            "gh",
            "api",
            "--method",
            "GET",
            "--paginate",
            f"repos/{repo}/pulls",
            "-f",
            "state=open",
            "-f",
            "base=" + base,
            "-f",
            "per_page=100",
            "--jq",
            ".[] | select(.draft == false) | [.number,.user.login,.head.sha] | @tsv",
        ]
    )
    heads = {}
    for line in listing.decode().splitlines():
        number, login, head = line.split("\t")
        if login.casefold() == owner.casefold():
            heads[int(number)] = head

    forecast = Forecast(scratch)
    forecast.git("remote", "add", "origin", f"https://github.com/{repo}.git")
    forecast.git("config", "credential.helper", "!gh auth git-credential")
    forecast.git("config", "remote.origin.promisor", "true")
    forecast.git("config", "remote.origin.partialclonefilter", "blob:none")
    refspecs = [f"refs/pull/{n}/head:refs/forecast/pr/{n}" for n in heads]
    forecast.git(
        "fetch",
        "--no-tags",
        "--filter=blob:none",
        "origin",
        f"refs/heads/{base}:refs/forecast/base",
        *refspecs,
    )
    base_oid = forecast.git("rev-parse", "refs/forecast/base")
    if expected_base != base_oid or any(
        forecast.git("rev-parse", f"refs/forecast/pr/{n}") != head
        for n, head in heads.items()
    ):
        raise ValueError(
            "a PR or the base moved between discovery and fetch; retry the forecast"
        )
    return forecast, base_oid, heads, owner


def show(report):
    print(
        f"Base {report['target']['base_ref']} {report['base']} | {len(report['prs'])} PRs | {len(report['pairs'])} pairs"
    )
    for n, pr in report["prs"].items():
        state = "clean" if pr["clean"] else "conflict: " + ", ".join(pr["paths"])
        print(f"  #{n} {pr['head']} {state}")
    print("\nPairwise conflicts by merge order (other tested orders are clean)")
    for pair in report["pairs"]:
        a, b = pair["a"], pair["b"]
        for before, after, result in (
            (a, b, pair["a_then_b"]),
            (b, a, pair["b_then_a"]),
        ):
            if result["status"] != "clean":
                print(
                    f"  #{before} -> #{after}: {result['status']} {', '.join(result['paths'])}"
                )
    print("\nConflicting peers among PRs initially clean")
    for item in report["risk"]:
        if item["peers"]:
            print(
                f"  #{item['number']}: "
                + ", ".join(f"#{peer}" for peer in item["peers"])
            )
    print("\nVerified batches (each starts at the original base; selection is greedy)")
    for index, batch in enumerate(report["batches"], start=1):
        print(f"  Batch {index}:")
        for entry in batch["order"]:
            note = "" if entry["changes_tree"] else " (already present in tree)"
            print(f"    #{entry['number']} {entry['tree']}{note}")
        print(f"    Final tree {batch['final_tree']}")
    for item in report["unplaced"]:
        print(
            f"  Cannot start on this base: #{item['number']} "
            + ", ".join(item["paths"])
        )
    print(
        "After a batch lands, rerun against the new base before choosing the next PR."
    )


def main():
    parser = argparse.ArgumentParser(
        description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter
    )
    mode = parser.add_mutually_exclusive_group(required=True)
    mode.add_argument(
        "--github",
        metavar="OWNER/REPO",
        help="discover signed-in user's open, non-draft PRs",
    )
    mode.add_argument(
        "--local",
        type=Path,
        metavar="CHECKOUT",
        help="read local refs and available objects",
    )
    parser.add_argument("--author", help="GitHub author to forecast (defaults to the signed-in user)")
    parser.add_argument(
        "--base", required=True, help="GitHub branch or local commit/ref"
    )
    parser.add_argument(
        "--pr",
        action="append",
        default=[],
        metavar="NUMBER=REF",
        help="local PR, repeatable",
    )
    parser.add_argument(
        "--json", action="store_true", help="emit the complete machine-readable matrix"
    )
    args = parser.parse_args()
    if args.local and args.author:
        parser.error("--author belongs to --github")
    if args.github and args.pr:
        parser.error(
            "--pr belongs to --local; --github discovers PRs by owner and base"
        )
    try:
        with tempfile.TemporaryDirectory(prefix="cacti-pr-forecast-") as directory:
            scratch = Path(directory)
            if args.local:
                forecast, base, heads = local_refs(
                    args.local, args.base, args.pr, scratch
                )
            else:
                forecast, base, heads, owner = github_refs(
                    args.github, args.base, scratch, args.author
                )
            report = forecast.run(base, heads)
            target = {"base_ref": args.base}
            if args.github:
                target.update(repository=args.github, owner=owner)
            else:
                target["checkout"] = str(args.local.resolve())
            report["target"] = target
        if args.json:
            print(json.dumps(report, indent=2))
        else:
            show(report)
    except (OSError, ValueError) as error:
        parser.exit(1, f"{parser.prog}: {error}\n")


if __name__ == "__main__":
    main()

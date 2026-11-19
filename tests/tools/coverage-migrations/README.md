# November 19 coverage registration migrations

The snapshot contains 39 contributor PRs targeting the same `develop` commit. Each entry pins the PR head, its tree after an ordinary squash onto that base, and its resulting tree with feature-local coverage registrations. Shared changes install the coverage and dispatch readers once. Paths outside the coverage tooling keep their original Git blobs.

Among these 39 heads, Git found 349 conflicting pairs before migration and 49 on the shared foundation, across 741 pairs tested in both squash orders. Twenty-two moves cover all three shared registration files touched by the PR. Seventeen retain custom behavior in one or more shared files. The original dispatch suites (570 methods) and proposed suites (597 methods) passed in isolated fixture repositories. Those checks cover dispatch behavior. Production coverage was also exercised for two sampled features.

## Reproduce one patch

Start in a checkout of the contributor tooling branch with Git, Python 3 and jq. In this example the `upstream` remote is the parent project and `fork` is the contributor fork. Select a PR number from the snapshot. The replay tool requires the pinned input commits and their history. With those objects already available, the fetch steps can be omitted.

```sh
tools_dir="$(pwd)/tests/tools"
pins="$tools_dir/coverage-migrations/2026-11-19.json"
pr=8813
base=$(jq -r '.base' "$pins")
head=$(jq -er --arg pr "$pr" '.prs[$pr].head' "$pins")
foundation=$(jq -r '.fork_foundation' "$pins")
git fetch --no-tags upstream "$base" "$head"
git fetch --no-tags fork "$foundation"
python3 "$tools_dir/apply_coverage_pinset.py" --repo . --pinset "$pins" --pr "$pr" > "$pr-registration.patch"
```

The script verifies both endpoint trees and the patch in a temporary Git repository before writing to stdout. To inspect the result in a new local branch, use a fresh worktree.

```sh
preview="../coverage-preview-$pr"
git worktree add -b "ci/coverage-preview-$pr" "$preview" "$base"
git -C "$preview" merge --squash "$head"
test "$(git -C "$preview" write-tree)" = "$(jq -r --arg pr "$pr" '.prs[$pr].source_tree' "$pins")"
git -C "$preview" apply --index "$(pwd)/$pr-registration.patch"
test "$(git -C "$preview" write-tree)" = "$(jq -r --arg pr "$pr" '.prs[$pr].tree' "$pins")"
git -C "$preview" diff --cached --stat
```

The resulting index contains the original feature and its coverage migration. The snapshot starts from the recorded SHAs. For a moving branch, rerun the forecast against its current head before landing changes.

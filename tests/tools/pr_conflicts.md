# Rehearse changes before landing them

Run this from a checkout with the contributor tools. The planner uses Git and Python. GitHub discovery also uses the authenticated GitHub CLI.

```sh
python3 tests/tools/forecast_pr_conflicts.py --github Cacti/cacti --base develop
python3 tests/tools/forecast_pr_conflicts.py --github Cacti/cacti --base 1.2.x
```

Each run selects the signed-in contributor’s open, ready PRs for that base. It pins the inputs, tries both squash orders for each pair, and replays every proposed batch cumulatively. Use the first batch as a candidate sequence for PRs that have passed CI and review. Rerun after the base or a head changes, or when taking a different order. Each additional batch starts from the original base. Pass `--json` for exact head SHAs, conflicting paths and cumulative result trees.

For an offline rehearsal, supply refs already available in a local checkout.

```sh
python3 tests/tools/forecast_pr_conflicts.py --local . --base upstream/develop \
  --pr 8813=refs/pull/8813/head --pr 8624=refs/pull/8624/head
```

The fork workflow runs the forecast for both branches on pushes to `ci/pr-conflict-tools`. Each job lists its first batch in the summary and attaches the full forecast. It reads the fork owner’s PRs against the parent repository.

## Resolve independent registrations on a contributor branch

```sh
python3 tests/tools/git_cacti_merge.py --repo ../feature merge-tree --write-tree HEAD upstream/develop
python3 tests/tools/git_cacti_merge.py --repo ../feature merge upstream/develop
```

The first command rehearses a merge. The second performs it in the specified checkout. The wrapper enables the extra merge rules for that invocation. It combines recognized independent coverage registrations and additions to the current 1.2.x release notes. Incompatible ordering, labels, dispatch behavior and historical notes require manual resolution. Repository attributes take precedence. Review the merged result and run the feature’s checks before pushing. GitHub uses its own merge machinery.

To inspect a maintenance changelog conflict that is already in progress, run `python3 tests/tools/resolve_cacti_changelog.py --repo ../feature`. Add `--write` to replace a pristine conflicted file, then inspect the diff and stage the result.

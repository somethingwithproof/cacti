#!/usr/bin/env bash
# Asserts the package-import write sink rejects a path-traversal entry.
# GHSA-vp35-4h28-r883 (CVE-2026-39939) / GHSA-j696-m433-87qq (CVE-2026-39950).
set -euo pipefail
IFS=$'\n\t'

: "${CACTI_E2E_PORT:=8088}"

cd "$(dirname "$0")/.."

DC=(docker compose -f docker-compose.yml)

set +e
PROBE_OUT=$("${DC[@]}" exec -T cacti-master php /var/www/html/tests/e2e/docker/probes/probe_package_import_traversal.php 2>&1)
PROBE_RC=$?
set -e

echo "$PROBE_OUT"

if [ "$PROBE_RC" -ne 0 ]; then
    echo "FAIL: probe_package_import_traversal.php exit=$PROBE_RC" >&2
    exit 1
fi

if ! echo "$PROBE_OUT" | grep -qx 'new_guard_malicious=BLOCKED'; then
    echo "FAIL: malicious entry was not blocked by the new guard" >&2
    exit 1
fi

if ! echo "$PROBE_OUT" | grep -q '^new_guard_benign=RESOLVED:'; then
    echo "FAIL: benign entry did not resolve" >&2
    exit 1
fi

echo "PASS: package import rejects traversal, resolves legitimate entry"

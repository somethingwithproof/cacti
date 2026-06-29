#!/usr/bin/env bash
# Standalone harness for the package-import path-traversal probe.
# Brings the docker e2e stack up, runs the probe inside cacti-master, writes
# evidence, then tears down. KEEP_UP=1 leaves the stack running for debugging.
#
# GHSA-vp35-4h28-r883 (CVE-2026-39939) / GHSA-j696-m433-87qq (CVE-2026-39950).
set -euo pipefail
IFS=$'\n\t'

: "${CACTI_E2E_PORT:=8088}"
export CACTI_E2E_PORT

cd "$(dirname "$0")/docker"

DC=(docker compose -f docker-compose.yml)
KEEP_UP="${KEEP_UP:-0}"
RESULTS="${RESULTS:-package_import_traversal_results.txt}"

teardown() {
    if [ "$KEEP_UP" = "1" ]; then
        echo "[probe] KEEP_UP=1, leaving stack up"
        return
    fi
    echo "[probe] tearing down"
    "${DC[@]}" down --volumes --remove-orphans >/dev/null 2>&1 || true
}
trap teardown EXIT

echo "[probe] bringing stack up"
"${DC[@]}" up -d --build

echo "[probe] waiting for cacti-master to serve /index.php"
ready=0
for _ in $(seq 1 60); do
    if "${DC[@]}" exec -T cacti-master sh -c 'curl -fsS http://127.0.0.1/index.php >/dev/null'; then
        ready=1
        break
    fi
    sleep 2
done
if [ "$ready" -ne 1 ]; then
    echo "[probe] cacti-master never became ready" >&2
    "${DC[@]}" logs --tail 100 cacti-master >&2 || true
    exit 1
fi

echo "[probe] running probe_package_import_traversal.php"
set +e
PROBE_OUT=$("${DC[@]}" exec -T cacti-master php /var/www/html/tests/e2e/docker/probes/probe_package_import_traversal.php 2>&1)
PROBE_RC=$?
set -e

printf '%s\n' "$PROBE_OUT" | tee "$RESULTS"
echo "[probe] evidence written to $(pwd)/$RESULTS"

if [ "$PROBE_RC" -ne 0 ]; then
    echo "[probe] FAIL: probe exit=$PROBE_RC" >&2
    exit 1
fi

if ! printf '%s\n' "$PROBE_OUT" | grep -qx 'new_guard_malicious=BLOCKED'; then
    echo "[probe] FAIL: malicious entry was not blocked" >&2
    exit 1
fi

echo "[probe] PASS"

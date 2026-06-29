<?php
/*
 * Probes import_package_resolve_file() against the package-import path-traversal
 * sink (GHSA-vp35-4h28-r883 / CVE-2026-39939, GHSA-j696-m433-87qq / CVE-2026-39950).
 *
 * A malicious package entry ('resource/../../../../tmp/cacti_pwn.php') must be
 * blocked. A benign entry ('scripts/legit.php') must resolve inside the base.
 * For contrast the probe also prints what the OLD substring guard + raw concat
 * would have produced, i.e. the out-of-tree path it WOULD have written to.
 *
 * Exit code: 0 when the malicious entry is blocked, 2 when it is not.
 */

chdir(__DIR__ . '/../../../..');

require __DIR__ . '/../../../../include/global_constants.php';
require __DIR__ . '/../../../../lib/functions.php';
require __DIR__ . '/../../../../lib/import.php';

$base = sys_get_temp_dir() . '/cacti_pkg_probe_' . bin2hex(random_bytes(6));
mkdir($base . '/scripts', 0700, true);
mkdir($base . '/resource/script_server', 0700, true);

$malicious = 'resource/../../../../tmp/cacti_pwn.php';
$benign    = 'scripts/legit.php';

$new_malicious = import_package_resolve_file($malicious, $base);
$new_benign    = import_package_resolve_file($benign, $base);

/* Replica of the pre-fix sink: substring guard + raw concatenation. */
$old_guard_accepts = (strpos($malicious, 'scripts/') !== false || strpos($malicious, 'resource/') !== false);
$old_target        = $base . "/$malicious";

echo 'malicious_name=' . $malicious . "\n";
echo 'new_guard_malicious=' . ($new_malicious === false ? 'BLOCKED' : 'RESOLVED:' . $new_malicious) . "\n";
echo 'old_guard_malicious=' . ($old_guard_accepts ? 'ACCEPTED' : 'BLOCKED') . "\n";
echo 'old_target_path=' . $old_target . "\n";
echo 'old_target_realdir=' . (realpath(dirname($old_target)) ?: '(nonexistent)') . "\n";
echo 'benign_name=' . $benign . "\n";
echo 'new_guard_benign=' . ($new_benign === false ? 'BLOCKED' : 'RESOLVED:' . $new_benign) . "\n";

@rmdir($base . '/resource/script_server');
@rmdir($base . '/resource');
@rmdir($base . '/scripts');
@rmdir($base);

if ($new_malicious !== false) {
    fwrite(STDERR, "FAIL: malicious package entry was not blocked\n");
    exit(2);
}

if (!is_string($new_benign)) {
    fwrite(STDERR, "FAIL: benign package entry did not resolve\n");
    exit(2);
}

echo "PASS: malicious entry blocked, benign entry resolved\n";
exit(0);

<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

// Regression guard for the 8f3c import signature bypass. import_validate_signature()
// returns an array (truthy) even when the package is self-signed, so the former
// !import_validate_signature() test never fired. import_read_package_data() must
// reject a self-signed package when not previewing.

require_once __DIR__ . '/../Helpers/ImportSignatureFixture.php';

$keys    = make_self_signed_keypair();
$package = make_package_file($keys['public'], $keys['private']);

$sig = import_validate_signature($package);

if (!is_array($sig)) {
	fwrite(STDERR, "expected import_validate_signature to return an array for a self-signed package\n");
	exit(1);
}

if (!empty($sig['valid'])) {
	fwrite(STDERR, "self-signed key was wrongly marked valid\n");
	exit(1);
}

$public_key = '';
$result = import_read_package_data($package, $public_key, false);

if ($result !== false) {
	fwrite(STDERR, "self-signed package was accepted by import_read_package_data\n");
	exit(1);
}

unlink($package);

print "issue8f3c import signature verify regression passed\n";

<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

// Shared fixture for the import package signature-verify tests. It loads
// lib/import.php with the minimal set of stubs the signature path needs
// (cacti_log, read_config_option, array_rekey and the package_public_keys
// lookup) so import_read_package_data() and import_validate_signature() run
// against real zlib package files and real openssl verification without the
// full Cacti bootstrap.

require_once dirname(__DIR__, 2) . '/include/global_constants.php';
require_once __DIR__ . '/UnitStubs.php';

if (!function_exists('array_rekey')) {
	function array_rekey($array, $key, $key_value) {
		$ret = [];

		if (is_array($array)) {
			foreach ($array as $row) {
				if (isset($row[$key])) {
					$ret[$row[$key]] = is_array($key_value) ? null : ($row[$key_value] ?? null);
				}
			}
		}

		return $ret;
	}
}

if (!function_exists('db_fetch_assoc')) {
	// No extra trusted keys; only the built-in official Cacti keys are honored.
	function db_fetch_assoc($sql, $log = true, $db_conn = false) {
		return [];
	}
}

require_once dirname(__DIR__, 2) . '/lib/xml.php';
require_once dirname(__DIR__, 2) . '/lib/import.php';

/**
 * Write a gzip package file with the given public key and, optionally, a valid
 * self-signature produced by $private. When $private is null the signature line
 * is left empty (tampered/unsigned).
 */
function make_package_file(string $public_key_pem, $private = null): string {
	$head = "<xml>\n"
		. "   <files></files>\n"
		. '   <publickeyname>' . base64_encode('fixture') . "</publickeyname>\n"
		. '   <publickey>' . base64_encode($public_key_pem) . "</publickey>\n";

	$tail = "   <info>\n"
		. "      <name>Fixture Package</name>\n"
		. "      <author>fixture</author>\n"
		. "      <homepage>http://example.test</homepage>\n"
		. "      <email>fixture@example.test</email>\n"
		. "   </info>\n"
		. "</xml>\n";

	// import_read_package_data() rebuilds the document with the signature line
	// normalized to exactly this form before verifying, so sign that canonical
	// text.
	$canonical = $head . "   <signature></signature>\n" . $tail;

	$sig_line = "   <signature></signature>\n";

	if ($private !== null) {
		openssl_sign($canonical, $binsig, $private, OPENSSL_ALGO_SHA256);
		$sig_line = '   <signature>' . base64_encode($binsig) . "</signature>\n";
	}

	$pkgxml = $head . $sig_line . $tail;

	$path = tempnam(sys_get_temp_dir(), 'cacti_pkg_sig') . '.xml.gz';
	file_put_contents("compress.zlib://$path", $pkgxml);

	return $path;
}

/** Generate a fresh RSA keypair not trusted by Cacti (a self-signed author). */
function make_self_signed_keypair(): array {
	$res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
	$pub = openssl_pkey_get_details($res)['key'];

	return ['private' => $res, 'public' => $pub];
}

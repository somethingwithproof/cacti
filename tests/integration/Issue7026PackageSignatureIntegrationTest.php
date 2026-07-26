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

/*
 * Issue #7026 across the real package boundary. import_read_package_data()
 * reads a gzip package from disk and verifies its detached signature. This
 * builds a genuine gzip package that carries the official 2048-bit public key
 * (so it clears the key-size gate) but a bogus signature, then confirms the
 * SHA-256-only verify path rejects it. The verification call site is exercised
 * end to end: zlib stream, line parser, openssl_verify().
 *
 * The success path (openssl_verify returns 1) and the per-file verify in
 * import_package() both require a package signed with Cacti's private release
 * key, which is not available to the test suite. Those lines are covered by the
 * behavioural SHA-256/SHA-1 test in Issue7026Sha1SignatureRegressionTest.
 */

if (!defined('POLLER_VERBOSITY_LOW')) {
	define('POLLER_VERBOSITY_LOW', 1);
}

if (!defined('POLLER_VERBOSITY_MEDIUM')) {
	define('POLLER_VERBOSITY_MEDIUM', 2);
}

if (!function_exists('cacti_log')) {
	function cacti_log($message, $stdout = false, $environ = 'CMDPHP', $level = 0) {
		$GLOBALS['__import_log'][] = $message;
		return true;
	}
}

require_once __DIR__ . '/../../lib/import.php';

/**
 * Write a gzip package that presents Cacti's official 2048-bit public key and
 * the supplied detached signature, then return its path.
 */
function build_signed_package(string $signatureB64): string {
	$pk  = base64_encode(get_public_key_sha256());
	$xml = "<xml>\n"
		 . "   <files></files>\n"
		 . "   <publickey>$pk</publickey>\n"
		 . "   <signature>$signatureB64</signature>\n"
		 . "   <info>\n"
		 . "      <name>test-package</name>\n"
		 . "   </info>\n"
		 . "</xml>\n";

	$path = tempnam(sys_get_temp_dir(), 'cactipkg') . '.xml.gz';
	file_put_contents($path, gzencode($xml));

	return $path;
}

beforeEach(function () {
	$GLOBALS['__import_log'] = array();
});

test('#7026: a package with a forged signature is rejected through the SHA-256 path', function () {
	$path = build_signed_package(base64_encode('forged-signature-bytes'));

	$public_key = '';
	$result     = import_read_package_data($path, $public_key);
	@unlink($path);

	expect($result)->toBeFalse();
	$joined = implode("\n", $GLOBALS['__import_log']);
	expect($joined)->toContain('Tampered');
});

test('#7026: the official 2048-bit key clears the key-size gate and reaches verification', function () {
	// A pre-2048-bit gate would short-circuit before the verify call; this
	// asserts we reach the signature check rather than the key-size rejection.
	$path = build_signed_package(base64_encode('forged-signature-bytes'));

	$public_key = '';
	import_read_package_data($path, $public_key);
	@unlink($path);

	$joined = implode("\n", $GLOBALS['__import_log']);
	expect($joined)->not->toContain('minimum 2048 required');
	expect($joined)->toContain('Got Package Signature');
});

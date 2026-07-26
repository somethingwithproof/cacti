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
 * Issue #7031 happy path across the real filesystem. A genuine gzip package on
 * disk must resolve through realpath() and open via compress.zlib://<local>.
 * This confirms a legitimate local file still parses after the wrapper is built
 * from the resolved path rather than the raw input.
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

function build_zlib_package_7031(): string {
	$pk  = base64_encode(get_public_key_sha256());
	$xml = "<xml>\n"
		 . "   <files></files>\n"
		 . "   <publickey>$pk</publickey>\n"
		 . "   <signature>" . base64_encode('forged') . "</signature>\n"
		 . "   <info>\n"
		 . "      <name>zlib-path-test</name>\n"
		 . "   </info>\n"
		 . "</xml>\n";

	$path = tempnam(sys_get_temp_dir(), 'cactipkg') . '.xml.gz';
	file_put_contents($path, gzencode($xml));

	return $path;
}

beforeEach(function () {
	$GLOBALS['__import_log'] = array();
});

test('#7031: a real gzip package resolves through realpath and parses', function () {
	$path = build_zlib_package_7031();

	$details = import_package_get_details($path);

	@unlink($path);

	expect($details)->toBeArray();
	expect($details['name'] ?? null)->toBe('zlib-path-test');
});

test('#7031: import_read_package_data opens the resolved local file', function () {
	$path = build_zlib_package_7031();

	$public_key = '';
	// Signature is forged, so it returns false, but only after the resolved
	// wrapper is built and the file is opened.
	$result = import_read_package_data($path, $public_key);

	@unlink($path);

	expect($result)->toBeFalse();
	$joined = implode("\n", $GLOBALS['__import_log']);
	expect($joined)->toContain('Got Package Signature');
	expect($joined)->not->toContain('not a valid local file');
});

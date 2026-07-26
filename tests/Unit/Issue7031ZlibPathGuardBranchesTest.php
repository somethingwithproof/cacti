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
 * Branch coverage for issue #7031. import_package_get_details() and
 * import_read_package_data() must resolve $xmlfile with realpath() and reject
 * null bytes and stream wrappers before building the compress.zlib:// path.
 * Each rejection branch is driven here; realpath()/is_file() are the only
 * external calls and they operate on the real (missing) paths under test.
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

beforeEach(function () {
	$GLOBALS['__import_log'] = array();
});

test('import_package_get_details rejects a null byte before realpath', function () {
	expect(import_package_get_details("/tmp/pkg\0.xml.gz"))->toBe(array());
});

test('import_read_package_data rejects a null byte before realpath', function () {
	$pk = '';
	expect(import_read_package_data("/tmp/pkg\0.xml.gz", $pk))->toBeFalse();
});

test('import_package_get_details rejects a stream-wrapper path (realpath false)', function () {
	// realpath() returns false for a wrapper, so the inner file is never opened.
	expect(import_package_get_details('compress.zlib:///etc/hosts'))->toBe(array());

	$joined = implode("\n", $GLOBALS['__import_log']);
	expect($joined)->toContain('not a valid local file');
});

test('import_read_package_data rejects a stream-wrapper path (realpath false)', function () {
	$pk = '';
	expect(import_read_package_data('phar:///tmp/evil.phar', $pk))->toBeFalse();

	$joined = implode("\n", $GLOBALS['__import_log']);
	expect($joined)->toContain('not a valid local file');
});

test('import_package_get_details rejects a non-existent local path', function () {
	expect(import_package_get_details('/tmp/does-not-exist-7031.xml.gz'))->toBe(array());
});

test('import_read_package_data rejects a non-existent local path', function () {
	$pk = '';
	expect(import_read_package_data('/tmp/does-not-exist-7031.xml.gz', $pk))->toBeFalse();
});

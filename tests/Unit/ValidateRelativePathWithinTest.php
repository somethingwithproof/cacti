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
 * validate_relative_path_within() is the containment primitive the package
 * import write path (lib/import.php) relies on to keep a crafted file name from
 * escaping the base directory. This covers its input-rejection branches: type,
 * empty, null byte, absolute paths, Windows drive letters, traversal and empty
 * segments, and a base directory that does not resolve. The filesystem success
 * and symlink branches are exercised in the integration test.
 */

require_once __DIR__ . '/../../lib/functions.php';

test('rejects non-string, empty and null-byte input', function () {
	$base = sys_get_temp_dir();

	expect(validate_relative_path_within(123, $base))->toBeFalse();
	expect(validate_relative_path_within('', $base))->toBeFalse();
	expect(validate_relative_path_within("scripts/a\0b", $base))->toBeFalse();
});

test('rejects absolute and Windows-drive paths', function () {
	$base = sys_get_temp_dir();

	expect(validate_relative_path_within('/etc/passwd', $base))->toBeFalse();
	expect(validate_relative_path_within('C:/Windows/win.ini', $base))->toBeFalse();
});

test('rejects traversal, current-dir and empty segments', function () {
	$base = sys_get_temp_dir();

	expect(validate_relative_path_within('scripts/../../../etc/passwd', $base))->toBeFalse();
	expect(validate_relative_path_within('scripts/./evil', $base))->toBeFalse();
	expect(validate_relative_path_within('scripts//evil', $base))->toBeFalse();
});

test('rejects a base directory that does not resolve', function () {
	expect(validate_relative_path_within('scripts/ok.php', '/no/such/base/dir/xyz-7031'))->toBeFalse();
});

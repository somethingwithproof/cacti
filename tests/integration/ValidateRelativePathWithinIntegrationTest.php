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
 * validate_relative_path_within() against a real filesystem base. Covers the
 * branches that need actual paths: an existing file that stays inside the base,
 * a not-yet-existing file whose parent is inside the base, a missing parent, and
 * a symlink pivot that must be refused. This is the containment the package
 * import write path in lib/import.php depends on.
 */

require_once __DIR__ . '/../../lib/functions.php';

beforeEach(function () {
	$this->base = sys_get_temp_dir() . '/cacti_vrpw_' . bin2hex(random_bytes(6));
	mkdir($this->base . '/scripts', 0700, true);
	file_put_contents($this->base . '/scripts/existing.php', "<?php\n");
});

afterEach(function () {
	$base = $this->base;
	@unlink($base . '/scripts/existing.php');
	@unlink($base . '/scripts/link');
	@rmdir($base . '/scripts');
	@rmdir($base . '/outside');
	@rmdir($base);
});

test('accepts an existing file that stays inside the base', function () {
	$result = validate_relative_path_within('scripts/existing.php', $this->base);

	expect($result)->toBe(realpath($this->base) . '/scripts/existing.php');
});

test('accepts a not-yet-existing file whose parent is inside the base', function () {
	$result = validate_relative_path_within('scripts/new.php', $this->base);

	expect($result)->toBe(realpath($this->base) . '/scripts/new.php');
});

test('rejects a path whose parent directory does not exist', function () {
	$result = validate_relative_path_within('missingdir/new.php', $this->base);

	expect($result)->toBeFalse();
});

test('rejects a symlink component pivoting out of the base', function () {
	if (!function_exists('symlink')) {
		$this->markTestSkipped('symlink() unavailable');
	}

	mkdir($this->base . '/outside', 0700, true);
	$made = @symlink($this->base . '/outside', $this->base . '/scripts/link');

	if ($made === false) {
		$this->markTestSkipped('symlink creation not permitted');
	}

	$result = validate_relative_path_within('scripts/link/evil.php', $this->base);

	expect($result)->toBeFalse();
});

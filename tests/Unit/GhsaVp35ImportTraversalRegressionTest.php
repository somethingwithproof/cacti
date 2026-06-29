<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Runtime regression for the import_package() path-traversal write sink.
 *
 * GHSA-vp35-4h28-r883 (CVE-2026-39939, arbitrary file write) and
 * GHSA-j696-m433-87qq (CVE-2026-39950, plugin PHP write -> RCE) both reach the
 * same foreach in import_package(). The old guard was a substring test
 * (strpos($name,'scripts/')!==false || strpos($name,'resource/')!==false) that
 * accepted 'resource/../shell.php', letting the write escape $config['base_path'].
 *
 * import_package_resolve_file() must reject any entry that escapes the scripts/
 * or resource/ subtrees and only return an absolute path contained in the base.
 */

beforeAll(function () {
	require_once dirname(__DIR__, 2) . '/include/global_constants.php';
	require_once dirname(__DIR__, 2) . '/lib/functions.php';
	require_once dirname(__DIR__, 2) . '/lib/import.php';
});

/* A base dir with the real scripts/ and resource/script_server/ subtrees so
   that validate_relative_path_within() can resolve the parent of a not-yet-
   existing candidate via realpath(dirname()). */
function ghsa_vp35_make_base() {
	$base = sys_get_temp_dir() . '/cacti_vp35_' . bin2hex(random_bytes(6));

	mkdir($base . '/scripts', 0700, true);
	mkdir($base . '/resource/script_server', 0700, true);

	return $base;
}

test('GHSA-vp35-4h28-r883 / GHSA-j696-m433-87qq: traversal and unprefixed entries are rejected', function () {
	$base = ghsa_vp35_make_base();

	$rejected = array(
		'resource/../shell.php',
		'scripts/../../escape.php',
		'/etc/passwd',
		'resource/sub/../../../escape.php',
		"scripts/\0evil.php",
		'evil.php',
		'scriptsX/evil.php',
		'resource',
	);

	foreach ($rejected as $name) {
		expect(import_package_resolve_file($name, $base))->toBe(false, "expected reject: $name");
	}
});

test('GHSA-vp35-4h28-r883 / GHSA-j696-m433-87qq: legitimate script and resource files resolve inside the base', function () {
	$base = ghsa_vp35_make_base();

	$script = import_package_resolve_file('scripts/myinput.php', $base);
	expect($script)->toBeString();
	expect(realpath(dirname($script)) . '/' . basename($script))->toEndWith('/scripts/myinput.php');
	expect(cacti_path_is_within(realpath(dirname($script)), realpath($base)))->toBeTrue();

	$resource = import_package_resolve_file('resource/script_server/test.pl', $base);
	expect($resource)->toBeString();
	expect(realpath(dirname($resource)) . '/' . basename($resource))->toEndWith('/resource/script_server/test.pl');
	expect(cacti_path_is_within(realpath(dirname($resource)), realpath($base)))->toBeTrue();
});

test('GHSA-vp35-4h28-r883 / GHSA-j696-m433-87qq: the old substring guard would have accepted the traversal the new guard rejects', function () {
	$base = ghsa_vp35_make_base();
	$name = 'resource/../shell.php';

	/* Replica of the vulnerable pre-fix guard. */
	$old_guard_accepts = (strpos($name, 'scripts/') !== false || strpos($name, 'resource/') !== false);

	expect($old_guard_accepts)->toBeTrue();
	expect(import_package_resolve_file($name, $base))->toBe(false);
});

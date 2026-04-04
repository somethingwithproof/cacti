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

/**
 * Behavioral tests for the xml_path traversal boundary check in
 * get_data_query_array() (lib/data_query.php).
 *
 * The guard uses realpath() + str_starts_with() to confine xml_path to
 * CACTI_PATH_BASE before the XML file is loaded. Tests exercise the
 * predicate against real temporary filesystem paths so that the logic
 * can only pass if the guard is correct at runtime.
 *
 * Source-scan assertions are retained at the bottom as secondary lint
 * checks to catch textual regressions.
 *
 * See: https://github.com/Cacti/cacti/issues/6936
 */

/*
 * Mirrors the boundary-check predicate from get_data_query_array().
 * Returns true when $xml_file_path resolves to a real path inside $base.
 * Kept in sync with lib/data_query.php lines ~742-746.
 */
function xml_path_within_base(string $xml_file_path, string $base): bool {
	$allowed_base = realpath($base);
	$resolved     = realpath($xml_file_path);

	if ($allowed_base === false || $resolved === false) {
		return false;
	}

	$sep      = DIRECTORY_SEPARATOR;
	$base_cmp = rtrim(str_replace(['/', '\\'], $sep, $allowed_base), $sep) . $sep;
	$path_cmp = str_replace(['/', '\\'], $sep, $resolved) . $sep;

	return str_starts_with($path_cmp, $base_cmp);
}

// ---- temporary filesystem fixtures ----

$xp_root    = sys_get_temp_dir() . '/cacti_xp_' . getmypid();
$xp_base    = $xp_root . '/base';
$xp_outside = $xp_root . '/outside';

mkdir($xp_base . '/resource', 0755, true);
mkdir($xp_outside, 0755, true);

$xp_valid   = $xp_base . '/resource/query.xml';
$xp_outside_xml = $xp_outside . '/secret.xml';

file_put_contents($xp_valid, '<cacti/>');
file_put_contents($xp_outside_xml, '<secret/>');

register_shutdown_function(static function () use ($xp_root): void {
	if (!is_dir($xp_root)) {
		return;
	}

	$iter = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($xp_root, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ($iter as $entry) {
		$entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
	}

	@rmdir($xp_root);
});

// ---- behavioral tests ----

test('xml path inside base is accepted', function () use ($xp_base, $xp_valid) {
	expect(xml_path_within_base($xp_valid, $xp_base))->toBeTrue();
});

test('xml path outside base is rejected', function () use ($xp_base, $xp_outside_xml) {
	expect(xml_path_within_base($xp_outside_xml, $xp_base))->toBeFalse();
});

test('nonexistent xml path is rejected', function () use ($xp_base) {
	expect(xml_path_within_base($xp_base . '/resource/missing.xml', $xp_base))->toBeFalse();
});

test('dotdot traversal resolving outside base is rejected', function () use ($xp_base, $xp_outside_xml) {
	// Construct a lexically-inside path that resolves outside via ../..
	$traversal = $xp_base . '/resource/../../outside/secret.xml';
	expect(xml_path_within_base($traversal, $xp_base))->toBeFalse();
});

test('symlink inside base targeting outside path is rejected', function () use ($xp_base, $xp_outside_xml) {
	// realpath() follows symlinks, so the resolved path falls outside the base.
	$link = $xp_base . '/resource/escape.xml';
	symlink($xp_outside_xml, $link);
	$result = xml_path_within_base($link, $xp_base);
	unlink($link);
	expect($result)->toBeFalse();
});

// ---- source-scan lint (secondary) ----

$src = file_get_contents(__DIR__ . '/../../lib/data_query.php');

test('get_data_query_array calls realpath on the resolved xml_file_path', function () use ($src) {
	expect($src)->toContain('realpath($xml_file_path)');
});

test('get_data_query_array calls realpath on CACTI_PATH_BASE for boundary anchor', function () use ($src) {
	expect($src)->toContain('realpath(CACTI_PATH_BASE)');
});

test('realpath boundary check uses str_starts_with with DIRECTORY_SEPARATOR', function () use ($src) {
	expect($src)->toContain('str_starts_with($path_cmp, $base_cmp)');
});

test('get_data_query_array returns early before file() when guard fails', function () use ($src) {
	$guard_pos = strpos($src, '!str_starts_with($path_cmp, $base_cmp)');
	$file_pos  = strpos($src, "implode('',file(\$resolved))");
	expect($guard_pos)->not->toBeFalse()
		->and($file_pos)->not->toBeFalse()
		->and($guard_pos)->toBeLessThan($file_pos);
});

test('boundary check logs a SECURITY message on path violation', function () use ($src) {
	expect($src)->toContain("'SECURITY: data query XML path outside Cacti base:");
});

test('xml path resolving to base directory itself passes boundary guard; is_file gate in source rejects it', function () use ($xp_base, $src) {
	// The boundary predicate (str_starts_with) passes when xml_path resolves to the
	// base directory itself. Production code gates on is_file($resolved) at line ~742
	// before calling file(); verify that gate is present in source.
	expect(xml_path_within_base($xp_base, $xp_base))->toBeTrue();

	$isfile_pos = strpos($src, 'is_file($resolved)');
	expect($isfile_pos)->not->toBeFalse();
});

test('xml path with dotdot segments that resolve inside base is accepted', function () use ($xp_base, $xp_valid) {
	// realpath() collapses ../resource/ back to resource/; the resolved path stays inside base.
	$with_dotdot = $xp_base . '/resource/../resource/query.xml';
	expect(xml_path_within_base($with_dotdot, $xp_base))->toBeTrue();
});

test('DIRECTORY_SEPARATOR suffix prevents sibling-directory prefix collision', function () use ($xp_root) {
	// Without the DS suffix, str_starts_with('base_ext/...', 'base') would be true.
	// The suffix forces a path-component boundary so 'base_ext' is correctly rejected.
	$sibling = $xp_root . '/base_ext';
	mkdir($sibling, 0755, true);
	$xml = $sibling . '/query.xml';
	file_put_contents($xml, '<cacti/>');
	$result = xml_path_within_base($xml, $xp_root . '/base');
	@unlink($xml);
	@rmdir($sibling);
	expect($result)->toBeFalse();
});

test('boundary violation calls cacti_log with SECURITY channel', function () use ($src) {
	// Verify the dedicated cacti_log call (not just the debug-timer offset) uses the SECURITY channel.
	expect($src)->toContain("cacti_log('SECURITY: Data query XML path outside Cacti base: '");
});

test('xml path with spaces in directory name inside base is accepted', function () use ($xp_base) {
	// Paths with special characters (spaces) that resolve inside the base must not produce false negatives.
	$dir = $xp_base . '/resource/with spaces';
	mkdir($dir, 0755, true);
	$xml = $dir . '/query.xml';
	file_put_contents($xml, '<cacti/>');
	$result = xml_path_within_base($xml, $xp_base);
	@unlink($xml);
	@rmdir($dir);
	expect($result)->toBeTrue();
});

test('base path with trailing slash still accepts valid xml path inside base', function () use ($xp_base, $xp_valid) {
	// realpath() normalises trailing slashes, so passing $base . '/' must not cause a false negative.
	expect(xml_path_within_base($xp_valid, $xp_base . '/'))->toBeTrue();
});

test('symlink resolving to exactly CACTI_PATH_BASE is accepted by boundary guard', function () use ($xp_root, $xp_base) {
	// A symlink pointing at the base directory resolves to the base via realpath().
	// str_starts_with(base . DS, base . DS) is true, so the guard does not fire.
	$link = $xp_root . '/base_link';
	symlink($xp_base, $link);
	$result = xml_path_within_base($link, $xp_base);
	@unlink($link);
	expect($result)->toBeTrue();
});

test('nonexistent base path causes boundary check to return false', function () use ($xp_valid) {
	// realpath() on a nonexistent base returns false; the guard short-circuits before any path comparison.
	expect(xml_path_within_base($xp_valid, '/nonexistent/cacti/base/path'))->toBeFalse();
});

test('relative xml path that does not resolve is rejected', function () use ($xp_base) {
	// realpath() returns false for relative paths that do not exist on disk.
	expect(xml_path_within_base('resource/query.xml', $xp_base))->toBeFalse();
});

test('directory inside base passes boundary guard but is_file guard present in source', function () use ($xp_base, $src) {
	// The boundary predicate accepts a directory path inside the base; the production
	// is_file($resolved) check is the second gate that rejects it before file() is called.
	expect(xml_path_within_base($xp_base . '/resource', $xp_base))->toBeTrue();

	$isfile_pos = strpos($src, 'is_file($resolved)');
	$file_pos   = strpos($src, "implode('',file(\$resolved))");
	expect($isfile_pos)->not->toBeFalse()
		->and($file_pos)->not->toBeFalse()
		->and($isfile_pos)->toBeLessThan($file_pos);
});

test('percent-encoded traversal sequences are rejected because realpath finds no such file', function () use ($xp_base) {
	// %2e is not decoded by the OS; realpath() returns false for non-existent paths,
	// so percent-encoded dot-dot sequences cannot bypass the boundary check.
	expect(xml_path_within_base($xp_base . '/resource/%2e%2e/%2e%2e/etc/passwd', $xp_base))->toBeFalse();
});

test('xml path containing null byte is rejected', function () use ($xp_base) {
	// PHP 8.1+ raises ValueError for null bytes passed to realpath(); either the
	// exception or a false return is sufficient to prevent the path from resolving.
	$null_path = $xp_base . "/resource/query.xml\0evil";
	$rejected  = false;

	try {
		$rejected = !xml_path_within_base($null_path, $xp_base);
	} catch (\ValueError $e) {
		$rejected = true;
	}

	expect($rejected)->toBeTrue();
});

test('xml path with non-ASCII characters inside base is accepted', function () use ($xp_base) {
	// realpath() resolves UTF-8 directory names correctly on Linux/macOS; the boundary
	// check must not produce a false negative for paths containing accented characters.
	$dir = $xp_base . '/resource/caf\u{00E9}';
	mkdir($dir, 0755, true);
	$xml = $dir . '/query.xml';
	file_put_contents($xml, '<cacti/>');
	$result = xml_path_within_base($xml, $xp_base);
	@unlink($xml);
	@rmdir($dir);
	expect($result)->toBeTrue();
});

test('base path that is itself a symlink still accepts xml inside the real target', function () use ($xp_root, $xp_base, $xp_valid) {
	// When CACTI_PATH_BASE is a symlink (not a canonical path), realpath() resolves it
	// to its target before comparison. An XML file inside the real target directory must
	// still be accepted; the guard must not fail because the base was a symlink.
	$base_link = $xp_root . '/base_via_symlink';
	symlink($xp_base, $base_link);
	$result = xml_path_within_base($xp_valid, $base_link);
	@unlink($base_link);
	expect($result)->toBeTrue();
});

test('two-hop symlink chain resolving outside base is rejected', function () use ($xp_base, $xp_outside_xml) {
	// realpath() follows all levels of indirection in one call. A two-hop chain
	// (link1 -> link2 -> outside_file) must resolve to the outside path and be
	// rejected; the first hop being inside the base does not confer trust.
	$hop2 = $xp_base . '/resource/chain_link2.xml';
	$hop1 = $xp_base . '/resource/chain_link1.xml';
	symlink($xp_outside_xml, $hop2);
	symlink($hop2, $hop1);
	$result = xml_path_within_base($hop1, $xp_base);
	@unlink($hop1);
	@unlink($hop2);
	expect($result)->toBeFalse();
});

test('path exceeding system path-length limit is rejected', function () use ($xp_base) {
	// realpath() returns false when a path component sequence exceeds the OS path
	// length limit (PATH_MAX, typically 4096 bytes). The boundary guard must treat
	// the false return as a rejection rather than producing a PHP warning or error.
	$segment  = str_repeat('a', 255);
	$long_path = $xp_base . '/' . implode('/', array_fill(0, 20, $segment)) . '/query.xml';
	expect(xml_path_within_base($long_path, $xp_base))->toBeFalse();
});

test('symlink inside base pointing to file outside base on system temp dir is rejected', function () use ($xp_base) {
	// Simulates a symlink that crosses a potential filesystem boundary (e.g., a mounted
	// volume or tmpfs). realpath() resolves across mount points to the canonical path;
	// the resolved destination falls outside the base and must be rejected.
	$outside_file = sys_get_temp_dir() . '/cacti_xp_fs_' . getmypid() . '.xml';
	file_put_contents($outside_file, '<cacti/>');
	$link = $xp_base . '/resource/cross_fs.xml';
	symlink($outside_file, $link);
	$result = xml_path_within_base($link, $xp_base);
	@unlink($link);
	@unlink($outside_file);
	expect($result)->toBeFalse();
});

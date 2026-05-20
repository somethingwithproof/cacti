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
 * Integration tests verifying that deprecated jQuery patterns have been
 * removed from Cacti JavaScript files and replaced with modern equivalents.
 *
 * Deprecated patterns:
 *   .unbind()    -> .off()
 *   .bind()      -> .on()
 *   .click(fn)   -> .on('click', fn) [standalone, not chained with .off()]
 *   $.parseJSON() -> JSON.parse()
 *   .hover()     -> .on('mouseenter mouseleave')
 */

$basePath = dirname(__DIR__, 2);

// --- include/layout.js ---

test('layout.js does not contain .unbind(', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/include/layout.js');

	expect($contents)->not->toContain('.unbind(');
});

test('layout.js does not contain standalone .bind(', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/include/layout.js');

	expect($contents)->not->toContain('.bind(');
});

test('layout.js does not contain standalone .click( without .on(', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/include/layout.js');

	// Verify no standalone .click( exists (all should have been migrated to .on('click'))
	expect($contents)->toContain(".on('click'");

	$lines = explode("\n", $contents);

	foreach ($lines as $lineNum => $line) {
		$trimmed = trim($line);

		if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '/*')) {
			continue;
		}

		if (preg_match('/\.click\s*\(/', $line)) {
			expect($line)->toMatch('/\.on\s*\(\s*[\'"]click/');
		}
	}
});

test('layout.js uses .off() and .on() patterns', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/include/layout.js');

	expect($contents)->toContain('.off(');
	expect($contents)->toContain('.on(');
});

// --- include/realtime.js ---

test('realtime.js does not contain $.parseJSON(', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/include/realtime.js');

	expect($contents)->not->toContain('$.parseJSON(');
});

test('realtime.js does not contain .bind(', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/include/realtime.js');

	expect($contents)->not->toContain('.bind(');
});

test('realtime.js does not contain .unbind(', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/include/realtime.js');

	expect($contents)->not->toContain('.unbind(');
});

test('realtime.js uses JSON.parse instead of $.parseJSON', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/include/realtime.js');

	expect($contents)->toContain('JSON.parse');
});

test('realtime.js uses .on() for event binding', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/include/realtime.js');

	expect($contents)->toContain('.on(');
});

// --- install/install.js ---

test('install.js does not contain .unbind(', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/install/install.js');

	expect($contents)->not->toContain('.unbind(');
});

test('install.js does not contain .bind(', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/install/install.js');

	expect($contents)->not->toContain('.bind(');
});

test('install.js does not contain $.parseJSON(', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/install/install.js');

	expect($contents)->not->toContain('$.parseJSON(');
});

test('install.js uses .on() for event binding', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/install/install.js');

	expect($contents)->toContain('.on(');
});

// --- Theme main.js files ---

test('theme main.js files do not contain .hover(', function () use ($basePath) {
	$themes = glob($basePath . '/include/themes/*/main.js');

	expect($themes)->not->toBeEmpty();

	foreach ($themes as $themeFile) {
		$contents = file_get_contents($themeFile);
		$themeName = basename(dirname($themeFile));

		expect($contents)->not->toContain('.hover(', "Theme '$themeName/main.js' still contains deprecated .hover(");
	}
});

test('theme main.js files do not contain .unbind(', function () use ($basePath) {
	$themes = glob($basePath . '/include/themes/*/main.js');

	expect($themes)->not->toBeEmpty();

	foreach ($themes as $themeFile) {
		$contents = file_get_contents($themeFile);
		$themeName = basename(dirname($themeFile));

		expect($contents)->not->toContain('.unbind(', "Theme '$themeName/main.js' still contains deprecated .unbind(");
	}
});

test('theme main.js files do not contain .bind(', function () use ($basePath) {
	$themes = glob($basePath . '/include/themes/*/main.js');

	expect($themes)->not->toBeEmpty();

	foreach ($themes as $themeFile) {
		$contents = file_get_contents($themeFile);
		$themeName = basename(dirname($themeFile));

		expect($contents)->not->toContain('.bind(', "Theme '$themeName/main.js' still contains deprecated .bind(");
	}
});

test('theme main.js files do not contain $.parseJSON(', function () use ($basePath) {
	$themes = glob($basePath . '/include/themes/*/main.js');

	expect($themes)->not->toBeEmpty();

	foreach ($themes as $themeFile) {
		$contents = file_get_contents($themeFile);
		$themeName = basename(dirname($themeFile));

		expect($contents)->not->toContain('$.parseJSON(', "Theme '$themeName/main.js' still contains deprecated $.parseJSON(");
	}
});

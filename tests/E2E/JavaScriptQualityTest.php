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

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';

// =====================================================================
// E2E JavaScript Quality Tests
//
// Verify JavaScript files are free of deprecated jQuery patterns and
// contain the expected modern replacements.
// =====================================================================

test('layout.js has no deprecated jQuery .unbind() method', function () {
	$path = dirname(__DIR__, 2) . '/include/layout.js';
	if (!file_exists($path)) {
		$this->markTestSkipped('include/layout.js not found');
	}

	$content = file_get_contents($path);
	expect($content)->not->toContain('.unbind(');
});

test('layout.js has no deprecated jQuery .bind() method', function () {
	$path = dirname(__DIR__, 2) . '/include/layout.js';
	if (!file_exists($path)) {
		$this->markTestSkipped('include/layout.js not found');
	}

	$content = file_get_contents($path);
	expect($content)->not->toContain('.bind(');
});

test('layout.js uses modern .off() for event unbinding', function () {
	$path = dirname(__DIR__, 2) . '/include/layout.js';
	if (!file_exists($path)) {
		$this->markTestSkipped('include/layout.js not found');
	}

	$content = file_get_contents($path);
	expect($content)->toContain('.off(');
});

test('layout.js uses modern .on() for event binding', function () {
	$path = dirname(__DIR__, 2) . '/include/layout.js';
	if (!file_exists($path)) {
		$this->markTestSkipped('include/layout.js not found');
	}

	$content = file_get_contents($path);
	expect($content)->toContain(".on('click'");
});

test('realtime.js has no deprecated jQuery methods', function () {
	$path = dirname(__DIR__, 2) . '/include/realtime.js';
	if (!file_exists($path)) {
		$this->markTestSkipped('include/realtime.js not found');
	}

	$content = file_get_contents($path);
	expect($content)->not->toContain('.unbind(');
	expect($content)->not->toContain('.bind(');
});

test('install.js has no deprecated jQuery methods', function () {
	$path = dirname(__DIR__, 2) . '/install/install.js';
	if (!file_exists($path)) {
		$this->markTestSkipped('install/install.js not found');
	}

	$content = file_get_contents($path);
	expect($content)->not->toContain('.unbind(');
	expect($content)->not->toContain('.bind(');
});

test('install.js uses modern .on() for event binding', function () {
	$path = dirname(__DIR__, 2) . '/install/install.js';
	if (!file_exists($path)) {
		$this->markTestSkipped('install/install.js not found');
	}

	$content = file_get_contents($path);
	expect($content)->toContain('.on(');
});

test('theme main.js files have no deprecated jQuery methods', function () {
	$base = dirname(__DIR__, 2) . '/include/themes';
	$themes = glob($base . '/*/main.js');

	expect(count($themes))->toBeGreaterThan(0, 'No theme main.js files found');

	foreach ($themes as $path) {
		$theme = basename(dirname($path));
		$content = file_get_contents($path);
		expect($content)->not->toContain('.unbind(', "Theme '{$theme}' main.js contains deprecated .unbind()");
		expect($content)->not->toContain('.bind(', "Theme '{$theme}' main.js contains deprecated .bind()");
	}
});

test('no JavaScript files use deprecated jQuery .live() method', function () {
	$jsFiles = array_merge(
		glob(dirname(__DIR__, 2) . '/include/*.js'),
		glob(dirname(__DIR__, 2) . '/install/*.js'),
		glob(dirname(__DIR__, 2) . '/include/themes/*/main.js'),
	);

	foreach ($jsFiles as $path) {
		$content = file_get_contents($path);
		$filename = basename($path);
		expect($content)->not->toContain('.live(', "File '{$filename}' contains deprecated .live() method");
	}
});

test('no JavaScript files use deprecated jQuery .die() method', function () {
	$jsFiles = array_merge(
		glob(dirname(__DIR__, 2) . '/include/*.js'),
		glob(dirname(__DIR__, 2) . '/install/*.js'),
		glob(dirname(__DIR__, 2) . '/include/themes/*/main.js'),
	);

	foreach ($jsFiles as $path) {
		$content = file_get_contents($path);
		$filename = basename($path);
		expect($content)->not->toContain('.die(', "File '{$filename}' contains deprecated .die() method");
	}
});

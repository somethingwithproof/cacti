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

require_once __DIR__ . '/../../lib/functions.php';
require_once __DIR__ . '/../../lib/html.php';

// --- html_escape ---

test('html_escape returns plain text unchanged', function () {
	expect(html_escape('hello world'))->toBe('hello world');
});

test('html_escape escapes angle brackets', function () {
	expect(html_escape('<script>alert(1)</script>'))->toBe('&lt;script&gt;alert(1)&lt;/script&gt;');
});

test('html_escape replaces backtick with entity', function () {
	expect(html_escape('test`xss'))->toContain('&#96;');
});

test('html_escape escapes single quotes', function () {
	expect(html_escape("it's"))->toBe('it&apos;s');
});

test('html_escape escapes double quotes', function () {
	expect(html_escape('"quoted"'))->toBe('&quot;quoted&quot;');
});

test('html_escape escapes ampersand', function () {
	expect(html_escape('a & b'))->toBe('a &amp; b');
});

test('html_escape returns empty string for empty input', function () {
	expect(html_escape(''))->toBe('');
});

test('html_escape does not double-encode existing entities', function () {
	// The false fourth argument to htmlspecialchars prevents double-encoding.
	expect(html_escape('&amp;'))->toBe('&amp;');
});

// --- html_split_string ---

test('html_split_string returns short string unchanged', function () {
	expect(html_split_string('short string', 90, 10))->toBe('short string');
});

test('html_split_string inserts br at word boundary', function () {
	// 20 chars of 'a', then a space, then 20 more 'b's; length=20, forgiveness=10.
	// The space sits exactly at position 20, within forgiveness, so a break occurs there.
	$left  = str_repeat('a', 20);
	$right = str_repeat('b', 20);
	$result = html_split_string($left . ' ' . $right, 20, 10);
	expect($result)->toContain('<br>');
});

test('html_split_string with no spaces skips br insertion', function () {
	// No spaces found within forgiveness range; for-loop exhausts without
	// appending to new_string, string is shortened each iteration until
	// it fits within length and is appended without <br>
	$long = str_repeat('x', 50);
	$result = html_split_string($long, 20, 5);
	expect($result)->not->toContain('<br>');
});

test('html_split_string caps at five iterations', function () {
	// A string long enough to require more than 5 splits; the safety valve truncates output.
	$long = str_repeat('abcdefghijklmnopqrst ', 30); // ~630 chars
	$result = html_split_string($long, 20, 5);
	// At most 5 <br> tags can appear (j breaks after index 4).
	expect(substr_count($result, '<br>'))->toBeLessThanOrEqual(5);
});

test('html_split_string respects custom length parameter', function () {
	$long = str_repeat('a', 10) . ' ' . str_repeat('b', 10);
	// With length=10 the space at position 10 is within forgiveness=5.
	$result = html_split_string($long, 10, 5);
	expect($result)->toContain('<br>');
});

test('html_split_string returns empty string for empty input', function () {
	expect(html_split_string('', 90, 10))->toBe('');
});

test('html_split_string returns string exactly at length unchanged', function () {
	$s = str_repeat('a', 90);
	expect(html_split_string($s, 90, 10))->toBe($s);
});

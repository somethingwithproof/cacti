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

// --- title_trim ---

test('title_trim returns text unchanged when equal to max_length', function () {
	expect(title_trim('hello', 5))->toBe('hello');
});

test('title_trim returns text unchanged when shorter than max_length', function () {
	expect(title_trim('hi', 10))->toBe('hi');
});

test('title_trim truncates and appends ellipsis when longer than max_length', function () {
	expect(title_trim('hello world', 5))->toBe('hello...');
});

test('title_trim returns ellipsis suffix only when max_length is zero', function () {
	expect(title_trim('hello', 0))->toBe('...');
});

test('title_trim returns empty string unchanged', function () {
	expect(title_trim('', 5))->toBe('');
});

// --- clean_up_lines ---

test('clean_up_lines replaces CRLF with a single space', function () {
	expect(clean_up_lines("line1\r\nline2"))->toBe('line1 line2');
});

test('clean_up_lines replaces bare LF with a single space', function () {
	expect(clean_up_lines("line1\nline2"))->toBe('line1 line2');
});

test('clean_up_lines collapses multiple consecutive newlines', function () {
	expect(clean_up_lines("a\n\n\nb"))->toBe('a b');
});

test('clean_up_lines returns null unchanged', function () {
	expect(clean_up_lines(null))->toBeNull();
});

test('clean_up_lines leaves strings without newlines unchanged', function () {
	expect(clean_up_lines('no newlines here'))->toBe('no newlines here');
});

// --- clean_up_name ---

test('clean_up_name converts spaces to underscores', function () {
	expect(clean_up_name('hello world'))->toBe('hello_world');
});

test('clean_up_name converts dots to underscores', function () {
	expect(clean_up_name('host.name.local'))->toBe('host_name_local');
});

test('clean_up_name removes special characters', function () {
	expect(clean_up_name('foo@bar!'))->toBe('foobar');
});

test('clean_up_name collapses multiple consecutive underscores', function () {
	expect(clean_up_name('a  b'))->toBe('a_b');
});

test('clean_up_name removes hyphens (unlike clean_up_file_name)', function () {
	expect(clean_up_name('my-device'))->toBe('mydevice');
});

test('clean_up_name returns null unchanged', function () {
	expect(clean_up_name(null))->toBeNull();
});

// --- clean_up_file_name ---

test('clean_up_file_name preserves hyphens', function () {
	expect(clean_up_file_name('my-file-name'))->toBe('my-file-name');
});

test('clean_up_file_name converts spaces to underscores', function () {
	expect(clean_up_file_name('my file'))->toBe('my_file');
});

test('clean_up_file_name removes special characters other than hyphen', function () {
	expect(clean_up_file_name('file@name!'))->toBe('filename');
});

test('clean_up_file_name collapses multiple consecutive underscores', function () {
	expect(clean_up_file_name('a  b'))->toBe('a_b');
});

test('clean_up_file_name returns null unchanged', function () {
	expect(clean_up_file_name(null))->toBeNull();
});

// --- sanitize_search_string ---

test('sanitize_search_string removes parentheses', function () {
	expect(sanitize_search_string('foo(bar)'))->toBe('foobar');
});

test('sanitize_search_string removes angle brackets', function () {
	// < and > are replaced with spaces, so surrounding spaces remain
	expect(sanitize_search_string('<script>'))->toBe(' script ');
});

test('sanitize_search_string removes backtick', function () {
	expect(sanitize_search_string('cmd`exec`'))->toBe('cmdexec');
});

test('sanitize_search_string replaces newlines with space', function () {
	expect(sanitize_search_string("line1\nline2"))->toBe('line1 line2');
});

test('sanitize_search_string strips HTML entity patterns', function () {
	// &nbsp; matches \b&[a-z]+;\b and is replaced with a space
	expect(sanitize_search_string('hello&nbsp;world'))->toBe('hello world');
});

test('sanitize_search_string strips URLs', function () {
	expect(sanitize_search_string('visit http://example.com now'))->toBe('visit   now');
});

test('sanitize_search_string leaves clean alphanumeric string unchanged', function () {
	expect(sanitize_search_string('normal search term'))->toBe('normal search term');
});

// --- is_valid_pathname ---

test('is_valid_pathname accepts a simple absolute path', function () {
	expect(is_valid_pathname('/var/www/html'))->toBeTrue();
});

test('is_valid_pathname accepts a path with dots', function () {
	expect(is_valid_pathname('/etc/cacti/config.php'))->toBeTrue();
});

test('is_valid_pathname accepts a path with hyphens and underscores', function () {
	expect(is_valid_pathname('/opt/my-app/some_dir'))->toBeTrue();
});

test('is_valid_pathname rejects paths containing spaces', function () {
	expect(is_valid_pathname('/var/www/my path'))->toBeFalse();
});

test('is_valid_pathname rejects paths containing semicolons', function () {
	expect(is_valid_pathname('/etc/cacti;evil'))->toBeFalse();
});

test('is_valid_pathname rejects empty string', function () {
	expect(is_valid_pathname(''))->toBeFalse();
});

// --- is_base64_encoded ---

test('is_base64_encoded returns true for valid base64', function () {
	expect(is_base64_encoded(base64_encode('hello world')))->toBeTrue();
});

test('is_base64_encoded returns true for standard padded base64', function () {
	// base64_encode('Cacti') = 'Q2FjdGk='
	expect(is_base64_encoded('Q2FjdGk='))->toBeTrue();
});

test('is_base64_encoded returns false for string with invalid chars', function () {
	expect(is_base64_encoded('this is not base64!!!'))->toBeFalse();
});

test('is_base64_encoded returns false for string that fails re-encode check', function () {
	// 'abc' passes the character regex but fails the re-encode comparison
	// because it has no padding and does not round-trip cleanly
	expect(is_base64_encoded('abc'))->toBeFalse();
});

test('is_base64_encoded returns true for empty string', function () {
	// base64_encode('') = '', which round-trips correctly
	expect(is_base64_encoded(''))->toBeTrue();
});

// --- generate_hash ---

test('generate_hash returns a 32-character string', function () {
	expect(strlen(generate_hash()))->toBe(32);
});

test('generate_hash returns only hexadecimal characters', function () {
	expect(generate_hash())->toMatch('/^[0-9a-f]+$/');
});

test('generate_hash produces different values on successive calls', function () {
	expect(generate_hash())->not->toBe(generate_hash());
});

<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Tests for the xml_path boundary check in lib/data_query.php
 * get_data_query_array().
 *
 * A tampered snmp_query.xml_path row could previously point anywhere on the
 * filesystem (e.g. /etc/passwd, ../../sensitive) because only file_exists()
 * was checked. The fix resolves the path with realpath() and verifies it falls
 * within CACTI_PATH_BASE before reading the file.
 */

function getDataQueryArraySource(): string {
	$src = file_get_contents(__DIR__ . '/../../lib/data_query.php');
	expect($src)->not->toBeFalse('Failed to read lib/data_query.php');
	$start = strpos($src, 'function get_data_query_array(');
	expect($start)->not->toBeFalse('get_data_query_array() must exist in lib/data_query.php');
	return substr($src, $start, 4000);
}

// --- realpath boundary check present ---

test('get_data_query_array resolves xml_file_path with realpath before accepting it', function () {
	$source = getDataQueryArraySource();
	$pattern = '/realpath\s*\(\s*\$xml_file_path\s*\)/';
	expect(preg_match($pattern, $source))->toBe(1,
		'get_data_query_array must resolve $xml_file_path with realpath()'
	);
});

test('get_data_query_array resolves CACTI_PATH_BASE with realpath', function () {
	$source = getDataQueryArraySource();
	$pattern = '/realpath\s*\(\s*CACTI_PATH_BASE\s*\)/';
	expect(preg_match($pattern, $source))->toBe(1,
		'get_data_query_array must resolve CACTI_PATH_BASE with realpath() for a reliable prefix check'
	);
});

test('get_data_query_array uses str_starts_with to enforce the base directory boundary', function () {
	$source = getDataQueryArraySource();
	$pattern = '/str_starts_with\s*\(\s*\$real_xml_path/';
	expect(preg_match($pattern, $source))->toBe(1,
		'get_data_query_array must use str_starts_with($real_xml_path, ...) to enforce the boundary'
	);
});

test('get_data_query_array rejects when realpath returns false', function () {
	$source = getDataQueryArraySource();
	$pattern = '/\$real_xml_path\s*===\s*false/';
	expect(preg_match($pattern, $source))->toBe(1,
		'get_data_query_array must return [] when realpath() returns false'
	);
});

// --- reads through the resolved path, not the raw DB value ---

test('get_data_query_array reads file via $real_xml_path not raw $xml_file_path', function () {
	$source = getDataQueryArraySource();
	/* file() must be called with the resolved path */
	$good = '/file\s*\(\s*\$real_xml_path\s*\)/';
	expect(preg_match($good, $source))->toBe(1,
		'get_data_query_array must pass $real_xml_path to file(), not the raw DB string'
	);
});

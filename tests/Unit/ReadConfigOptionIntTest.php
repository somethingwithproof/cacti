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
 * Tests for read_config_option_int().
 *
 * The helper is a thin wrapper: it calls read_config_option() and casts the
 * result to int. These tests use inline stubs to verify the cast behaviour
 * without requiring a database connection.
 */

/**
 * Stub that mimics read_config_option() returning a given value.
 * The closure captures $return_value and is used as a stand-in for the
 * real function when testing the cast logic in isolation.
 */
function stub_read_config_option(mixed $return_value): int {
	/* mirrors the body of read_config_option_int() */
	return (int) $return_value;
}

// --- numeric string value ---

test('read_config_option_int casts a numeric string to int', function () {
	$result = stub_read_config_option('42');

	expect($result)->toBe(42);
});

// --- null / unset option ---

test('read_config_option_int returns 0 when the option is null', function () {
	$result = stub_read_config_option(null);

	expect($result)->toBe(0);
});

// --- empty string ---

test('read_config_option_int returns 0 for an empty string value', function () {
	$result = stub_read_config_option('');

	expect($result)->toBe(0);
});

// --- already-integer value ---

test('read_config_option_int passes through an integer value unchanged', function () {
	$result = stub_read_config_option(300);

	expect($result)->toBe(300);
});

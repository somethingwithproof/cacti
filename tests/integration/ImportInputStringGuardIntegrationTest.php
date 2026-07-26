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

require_once dirname(__DIR__) . '/Helpers/CactiLibBootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/import.php';

// xml_to_data_input_method() runs the same shell-metacharacter guard the GUI
// enforces, so a hostile template or package cannot persist a command-injection
// payload in a data input method's input_string.

beforeEach(function () {
	// Default posture: unsafe metacharacters are not allowed.
	$GLOBALS['config'][OPTIONS_CLI]['allow_unsafe_metachars'] = '';

	// Field descriptors drive the import loop; only the keys matter here.
	$GLOBALS['fields_data_input_edit']        = ['name' => [], 'input_string' => []];
	$GLOBALS['fields_data_input_field_edit']   = [];
	$GLOBALS['fields_data_input_field_edit_1'] = [];
	$GLOBALS['import_debug_info']              = [];
	$GLOBALS['preview_only']                   = false;
});

it('refuses to import a data input method with an injecting input_string', function () {
	$xml = [
		'name'         => 'Evil Method',
		'input_string' => 'ss_check.php; rm -rf /',
	];
	$cache = [];

	$result = xml_to_data_input_method('0123456789abcdef0123456789abcdef', $xml, $cache);

	expect($result)->toBeFalse()
		->and($GLOBALS['import_debug_info']['type'])->toBe('fail')
		->and($GLOBALS['import_debug_info']['unsafe'])->toBeTrue();
});

it('refuses an input_string whose only field is unsafe', function () {
	$xml = [
		'name'         => 'Evil Method',
		'input_string' => 'ss_check.php `id`',
	];
	$cache = [];

	$result = xml_to_data_input_method('fedcba9876543210fedcba9876543210', $xml, $cache);

	expect($result)->toBeFalse()
		->and($GLOBALS['import_debug_info']['unsafe'])->toBeTrue();
});

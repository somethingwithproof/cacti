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

namespace CliParseArgumentFixture;

$root = dirname(__DIR__, 4);
require_once $root . '/tests/Helpers/SourceSlice.php';

cacti_fixture_source_function($root . '/lib/functions.php', 'cacti_cli_parse_argument', __NAMESPACE__);

test('cacti_cli_parse_argument splits name and value on the first equals sign only', function () {
	expect(cacti_cli_parse_argument('--type=boost'))->toBe(array('--type', 'boost'))
		->and(cacti_cli_parse_argument('--filter=a=b=c'))->toBe(array('--filter', 'a=b=c'))
		->and(cacti_cli_parse_argument('--empty='))->toBe(array('--empty', ''));
});

test('cacti_cli_parse_argument returns an empty value for a bare flag', function () {
	expect(cacti_cli_parse_argument('--force'))->toBe(array('--force', ''));
});

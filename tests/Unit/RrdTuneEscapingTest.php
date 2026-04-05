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
 * Source-scan tests for rrdtool_function_tune() shell escaping.
 *
 * Verifies that the popen() command in rrdtool_function_tune() escapes
 * the rrdtool binary path and all tune arguments to prevent command
 * injection via crafted data source names or values.
 */

function getRrdTuneFunctionSource(): string {
	$rrdPhp = file_get_contents(__DIR__ . '/../../lib/rrd.php');
	expect($rrdPhp)->not->toBeFalse('Failed to read lib/rrd.php');

	$start = strpos($rrdPhp, 'function rrdtool_function_tune(');
	expect($start)->not->toBeFalse('rrdtool_function_tune() must exist in lib/rrd.php');

	$region = substr($rrdPhp, $start, 3000);

	return $region;
}

test('rrdtool binary path is escaped with cacti_escapeshellcmd', function () {
	$source = getRrdTuneFunctionSource();

	expect(str_contains($source, "cacti_escapeshellcmd(read_config_option('path_rrdtool'))"))->toBeTrue(
		'The rrdtool binary path must be wrapped with cacti_escapeshellcmd()'
	);
});

test('data source path is escaped with cacti_escapeshellarg', function () {
	$source = getRrdTuneFunctionSource();

	expect(str_contains($source, 'cacti_escapeshellarg($data_source_path)'))->toBeTrue(
		'The data source path must be wrapped with cacti_escapeshellarg()'
	);
});

test('all tune arguments use cacti_escapeshellarg', function () {
	$source = getRrdTuneFunctionSource();

	$tuneFlags = ['--heartbeat', '--minimum', '--maximum', '--data-source-type', '--data-source-rename'];

	foreach ($tuneFlags as $flag) {
		$escaped = preg_quote($flag, '/');
		$pattern = '/' . $escaped . "\\s*'\\s*\\.\\s*cacti_escapeshellarg\\(/s";

		expect(preg_match($pattern, $source))->toBe(1,
			"Tune flag {$flag} must have its value wrapped with cacti_escapeshellarg()"
		);
	}
});

test('no raw variable interpolation in popen command', function () {
	$source = getRrdTuneFunctionSource();

	/*
	 * The old vulnerable pattern used string interpolation:
	 *   popen(read_config_option('path_rrdtool') . " tune $data_source_path $rrd_tune", 'r')
	 * Verify no dollar-sign interpolation remains in the popen() call.
	 */
	$pattern = '/popen\([^)]*\$data_source_path[^)]*\)/';

	expect(preg_match($pattern, $source))->toBe(0,
		'popen() must not contain raw $data_source_path interpolation'
	);
});

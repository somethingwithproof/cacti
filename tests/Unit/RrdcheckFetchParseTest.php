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
 * do_rrdcheck() parses `rrdtool fetch` output inline: the first line holds
 * the data source names and each later 'timestamp: value value ...' line is
 * a data row. Lines that are not data rows, including diagnostics such as
 * 'ERROR: bad: thing' that contain a colon, must not be counted as rows.
 * The parse loop is sliced out of lib/rrdcheck.php and run on sample output.
 */

function rrdcheck_fetch_parse_run($output) {
	$src   = file_get_contents(__DIR__ . '/../../lib/rrdcheck.php');
	$start = strpos($src, 'foreach($info_array as $line) {');
	$depth = 0;

	for ($i = strpos($src, '{', $start); $i < strlen($src); $i++) {
		if ($src[$i] == '{') {
			$depth++;
		} elseif ($src[$i] == '}' && --$depth == 0) {
			break;
		}
	}

	$info_array     = explode("\n", $output);
	$first          = true;
	$lines_24       = 0;
	$lines_1        = 0;
	$nan_24         = array();
	$nan_1          = array();
	$dsses          = 0;
	$one_hour_limit = 0;

	/* run in a namespace so the cacti_sizeof() stub cannot collide with the
	 * real one when lib/functions.php is loaded by another test */
	if (!function_exists('RrdcheckFetchParse\\cacti_sizeof')) {
		eval('namespace RrdcheckFetchParse; function cacti_sizeof($a) { return is_array($a) ? count($a) : 0; }');
	}

	eval('namespace RrdcheckFetchParse; ' . substr($src, $start, $i - $start + 1));

	return array('names' => $data_source_names, 'rows' => $lines_24, 'nan' => $nan_24);
}

test('rrdcheck fetch parsing counts only timestamped data rows', function () {
	$result = rrdcheck_fetch_parse_run(
		"                       traffic_in          traffic_out\n\n" .
		"1700000000: 1.0e+00 2.0e+00\n" .
		"1700000300: nan nan\n" .
		"ERROR: bad: thing\n" .
		"Warning: x: y z\n" .
		"a line without a colon\n" .
		"1700000600: 3.0e+00 -nan\n" .
		"OK u:0.03 s:0.12 r:0.33\n"
	);

	expect($result['names'])->toBe(array('traffic_in', 'traffic_out'));
	expect($result['rows'])->toBe(3);
	expect($result['nan'])->toBe(array(0 => 1, 1 => 2));
});

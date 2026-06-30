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
 * get_allowed_graphs() builds a dsstats ORDER BY from $sql_order['measure']
 * and $sql_order['order']. Both originate from unfiltered request vars
 * (graph_view.php registers no filter for measure/graph_order, so
 * get_request_var() returns the raw $_REQUEST value), landing in ORDER BY
 * identifier/direction position when dsstats sorting is active.
 *
 * The fix validates the measure against the real columns of the dsstats
 * partition table via db_column_exists() (falling back to 'average', the
 * metric dropdown default) and clamps the direction to ASC/DESC before the
 * concat. The legitimate measures (average, peak, p25n..p95n, sum) are all
 * columns of data_source_stats_*, so valid sorts keep working.
 */

// --- Source contract on the sink in lib/auth.php ---

test('dsstats ORDER BY validates the measure column before concat', function () {
	$auth = file_get_contents(dirname(__DIR__, 2) . '/lib/auth.php');

	expect($auth)->toContain("if (!db_column_exists(\$table, \$measure)) {")
		->and($auth)->toContain("\$measure = 'average';");
});

test('dsstats ORDER BY clamps the direction to ASC/DESC', function () {
	$auth = file_get_contents(dirname(__DIR__, 2) . '/lib/auth.php');

	expect($auth)->toContain("\$dir = (strtoupper(trim((string) \$sql_order['order'])) === 'DESC') ? 'DESC' : 'ASC';");
});

test('dsstats ORDER BY backticks the validated measure and drops raw interpolation', function () {
	$auth = file_get_contents(dirname(__DIR__, 2) . '/lib/auth.php');

	expect($auth)->toContain("\$sql_order = 'ORDER BY rs.`' . \$measure . '` ' . \$dir;")
		->and($auth)->not->toContain("'ORDER BY rs.' . \$sql_order['measure'] . ' ' . \$sql_order['order']");
});

// --- Runtime mirror of the clamp/allowlist (live fn needs a DB session) ---

function dsstats_orderby_clamp(array $order, array $columns): string {
	$measure = (string) $order['measure'];

	if (!in_array($measure, $columns, true)) {
		$measure = 'average';
	}

	$dir = (strtoupper(trim((string) $order['order'])) === 'DESC') ? 'DESC' : 'ASC';

	return 'ORDER BY rs.`' . $measure . '` ' . $dir;
}

// data_source_stats_daily columns (cacti.sql)
$dsstats_columns = [
	'local_data_id', 'rrd_name', 'cf', 'average', 'peak',
	'p95n', 'p90n', 'p75n', 'p50n', 'p25n', 'sum', 'stddev',
	'lslslope', 'lslint', 'lslcorrel',
];

test('legitimate average/desc sort is preserved', function () use ($dsstats_columns) {
	expect(dsstats_orderby_clamp(['measure' => 'average', 'order' => 'desc'], $dsstats_columns))
		->toBe('ORDER BY rs.`average` DESC');
});

test('legitimate peak/asc sort is preserved', function () use ($dsstats_columns) {
	expect(dsstats_orderby_clamp(['measure' => 'peak', 'order' => 'asc'], $dsstats_columns))
		->toBe('ORDER BY rs.`peak` ASC');
});

test('percentile measures are preserved', function () use ($dsstats_columns) {
	foreach (['p25n', 'p50n', 'p75n', 'p90n', 'p95n', 'sum'] as $m) {
		expect(dsstats_orderby_clamp(['measure' => $m, 'order' => 'asc'], $dsstats_columns))
			->toBe('ORDER BY rs.`' . $m . '` ASC');
	}
});

test('injected measure falls back to average', function () use ($dsstats_columns) {
	expect(dsstats_orderby_clamp(['measure' => '(SELECT 1)', 'order' => 'asc'], $dsstats_columns))
		->toBe('ORDER BY rs.`average` ASC');
});

test('stacked-write payload in measure falls back to average', function () use ($dsstats_columns) {
	$payload = 'average; DROP TABLE user_auth; --';
	expect(dsstats_orderby_clamp(['measure' => $payload, 'order' => 'asc'], $dsstats_columns))
		->toBe('ORDER BY rs.`average` ASC');
});

test('injected direction clamps to ASC', function () use ($dsstats_columns) {
	expect(dsstats_orderby_clamp(['measure' => 'average', 'order' => 'asc, (SELECT SLEEP(5))'], $dsstats_columns))
		->toBe('ORDER BY rs.`average` ASC');
});

test('DESC direction survives mixed case and whitespace', function () use ($dsstats_columns) {
	expect(dsstats_orderby_clamp(['measure' => 'average', 'order' => '  DeSc '], $dsstats_columns))
		->toBe('ORDER BY rs.`average` DESC');
});

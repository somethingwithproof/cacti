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
 * Regression guard for the incomplete fix of GHSA-jrxg-8wh8-943x / CVE-2024-31458.
 *
 * graph_template_input.column_name is stored from user input and later concatenated
 * raw as a SQL identifier (second-order SQL injection, stacked-query capable). The
 * original advisory only guarded lib/html_form_template.php. These source-contract
 * checks pin the remaining sinks on develop so every raw use of column_name as an
 * identifier is gated by db_column_exists('graph_templates_item', ...) first.
 */

$root = dirname(__DIR__, 2);

// --- lib/template.php: push_out_graph_input() gates column_name before use ---

test('lib/template.php gates column_name with db_column_exists before raw concatenation', function () use ($root) {
	$contents = file_get_contents($root . '/lib/template.php');

	$guard = strpos($contents, "db_column_exists('graph_templates_item', \$graph_input['column_name'])");
	$sink  = strpos($contents, "'SELECT local_graph_id,' . \$graph_input['column_name']");

	expect($guard)->not->toBeFalse();
	expect($sink)->not->toBeFalse();
	expect($guard)->toBeLessThan($sink);
});

// --- graphs.php: save loop gates column_name before the UPDATE identifier ---

test('graphs.php gates column_name with db_column_exists before raw UPDATE identifier', function () use ($root) {
	$contents = file_get_contents($root . '/graphs.php');

	$guard = strpos($contents, "db_column_exists('graph_templates_item', \$input['column_name'])");
	$sink  = strpos($contents, "SET ' . \$input['column_name'] . ' = ?");

	expect($guard)->not->toBeFalse();
	expect($sink)->not->toBeFalse();
	expect($guard)->toBeLessThan($sink);
});

// --- graph_templates.php: save_component_input validates column_name before sql_save ---

test('graph_templates.php validates column_name with db_column_exists before save', function () use ($root) {
	$contents = file_get_contents($root . '/graph_templates.php');

	$guard = strpos($contents, "db_column_exists('graph_templates_item', \$save['column_name'])");
	$save  = strpos($contents, "sql_save(\$save, 'graph_template_input')");

	expect($guard)->not->toBeFalse();
	expect($save)->not->toBeFalse();
	expect($guard)->toBeLessThan($save);
});

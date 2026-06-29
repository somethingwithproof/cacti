<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-jrxg-8wh8-943x (incomplete fix for CVE-2024-31458).
 *
 * graph_template_input.column_name is stored unvalidated and concatenated raw
 * into SQL as an identifier. The 1.2.27 fix only guarded
 * lib/html_form_template.php; these sibling sinks stayed raw. Each must verify
 * the value is a real graph_templates_item column before the raw concatenation.
 */

$templateSource = file_get_contents(__DIR__ . '/../../lib/template.php');
$graphsSource   = file_get_contents(__DIR__ . '/../../graphs.php');
$inputsSource   = file_get_contents(__DIR__ . '/../../graph_templates_inputs.php');

test('GHSA-jrxg: push_out_graph_input gates column_name before the raw SELECT/UPDATE', function () use ($templateSource) {
	$start = strpos($templateSource, 'function push_out_graph_input(');
	expect($start)->not->toBeFalse();

	$end  = strpos($templateSource, "\n}\n", $start);
	$body = substr($templateSource, $start, $end - $start);

	$gate = strpos($body, "db_column_exists('graph_templates_item', \$graph_input['column_name'])");
	$sink = strpos($body, "'SELECT local_graph_id,' . \$graph_input['column_name']");

	expect($gate)->not->toBeFalse();
	expect($sink)->not->toBeFalse();
	expect($gate)->toBeLessThan($sink);
});

test('GHSA-jrxg: graphs.php gates column_name before the raw UPDATE', function () use ($graphsSource) {
	$gate = strpos($graphsSource, "db_column_exists('graph_templates_item', \$input['column_name'])");
	$sink = strpos($graphsSource, "SET ' . \$input['column_name'] . ' = ?");

	expect($gate)->not->toBeFalse();
	expect($sink)->not->toBeFalse();
	expect($gate)->toBeLessThan($sink);
});

test('GHSA-jrxg: graph_templates_inputs.php rejects a non-column column_name on save', function () use ($inputsSource) {
	$save = strpos($inputsSource, "\$save['column_name'] = form_input_validate(");
	$gate = strpos($inputsSource, "db_column_exists('graph_templates_item', \$save['column_name'])");

	expect($save)->not->toBeFalse();
	expect($gate)->not->toBeFalse();
	expect($gate)->toBeGreaterThan($save);
});

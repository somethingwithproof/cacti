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

/**
 * Source-scan tests for the canonical column-allowlist helper relocation and
 * the raw-identifier sinks routed through db_column_exists()/sanitize_sql_column()/db_qstr().
 *
 * api_automation_column_exists() moved verbatim from lib/api_automation.php into
 * lib/functions.php (loaded first), so non-automation callers can reach it while
 * the existing automation callers keep resolving the same name.
 */

$functions   = file_get_contents(__DIR__ . '/../../lib/functions.php');
$automation  = file_get_contents(__DIR__ . '/../../lib/api_automation.php');
$graphs_new  = file_get_contents(__DIR__ . '/../../graphs_new.php');
$api_graph   = file_get_contents(__DIR__ . '/../../lib/api_graph.php');
$template    = file_get_contents(__DIR__ . '/../../lib/template.php');

// Part 1: relocated helper
test('api_automation_column_exists is defined in lib/functions.php', function () use ($functions) {
	expect($functions)->toContain('function api_automation_column_exists(string $column, array $tables) : bool {');
});

test('lib/api_automation.php no longer defines api_automation_column_exists', function () use ($automation) {
	expect($automation)->not->toContain('function api_automation_column_exists');
});

test('the helper is defined exactly once across functions.php and api_automation.php', function () use ($functions, $automation) {
	$count = substr_count($functions, 'function api_automation_column_exists')
		+ substr_count($automation, 'function api_automation_column_exists');

	expect($count)->toBe(1);
});

test('automation callers still reference api_automation_column_exists', function () use ($automation) {
	expect(substr_count($automation, 'api_automation_column_exists('))->toBeGreaterThan(0);
});

test('relocated helper preserves the prefix-stripping body', function () use ($functions) {
	expect($functions)->toContain("str_replace(['h.', 'ht.', 'gt.', 'gl.', 'gtg.']");
});

// Part 2.1: graphs_new.php magic-query literal + alias
test('graphs_new.php db_qstr()s the field_name literal in the MAX(CASE WHEN...) expression', function () use ($graphs_new) {
	expect($graphs_new)->toContain("MAX(CASE WHEN field_name=' . db_qstr(\$field_name) . ' THEN field_value ELSE NULL END)");
});

test('graphs_new.php sanitizes the field_name alias with sanitize_sql_column()', function () use ($graphs_new) {
	expect($graphs_new)->toContain("AS `' . sanitize_sql_column(\$field_name) . '`");
});

test('graphs_new.php no longer interpolates the raw field_name into the alias', function () use ($graphs_new) {
	expect($graphs_new)->not->toContain("ELSE NULL END) AS '\$field_name'");
});

// Part 2.2: api_graph.php graph_templates_graph UPDATE gate
test('api_graph.php gates the graph_templates_graph UPDATE with db_column_exists()', function () use ($api_graph) {
	expect($api_graph)->toContain("db_column_exists('graph_templates_graph', \$suggested_value['field_name'])");
});

// Part 2.3: template.php suggested-value sinks
test('template.php gates the graph_templates_graph UPDATE', function () use ($template) {
	expect($template)->toContain("db_column_exists('graph_templates_graph', \$field_name)");
});

test('template.php gates the graph_templates_item UPDATE', function () use ($template) {
	expect($template)->toContain("db_column_exists('graph_templates_item', \$field_name)");
});

test('template.php gates the data_template_data UPDATE', function () use ($template) {
	expect($template)->toContain("db_column_exists('data_template_data', \$field_name)");
});

test('template.php gates the data_template_rrd UPDATE', function () use ($template) {
	expect($template)->toContain("db_column_exists('data_template_rrd', \$field_name)");
});

test('template.php gates the data_template_data compatibility SELECTs by vkey', function () use ($template) {
	expect($template)->toContain("db_column_exists('data_template_data', \$vkey)");
});

test('template.php gates the data_template_rrd compatibility SELECTs by vkey', function () use ($template) {
	expect($template)->toContain("db_column_exists('data_template_rrd', \$vkey)");
});

test('template.php gates both compatibility-check SELECT branches for each table', function () use ($template) {
	// Two SELECT branches per table: the data_template/data_template_item path and the is_numeric($type) path.
	expect(substr_count($template, "db_column_exists('data_template_data', \$vkey)"))->toBe(2)
		->and(substr_count($template, "db_column_exists('data_template_rrd', \$vkey)"))->toBe(2);
});

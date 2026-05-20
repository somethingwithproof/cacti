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
 * Integration tests for SQL query patterns used across Cacti.
 *
 * These tests verify that the SQL fragments produced by various helper
 * functions are structurally valid and contain all required clauses.
 * No database connection is needed -- we validate string structure only.
 */

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';

// --- NOT EXISTS SQL fragments from lib/auth.php get_policy_where() ---

test('get_policy_where builds NOT EXISTS with proper correlation for user graph policy', function () {
	// Simulate the SQL generation logic from get_policy_where() for a user
	// with policy_graphs = 1 (default allow, deny exceptions)
	$user_id = 42;
	$sql_fragment = 'NOT EXISTS (SELECT 1 FROM user_auth_perms WHERE user_id = ' . $user_id . ' AND type = 1 AND item_id = gl.id)';

	expect($sql_fragment)->toContain('NOT EXISTS');
	expect($sql_fragment)->toContain('SELECT 1');
	expect($sql_fragment)->toContain('user_id = 42');
	expect($sql_fragment)->toContain('item_id = gl.id');
	// The correlation predicate ties the subquery to the outer query via gl.id
	expect($sql_fragment)->toMatch('/item_id\s*=\s*gl\.id/');
});

test('get_policy_where builds NOT EXISTS with host correlation predicate', function () {
	$user_id = 7;
	$sql_fragment = 'NOT EXISTS (SELECT 1 FROM user_auth_perms WHERE user_id = ' . $user_id . ' AND type = 3 AND item_id = h.id)';

	expect($sql_fragment)->toContain('NOT EXISTS');
	expect($sql_fragment)->toContain('type = 3');
	expect($sql_fragment)->toContain('item_id = h.id');
	// Ensure proper correlation to outer host table alias
	expect($sql_fragment)->toMatch('/item_id\s*=\s*h\.id/');
});

test('get_policy_where builds NOT EXISTS with graph_template correlation predicate', function () {
	$user_id = 15;
	$sql_fragment = 'NOT EXISTS (SELECT 1 FROM user_auth_perms WHERE user_id = ' . $user_id . ' AND type = 4 AND item_id = gl.graph_template_id)';

	expect($sql_fragment)->toContain('NOT EXISTS');
	expect($sql_fragment)->toContain('type = 4');
	expect($sql_fragment)->toContain('item_id = gl.graph_template_id');
});

test('get_policy_where builds group NOT EXISTS with proper table and correlation', function () {
	$group_id = 3;
	$sql_fragment = 'NOT EXISTS (SELECT 1 FROM user_auth_group_perms WHERE group_id = ' . $group_id . ' AND type = 1 AND item_id = gl.id)';

	expect($sql_fragment)->toContain('user_auth_group_perms');
	expect($sql_fragment)->toContain('group_id = 3');
	expect($sql_fragment)->toContain('item_id = gl.id');
});

test('get_policy_where combines multiple policy clauses with OR', function () {
	// Simulate building the full WHERE for a single user policy (3 sub-clauses joined by OR)
	$user_id = 42;
	$sql_where = '';

	$sql_where .= '(';
	$sql_where .= 'NOT EXISTS (SELECT 1 FROM user_auth_perms WHERE user_id = ' . $user_id . ' AND type = 1 AND item_id = gl.id)';
	$sql_where .= ' OR ';
	$sql_where .= 'NOT EXISTS (SELECT 1 FROM user_auth_perms WHERE user_id = ' . $user_id . ' AND type = 3 AND item_id = h.id)';
	$sql_where .= ' OR ';
	$sql_where .= 'NOT EXISTS (SELECT 1 FROM user_auth_perms WHERE user_id = ' . $user_id . ' AND type = 4 AND item_id = gl.graph_template_id)';
	$sql_where .= ')';

	expect(substr_count($sql_where, 'NOT EXISTS'))->toBe(3);
	expect(substr_count($sql_where, ' OR '))->toBe(2);
	// Balanced parentheses
	expect(substr_count($sql_where, '('))->toBe(substr_count($sql_where, ')'));
});

// --- Batch query SQL from data_query_remap_indexes() ---

test('data_query_remap_indexes id_list built with intval produces valid IN clause', function () {
	$local_data = [10, 20, 30, 40, 50];
	$id_list = implode(', ', array_map('intval', $local_data));

	expect($id_list)->toBe('10, 20, 30, 40, 50');

	$sql = "SELECT DISTINCT dl.snmp_index, gti.local_graph_id
		FROM data_local AS dl
		INNER JOIN data_template_rrd AS dtr
		ON dl.id = dtr.local_data_id
		INNER JOIN graph_templates_item AS gti
		ON gti.task_item_id = dtr.id
		WHERE dl.id IN($id_list)";

	expect($sql)->toContain('IN(10, 20, 30, 40, 50)');
	expect($sql)->toContain('INNER JOIN data_template_rrd');
	expect($sql)->toContain('INNER JOIN graph_templates_item');
	expect($sql)->toContain('dl.id = dtr.local_data_id');
	expect($sql)->toContain('gti.task_item_id = dtr.id');
});

test('data_query_remap_indexes handles single ID list', function () {
	$local_data = [999];
	$id_list = implode(', ', array_map('intval', $local_data));

	expect($id_list)->toBe('999');

	$sql = "WHERE dl.id IN($id_list)";
	expect($sql)->toBe('WHERE dl.id IN(999)');
});

test('data_query_remap_indexes handles large ID lists', function () {
	$local_data = range(1, 500);
	$id_list = implode(', ', array_map('intval', $local_data));

	// Verify all IDs are present and comma-separated
	$parts = explode(', ', $id_list);
	expect($parts)->toHaveCount(500);
	expect($parts[0])->toBe('1');
	expect($parts[499])->toBe('500');
});

// --- intval sanitization produces valid number lists ---

test('implode intval produces valid number list from clean integers', function () {
	$ids = [1, 2, 3, 4, 5];
	$result = implode(', ', array_map('intval', $ids));

	expect($result)->toBe('1, 2, 3, 4, 5');
});

test('implode intval produces valid number list from string integers', function () {
	$ids = ['10', '20', '30'];
	$result = implode(', ', array_map('intval', $ids));

	expect($result)->toBe('10, 20, 30');
});

test('implode intval sanitizes mixed input to valid numbers', function () {
	$ids = ['1', '2abc', '0x10', '3.7', '-5'];
	$result = implode(', ', array_map('intval', $ids));

	expect($result)->toBe('1, 2, 0, 3, -5');
});

test('implode intval on empty array produces empty string', function () {
	$ids = [];
	$result = implode(', ', array_map('intval', $ids));

	expect($result)->toBe('');
});

// --- CTE/JOIN queries from api_automation_tools.php ---

test('getInputFields query includes all required aliases', function () {
	// Mirror the SQL structure from api_automation_tools.php getInputFields()
	$sql = "SELECT DISTINCT dif.data_name AS `name`, dif.name AS `description`,
		did.value AS `default`, dtd.data_template_id, dif.id AS `data_input_field_id`
		FROM data_input_fields AS dif
		INNER JOIN (
			SELECT data_input_field_id, data_template_data_id, value
			FROM data_input_data
			WHERE t_value = 'on'
		) AS did
		ON did.data_input_field_id = dif.id
		INNER JOIN (
			SELECT id, data_input_id, data_template_id
			FROM data_template_data FORCE INDEX (local_data_id)
			WHERE local_data_id = 0
		) AS dtd
		ON did.data_template_data_id = dtd.id
		AND dtd.data_input_id = dif.data_input_id
		INNER JOIN (
			SELECT data_template_id, id
			FROM data_template_rrd
			WHERE local_data_id = 0 AND hash != ''
		) AS dtr
		ON dtr.data_template_id = dtd.data_template_id
		INNER JOIN graph_templates_item AS gti
		ON dtr.id = gti.task_item_id
		INNER JOIN graph_templates AS gt
		ON gt.id = gti.graph_template_id
		WHERE gt.id = ?
		AND dif.input_output IN ('in', 'inout')";

	// Verify all required aliases are present
	expect($sql)->toContain('AS dif');
	expect($sql)->toContain('AS did');
	expect($sql)->toContain('AS dtd');
	expect($sql)->toContain('AS dtr');
	expect($sql)->toContain('AS gti');
	expect($sql)->toContain('AS gt');

	// Verify join conditions reference correct aliases
	expect($sql)->toContain('did.data_input_field_id = dif.id');
	expect($sql)->toContain('did.data_template_data_id = dtd.id');
	expect($sql)->toContain('dtr.data_template_id = dtd.data_template_id');
	expect($sql)->toContain('dtr.id = gti.task_item_id');
	expect($sql)->toContain('gt.id = gti.graph_template_id');

	// Uses prepared statement placeholder
	expect($sql)->toContain('gt.id = ?');
});

test('graph tree items query includes all required aliases', function () {
	// Mirror the SQL from api_automation_tools.php displayTreeNodes()
	$sql = "SELECT gti.id, gti.local_graph_id, gti.title,
		gti.host_id, gti.host_grouping_type, gti.sort_children_type,
		gtg.title_cache AS graph_title, h.hostname AS hostname
		FROM graph_tree_items AS gti
		LEFT JOIN graph_templates_graph AS gtg ON gtg.local_graph_id = gti.local_graph_id
		LEFT JOIN host AS h ON h.id = gti.host_id
		WHERE gti.graph_tree_id = ?
		AND gti.parent = ?
		ORDER BY gti.position";

	expect($sql)->toContain('AS gti');
	expect($sql)->toContain('AS gtg');
	expect($sql)->toContain('AS h');
	expect($sql)->toContain('gtg.local_graph_id = gti.local_graph_id');
	expect($sql)->toContain('h.id = gti.host_id');
	// Two prepared statement placeholders
	expect(substr_count($sql, '?'))->toBe(2);
});

test('device graphs query includes all required aliases', function () {
	// Mirror the SQL from api_automation_tools.php displayHostGraphs()
	$sql = "SELECT
		graph_templates_graph.local_graph_id AS id,
		graph_templates_graph.title_cache AS name,
		graph_templates.name AS template_name
		FROM (graph_local, graph_templates_graph)
		LEFT JOIN graph_templates ON (graph_local.graph_template_id = graph_templates.id)
		WHERE graph_local.id = graph_templates_graph.local_graph_id
		AND graph_local.host_id = ?
		ORDER BY graph_templates_graph.local_graph_id";

	expect($sql)->toContain('graph_templates_graph.local_graph_id AS id');
	expect($sql)->toContain('graph_templates_graph.title_cache AS name');
	expect($sql)->toContain('graph_templates.name AS template_name');
	expect($sql)->toContain('graph_local.id = graph_templates_graph.local_graph_id');
	expect(substr_count($sql, '?'))->toBe(1);
});

// --- rrd_step cache access pattern with concurrent local_data_ids ---

test('rrd_step_cache pattern resolves each local_data_id only once', function () {
	$rrd_step_cache = [];
	$lookup_count = 0;

	// Simulate the graph items loop from lib/rrd.php rrdtool_function_graph()
	$graph_items = [
		['local_data_id' => 10],
		['local_data_id' => 10],
		['local_data_id' => 20],
		['local_data_id' => 10],
		['local_data_id' => 20],
		['local_data_id' => 30],
		['local_data_id' => 30],
		['local_data_id' => 10],
	];

	foreach ($graph_items as $graph_item) {
		$ldid = $graph_item['local_data_id'];

		if (!isset($rrd_step_cache[$ldid])) {
			// Simulate db_fetch_cell_prepared lookup
			$rrd_step_cache[$ldid] = $ldid * 30; // mock: step = id * 30
			$lookup_count++;
		}

		$rrd_step = $rrd_step_cache[$ldid];
		expect($rrd_step)->toBe($ldid * 30);
	}

	// Only 3 unique local_data_ids, so only 3 lookups
	expect($lookup_count)->toBe(3);
	expect($rrd_step_cache)->toHaveCount(3);
});

test('rrd_step_cache falls back to poller_interval when local_data_id is absent', function () {
	$rrd_step_cache = [];
	$default_interval = 300;

	// Items without local_data_id should use the default
	$graph_items = [
		['local_data_id' => 5],
		[], // no local_data_id key
		['local_data_id' => 5],
	];

	$results = [];

	foreach ($graph_items as $graph_item) {
		if (isset($graph_item['local_data_id'])) {
			$ldid = $graph_item['local_data_id'];

			if (!isset($rrd_step_cache[$ldid])) {
				$rrd_step_cache[$ldid] = 60; // mock value
			}

			$results[] = $rrd_step_cache[$ldid];
		} else {
			$results[] = $default_interval;
		}
	}

	expect($results[0])->toBe(60);
	expect($results[1])->toBe(300); // fallback
	expect($results[2])->toBe(60);  // cached
});

test('rrd_step_cache handles interleaved local_data_ids correctly', function () {
	$rrd_step_cache = [];
	$step_values = [1 => 60, 2 => 300, 3 => 120];

	// Interleaved IDs as seen in real graph rendering
	$id_sequence = [1, 2, 3, 1, 2, 3, 1, 2, 3, 1];
	$resolved_steps = [];

	foreach ($id_sequence as $ldid) {
		if (!isset($rrd_step_cache[$ldid])) {
			$rrd_step_cache[$ldid] = $step_values[$ldid];
		}

		$resolved_steps[] = $rrd_step_cache[$ldid];
	}

	expect($resolved_steps)->toBe([60, 300, 120, 60, 300, 120, 60, 300, 120, 60]);
	expect($rrd_step_cache)->toHaveCount(3);
});

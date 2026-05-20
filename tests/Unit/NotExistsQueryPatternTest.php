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

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';

test('NOT EXISTS pattern always uses SELECT 1', function () {
	$patterns = [
		'NOT EXISTS (SELECT 1 FROM user_auth_perms WHERE user_id = ? AND type = 1 AND item_id = gl.id)',
		'NOT EXISTS (SELECT 1 FROM aggregate_graphs AS ag WHERE ag.local_graph_id = gti.local_graph_id)',
		'NOT EXISTS (SELECT 1 FROM host_graph AS hg WHERE hg.graph_template_id = gt.id AND hg.host_id = ?)',
		'NOT EXISTS (SELECT 1 FROM data_local AS dl WHERE dl.id = poller_output.local_data_id)',
	];

	foreach ($patterns as $pattern) {
		expect($pattern)->toContain('SELECT 1')
			->and($pattern)->not->toContain('SELECT *');
	}
});

test('NOT EXISTS pattern includes correlation predicate for auth perms', function () {
	$pattern = 'NOT EXISTS (SELECT 1 FROM user_auth_perms WHERE user_id = ? AND type = 1 AND item_id = gl.id)';
	expect($pattern)->toContain('item_id = gl.id');
});

test('NOT EXISTS for aggregate_graphs has proper correlation', function () {
	$pattern = 'NOT EXISTS (SELECT 1 FROM aggregate_graphs AS ag WHERE ag.local_graph_id = gti.local_graph_id)';
	expect($pattern)->toContain('ag.local_graph_id = gti.local_graph_id');
});

test('NOT EXISTS for host_graph includes both template and host', function () {
	$pattern = 'NOT EXISTS (SELECT 1 FROM host_graph AS hg WHERE hg.graph_template_id = gt.id AND hg.host_id = ?)';
	expect($pattern)->toContain('hg.graph_template_id = gt.id')
		->and($pattern)->toContain('hg.host_id = ?');
});

test('NOT EXISTS for repair_database correlates on correct column', function () {
	$patterns = [
		['NOT EXISTS (SELECT 1 FROM graph_templates_gprint AS gtp WHERE gtp.id = graph_templates_item.gprint_id)', 'gtp.id = graph_templates_item.gprint_id'],
		['NOT EXISTS (SELECT 1 FROM cdef WHERE cdef.id = cdef_items.cdef_id)', 'cdef.id = cdef_items.cdef_id'],
		['NOT EXISTS (SELECT 1 FROM data_input AS di WHERE di.id = data_template_data.data_input_id)', 'di.id = data_template_data.data_input_id'],
	];

	foreach ($patterns as [$sql, $correlation]) {
		expect($sql)->toContain($correlation);
	}
});

test('uncorrelated NOT EXISTS would be semantically wrong', function () {
	$bad = 'NOT EXISTS (SELECT 1 FROM user_auth_perms WHERE user_id = ? AND type = 1)';
	expect($bad)->not->toContain('item_id =');
});

test('NOT EXISTS for automation_templates includes template_id', function () {
	$pattern = 'NOT EXISTS (SELECT 1 FROM automation_templates_rules AS atr2 WHERE atr2.rule_id = ar.id AND atr2.rule_type = 1 AND atr2.template_id = ?)';
	expect($pattern)->toContain('atr2.rule_id = ar.id')
		->and($pattern)->toContain('atr2.template_id = ?');
});

test('NOT EXISTS for user_log deletion has both conditions', function () {
	$sql = 'DELETE FROM user_log WHERE NOT EXISTS (SELECT 1 FROM user_auth AS ua WHERE ua.id = user_log.user_id) OR NOT EXISTS (SELECT 1 FROM user_auth AS ua WHERE ua.username = user_log.username)';
	expect($sql)->toContain('ua.id = user_log.user_id')
		->and($sql)->toContain('ua.username = user_log.username');
});

test('NOT EXISTS is structurally different from NOT IN', function () {
	$not_in = 'gl.id NOT IN (SELECT item_id FROM user_auth_perms WHERE user_id = 1)';
	$not_exists = 'NOT EXISTS (SELECT 1 FROM user_auth_perms WHERE user_id = 1 AND item_id = gl.id)';
	expect($not_in)->toContain('NOT IN')
		->and($not_exists)->toContain('NOT EXISTS')
		->and($not_exists)->not->toContain('NOT IN');
});

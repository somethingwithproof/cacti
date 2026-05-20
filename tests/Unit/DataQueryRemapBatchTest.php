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

test('grouping produces correct snmp_index to graph_id mapping', function () {
	$rows = [
		['snmp_index' => 'eth0', 'local_graph_id' => '1'],
		['snmp_index' => 'eth0', 'local_graph_id' => '2'],
		['snmp_index' => 'eth1', 'local_graph_id' => '3'],
	];
	$index_to_graphs = [];
	foreach ($rows as $row) {
		$index_to_graphs[$row['snmp_index']][$row['local_graph_id']] = $row['local_graph_id'];
	}
	expect($index_to_graphs)->toHaveCount(2)
		->and($index_to_graphs['eth0'])->toBe(['1' => '1', '2' => '2'])
		->and($index_to_graphs['eth1'])->toBe(['3' => '3']);
});

test('grouping with empty rows returns empty array', function () {
	$rows = [];
	$index_to_graphs = [];
	foreach ($rows as $row) {
		$index_to_graphs[$row['snmp_index']][$row['local_graph_id']] = $row['local_graph_id'];
	}
	expect($index_to_graphs)->toBe([]);
});

test('grouping with single row', function () {
	$rows = [['snmp_index' => 'GigabitEthernet0/1', 'local_graph_id' => '42']];
	$index_to_graphs = [];
	foreach ($rows as $row) {
		$index_to_graphs[$row['snmp_index']][$row['local_graph_id']] = $row['local_graph_id'];
	}
	expect($index_to_graphs)->toHaveCount(1)
		->and($index_to_graphs['GigabitEthernet0/1'])->toBe(['42' => '42']);
});

test('grouping with all same snmp_index', function () {
	$rows = [
		['snmp_index' => 'lo', 'local_graph_id' => '10'],
		['snmp_index' => 'lo', 'local_graph_id' => '20'],
		['snmp_index' => 'lo', 'local_graph_id' => '30'],
	];
	$index_to_graphs = [];
	foreach ($rows as $row) {
		$index_to_graphs[$row['snmp_index']][$row['local_graph_id']] = $row['local_graph_id'];
	}
	expect($index_to_graphs)->toHaveCount(1)
		->and($index_to_graphs['lo'])->toHaveCount(3);
});

test('grouping with all different snmp_indexes', function () {
	$rows = [
		['snmp_index' => 'eth0', 'local_graph_id' => '1'],
		['snmp_index' => 'eth1', 'local_graph_id' => '2'],
		['snmp_index' => 'eth2', 'local_graph_id' => '3'],
	];
	$index_to_graphs = [];
	foreach ($rows as $row) {
		$index_to_graphs[$row['snmp_index']][$row['local_graph_id']] = $row['local_graph_id'];
	}
	expect($index_to_graphs)->toHaveCount(3);
	foreach ($index_to_graphs as $graphs) {
		expect($graphs)->toHaveCount(1);
	}
});

test('duplicate graph IDs within same index are deduplicated', function () {
	$rows = [
		['snmp_index' => 'eth0', 'local_graph_id' => '1'],
		['snmp_index' => 'eth0', 'local_graph_id' => '1'],
		['snmp_index' => 'eth0', 'local_graph_id' => '2'],
	];
	$index_to_graphs = [];
	foreach ($rows as $row) {
		$index_to_graphs[$row['snmp_index']][$row['local_graph_id']] = $row['local_graph_id'];
	}
	expect($index_to_graphs['eth0'])->toHaveCount(2);
});

test('intval sanitization of grouped IDs produces safe SQL', function () {
	$groups = ['eth0' => ['1' => '1', '2' => '2'], 'eth1' => ['3' => '3']];
	foreach ($groups as $idx => $ids) {
		$fragment = implode(', ', array_map('intval', $ids));
		expect($fragment)->toMatch('/^[\d, ]+$/');
	}
});

test('id_list construction sanitizes input', function () {
	$local_data = [1, 2, '3', 'abc', '5 OR 1=1'];
	$id_list = implode(', ', array_map('intval', $local_data));
	expect($id_list)->toBe('1, 2, 3, 0, 5');
});

test('numeric string snmp_indexes group correctly', function () {
	$rows = [
		['snmp_index' => '1', 'local_graph_id' => '10'],
		['snmp_index' => '1', 'local_graph_id' => '11'],
		['snmp_index' => '2', 'local_graph_id' => '12'],
	];
	$index_to_graphs = [];
	foreach ($rows as $row) {
		$index_to_graphs[$row['snmp_index']][$row['local_graph_id']] = $row['local_graph_id'];
	}
	expect($index_to_graphs)->toHaveCount(2)
		->and($index_to_graphs['1'])->toHaveCount(2);
});

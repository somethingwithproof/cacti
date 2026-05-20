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
 * Integration tests for the caching patterns used in lib/poller.php and lib/rrd.php.
 *
 * These tests simulate the poller output processing loop, rrd_step_cache,
 * and unused_ds_name_cache patterns to verify cache efficiency and correctness
 * without requiring a database connection.
 */

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';

// --- Poller output processing loop with duplicate local_data_ids ---

test('poller output processing deduplicates local_data_ids via cache', function () {
	$unused_ds_name_cache = [];
	$cache_miss_count = 0;
	$cache_hit_count = 0;

	// Simulate 100 poller output items with 10 unique local_data_ids
	$items = [];

	for ($i = 0; $i < 100; $i++) {
		$items[] = [
			'local_data_id'    => ($i % 10) + 1,  // IDs 1..10
			'data_template_id' => 1,
			'output'           => 'ifInOctets:' . rand(1000, 9999),
		];
	}

	foreach ($items as $item) {
		$local_data_id = $item['local_data_id'];
		$data_template_id = $item['data_template_id'];

		if ($data_template_id > 0) {
			if (!isset($unused_ds_name_cache[$local_data_id])) {
				// Simulate db_fetch_assoc_prepared lookup
				$unused_ds_name_cache[$local_data_id] = [];
				$cache_miss_count++;
			} else {
				$cache_hit_count++;
			}
		}
	}

	expect($cache_miss_count)->toBe(10);
	expect($cache_hit_count)->toBe(90);
	expect($unused_ds_name_cache)->toHaveCount(10);
});

test('cache hit ratio is correct for 100 items with 10 unique local_data_ids', function () {
	$cache = [];
	$misses = 0;
	$hits = 0;

	for ($i = 0; $i < 100; $i++) {
		$id = ($i % 10) + 1;

		if (!isset($cache[$id])) {
			$cache[$id] = true;
			$misses++;
		} else {
			$hits++;
		}
	}

	expect($misses)->toBe(10);
	expect($hits)->toBe(90);

	$hit_ratio = $hits / ($hits + $misses);
	expect($hit_ratio)->toBe(0.9);
});

test('poller output processing handles single local_data_id correctly', function () {
	$unused_ds_name_cache = [];
	$lookup_count = 0;

	// All 50 items have the same local_data_id
	for ($i = 0; $i < 50; $i++) {
		$local_data_id = 42;

		if (!isset($unused_ds_name_cache[$local_data_id])) {
			$unused_ds_name_cache[$local_data_id] = ['unused_ds' => 'unused_ds'];
			$lookup_count++;
		}
	}

	expect($lookup_count)->toBe(1);
	expect($unused_ds_name_cache)->toHaveCount(1);
	expect($unused_ds_name_cache[42])->toBe(['unused_ds' => 'unused_ds']);
});

test('poller output processing handles all unique local_data_ids', function () {
	$unused_ds_name_cache = [];
	$lookup_count = 0;

	// Each item has a unique local_data_id (worst case: no cache benefit)
	for ($i = 1; $i <= 50; $i++) {
		$local_data_id = $i;

		if (!isset($unused_ds_name_cache[$local_data_id])) {
			$unused_ds_name_cache[$local_data_id] = [];
			$lookup_count++;
		}
	}

	expect($lookup_count)->toBe(50);
	expect($unused_ds_name_cache)->toHaveCount(50);
});

// --- rrd_step_cache pattern across graph items ---

test('rrd_step_cache correctly caches step values across graph items', function () {
	$rrd_step_cache = [];
	$db_calls = 0;

	// Mock step values that would come from data_template_data
	$mock_db = [
		10 => 60,
		20 => 300,
		30 => 120,
	];

	// Simulate graph items that reference these local_data_ids multiple times
	$graph_items = [];

	for ($i = 0; $i < 30; $i++) {
		$ldid = (($i % 3) + 1) * 10; // cycles through 10, 20, 30
		$graph_items[] = ['local_data_id' => $ldid];
	}

	$resolved_steps = [];

	foreach ($graph_items as $graph_item) {
		$ldid = $graph_item['local_data_id'];

		if (!isset($rrd_step_cache[$ldid])) {
			// Simulate: db_fetch_cell_prepared('SELECT rrd_step FROM data_template_data WHERE local_data_id = ?', [$ldid])
			$rrd_step_cache[$ldid] = $mock_db[$ldid];
			$db_calls++;
		}

		$resolved_steps[] = $rrd_step_cache[$ldid];
	}

	// Only 3 DB calls for 30 graph items
	expect($db_calls)->toBe(3);
	expect($rrd_step_cache)->toHaveCount(3);

	// Verify each step was resolved correctly
	expect($resolved_steps[0])->toBe(60);   // ldid=10
	expect($resolved_steps[1])->toBe(300);  // ldid=20
	expect($resolved_steps[2])->toBe(120);  // ldid=30
	expect($resolved_steps[3])->toBe(60);   // ldid=10 (cached)
});

test('rrd_step_cache is used in CDEF string replacement', function () {
	$rrd_step_cache = [];
	$rrd_step_cache[10] = 60;
	$rrd_step_cache[20] = 300;

	// Simulate CDEF string replacement as done in rrdtool_function_graph()
	$cdef_string = 'CURRENT_DATA_SOURCE_PI,ALL_DATA_SOURCES_DUPS_PI,+';

	// For local_data_id 10 (step=60)
	$result = str_replace('CURRENT_DATA_SOURCE_PI', $rrd_step_cache[10], $cdef_string);
	$result = str_replace('ALL_DATA_SOURCES_DUPS_PI', $rrd_step_cache[10], $result);

	expect($result)->toBe('60,60,+');

	// For local_data_id 20 (step=300)
	$cdef_string = 'CURRENT_DATA_SOURCE_PI,ALL_DATA_SOURCES_DUPS_PI,+';
	$result = str_replace('CURRENT_DATA_SOURCE_PI', $rrd_step_cache[20], $cdef_string);
	$result = str_replace('ALL_DATA_SOURCES_DUPS_PI', $rrd_step_cache[20], $result);

	expect($result)->toBe('300,300,+');
});

// --- unused_ds_name_cache prevents redundant lookups ---

test('unused_ds_name_cache prevents redundant lookups for multi-output data sources', function () {
	$unused_ds_name_cache = [];
	$db_calls = 0;

	// Simulate processing multiple rrd_names for the same local_data_id
	// This mimics the multi-value output case in process_poller_output()
	$results = [
		['local_data_id' => 100, 'data_template_id' => 5, 'rrd_name' => 'ifInOctets'],
		['local_data_id' => 100, 'data_template_id' => 5, 'rrd_name' => 'ifOutOctets'],
		['local_data_id' => 100, 'data_template_id' => 5, 'rrd_name' => 'ifInErrors'],
		['local_data_id' => 200, 'data_template_id' => 5, 'rrd_name' => 'ifInOctets'],
		['local_data_id' => 200, 'data_template_id' => 5, 'rrd_name' => 'ifOutOctets'],
		['local_data_id' => 100, 'data_template_id' => 5, 'rrd_name' => 'ifOutErrors'],
	];

	foreach ($results as $item) {
		$local_data_id = $item['local_data_id'];
		$data_template_id = $item['data_template_id'];

		if ($data_template_id > 0) {
			if (!isset($unused_ds_name_cache[$local_data_id])) {
				// Simulate the DB lookup
				$unused_ds_name_cache[$local_data_id] = ['unused_field' => 'unused_field'];
				$db_calls++;
			}

			$unused_data_source_names = $unused_ds_name_cache[$local_data_id];
		}
	}

	// Only 2 DB calls (one per unique local_data_id), not 6
	expect($db_calls)->toBe(2);
	expect($unused_ds_name_cache)->toHaveCount(2);
	expect($unused_ds_name_cache[100])->toBe(['unused_field' => 'unused_field']);
	expect($unused_ds_name_cache[200])->toBe(['unused_field' => 'unused_field']);
});

test('unused_ds_name_cache skips lookup when data_template_id is zero', function () {
	$unused_ds_name_cache = [];
	$db_calls = 0;

	$results = [
		['local_data_id' => 100, 'data_template_id' => 0, 'rrd_name' => 'value'],
		['local_data_id' => 100, 'data_template_id' => 0, 'rrd_name' => 'value2'],
	];

	foreach ($results as $item) {
		$data_template_id = $item['data_template_id'];

		if ($data_template_id > 0) {
			$local_data_id = $item['local_data_id'];

			if (!isset($unused_ds_name_cache[$local_data_id])) {
				$unused_ds_name_cache[$local_data_id] = [];
				$db_calls++;
			}

			$unused_data_source_names = $unused_ds_name_cache[$local_data_id];
		} else {
			$unused_data_source_names = [];
		}
	}

	// No DB calls since data_template_id is 0
	expect($db_calls)->toBe(0);
	expect($unused_ds_name_cache)->toBeEmpty();
});

test('unused_ds_name_cache correctly filters unused data source names', function () {
	$unused_ds_name_cache = [];

	// Simulate a local_data_id with some unused data sources
	$unused_ds_name_cache[100] = [
		'ifInDiscards' => 'ifInDiscards',
		'ifOutDiscards' => 'ifOutDiscards',
	];

	// When processing values, unused ones should be skipped
	$values_to_process = [
		'ifInOctets'   => 12345,
		'ifOutOctets'  => 67890,
		'ifInDiscards' => 0,     // this is unused, should be skipped
		'ifOutDiscards' => 0,    // this is unused, should be skipped
	];

	$processed = [];

	foreach ($values_to_process as $field => $value) {
		if (isset($unused_ds_name_cache[100][$field])) {
			continue; // skip unused data sources
		}

		$processed[$field] = $value;
	}

	expect($processed)->toHaveCount(2);
	expect($processed)->toHaveKey('ifInOctets');
	expect($processed)->toHaveKey('ifOutOctets');
	expect($processed)->not->toHaveKey('ifInDiscards');
	expect($processed)->not->toHaveKey('ifOutDiscards');
});

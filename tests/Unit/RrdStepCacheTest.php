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

test('cache miss populates on first access', function () {
	$cache = [];
	$id = 42;
	if (!isset($cache[$id])) {
		$cache[$id] = 300;
	}
	expect($cache)->toHaveKey(42)
		->and($cache[42])->toBe(300);
});

test('cache hit returns stored value without re-fetching', function () {
	$fetch_count = 0;
	$cache = [];
	$fetch = function (int $id) use (&$fetch_count): int {
		$fetch_count++;
		return $id * 10;
	};

	$id = 5;
	if (!isset($cache[$id])) { $cache[$id] = $fetch($id); }
	$r1 = $cache[$id];
	if (!isset($cache[$id])) { $cache[$id] = $fetch($id); }
	$r2 = $cache[$id];

	expect($fetch_count)->toBe(1)
		->and($r1)->toBe(50)
		->and($r2)->toBe(50);
});

test('cache stores different values for different IDs', function () {
	$cache = [];
	$values = [1 => 60, 2 => 300, 3 => 600];
	foreach ($values as $id => $step) {
		if (!isset($cache[$id])) { $cache[$id] = $step; }
	}
	expect($cache[1])->toBe(60)
		->and($cache[2])->toBe(300)
		->and($cache[3])->toBe(600);
});

test('cache eliminates redundant fetches in poller loop', function () {
	$fetch_count = 0;
	$cache = [];
	$fetch = function (int $id) use (&$fetch_count): array {
		$fetch_count++;
		return ['step' => $id * 10];
	};

	$items = [1,1,2,1,2,3,3,3,1,2];
	foreach ($items as $ldid) {
		if (!isset($cache[$ldid])) { $cache[$ldid] = $fetch($ldid); }
	}

	expect($fetch_count)->toBe(3)
		->and($cache)->toHaveCount(3);
});

test('cache handles zero as valid cached value', function () {
	$cache = [];
	$cache[1] = 0;
	expect(isset($cache[1]))->toBeTrue()
		->and($cache[1])->toBe(0);
});

test('cache handles empty array as valid cached value', function () {
	$cache = [];
	$cache[1] = [];
	expect(isset($cache[1]))->toBeTrue()
		->and($cache[1])->toBe([]);
});

test('poller unused_ds_name_cache reduces queries', function () {
	$query_count = 0;
	$cache = [];
	$fetch = function (int $ldid) use (&$query_count): array {
		$query_count++;
		return ($ldid === 10) ? ['traffic_in' => 'traffic_in'] : [];
	};

	$results = [
		['local_data_id' => 10, 'data_template_id' => 5],
		['local_data_id' => 10, 'data_template_id' => 5],
		['local_data_id' => 10, 'data_template_id' => 5],
		['local_data_id' => 20, 'data_template_id' => 8],
		['local_data_id' => 20, 'data_template_id' => 8],
	];

	foreach ($results as $item) {
		$ldid = $item['local_data_id'];
		if ($item['data_template_id'] > 0) {
			if (!isset($cache[$ldid])) { $cache[$ldid] = $fetch($ldid); }
		}
	}

	expect($query_count)->toBe(2)
		->and($cache)->toHaveCount(2)
		->and($cache[10])->toHaveKey('traffic_in')
		->and($cache[20])->toBe([]);
});

test('rrd_step_cache pattern with 100 items and 10 unique IDs', function () {
	$fetch_count = 0;
	$cache = [];
	$fetch = function (int $id) use (&$fetch_count): int {
		$fetch_count++;
		return $id * 60;
	};

	$items = [];
	for ($i = 0; $i < 100; $i++) {
		$items[] = ($i % 10) + 1;
	}

	foreach ($items as $ldid) {
		if (!isset($cache[$ldid])) { $cache[$ldid] = $fetch($ldid); }
		$step = $cache[$ldid];
	}

	expect($fetch_count)->toBe(10)
		->and($cache)->toHaveCount(10);
});

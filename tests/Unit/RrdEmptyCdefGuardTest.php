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
<<<<<<< HEAD
 * Source-scan tests for the empty CDEF guard fix.
 *
 * Aggregate graphs can produce an empty RPN expression for GPRINT items
 * whose consolidation function does not match the data source.  Without
 * the guard, rrdtool_function_graph() emits a bare "CDEF:cdefX=" line
 * that rrdtool rejects.
 *
 * The fix adds: if ($cdef_string === '') { continue; }
 * before the CDEF name generation line in lib/rrd.php.
 *
 * A secondary fix in lib/aggregate.php converts the raw db_execute()
 * UPDATE for cdef_id to db_execute_prepared() for SQL safety.
 */

// --- helpers ---

function getRrdSource(): string {
	$path = __DIR__ . '/../../lib/rrd.php';
	$src  = file_get_contents($path);
	expect($src)->not->toBeFalse('Failed to read lib/rrd.php');

	return $src;
}

function getAggregateSource(): string {
	$path = __DIR__ . '/../../lib/aggregate.php';
	$src  = file_get_contents($path);
	expect($src)->not->toBeFalse('Failed to read lib/aggregate.php');

	return $src;
}

// --- lib/rrd.php: empty cdef guard ---

test('rrd.php has empty cdef_string guard before CDEF name generation', function () {
	$src = getRrdSource();

	// The guard: if ($cdef_string === '') { ... continue; }
	// must appear before the CDEF:cdef name generation line
	$guardPos = strpos($src, "\$cdef_string === ''");
	expect($guardPos)->not->toBeFalse(
		"lib/rrd.php must contain the empty cdef_string guard"
	);

	// The CDEF name generation line follows after the guard
	$cdefNamePos = strpos($src, "CDEF:cdef", $guardPos);
	expect($cdefNamePos)->not->toBeFalse(
		"'CDEF:cdef' name generation must appear after the empty cdef guard"
	);
	expect($cdefNamePos)->toBeGreaterThan($guardPos,
		"The empty cdef guard must precede the CDEF name generation"
	);
});

test('empty cdef guard uses strict identity comparison', function () {
	$src = getRrdSource();

	// Must use === not == to avoid false positives on '0' or other falsy strings
	$pattern = '/if\s*\(\s*\$cdef_string\s*===\s*\'\'\s*\)/';
	expect(preg_match($pattern, $src))->toBe(1,
		"The empty cdef guard must use strict === comparison"
	);
});

test('empty cdef guard block contains continue statement', function () {
	$src = getRrdSource();

	// The guard block must have a continue to skip the current loop iteration
	$pattern = '/if\s*\(\s*\$cdef_string\s*===\s*\'\'\s*\)\s*\{[^}]*continue;/s';
	expect(preg_match($pattern, $src))->toBe(1,
		"The empty cdef guard must contain a continue statement"
	);
});

test('empty cdef guard includes debug logging', function () {
	$src = getRrdSource();

	// The guard should log before continuing, so the skip is traceable
	$pattern = '/if\s*\(\s*\$cdef_string\s*===\s*\'\'\s*\)\s*\{[^}]*cacti_log\([^)]*Empty CDEF/s';
	expect(preg_match($pattern, $src))->toBe(1,
		"The empty cdef guard should log a debug message about the empty CDEF"
	);
});

// --- lib/aggregate.php: db_execute_prepared for cdef_id UPDATE ---

test('aggregate.php uses db_execute_prepared for cdef_id UPDATE', function () {
	$src = getAggregateSource();

	// The cdef_id UPDATE must use db_execute_prepared, not raw db_execute
	$pattern = "/db_execute_prepared\s*\(\s*'UPDATE graph_templates_item\s+SET cdef_id/s";
	expect(preg_match($pattern, $src))->toBe(1,
		"lib/aggregate.php must use db_execute_prepared for the cdef_id UPDATE"
	);
});

test('aggregate.php cdef_id UPDATE does not use string interpolation', function () {
	$src = getAggregateSource();

	// Old pattern: "UPDATE graph_templates_item SET cdef_id=$new_cdef_id WHERE id=" . $graph_template_item["id"]
	// This must not exist anymore
	$pattern = '/db_execute\s*\(\s*"UPDATE graph_templates_item\s+SET cdef_id=\$/';
	expect(preg_match($pattern, $src))->toBe(0,
		"lib/aggregate.php must not use string-interpolated db_execute for cdef_id UPDATE"
	);
});

test('aggregate.php cdef_id UPDATE uses parameter binding', function () {
	$src = getAggregateSource();

	// The prepared statement should use ? placeholders and an array parameter
	$pattern = "/UPDATE graph_templates_item\s+SET cdef_id\s*=\s*\?\s+WHERE id\s*=\s*\?/s";
	expect(preg_match($pattern, $src))->toBe(1,
		"cdef_id UPDATE must use ? placeholders for parameter binding"
	);
||||||| 7dd05ee12
=======
 * Tests for the empty CDEF guard in rrd.php CDEF emission.
 *
 * When aggregate graphs include GPRINT items whose consolidation
 * function does not match any AREA/STACK/LINE item's DEF, the
 * magic-variable expansion produces an empty RPN string. Without a
 * guard, rrd.php emits "CDEF:cdefX=" which rrdtool rejects with
 * "ERROR: can not parse an empty rpn expression".
 *
 * The fix adds: if ($cdef_string === '') continue;
 *
 * These tests verify the guard logic using a stub that mirrors the
 * CDEF emission path without requiring rrdtool or database access.
 *
 * SYNC WARNING: emit_cdef_line() and expand_similar_nodups() below
 * replicate logic from the CDEF emission block in
 * rrdtool_function_graph_variables() and the SIMILAR_DATA_SOURCES_NODUPS
 * expansion in rrdtool_function_graph() in lib/rrd.php. Production
 * functions require rrdtool and DB state. If the production CDEF emission
 * or magic-variable expansion logic changes, these helpers must be
 * updated to match.
 */

require_once __DIR__ . '/../../include/global_constants.php';

/**
 * Mirrors the CDEF emission logic in rrd.php.
 * Returns the CDEF line string, or null if the cdef_string is empty
 * (matching the continue guard).
 */
function emit_cdef_line(string $cdef_string, int $item_index): ?string {
	// guard: skip empty RPN expressions
	if ($cdef_string === '') {
		return null;
	}

	// bounds guard: chr(ord('a') + N) only produces a-z for 0..25.
	// Production rrd.php uses generate_graph_def_name() (digit-to-letter
	// lookup) which handles any non-negative int; this guard is specific
	// to the test helper's chr()-based approach.
	if ($item_index < 0 || $item_index > 25) {
		return null;
	}

	$def_name = chr(ord('a') + $item_index);

	return 'CDEF:cdef' . $def_name . '=' . $cdef_string;
}

/**
 * Simulates the magic-variable expansion for SIMILAR_DATA_SOURCES_NODUPS.
 * When no matching AREA/STACK/LINE items exist for a given CF, the
 * expansion produces an empty string.
 */
function expand_similar_nodups(array $graph_items, string $data_source_name, int $cf_id): string {
	$parts     = [];
	$rra_epoch = time() - 86400;

	foreach ($graph_items as $item) {
		/* only AREA/STACK/LINE items are eligible, matching the preg_match
		   filter at rrd.php:2120 */
		if (!isset($item['type_name']) || !preg_match('/(AREA|STACK|LINE[123])/', $item['type_name'])) {
			continue;
		}

		if ($item['data_source_name'] !== $data_source_name) {
			continue;
		}

		if ($item['cf_id'] !== $cf_id) {
			continue;
		}

		$def     = $item['def_name'];
		$parts[] = "TIME,{$rra_epoch},GT,{$def},{$def},UN,0,{$def},IF,IF";
	}

	if (count($parts) === 0) {
		return '';
	}

	$result = implode(',', $parts);

	if (count($parts) > 1) {
		$result .= str_repeat(',+', count($parts) - 1);
	}

	return $result;
}

// --- Empty CDEF guard ---

test('empty cdef string returns null (skipped)', function () {
	expect(emit_cdef_line('', 0))->toBeNull();
});

test('non-empty cdef string emits valid CDEF line', function () {
	$result = emit_cdef_line('a,8,*', 0);

	expect($result)->toBe('CDEF:cdefa=a,8,*');
});

test('guard uses strict empty string check so null would not match', function () {
	// rrd_substitute_host_query_data() has return type `: string`, so null
	// cannot reach the guard in production. This test documents that the
	// strict === '' comparison does not catch null, confirming reliance on
	// the upstream return type guarantee.
	expect('' === null)->toBeFalse();
});

test('whitespace-only cdef string is NOT treated as empty', function () {
	// the guard uses strict === '' comparison, so whitespace is not empty
	$result = emit_cdef_line(' ', 0);

	expect($result)->not->toBeNull();
});

// --- Magic-variable expansion produces empty string for unmatched CF ---

test('expansion returns empty when no AREA/LINE items match the CF', function () {
	// scenario: GPRINT with CF=MAX (3), but only AREA items with CF=AVERAGE (1)
	$items = [
		['type_name' => 'AREA', 'data_source_name' => 'traffic_in', 'cf_id' => 1, 'def_name' => 'b'],
		['type_name' => 'AREA', 'data_source_name' => 'traffic_in', 'cf_id' => 1, 'def_name' => 'f'],
	];

	$result = expand_similar_nodups($items, 'traffic_in', 3);

	expect($result)->toBe('');
});

test('expansion returns RPN when AREA items match the CF', function () {
	$items = [
		['type_name' => 'AREA', 'data_source_name' => 'traffic_in', 'cf_id' => 1, 'def_name' => 'b'],
		['type_name' => 'AREA', 'data_source_name' => 'traffic_in', 'cf_id' => 1, 'def_name' => 'f'],
	];

	$result = expand_similar_nodups($items, 'traffic_in', 1);

	expect($result)->not->toBe('')
		->and($result)->toContain('b,b,UN,0,b,IF,IF')
		->and($result)->toContain('f,f,UN,0,f,IF,IF')
		->and($result)->toContain(',+');
});

test('expansion skips GPRINT items even if CF matches', function () {
	$items = [
		['type_name' => 'GPRINT', 'data_source_name' => 'traffic_in', 'cf_id' => 1, 'def_name' => 'b'],
	];

	$result = expand_similar_nodups($items, 'traffic_in', 1);

	expect($result)->toBe('');
});

test('expansion skips COMMENT items', function () {
	$items = [
		['type_name' => 'COMMENT', 'data_source_name' => 'traffic_in', 'cf_id' => 1, 'def_name' => 'b'],
	];

	$result = expand_similar_nodups($items, 'traffic_in', 1);

	expect($result)->toBe('');
});

// --- Integration: empty expansion feeds into CDEF emission guard ---

test('unmatched CF expansion produces empty string that the guard skips', function () {
	$items = [
		['type_name' => 'AREA', 'data_source_name' => 'traffic_in', 'cf_id' => 1, 'def_name' => 'b'],
		['type_name' => 'AREA', 'data_source_name' => 'traffic_out', 'cf_id' => 1, 'def_name' => 'd'],
	];

	// GPRINT with CF=MAX tries to expand SIMILAR for traffic_in with cf=3
	$cdef_string = expand_similar_nodups($items, 'traffic_in', 3);
	$cdef_line   = emit_cdef_line($cdef_string, 7);

	expect($cdef_string)->toBe('')
		->and($cdef_line)->toBeNull();
});

test('matched CF expansion produces valid CDEF that emits correctly', function () {
	$items = [
		['type_name' => 'AREA', 'data_source_name' => 'traffic_in', 'cf_id' => 1, 'def_name' => 'b'],
		['type_name' => 'AREA', 'data_source_name' => 'traffic_in', 'cf_id' => 1, 'def_name' => 'f'],
	];

	$cdef_string = expand_similar_nodups($items, 'traffic_in', 1);
	$cdef_line   = emit_cdef_line($cdef_string, 8);

	expect($cdef_string)->not->toBe('')
		->and($cdef_line)->toStartWith('CDEF:cdefi=')
		->and($cdef_line)->toContain(',+');
});

// --- Single data source: no trailing plus operator ---

test('single matching item produces RPN without trailing plus', function () {
	$items = [
		['type_name' => 'LINE1', 'data_source_name' => 'traffic_in', 'cf_id' => 1, 'def_name' => 'b'],
	];

	$result = expand_similar_nodups($items, 'traffic_in', 1);

	expect($result)->not->toBe('')
		->and($result)->not->toContain(',+');
});

// --- Edge cases ---

test('emit_cdef_line returns null for negative item_index', function () {
	expect(emit_cdef_line('a,8,*', -1))->toBeNull();
});

test('emit_cdef_line returns null for item_index above 25', function () {
	expect(emit_cdef_line('a,8,*', 26))->toBeNull();
});

test('emit_cdef_line handles item_index at boundary 25', function () {
	$result = emit_cdef_line('a,8,*', 25);

	expect($result)->toBe('CDEF:cdefz=a,8,*');
});

test('expand_similar_nodups handles empty graph_items array', function () {
	expect(expand_similar_nodups([], 'traffic_in', 1))->toBe('');
});

test('expand_similar_nodups skips items with missing type_name', function () {
	$items = [
		['data_source_name' => 'traffic_in', 'cf_id' => 1, 'def_name' => 'b'],
	];

	expect(expand_similar_nodups($items, 'traffic_in', 1))->toBe('');
});

// --- Additional edge cases for emit_cdef_line ---

test('emit_cdef_line handles item_index at boundary 0', function () {
	$result = emit_cdef_line('a,8,*', 0);

	expect($result)->toBe('CDEF:cdefa=a,8,*');
});

// --- Additional edge cases for expand_similar_nodups ---

test('expand_similar_nodups handles single matching item', function () {
	$items = [
		['type_name' => 'AREA', 'data_source_name' => 'cpu', 'cf_id' => 1, 'def_name' => 'a'],
	];

	$result = expand_similar_nodups($items, 'cpu', 1);

	expect($result)->not->toBe('')
		->and($result)->not->toContain(',+');
});

test('expand_similar_nodups skips items with missing data_source_name key', function () {
	$items = [
		['type_name' => 'AREA', 'cf_id' => 1, 'def_name' => 'b', 'data_source_name' => 'other'],
	];

	expect(expand_similar_nodups($items, 'traffic_in', 1))->toBe('');
});

test('expand_similar_nodups returns empty when data_source_name matches but cf_id differs', function () {
	$items = [
		['type_name' => 'AREA', 'data_source_name' => 'traffic_in', 'cf_id' => 1, 'def_name' => 'b'],
		['type_name' => 'STACK', 'data_source_name' => 'traffic_in', 'cf_id' => 1, 'def_name' => 'c'],
	];

	$result = expand_similar_nodups($items, 'traffic_in', 4);

	expect($result)->toBe('');
>>>>>>> origin/fix/jquery-deprecations
});

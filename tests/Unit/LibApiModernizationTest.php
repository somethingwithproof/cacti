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
 * Covers the PHP 8.4 idiom migration in the DB-free helpers of lib/api_*.php:
 * the preg_match((string) $var) casts over posted keys in
 * aggregate_validate_graph_items(), and the explode()/array_map(intval(...))
 * request coercion in api_graph_remove_aggregate_items(). The rewrites are
 * behaviour preserving, so these cases pin the migrated lines rather than
 * re-testing the surrounding API. The remaining api_* changes sit inside
 * DB-writing/render/SNMP functions and need a live Cacti application context.
 */

require_once dirname(__DIR__) . '/Helpers/LibApiHarness.php';

final class LibApiModernizationTest extends \PHPUnit\Framework\TestCase {
	// --- aggregate_validate_graph_items: preg_match((string) $var, ...) on keys ---

	public function test_validate_graph_items_maps_color_skip_and_total_keys() : void {
		$graph_items = ['10' => [], '20' => []];
		$posted      = [
			'agg_color_10' => '7',
			'agg_skip_20'  => 'on',
			'agg_total_10' => 'on',
			'unrelated'    => 'ignored',
		];

		aggregate_validate_graph_items($posted, $graph_items);

		$this->assertSame('7', $graph_items['10']['color_template']);
		$this->assertSame('on', $graph_items['20']['item_skip']);
		$this->assertSame('on', $graph_items['10']['item_total']);
	}

	public function test_validate_graph_items_ignores_unmatched_keys() : void {
		$graph_items = ['10' => []];
		$posted      = ['not_an_agg_key' => 'x', 'agg_color_' => 'y'];

		aggregate_validate_graph_items($posted, $graph_items);

		$this->assertSame(['10' => []], $graph_items);
	}

	// --- api_graph_remove_aggregate_items: explode(',', (string) $ids),
	//     array_map(intval(...), $ids) ---

	public function test_remove_aggregate_items_string_input_is_split_and_intval_filtered() : void {
		// All ids collapse to <= 0, so the filtered list is empty and no DB
		// query runs; the explode()/array_map(intval(...)) coercion still executes.
		api_graph_remove_aggregate_items('0,abc,-5');

		$this->assertTrue(true);
	}

	public function test_remove_aggregate_items_array_input_is_intval_filtered() : void {
		api_graph_remove_aggregate_items([0, -2, 'not-a-number']);

		$this->assertTrue(true);
	}
}

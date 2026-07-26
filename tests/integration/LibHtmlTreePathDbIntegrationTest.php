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
 * DB-boundary coverage for the get_tree_path() idiom migration in
 * lib/html_tree.php: str_contains((string) grv('node')) and the explode()s on
 * the node/hgd request values. get_tree_path() walks branch ancestry through
 * db_fetch_row_prepared() against graph_tree_items, whose result is gated on
 * PDOStatement::rowCount(); the PDO sqlite driver reports 0 there, so the parent
 * walk only executes against a real MySQL/MariaDB server. The test connects to
 * the local instance, seeds two branch rows under a high id range, and removes
 * them afterwards. It is skipped when the server is unreachable.
 */

// database.php must load before the harness so its real get_mysql_info() wins
// and the harness stub (guarded) stands down.
require_once dirname(__DIR__, 2) . '/lib/database.php';
require_once dirname(__DIR__) . '/Helpers/LibHtmlHarness.php';
require_once dirname(__DIR__, 2) . '/lib/html_tree.php';

final class LibHtmlTreePathDbIntegrationTest extends \PHPUnit\Framework\TestCase {
	private const ROOT_ID = 999900010;
	private const LEAF_ID = 999900020;
	private const TREE_ID = 60000; // graph_tree_id is smallint unsigned (<= 65535)

	private static bool $connected = false;

	public static function setUpBeforeClass() : void {
		if (!libhtml_connect_default()) {
			return;
		}

		self::$connected = true;

		db_execute_prepared('DELETE FROM graph_tree_items WHERE id IN (?, ?)', [self::ROOT_ID, self::LEAF_ID]);
		db_execute_prepared('INSERT INTO graph_tree_items (id, graph_tree_id, parent) VALUES (?, ?, 0)', [self::ROOT_ID, self::TREE_ID]);
		db_execute_prepared('INSERT INTO graph_tree_items (id, graph_tree_id, parent) VALUES (?, ?, ?)', [self::LEAF_ID, self::TREE_ID, self::ROOT_ID]);
	}

	public static function tearDownAfterClass() : void {
		if (self::$connected) {
			db_execute_prepared('DELETE FROM graph_tree_items WHERE id IN (?, ?)', [self::ROOT_ID, self::LEAF_ID]);
		}

		libhtml_disconnect_default();
	}

	protected function setUp() : void {
		if (!self::$connected) {
			$this->markTestSkipped('local MariaDB/cacti database not reachable');
		}

		libhtml_connect_default();
	}

	public function test_tbranch_node_resolves_branch_ancestry() : void {
		libhtml_reset('graph_view.php', ['node' => 'tbranch-' . self::LEAF_ID, 'hgd' => 'gt:5']);

		$path = get_tree_path();

		$this->assertContains('tree_anchor-' . self::TREE_ID . '_anchor', $path);
		$this->assertContains('tbranch-' . self::LEAF_ID, $path);
		$this->assertContains('tbranch-' . self::ROOT_ID, $path);
	}

	public function test_tree_anchor_node_returns_anchor() : void {
		libhtml_reset('graph_view.php', ['node' => 'tree_anchor-' . self::TREE_ID]);

		$this->assertContains('tree_anchor-' . self::TREE_ID . '_anchor', get_tree_path());
	}

	public function test_no_node_returns_empty_path() : void {
		libhtml_reset('graph_view.php', []);

		$this->assertSame([], get_tree_path());
	}
}

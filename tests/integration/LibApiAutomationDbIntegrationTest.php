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
 * DB-boundary coverage for the str_starts_with((string) $field, 't_') /
 * substr((string) $field, 2) idiom migration in
 * automation_graph_automation_eligible() (lib/api_automation.php). The function
 * reads graph_templates_graph and iterates that row's t_* override columns, so
 * it runs against a real database. A single override row is seeded under a high
 * graph_template_id and removed afterwards. Read-only apart from the fixture;
 * skipped when the local database is unreachable.
 */

require_once dirname(__DIR__, 2) . '/lib/database.php';
require_once dirname(__DIR__) . '/Helpers/LibApiHarness.php';

final class LibApiAutomationDbIntegrationTest extends \PHPUnit\Framework\TestCase {
	private const GT_ID = 999901;

	private static bool $connected = false;

	public static function setUpBeforeClass() : void {
		if (!libapi_connect_default()) {
			return;
		}

		self::$connected = true;

		// A t_* override toggled on with an empty parent marks the template
		// ineligible, which is the branch that runs the migrated casts.
		db_execute_prepared('DELETE FROM graph_templates_graph WHERE graph_template_id = ?', [self::GT_ID]);
		db_execute_prepared(
			'INSERT INTO graph_templates_graph (graph_template_id, local_graph_id, title, t_title)
			VALUES (?, 0, ?, ?)',
			[self::GT_ID, '', 'on']
		);
	}

	public static function tearDownAfterClass() : void {
		if (self::$connected) {
			db_execute_prepared('DELETE FROM graph_templates_graph WHERE graph_template_id = ?', [self::GT_ID]);
		}

		libapi_disconnect_default();
	}

	protected function setUp() : void {
		if (!self::$connected) {
			$this->markTestSkipped('local MariaDB/cacti database not reachable');
		}

		libapi_connect_default();
	}

	public function test_template_with_enabled_override_and_empty_parent_is_ineligible() : void {
		$this->assertFalse(automation_graph_automation_eligible(self::GT_ID));
	}

	public function test_unknown_template_is_eligible() : void {
		$this->assertTrue(automation_graph_automation_eligible(2147480000));
	}
}

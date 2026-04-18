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

// GHSA-8p2f-6jvx-j75j: Reports IDOR. Mutation endpoints (form_save,
// form_actions, item_remove, item_moveup, item_movedown, item_dnd) accepted a
// report id from the request and acted on it without checking the session
// user's ownership or admin realm. The fix introduces
// reports_user_may_mutate(report_id) plus reports_user_may_mutate_item(item_id)
// and gates each mutation with one of them.

test('helper function reports_user_may_mutate is declared in lib/html_reports.php', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/html_reports.php');

	expect($source)->toContain('function reports_user_may_mutate(int $report_id) : bool');
	expect($source)->toContain('function reports_user_may_mutate_item(int $item_id) : bool');
});

test('reports_form_save gates non-admin updates through reports_user_may_mutate', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/html_reports.php');

	$openPos = strpos($source, 'function reports_form_save');
	expect($openPos)->not->toBeFalse();

	$slice = substr($source, $openPos, 6000);
	expect($slice)->toContain('reports_user_may_mutate((int) $post[\'id\'])');
});

test('reports_form_actions guards per-row mutations', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/html_reports.php');

	$openPos = strpos($source, 'function reports_form_actions');
	expect($openPos)->not->toBeFalse();

	$slice = substr($source, $openPos, 8000);

	$guardPos  = strpos($slice, '!reports_user_may_mutate($report_id)');
	$deletePos = strpos($slice, 'REPORTS_DELETE');

	expect($guardPos)->not->toBeFalse();
	expect($deletePos)->not->toBeFalse();
	expect($guardPos)->toBeLessThan($deletePos);
});

test('reports_item_remove checks ownership via the item helper', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/html_reports.php');

	$openPos = strpos($source, 'function reports_item_remove');
	expect($openPos)->not->toBeFalse();

	$slice = substr($source, $openPos, 800);
	expect($slice)->toContain('reports_user_may_mutate_item($item_id)');

	$guardPos  = strpos($slice, '!reports_user_may_mutate_item');
	$deletePos = strpos($slice, 'DELETE FROM reports_items');

	expect($guardPos)->toBeLessThan($deletePos);
});

test('reports_item_moveup and movedown reject non-owners', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/html_reports.php');

	foreach (['reports_item_moveup', 'reports_item_movedown'] as $fn) {
		$openPos = strpos($source, "function $fn");
		expect($openPos)->not->toBeFalse();

		$slice = substr($source, $openPos, 800);
		expect($slice)->toContain('!reports_user_may_mutate((int) grv(\'id\'))');
	}
});

test('reports_item_dnd gates ownership before mutating sequence', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/html_reports.php');

	$openPos = strpos($source, 'function reports_item_dnd');
	expect($openPos)->not->toBeFalse();

	$slice = substr($source, $openPos, 1500);

	$guardPos  = strpos($slice, '!reports_user_may_mutate((int) grv(\'id\'))');
	$updatePos = strpos($slice, 'UPDATE reports_items');

	expect($guardPos)->not->toBeFalse();
	expect($updatePos)->not->toBeFalse();
	expect($guardPos)->toBeLessThan($updatePos);
});

test('is_reports_admin short-circuits ownership check', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/html_reports.php');

	$openPos = strpos($source, 'function reports_user_may_mutate(int $report_id)');
	expect($openPos)->not->toBeFalse();

	$slice    = substr($source, $openPos, 800);
	$adminPos = strpos($slice, 'is_reports_admin()');
	$queryPos = strpos($slice, 'SELECT user_id FROM reports');

	expect($adminPos)->toBeLessThan($queryPos);
});

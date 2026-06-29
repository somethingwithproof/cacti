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
 * GHSA-8p2f-6jvx-j75j (CVE-2026-40081) - Reports IDOR.
 *
 * The report mutation endpoints in lib/html_reports.php accepted a report or
 * report-item id from the request and acted on it without checking ownership,
 * letting any authenticated user modify or delete another user's reports. The
 * fix routes each endpoint through cacti_authorize_resource(), which is
 * fail-closed: non-owner, missing row, or unknown resource type all deny.
 *
 * These tests assert (a) the source contract - every mutation endpoint gates on
 * an ownership check before it mutates - and (b) the runtime fail-closed
 * behaviour of cacti_authorize_resource() itself.
 */

$root = dirname(__DIR__, 2);

if (!function_exists('read_config_option')) {
	function read_config_option($name) {
		return '';
	}
}

// cacti_authorize_resource() and its two realm helpers reach the database via
// db_fetch_cell_prepared(). lib/database.php is not loaded by the Pest
// bootstrap, so stand in a controllable stub when nothing else has defined it.
// The stub denies admin/realm membership and returns the configured owner id
// for the row lookups, which is all the predicate needs.
if (!function_exists('db_fetch_cell_prepared')) {
	$GLOBALS['__ghsa8p2f_stub'] = true;

	function db_fetch_cell_prepared($sql, $params = [], $col_name = '', $log = true, $db_conn = false, $timeout = 0) {
		// Realm/admin lookups hit user_auth_* tables; deny so ownership decides.
		if (strpos($sql, 'user_auth') !== false) {
			return false;
		}

		// reports / reports_items ownership lookup.
		return $GLOBALS['__ghsa8p2f_owner'] ?? false;
	}
}

require_once $root . '/lib/auth.php';

$stub_active = ($GLOBALS['__ghsa8p2f_stub'] ?? false) === true;

/*
 * Extract a single top-level function body from a source file so ordering of
 * the guard relative to the mutation can be asserted per endpoint.
 */
function ghsa8p2f_function_body(string $source, string $name): string {
	$start = strpos($source, 'function ' . $name . '(');
	expect($start)->not->toBeFalse("endpoint $name not found");

	$next = strpos($source, "\nfunction ", $start + 1);
	$end  = $next === false ? strlen($source) : $next;

	return substr($source, $start, $end - $start);
}

$reportsSource = file_get_contents($root . '/lib/html_reports.php');

// --- source contract: each endpoint gates before mutating ---

test('reports_item_remove authorizes the report item before deleting it', function () use ($reportsSource) {
	$body = ghsa8p2f_function_body($reportsSource, 'reports_item_remove');

	$guard    = strpos($body, "cacti_authorize_resource");
	$mutation = strpos($body, 'DELETE FROM reports_items');

	expect($guard)->not->toBeFalse();
	expect($body)->toContain("'report_item'");
	expect($guard)->toBeLessThan($mutation);
});

test('reports_item_movedown authorizes the report before moving the item', function () use ($reportsSource) {
	$body = ghsa8p2f_function_body($reportsSource, 'reports_item_movedown');

	$guard    = strpos($body, "cacti_authorize_resource");
	$mutation = strpos($body, 'move_item_down');

	expect($guard)->not->toBeFalse();
	expect($guard)->toBeLessThan($mutation);
});

test('reports_item_moveup authorizes the report before moving the item', function () use ($reportsSource) {
	$body = ghsa8p2f_function_body($reportsSource, 'reports_item_moveup');

	$guard    = strpos($body, "cacti_authorize_resource");
	$mutation = strpos($body, 'move_item_up');

	expect($guard)->not->toBeFalse();
	expect($guard)->toBeLessThan($mutation);
});

test('reports_item_dnd authorizes the report before resequencing items', function () use ($reportsSource) {
	$body = ghsa8p2f_function_body($reportsSource, 'reports_item_dnd');

	$guard    = strpos($body, "cacti_authorize_resource");
	$mutation = strpos($body, 'UPDATE reports_items');

	expect($guard)->not->toBeFalse();
	expect($guard)->toBeLessThan($mutation);
});

test('reports_form_save authorizes an existing report before reading its owner', function () use ($reportsSource) {
	$body = ghsa8p2f_function_body($reportsSource, 'reports_form_save');

	$guard    = strpos($body, "cacti_authorize_resource");
	$mutation = strpos($body, 'SELECT user_id FROM reports WHERE id');

	expect($guard)->not->toBeFalse();
	expect($guard)->toBeLessThan($mutation);
});

test('reports_edit authorizes a fetched report before rendering it', function () use ($reportsSource) {
	$body = ghsa8p2f_function_body($reportsSource, 'reports_edit');

	$fetch = strpos($body, 'SELECT * FROM reports WHERE id');
	$guard = strpos($body, "cacti_authorize_resource");

	expect($guard)->not->toBeFalse();
	expect($guard)->toBeGreaterThan($fetch);
});

test('reports_form_actions filters selected reports through an ownership check', function () use ($reportsSource) {
	// This endpoint was hardened earlier (#7169) with a per-item ownership
	// closure rather than cacti_authorize_resource(); assert that gate survives.
	$body = ghsa8p2f_function_body($reportsSource, 'reports_form_actions');

	$guard  = strpos($body, 'is_reports_admin');
	$filter = strpos($body, 'can_manage_report');
	$delete = strpos($body, "DELETE FROM reports WHERE id = ?");

	expect($guard)->not->toBeFalse();
	expect($filter)->not->toBeFalse();
	expect($filter)->toBeLessThan($delete);
});

// --- runtime: cacti_authorize_resource is fail-closed ---

test('cacti_authorize_resource denies a non-positive resource id', function () {
	expect(cacti_authorize_resource(7, 0, 'reports'))->toBeFalse();
	expect(cacti_authorize_resource(7, -1, 'reports'))->toBeFalse();
});

test('cacti_authorize_resource denies a non-positive user id', function () {
	expect(cacti_authorize_resource(0, 5, 'reports'))->toBeFalse();
});

test('cacti_authorize_resource denies an unknown resource type', function () use ($stub_active) {
	$GLOBALS['__ghsa8p2f_owner'] = 1001;
	expect(cacti_authorize_resource(1001, 5, 'no_such_type'))->toBeFalse();
})->skip(!$stub_active, 'requires the in-test db stub');

test('cacti_authorize_resource grants the owning user', function () use ($stub_active) {
	$GLOBALS['__ghsa8p2f_owner'] = 2002;
	// distinct ids avoid the realm/admin static caches in the helpers.
	expect(cacti_authorize_resource(2002, 11, 'reports'))->toBeTrue();
})->skip(!$stub_active, 'requires the in-test db stub');

test('cacti_authorize_resource denies a non-owning user', function () use ($stub_active) {
	$GLOBALS['__ghsa8p2f_owner'] = 3003;
	expect(cacti_authorize_resource(4004, 12, 'reports'))->toBeFalse();
})->skip(!$stub_active, 'requires the in-test db stub');

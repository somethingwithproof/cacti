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
require_once dirname(__DIR__, 2) . '/lib/ldap.php';

// Exercises the PHP 8.4 idiom migrations in lib/database.php and lib/ldap.php
// that do not require a live database or directory server.

// =====================================================================
// db_in_clause - line 2149 (non-numeric branch, db_qstr callable)
// =====================================================================

test('db_in_clause quotes a non-numeric value list', function () {
	// numeric=false routes through array_map(db_qstr(...)); with no live
	// connection db_qstr falls back to its literal escaping.
	$clause = db_in_clause('host.hostname', ['alpha', 'beta'], false);

	expect($clause)->toBe("(host.hostname IN ('alpha','beta'))");
});

test('db_in_clause escapes embedded quotes in the non-numeric branch', function () {
	$clause = db_in_clause('name', ["a'b"], false);

	expect($clause)->toContain("\\'");
});

// =====================================================================
// Ldap::SetLdapHandler / RestoreCactiHandler - lines 538, 548
// =====================================================================

test('Ldap Authenticate swaps and restores the error handler on a failed connect', function () {
	// Authenticate() calls SetLdapHandler() before connecting and
	// RestoreCactiHandler() once Connect() reports an error. An empty username
	// makes Connect() fail immediately, so both handler swaps run without a
	// directory server. Snapshot PHPUnit's handler and put it back afterwards
	// because those methods manipulate the global error-handler stack.
	$saved = set_error_handler(null);
	restore_error_handler();

	$ldap = new Ldap(0);
	$ldap->username = '';
	$ldap->debug    = POLLER_VERBOSITY_HIGH;

	$result = $ldap->Authenticate();

	// RestoreCactiHandler left CactiErrorHandler on top; restore PHPUnit's.
	restore_error_handler();
	set_error_handler($saved);

	expect($result)->toBeArray()
		->and($result)->toHaveKey('error_num');
});

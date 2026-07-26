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

// auth_changepassword.php is a top-level entry point that ends each branch with
// exit(), so its checkpass guard is verified structurally here and executed end
// to end in the integration test. The guard must reject an anonymous caller
// before secpass_check_pass() can reveal the password policy.

function authCheckpassSource(): string {
	$src = file_get_contents(dirname(__DIR__, 2) . '/auth_changepassword.php');
	expect($src)->not->toBeFalse('failed to read auth_changepassword.php');

	return $src;
}

it('gates the checkpass action on an authenticated session', function () {
	$src = authCheckpassSource();

	// Isolate the checkpass case body up to the next case.
	$checkpass = substr($src, strpos($src, "case 'checkpass':"));
	$checkpass = substr($checkpass, 0, strpos($checkpass, 'secpass_check_pass'));

	expect($checkpass)->toContain('!isset($_SESSION[SESS_USER_ID])')
		->and($checkpass)->toContain('validate_redirect_url')
		->and($checkpass)->toContain('exit;');
});

it('places the session check before the password policy probe', function () {
	$src = authCheckpassSource();

	$case_pos    = strpos($src, "case 'checkpass':");
	$guard_pos   = strpos($src, '!isset($_SESSION[SESS_USER_ID])', $case_pos);
	$secpass_pos = strpos($src, 'secpass_check_pass', $case_pos);

	expect($guard_pos)->toBeGreaterThan($case_pos)
		->and($secpass_pos)->toBeGreaterThan($guard_pos);
});

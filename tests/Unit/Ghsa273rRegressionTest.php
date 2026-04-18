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

// GHSA-273r-qr93-wgcp: session fixation. Cacti populated $_SESSION[SESS_USER_ID]
// on login, cookie restore, and basic-auth promotion without regenerating the
// PHP session id. An attacker who planted the session cookie before the victim
// authenticated inherited the authenticated session. The fix calls
// session_regenerate_id(true) at each authentication transition so the
// pre-auth id cannot be reused post-auth.

test('auth_login.php regenerates session id before granting authenticated session', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/auth_login.php');
	$lines  = explode("\n", $source);

	$userIdLine = null;

	foreach ($lines as $n => $line) {
		if (preg_match('/\$_SESSION\[SESS_USER_ID\]\s*=\s*\$user\[[\'"]id[\'"]\]/', $line)) {
			$userIdLine = $n;

			break;
		}
	}

	expect($userIdLine)->not->toBeNull();

	$window = implode("\n", array_slice($lines, max(0, $userIdLine - 12), 12));

	expect($window)->toContain('session_regenerate_id(true)');
});

test('include/auth.php regenerates session id on cookie-based login', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/include/auth.php');
	$lines  = explode("\n", $source);

	$cookieLine = null;

	foreach ($lines as $n => $line) {
		if (preg_match('/\$_SESSION\[SESS_USER_ID\]\s*=\s*\$cookie_user/', $line)) {
			$cookieLine = $n;

			break;
		}
	}

	expect($cookieLine)->not->toBeNull();

	$window = implode("\n", array_slice($lines, max(0, $cookieLine - 12), 12));

	expect($window)->toContain('session_regenerate_id(true)');
});

test('include/auth.php regenerates session id on basic-auth promotion', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/include/auth.php');
	$lines  = explode("\n", $source);

	$basicLine = null;

	foreach ($lines as $n => $line) {
		if (preg_match('/\$_SESSION\[SESS_USER_ID\]\s*=\s*\$current_user\[[\'"]id[\'"]\]/', $line)) {
			$basicLine = $n;

			break;
		}
	}

	expect($basicLine)->not->toBeNull();

	$window = implode("\n", array_slice($lines, max(0, $basicLine - 12), 12));

	expect($window)->toContain('session_regenerate_id(true)');
});

test('session_regenerate_id call is guarded against inactive sessions', function () {
	$auth = file_get_contents(dirname(__DIR__, 2) . '/include/auth.php');
	$login = file_get_contents(dirname(__DIR__, 2) . '/auth_login.php');

	foreach ([$auth, $login] as $source) {
		if (str_contains($source, 'session_regenerate_id(true)')) {
			expect($source)->toContain('session_status() === PHP_SESSION_ACTIVE');
		}
	}
});

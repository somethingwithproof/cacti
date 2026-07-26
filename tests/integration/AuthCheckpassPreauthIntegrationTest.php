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

// End-to-end check that an anonymous checkpass request to auth_changepassword.php
// is redirected before secpass_check_pass() runs, so the password policy is not
// disclosed. The entry point calls exit(), so it is exercised in a subprocess.
// It needs the full runtime (a populated vendor tree, include/config.php and a
// reachable database); the test skips when the runtime cannot boot.

$root = dirname(__DIR__, 2);

function checkpass_runtime_boots(string $root): bool {
	$probe   = 'chdir(' . var_export($root, true) . ');'
		. 'include ' . var_export($root . '/include/global.php', true) . ';'
		. 'echo "BOOT_OK";';
	$cmd     = escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 -r ' . escapeshellarg($probe) . ' 2>/dev/null';

	return str_contains((string) shell_exec($cmd), 'BOOT_OK');
}

if (!checkpass_runtime_boots($root)) {
	test('checkpass preauth guard: runtime not available', function () {})
		->skip('the full Cacti runtime (vendor, config, database) is not available');

	return;
}

it('does not disclose the password policy to an anonymous caller', function () use ($root) {
	// A strong password would pass the policy, so without the guard an anonymous
	// caller would receive "ok". The guard must redirect first.
	$snippet = "\$_GET['action']=\$_REQUEST['action']='checkpass';"
		. "\$_GET['password']=\$_POST['password']=\$_REQUEST['password']='StrongP@ssw0rd!9xZ';"
		. "\$_SERVER['HTTP_REFERER']='http://localhost/cacti/graph_view.php';"
		. 'chdir(' . var_export($root, true) . ');'
		. 'include ' . var_export($root . '/auth_changepassword.php', true) . ';';

	$cmd = escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 -r ' . escapeshellarg($snippet) . ' 2>/dev/null';

	$stdout = (string) shell_exec($cmd);

	expect($stdout)->not->toContain('ok');
});

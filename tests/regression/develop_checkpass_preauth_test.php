<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

// Regression guard: an unauthenticated checkpass request must be redirected
// rather than returning the password policy verdict.

$root = dirname(__DIR__, 2);

$boot_probe = 'chdir(' . var_export($root, true) . ');'
	. 'include ' . var_export($root . '/include/global.php', true) . ';'
	. 'echo "BOOT_OK";';
$boot_cmd = escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 -r ' . escapeshellarg($boot_probe) . ' 2>/dev/null';

if (!str_contains((string) shell_exec($boot_cmd), 'BOOT_OK')) {
	print "develop checkpass preauth regression skipped (runtime not available)\n";
	exit(0);
}

$snippet = "\$_GET['action']=\$_REQUEST['action']='checkpass';"
	. "\$_GET['password']=\$_POST['password']=\$_REQUEST['password']='StrongP@ssw0rd!9xZ';"
	. "\$_SERVER['HTTP_REFERER']='http://localhost/cacti/graph_view.php';"
	. 'chdir(' . var_export($root, true) . ');'
	. 'include ' . var_export($root . '/auth_changepassword.php', true) . ';';

$cmd = escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 -r ' . escapeshellarg($snippet) . ' 2>/dev/null';

$stdout = (string) shell_exec($cmd);

if (str_contains($stdout, 'ok')) {
	fwrite(STDERR, "anonymous checkpass returned a password policy verdict\n");
	exit(1);
}

print "develop checkpass preauth regression passed\n";

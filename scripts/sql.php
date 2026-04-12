<?php

error_reporting(0);

include(__DIR__ . '/../include/cli_check.php');

global $database_hostname, $database_username, $database_password;

$cmd = 'mysqladmin -h ' . cacti_escapeshellarg($database_hostname) . ' -u ' . cacti_escapeshellarg($database_username);

if ($database_password != '') {
	$cmd .= ' -p' . cacti_escapeshellarg($database_password);
}

<<<<<<< HEAD
$cmd .= ' status';
||||||| 7dd05ee12
print trim($sql);
=======
$cmd .= " status | awk '{print \$6 }'";
>>>>>>> origin/fix/jquery-deprecations

<<<<<<< HEAD
$output = shell_exec($cmd);

if ($output === null || $output === '') {
	print 'U';
} else {
	// Extract the 6th field (Queries per second avg), matching original awk '{print $6}'
	$parts = preg_split('/\s+/', trim($output));
	print isset($parts[5]) ? $parts[5] : 'U';
}
||||||| 7dd05ee12
=======
$sql = shell_exec($cmd);

// Cacti expects 'U' on error, not empty string or 0.
print trim($sql ?? '') ?: 'U';
>>>>>>> origin/fix/jquery-deprecations

<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

if (PHP_SAPI !== 'cli' || getenv('CACTI_E2E_DISPOSABLE', true) !== '1') {
	fwrite(STDERR, "This fixture requires an explicitly selected disposable E2E container.\n");
	exit(1);
}

$root = dirname(__DIR__, 3);
require $root . '/include/cli_check.php';

function cacti_accessibility_fixture_command(string $script, array $arguments): void {
	$process = proc_open(array_merge([PHP_BINARY, dirname(__DIR__, 3) . '/cli/' . $script], $arguments), [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes);

	if (!is_resource($process) || proc_close($process) !== 0) {
		throw new RuntimeException('Fixture command failed: ' . $script);
	}
}

if (!(int) db_fetch_cell('SELECT COUNT(*) FROM host_template')) {
	cacti_accessibility_fixture_command('import_package.php', ['--filename=' . $root . '/install/templates/Local_Linux_Machine.xml.gz', '--with-profile']);
}

if (!(int) db_fetch_cell('SELECT COUNT(*) FROM host')) {
	cacti_accessibility_fixture_command('add_device.php', ['--description=Sonar browser fixture', '--ip=127.0.0.1', '--template=1', '--disable=1', '--version=0', '--avail=none']);
}

if (!(int) db_fetch_cell('SELECT COUNT(*) FROM user_auth_group')) {
	cacti_accessibility_fixture_command('add_group.php', ['--type=add_group', '--name=Sonar browser fixture', '--description=Disposable browser regression fixture']);
}

if (!is_dir(CACTI_PATH_PKI) && !mkdir(CACTI_PATH_PKI, 0700, true) && !is_dir(CACTI_PATH_PKI)) {
	throw new RuntimeException('Unable to create the disposable key directory.');
}

if (!is_file(CACTI_PATH_PKI . '/package.info')) {
	cacti_accessibility_fixture_command('genkey.php', ['--generate', '--author=Disposable Sonar fixture', '--homepage=https://example.invalid', '--email=fixture@example.invalid', '--country=US', '--state=Test', '--org=Test', '--unit=Test', '--days=1']);
}
cacti_accessibility_fixture_command('add_perms.php', ['--user-id=1', '--item-type=graph', '--item-id=1']);
db_execute_prepared('UPDATE user_auth SET must_change_password = ?, password_change = ?, policy_graphs = ? WHERE id = ?', ['', 'on', 2, 1]);
db_execute_prepared('REPLACE INTO settings (name, value) VALUES (?, ?)', ['auth_method', '1']);
db_execute_prepared('REPLACE INTO settings_user (user_id, name, value) VALUES (?, ?, ?)', [1, 'hide_disabled', '']);
db_execute_prepared('REPLACE INTO user_auth_reset_hashes (user_id, hash, expiry) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))', [1, 'sonarbrowserfixture20261010']);
print "Disposable native accessibility fixtures prepared.\n";

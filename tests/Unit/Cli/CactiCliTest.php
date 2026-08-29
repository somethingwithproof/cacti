<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit tests for the CactiCli argument wrapper.
 * Runs under the source-scan (no-DB) bootstrap, which loads the Composer
 * autoloader, so the Symfony Console classes CactiCli wraps are available.
 */

require_once dirname(__DIR__, 3) . '/lib/CactiCli.php';

function _cli(): CactiCli {
	return (new CactiCli('example.php', 'Example command.'))
		->option('host-id', 'The device id', required: true)
		->option('label', 'An optional label', default: 'none')
		->flag('dry-run', 'Do not write changes');
}

test('parses --name=value options and a flag', function () {
	$cli = _cli()->parse(['example.php', '--host-id=7', '--dry-run']);

	expect($cli->get('host-id'))->toBe('7');
	expect($cli->isset('dry-run'))->toBeTrue();
});

test('applies the declared default when an optional option is absent', function () {
	$cli = _cli()->parse(['example.php', '--host-id=7']);

	expect($cli->get('label'))->toBe('none');
	expect($cli->isset('dry-run'))->toBeFalse();
});

test('help lists every declared option plus version and help', function () {
	$help = _cli()->help();

	expect($help)->toContain('example.php - Example command.');
	expect($help)->toContain('--host-id');
	expect($help)->toContain('--label');
	expect($help)->toContain('--dry-run');
	expect($help)->toContain('--version');
	expect($help)->toContain('--help');
});

test('reading an option before parse() throws instead of returning stale data', function () {
	expect(fn () => (new CactiCli('example.php'))->get('host-id'))
		->toThrow(RuntimeException::class);
});

test('an unknown option is rejected rather than silently ignored', function () {
	// parse() calls exit(1) on a bad option; run it in a subprocess and assert
	// the non-zero status and that the usage was written to stderr.
	$script = tempnam(sys_get_temp_dir(), 'clitest') . '.php';
	file_put_contents($script,
		"<?php require '" . dirname(__DIR__, 3) . "/include/vendor/autoload.php';" .
		" require '" . dirname(__DIR__, 3) . "/lib/CactiCli.php';" .
		" (new CactiCli('t.php'))->option('host-id','id')->parse(['t.php','--nope=1']);"
	);

	$out = [];
	$rc  = 0;
	exec(PHP_BINARY . ' ' . escapeshellarg($script) . ' 2>&1', $out, $rc);
	@unlink($script);

	expect($rc)->toBe(1);
	expect(implode("\n", $out))->toContain('The "--nope" option does not exist');
});

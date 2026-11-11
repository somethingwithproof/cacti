<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$pingSource = file_get_contents(__DIR__ . '/../../lib/ping.php');
$snmpSource = file_get_contents(__DIR__ . '/../../lib/snmp.php');
$dqSource   = file_get_contents(__DIR__ . '/../../lib/data_query.php');
$impSource  = file_get_contents(__DIR__ . '/../../lib/import.php');

test('ping constructor casts retries to int', function () use ($pingSource) {
	expect($pingSource)->toContain('$retries = (int)$retries');
});

test('ping constructor casts timeout to int', function () use ($pingSource) {
	expect($pingSource)->toContain('$timeout = (int)$timeout');
});

test('snmp_escape_string always wraps Windows strings', function () use ($snmpSource) {
	$start = strpos($snmpSource, 'function snmp_escape_string(');
	$body = substr($snmpSource, $start, 500);
	expect($body)->not->toContain('if (substr_count($string, SNMP_ESCAPE_CHARACTER))');
});

test('get_script_query_path sanitizes the script path without corrupting Windows separators', function () use ($dqSource) {
	$start = strpos($dqSource, 'function get_script_query_path(');
	$body  = substr($dqSource, $start, 2400);

	/* On Windows the path is stripped of shell metacharacters but the
	 * backslash separator is preserved; cacti_escapeshellcmd() would replace
	 * backslashes with spaces and corrupt the path to just 'C:'. On other
	 * platforms escapeshellcmd() remains in use. */
	expect($body)->toContain("\$config['cacti_server_os'] == 'win32'")
		->and($body)->toContain("str_replace(array('#', '&', ';'")
		->and($body)->toContain("), '', \$parsed_script_path)")
		->and($body)->toContain('cacti_escapeshellcmd($parsed_script_path)');
});

/* Load get_script_query_path() and the real escaping helpers it relies on
 * into an isolated namespace, so the traversal check is exercised against the
 * shipped code rather than matched as text. */
function core_hardening_load_script_query_path() {
	static $loaded = false;

	if ($loaded) {
		return;
	}

	$code = 'namespace CoreHardeningScriptQueryPath; ';

	foreach (array('lib/functions.php' => 'cacti_escapeshellcmd', 'lib/data_query.php' => 'get_script_query_path') as $file => $name) {
		$src   = file_get_contents(__DIR__ . '/../../' . $file);
		$start = strpos($src, "function $name(");
		$depth = 0;

		for ($i = strpos($src, '{', $start); $i < strlen($src); $i++) {
			if ($src[$i] == '{') {
				$depth++;
			} elseif ($src[$i] == '}' && --$depth == 0) {
				break;
			}
		}

		$code .= substr($src, $start, $i - $start + 1) . "\n";
	}

	$code .= 'function substitute_script_query_path($path) { global $config; return $config["cacti_server_os"] == "win32" ? str_replace("/", "\\\\", $path) : $path; }
		function cacti_log($message) { $GLOBALS["core_hardening_log"][] = $message; }';

	eval($code);

	$loaded = true;
}

function core_hardening_script_query_path($os, $path) {
	global $config;

	core_hardening_load_script_query_path();

	/* get_script_query_path() includes variables.php from library_path; give
	 * it an empty one, as the substitution helper is stubbed above. */
	$library = sys_get_temp_dir() . '/cacti-core-hardening';

	if (!is_dir($library)) {
		mkdir($library);
	}

	touch($library . '/variables.php');

	$saved  = isset($config) ? $config : null;
	$config = array('cacti_server_os' => $os, 'library_path' => $library);

	try {
		return \CoreHardeningScriptQueryPath\get_script_query_path('', $path, 0);
	} finally {
		$config = $saved;
	}
}

test('get_script_query_path rejects traversal after sanitizing', function () {
	foreach (array('win32', 'unix') as $os) {
		expect(core_hardening_script_query_path($os, '/cacti/scripts/../x.php'))->toBeFalse();
		expect(core_hardening_script_query_path($os, '../x.php'))->toBeFalse();
		expect(core_hardening_script_query_path($os, '/cacti/scripts/..'))->toBeFalse();
		expect(core_hardening_script_query_path($os, '/usr/bin/php -q ../x.php'))->toBeFalse();
	}

	/* metacharacters that are stripped on Windows, or quotes that cmd.exe
	 * drops, must not join two dots */
	foreach (array('.;.', '.#.', '.^.', '.%.', '."."') as $joined) {
		expect(core_hardening_script_query_path('win32', "/cacti/scripts/$joined/x.php"))->toBeFalse();
	}
});

test('get_script_query_path rejects any two consecutive dots in the sanitized path', function () {
	foreach (array('win32', 'unix') as $os) {
		expect(core_hardening_script_query_path($os, '/cacti/scripts..old/x.php'))->toBeFalse();
		expect(core_hardening_script_query_path($os, '/cacti/scripts/a..b.php'))->toBeFalse();
	}

	expect(core_hardening_script_query_path('win32', 'C:..\\x.php'))->toBeFalse();
});

test('get_script_query_path strips cmd.exe variable expansion on Windows', function () {
	expect(core_hardening_script_query_path('win32', '/cacti/scripts/%COMSPEC%x.php'))->toBe('\\cacti\\scripts\\COMSPECx.php ');
	expect(core_hardening_script_query_path('win32', '"/Program Files/cacti/%TEMP%/x.php" test'))->toBe('"\\Program Files\\cacti\\TEMP\\x.php" test ');
});

test('get_script_query_path keeps quotes around Windows paths with spaces', function () {
	expect(core_hardening_script_query_path('win32', '"C:/Program Files/Cacti/scripts/test.php" test'))->toBe('"C:\\Program Files\\Cacti\\scripts\\test.php" test ');
});

test('data_query value_index_parse constrains PCRE backtrack limit', function () use ($dqSource) {
	expect($dqSource)->toContain("ini_set('pcre.backtrack_limit', '10000')");
	expect($dqSource)->toContain("ini_set('pcre.recursion_limit', '10000')");
});

test('data_query value_index_parse restores PCRE limits', function () use ($dqSource) {
	expect($dqSource)->toContain("ini_set('pcre.backtrack_limit', \$old_backtrack)");
	expect($dqSource)->toContain("ini_set('pcre.recursion_limit', \$old_recursion)");
});

test('data_query_duplicate uses strip_tags on name', function () use ($dqSource) {
	$start = strpos($dqSource, 'function data_query_duplicate(');
	$body = substr($dqSource, $start, 500);
	expect($body)->toContain('strip_tags($data_query_name)');
});

test('import.php disables entity loader for XXE prevention', function () use ($impSource) {
	expect($impSource)->toContain('libxml_disable_entity_loader(true)');
});

test('import.php suppresses libxml warnings', function () use ($impSource) {
	expect($impSource)->toContain('libxml_use_internal_errors(true)');
});

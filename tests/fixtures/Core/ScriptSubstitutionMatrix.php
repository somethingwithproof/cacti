<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

if (getenv('CACTI_PR_RAW_COVERAGE') && !getenv('CACTI_COVERAGE_FILE')) {
	require_once dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
}

$coverage = getenv('CACTI_COVERAGE_FILE');

if ($coverage !== false && $coverage !== '') {
	if (!function_exists('xdebug_start_code_coverage')) {
		throw new RuntimeException('Xdebug coverage is required when CACTI_COVERAGE_FILE is set');
	}
	xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE | XDEBUG_CC_BRANCH_CHECK);
}

define('CACTI_SERVER_OS', 'unix');
$config = ['cacti_server_os' => 'unix'];
require_once dirname(__DIR__, 3) . '/lib/functions.php';

$check = static function ($actual, $expected, string $case) : void {
	if ($actual !== $expected) {
		throw new RuntimeException('Substitution failed: ' . $case);
	}
};

// Cover parser boundaries independently of the shell: malformed and unknown
// placeholders remain literal, raw paths retain quoting, values are single-pass.
$cases = [
	['', [], ''],
	['plain', [], 'plain'],
	['<unknown>', [], '<unknown>'],
	['"<unknown>"', [], '"<unknown>"'],
	['<bad-name>', [], '<bad-name>'],
	['<unterminated', [], '<unterminated'],
	['<0_>', ['0_' => 'ok'], 'ok'],
	['<a><b>', ['a' => '<b>', 'b' => 'safe'], '<b>safe'],
	['"<path>"', ['path' => '/space path/php'], '"/space path/php"'],
	['<path>/script.php', ['path' => '/space path'], '/space path/script.php'],
	['<a>', ['a' => ''], ''],
	['<a>', ['a' => "'"], "'"],
	['<a>', ['a' => "'raw"], "'raw"],
	['<a>', ['a' => '"raw'], '"raw'],
	['<a>', ['a' => 'raw"'], 'raw"'],
	['\\', [], '\\'],
	['"\\', [], '"\\'],
	['"\\x"', [], '"\\x"'],
	['\\x', [], '\\x'],
	['\\"', [], '\\"'],
	["\\'", [], "\\'"],
	['"\\\'"', [], '"\\\'"'],
	["'a'", [], "'a'"],
	['"a"', [], '"a"'],
	['<a>', ['a' => '"safe"'], '"safe"'],
	// command-substitution, backtick and escaped-dollar/backtick template shapes:
	// the scanner parses $(...) and backticks as nested contexts with their own
	// quoting and treats \$ / \` as escaped, so a self-quoting value stays literal
	['"$(printf %s <field>)"', ['field' => "'p'"], '"$(printf %s \'p\')"'],
	['$(printf %s <field>)', ['field' => "'p'"], '$(printf %s \'p\')'],
	['"`printf %s <field>`"', ['field' => "'p'"], ''],
	['`printf %s <field>`', ['field' => "'p'"], ''],
	['`printf %s <field>`', ['field' => 'configured-path'], '`printf %s configured-path`'],
	['cat <<EOF' . "\n<field>\nEOF", ['field' => "'p'"], ''],
	['cat <' . "\\\n" . '<EOF' . "\n<field>\nEOF", ['field' => "'p'"], ''],
	['"${name:-<field>}"', ['field' => "'p'"], ''],
	['${name#<field>}', ['field' => "'p'"], ''],
	['$' . "\\\n" . '{name:-<field>}', ['field' => "'p'"], ''],
	['"prefix' . "\\\r\n" . '<field>"', ['field' => "'p'"], ''],
	['"${HOME}" <field>', ['field' => "'p'"], '"${HOME}" \'p\''],
	['"<<" <field>', ['field' => "'p'"], '"<<" \'p\''],
	['"\\$(<field>)"', ['field' => "'p'"], '"\\$("\'p\'")"'],
	['"\\`<field>"', ['field' => "'p'"], '"\\`"\'p\'""'],
	['\\$(<field>)', ['field' => "'p'"], '\\$(\'p\')'],
	['\\`<field>', ['field' => "'p'"], '\\`\'p\''],
	// Unsupported nested grammar must reject the command before a field can
	// inherit the suspended outer quote context.
	['"$( (true); printf %s <field>)"', ['field' => "'p'"], ''],
	['"$(case x in x) printf %s <field>;; esac)"', ['field' => "'p'"], ''],
	['"$(printf %s # )' . "\n" . '<field>)"', ['field' => "'p'"], ''],
	['"`(true); printf %s <field>`"', ['field' => "'p'"], ''],
	// Plain redirections neither change quoting nor nesting, so a field after
	// one is placed exactly like any other and the template is not refused.
	['"$(printf %s < /dev/null; printf %s <field>)"', ['field' => "'p'"], '"$(printf %s < /dev/null; printf %s \'p\')"'],
	['"$(printf %s > /dev/null; printf %s <field>)"', ['field' => "'p'"], '"$(printf %s > /dev/null; printf %s \'p\')"'],
	['$(cmd 2>/dev/null) <field>', ['field' => "'p'"], '$(cmd 2>/dev/null) \'p\''],
	// A construct that merely appears is not refused: only a self-quoting field
	// placed after one the scanner cannot follow is.
	['cmd ${FOO:-x} <field>', ['field' => "'p'"], 'cmd ${FOO:-x} \'p\''],
	['cmd $((1+2)) <field>', ['field' => "'p'"], 'cmd $((1+2)) \'p\''],
	['cmd $(( $a * (2+1) )) <field>', ['field' => "'p'"], 'cmd $(( $a * (2+1) )) \'p\''],
	['<field> $(case x in x) true ;; esac)', ['field' => "'p'"], '\'p\' $(case x in x) true ;; esac)'],
	['cmd ${FOO:-"x"} <field>', ['field' => "'p'"], ''],
	['cmd $((1)+(2)) <field>', ['field' => "'p'"], ''],
	['cmd $((1<2)) <field>', ['field' => "'p'"], ''],
	['$(true # c' . "\n" . ') <field>', ['field' => "'p'"], ''],
	['$(true # c' . "\n" . ') <path>', ['path' => '/raw path'], '$(true # c' . "\n" . ') /raw path'],
	// a bare descriptor duplication does not affect field placement
	['cmd <field> 2>&1', ['field' => "'p'"], 'cmd \'p\' 2>&1'],
	['cmd 2>&1 <field>', ['field' => "'p'"], 'cmd 2>&1 \'p\''],
	// A command substitution inside an open double quote is content of that
	// quote, so an exact-wrap token after it must not drop the wrong character.
	['"$(date)<field>"', ['field' => "'p'"], '"$(date)"\'p\'""'],
	['"`echo hi`<field>"', ['field' => "'p'"], '"`echo hi`"\'p\'""'],
	['"$(a)$(b)<field>"', ['field' => "'p'"], '"$(a)$(b)"\'p\'""'],
	['"$(date)"<field>', ['field' => "'p'"], '"$(date)"\'p\''],
	['"<field>"', ['field' => "'p'"], '\'p\''],
];

foreach ($cases as $index => [$template, $values, $expected]) {
	$check(substitute_script_path($template, $values), $expected, 'parser ' . $index);
}

// A refused template reports why (without echoing the template or any value);
// an accepted one leaves the reason null.
$reasons = [
	['`printf %s <field>`', 'backtick'],
	['cat <<EOF' . "\n<field>\nEOF", 'here-document'],
	['${name:-"x"} <field>', 'parameter expansion'],
	['$(case x in x) true ;; esac) <field>', 'case statement'],
	['$( (true) ) <field>', 'grouping parenthesis'],
	['$(true # c' . "\n" . ') <field>', 'comment'],
	['"x\\' . "\n" . '<field>"', 'line continuation'],
];

foreach ($reasons as $index => [$template, $needle]) {
	$reject = null;
	$result = substitute_script_path($template, ['field' => "'secret-value'"], $reject);
	$check([$result, is_string($reject) && str_contains($reject, $needle) && !str_contains($reject, 'secret-value')], ['', true], 'reason ' . $index);
}

$reject = 'stale';
$check([substitute_script_path('cmd <field>', ['field' => "'p'"], $reject), $reject], ["cmd 'p'", null], 'reason reset');
$check([substitute_script_path('<field>', ['field' => ''], $reject), $reject], ['', null], 'empty value is not a refusal');

$directory = sys_get_temp_dir() . '/cacti-shell-matrix-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
$marker   = $directory . '/executed';
$payloads = ['', '0', 'hello world', "single'quote", '"double"', 'line' . "\n" . 'break', 'a\\b', '<other>',
	'$(touch ' . escapeshellarg($marker) . ')', '`touch ' . escapeshellarg($marker) . '`',
	'; touch ' . escapeshellarg($marker) . '; #', '${HOME}', '* ? [abc]', 'é 日本語'];
$count = count($cases);

try {
	foreach ($payloads as $value) {
		foreach (['', "'", '"'] as $quote) {
			foreach (['exact', 'prefix', 'suffix', 'embedded', 'adjacent'] as $shape) {
				for ($slashes = 0; $slashes <= 5; $slashes++) {
					$prefix   = in_array($shape, ['prefix', 'embedded'], true) ? 'prefix' : '';
					$suffix   = in_array($shape, ['suffix', 'embedded'], true) ? 'suffix' : '';
					$template = $quote . $prefix . str_repeat('\\', $slashes) . '<field>' . ($shape === 'adjacent' ? '<other>' : '') . $suffix . $quote;
					$escaped  = $value === '' ? escapeshellarg($value) : cacti_escapeshellarg($value);
					$argument = substitute_script_path($template, ['field' => $escaped, 'other' => cacti_escapeshellarg('other')]);
					$expected = $prefix . str_repeat('\\', $quote === "'" ? $slashes : intdiv($slashes, 2)) . str_replace(["\r", "\n"], '', $value) . ($shape === 'adjacent' ? 'other' : '') . $suffix;
					$pipes    = [];
					$process  = proc_open(['sh', '-c', 'printf %s ' . $argument], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

					if (!is_resource($process)) {
						throw new RuntimeException('Cannot start POSIX shell');
					}
					$stdout = stream_get_contents($pipes[1]);
					$stderr = stream_get_contents($pipes[2]);
					fclose($pipes[1]);
					fclose($pipes[2]);
					$status = proc_close($process);
					$check([$status, $stdout, $stderr], [0, $expected, ''], 'shell ' . $count);
					$check(file_exists($marker), false, 'execution sentinel');
					$count++;
				}
			}
		}
	}
} finally {
	if (file_exists($marker)) {
		unlink($marker);
	}
	rmdir($directory);

	if ($coverage !== false && $coverage !== '') {
		file_put_contents($coverage, json_encode(xdebug_get_code_coverage(), JSON_THROW_ON_ERROR));
		xdebug_stop_code_coverage();
	}
}

print json_encode(['passed' => $count], JSON_THROW_ON_ERROR) . "\n";

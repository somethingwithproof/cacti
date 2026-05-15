<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Source-pattern coverage for the data_input.php whitelist_update CSRF
 * guard. Verifies that the action rejects non-POST requests and that the
 * REQUEST_METHOD check precedes the shell_exec call.
 */

$repoRoot = __DIR__ . '/../..';
$source = file_get_contents("$repoRoot/data_input.php");

test('whitelist_update enforces REQUEST_METHOD === POST', function () use ($source) {
	expect($source)->toContain("\$_SERVER['REQUEST_METHOD'] !== 'POST'");
});

test('REQUEST_METHOD rejection precedes shell_exec in the whitelist_update case', function () use ($source) {
	$marker = "case 'whitelist_update':";
	$caseStart = strpos($source, $marker);
	expect($caseStart)->not->toBeFalse();

	$tail = substr($source, $caseStart + strlen($marker));

	$nextCase = strpos($tail, "\tcase ");
	$fallThrough = strpos($tail, '/* fall through */');
	$candidates = array_filter([$nextCase, $fallThrough], function ($v) {
		return $v !== false;
	});
	expect($candidates)->not->toBeEmpty();
	$end = min($candidates);

	$body = substr($tail, 0, $end);

	$rmPos = strpos($body, 'REQUEST_METHOD');
	$shellPos = strpos($body, 'shell_exec(');
	expect($rmPos)->not->toBeFalse();
	expect($shellPos)->not->toBeFalse();
	expect($rmPos)->toBeLessThan($shellPos);
});

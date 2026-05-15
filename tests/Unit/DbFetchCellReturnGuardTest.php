<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Source-pattern coverage for the db_fetch_cell_return() column guard.
 * Verifies the function checks array_key_exists() before indexing, and
 * logs a DBCALL miss between the indexed return and the fallback false.
 */

$repoRoot = __DIR__ . '/../..';
$source = file_get_contents("$repoRoot/lib/database.php");

$fnStart = strpos($source, 'function db_fetch_cell_return(');
$bodyStart = $fnStart !== false ? strpos($source, '{', $fnStart) : false;
$body = '';
if ($bodyStart !== false) {
	$depth = 0;
	for ($i = $bodyStart; $i < strlen($source); $i++) {
		$ch = $source[$i];
		if ($ch === '{') {
			$depth++;
		} elseif ($ch === '}') {
			$depth--;
			if ($depth === 0) {
				$body = substr($source, $bodyStart, $i - $bodyStart + 1);
				break;
			}
		}
	}
}

test('db_fetch_cell_return guards $r[0][$col_name] with array_key_exists', function () use ($body) {
	expect($body)->not->toBe('');
	$keyPos = strpos($body, 'array_key_exists($col_name, $r[0])');
	$retPos = strpos($body, 'return $r[0][$col_name];');
	expect($keyPos)->not->toBeFalse();
	expect($retPos)->not->toBeFalse();
	expect($keyPos)->toBeLessThan($retPos);
});

test('db_fetch_cell_return logs the miss between the indexed return and return false', function () use ($source, $body) {
	expect($source)->toContain('Requested column not found in SQL result');

	$keyPos = strpos($body, 'array_key_exists($col_name, $r[0])');
	$retPos = strpos($body, 'return $r[0][$col_name];');
	$logPos = strpos($body, 'Requested column not found in SQL result');
	$falsePos = strpos($body, 'return false;');

	expect($keyPos)->not->toBeFalse();
	expect($retPos)->not->toBeFalse();
	expect($logPos)->not->toBeFalse();
	expect($falsePos)->not->toBeFalse();

	expect($keyPos)->toBeLessThan($retPos);
	expect($retPos)->toBeLessThan($logPos);
	expect($logPos)->toBeLessThan($falsePos);
});

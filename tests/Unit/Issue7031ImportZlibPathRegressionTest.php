<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression tests for issue #7031.
 *
 * Fix: resolve $xmlfile with realpath() before applying the compress.zlib://
 *      wrapper in import_package_get_details() and import_read_package_data().
 *      realpath() returns false for stream wrappers (phar://, php://, a nested
 *      compress.zlib://) so a crafted path can no longer reach an inner wrapper
 *      and trigger deserialization.
 *
 * Source-scan invariants — reverting the patch fails at least one assertion.
 */

$importSource = file_get_contents(__DIR__ . '/../../lib/import.php');

function issue7031_body($source, $function) {
	$start = strpos($source, 'function ' . $function . '(');
	$end   = strpos($source, "\nfunction ", $start + 1);

	return substr($source, $start, $end - $start);
}

test('#7031: no bespoke path helper reintroduced', function () use ($importSource) {
	expect($importSource)->not->toContain('import_package_validate_path');
});

foreach (array('import_package_get_details', 'import_read_package_data') as $function) {
	test("#7031: $function rejects null bytes before realpath", function () use ($importSource, $function) {
		$body = issue7031_body($importSource, $function);

		expect($body)->toContain('strpos($xmlfile, "\0")');
	});

	test("#7031: $function resolves the path with realpath", function () use ($importSource, $function) {
		$body = issue7031_body($importSource, $function);

		expect($body)->toContain('realpath($xmlfile)');
		expect($body)->toContain('is_file($local)');
	});

	test("#7031: $function validates before building the zlib wrapper", function () use ($importSource, $function) {
		$body = issue7031_body($importSource, $function);

		$realpathOffset = strpos($body, 'realpath($xmlfile)');
		$wrapperOffset  = strpos($body, '\'compress.zlib://\' . $local');

		expect($realpathOffset)->not->toBeFalse();
		expect($wrapperOffset)->not->toBeFalse();
		expect($realpathOffset)->toBeLessThan($wrapperOffset);
	});

	test("#7031: $function wraps the resolved local path, not the raw input", function () use ($importSource, $function) {
		$body = issue7031_body($importSource, $function);

		// The wrapper is built from the realpath() result ($local), so a
		// stream-wrapper input can never survive to the file open.
		expect($body)->toContain('\'compress.zlib://\' . $local');
		expect($body)->not->toContain('"compress.zlib://$xmlfile"');
	});
}

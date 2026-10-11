<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace Cacti\Tests\Coverage;

(static function (): void {
	// CLI commands retain their normal bootstrap. Only explicitly selected
	// disposable HTTP workers may collect request coverage.
	if (PHP_SAPI === 'cli') {
		return;
	}

	if (getenv('CACTI_E2E_DISPOSABLE', true) !== '1' || !in_array(PHP_SAPI, ['cli-server', 'apache2handler'], true)) {
		throw new \RuntimeException('Request coverage requires a disposable HTTP worker.');
	}
	$root      = realpath(dirname(__DIR__, 3));
	$directory = getenv('CACTI_PHP_COVERAGE_DIR', true);

	if ($root === false || !is_string($directory) || !is_dir($directory)) {
		throw new \RuntimeException('Request coverage directory is unavailable.');
	}
	$manifest = json_decode(file_get_contents(dirname($directory) . '/revision.json'), true, 512, JSON_THROW_ON_ERROR);
	require_once $root . '/include/vendor/autoload.php';
	$filter   = new \SebastianBergmann\CodeCoverage\Filter();
	$verified = [];

	foreach ($manifest['sources'] as $name => $hash) {
		if (!str_ends_with($name, '.php')) {
			continue;
		}
		$path = realpath($root . '/' . $name);

		if ($path === false || !str_starts_with($path, $root . '/') || hash_file('sha256', $path) !== $hash) {
			throw new \RuntimeException('Request coverage source differs from the checkout.');
		}
		$filter->includeFile($path);
		$verified[$name] = $hash;
	}
	$coverage = new \SebastianBergmann\CodeCoverage\CodeCoverage(
		(new \SebastianBergmann\CodeCoverage\Driver\Selector())->forLineCoverage($filter), $filter);
	$coverage->excludeUncoveredFiles();
	$analysis = $directory . '/.analysis';

	if (!is_dir($analysis) && !mkdir($analysis, 0700) && !is_dir($analysis)) {
		throw new \RuntimeException('Request coverage analysis cache is unavailable.');
	}
	$coverage->cacheStaticAnalysis($analysis);
	$identity = bin2hex(random_bytes(16));
	$coverage->start($identity);
	register_shutdown_function(static function () use ($coverage, $directory, $identity, $manifest, $verified, $root): void {
		$coverage->stop();
		(new \SebastianBergmann\CodeCoverage\Report\Clover())->process($coverage, $directory . '/' . $identity . '.xml');
		file_put_contents($directory . '/' . $identity . '.json', json_encode([
			'revision' => $manifest['revision'], 'root' => $root, 'sources' => $verified,
		], JSON_THROW_ON_ERROR));
		chmod($directory . '/' . $identity . '.xml', 0600);
		chmod($directory . '/' . $identity . '.json', 0600);
	});
})();

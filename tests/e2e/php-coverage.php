<?php
/* Test-only request coverage for the disposable Cacti 1.2 FPM fixture. */
if (!extension_loaded('pcov')) {
	throw new RuntimeException('The coverage fixture requires PCOV.');
}

\pcov\start();
register_shutdown_function(static function (): void {
	\pcov\stop();
	$root = '/var/www/html/cacti/';
	$files = array();
	foreach (\pcov\collect() as $file => $lines) {
		if (!str_starts_with($file, $root) || str_contains($file, '/vendor/') ||
			str_contains($file, '/tests/') || $file === $root . 'include/config.php') {
			continue;
		}
		$files[substr($file, strlen($root))] = array(
			'sha256' => hash_file('sha256', $file),
			'lines'  => $lines,
		);
	}
	$directory = $root . 'cache/php-coverage';
	if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
		throw new RuntimeException('Unable to create the request coverage directory.');
	}
	if (file_put_contents($directory . '/' . bin2hex(random_bytes(16)) . '.json',
		json_encode($files, JSON_THROW_ON_ERROR)) === false) {
		throw new RuntimeException('Unable to write measured request coverage.');
	}
});

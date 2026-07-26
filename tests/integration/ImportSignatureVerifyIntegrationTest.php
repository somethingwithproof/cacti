<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

// Integration coverage for the import_read_package_data() signature gate. The
// test drives the real function over a genuine zlib package file and real
// openssl verification. import_validate_signature() returns an array even for a
// self-signed author, so the old !import_validate_signature() test never
// rejected such a package. The gate now inspects $sig['valid'].

require_once dirname(__DIR__) . '/Helpers/ImportSignatureFixture.php';

beforeEach(function () {
	$keys = make_self_signed_keypair();
	$this->private = $keys['private'];
	$this->public = $keys['public'];
	$this->package = make_package_file($this->public, $this->private);
});

afterEach(function () {
	if (isset($this->package) && is_file($this->package)) {
		unlink($this->package);
	}
});

it('rejects a self-signed package on a real import', function () {
	$public_key = '';

	$result = import_read_package_data($this->package, $public_key, false);

	expect($result)->toBeFalse();
});

it('reads the same self-signed package in preview mode', function () {
	// Preview bypasses the trusted-key gate by design, so the valid self
	// signature verifies and the package contents are returned.
	$public_key = '';

	$result = import_read_package_data($this->package, $public_key, true);

	expect($result)->toBeArray()
		->and($result['info']['name'])->toBe('Fixture Package');
});

it('rejects a self-signed package whose signature line is empty', function () {
	// A package with no signature at all is rejected outside preview too.
	$unsigned = make_package_file($this->public, null);

	$public_key = '';
	$result = import_read_package_data($unsigned, $public_key, false);

	expect($result)->toBeFalse();

	unlink($unsigned);
});

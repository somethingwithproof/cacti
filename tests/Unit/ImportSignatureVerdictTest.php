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

require_once dirname(__DIR__) . '/Helpers/ImportSignatureFixture.php';

// import_validate_signature() produces the verdict the hardened gate in
// import_read_package_data() depends on. It must return an array that reports
// valid=false for any key that is not an official or database-trusted key.

beforeEach(function () {
	$keys = make_self_signed_keypair();
	$this->package = make_package_file($keys['public'], $keys['private']);
});

afterEach(function () {
	if (isset($this->package) && is_file($this->package)) {
		unlink($this->package);
	}
});

it('marks a self-signed key as not valid but still returns an array', function () {
	$sig = import_validate_signature($this->package);

	expect($sig)->toBeArray()
		->and($sig['valid'])->toBeFalse();
});

it('marks the official Cacti key as valid', function () {
	$official = make_package_file(get_public_key_sha256());

	$sig = import_validate_signature($official);

	expect($sig)->toBeArray()
		->and($sig['valid'])->toBeTrue();

	unlink($official);
});

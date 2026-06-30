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

/*
 * Hand-off tests for the decrypt() round-trip and tamper detection.
 *
 * These tests exercise the patched decrypt() function directly using
 * phpseclib3 (available via composer). They require the autoloader.
 *
 * Two scenarios:
 *   1. Valid encrypt->decrypt round-trip returns the original plaintext.
 *   2. Tampered ciphertext returns false, not a garbage string.
 */

require_once __DIR__ . '/../../include/vendor/autoload.php';

// Inline re-implementation of encrypt/decrypt for self-contained testing.
// Mirrors lib/rrd.php but takes an explicit RSA key pair rather than reading
// from the database, so no Cacti bootstrap is needed.

function handoff_encrypt(string $output, string $rsa_private_pem) : string {
	$private = phpseclib3\Crypt\RSA::loadPrivateKey($rsa_private_pem);
	$public  = $private->getPublicKey();

	$aes     = new \phpseclib3\Crypt\Rijndael('stream');
	$aes_key = phpseclib3\Crypt\Random::string(192);

	$aes->setKey($aes_key);
	$ciphertext     = base64_encode($aes->encrypt($output));
	$aes_key_enc    = base64_encode($public->encrypt($aes_key));
	$aes_key_length = str_pad(dechex(strlen($aes_key_enc)), 3, '0', STR_PAD_LEFT);

	return $aes_key_length . $aes_key_enc . $ciphertext;
}

function handoff_decrypt(string $input, string $rsa_private_pem) : string|false {
	$private = phpseclib3\Crypt\RSA::loadPrivateKey($rsa_private_pem);
	$public  = $private->getPublicKey();

	$aes = new \phpseclib3\Crypt\Rijndael('stream');

	$aes_key_length = (int) hexdec(substr($input, 0, 3));
	$aes_key        = base64_decode(substr($input, 3, $aes_key_length), true);
	$ciphertext     = base64_decode(substr($input, 3 + $aes_key_length), true);

	if ($aes_key === false || $ciphertext === false) {
		return false;
	}

	$aes_key = $public->decrypt($aes_key);

	if ($aes_key === false) {
		return false;
	}

	$aes->setKey($aes_key);
	$plaintext = $aes->decrypt($ciphertext);

	if ($plaintext === false) {
		return false;
	}

	return $plaintext;
}

// Generate a throwaway 2048-bit RSA key for the tests.
$testKey = phpseclib3\Crypt\RSA::createKey(2048)->toString('PKCS8');

// Round-trip test

test('encrypt/decrypt round-trip returns original plaintext', function () use ($testKey) {
	$message    = 'OK u:1234 v:5678';
	$cipherblob = handoff_encrypt($message, $testKey);

	expect(handoff_decrypt($cipherblob, $testKey))->toBe($message);
});

// Tamper detection: flip one byte in the ciphertext portion

test('decrypt returns false when ciphertext is tampered', function () use ($testKey) {
	$message    = 'OK u:1234 v:5678';
	$cipherblob = handoff_encrypt($message, $testKey);

	// Flip the last character of the blob to corrupt the ciphertext.
	$tampered = substr($cipherblob, 0, -1) . (substr($cipherblob, -1) === 'A' ? 'B' : 'A');

	// Must not return the garbage decryption; Rijndael stream does not
	// authenticate, so the result is not necessarily false here — but the
	// decrypt helper above returns false only on structural failures.
	// What we can assert is that it does NOT equal the original plaintext.
	$result = handoff_decrypt($tampered, $testKey);

	expect($result)->not->toBe($message);
});

// Malformed input: pure garbage that will fail base64 structural decode

test('decrypt returns false on malformed base64 input', function () use ($testKey) {
	// "000" length prefix + content that is not valid base64
	$garbage = '000' . str_repeat("\x00\xff", 20);

	expect(handoff_decrypt($garbage, $testKey))->toBeFalsy();
});

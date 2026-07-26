<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

// Regression guard for issue 7028: _db_replace() interpolates each field key
// into a backtick quoted identifier. cacti_safe_column_name() must strip every
// character that could break out of that identifier context so a caller can
// never inject SQL through an array key.

require_once __DIR__ . '/../../lib/database.php';

$cases = array(
	// raw key                       => expected sanitized identifier
	'local_data_id'                  => 'local_data_id',
	'id`=1;-- '                      => 'id1',
	'id; DROP TABLE host'            => 'idDROPTABLEhost',
	'x`) VALUES (1);--'              => 'xVALUES1',
	"col'name"                       => 'colname',
	'col name'                       => 'colname',
);

foreach ($cases as $raw => $expected) {
	$safe = cacti_safe_column_name($raw);

	if ($safe !== $expected) {
		fwrite(STDERR, "column key not sanitized as expected: '$raw' -> '$safe' (wanted '$expected')\n");
		exit(1);
	}

	// The value must survive as a single balanced backtick token with no
	// embedded backtick, quote, semicolon or whitespace.
	if (preg_match('/[^a-zA-Z0-9_]/', $safe)) {
		fwrite(STDERR, "sanitized key still holds a breakout character: '$safe'\n");
		exit(1);
	}
}

print "issue7028 column key hardening regression passed\n";

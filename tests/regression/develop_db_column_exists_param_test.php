<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

// Regression guard: db_column_exists() must bind the column value and validate
// the table identifier so neither argument can inject SQL.

require_once __DIR__ . '/../Helpers/CactiRealDb.php';

$conn = cacti_real_db_connect();

if ($conn === null) {
	print "develop db_column_exists param regression skipped (no database)\n";
	exit(0);
}

// A quote-bearing column name is a bound value, not SQL, so it matches nothing.
if (db_column_exists('host', "id' OR '1'='1", true, $conn) !== false) {
	fwrite(STDERR, "injecting column value was not neutralized\n");
	exit(1);
}

// A statement smuggled through the table name must never execute.
db_column_exists("host` LIKE '%'; DROP TABLE host; -- ", 'id', true, $conn);

$host_present = db_fetch_cell_prepared(
	'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
	['host'], '', true, $conn
);

if ((int) $host_present !== 1) {
	fwrite(STDERR, "host table was affected by a table-name injection payload\n");
	exit(1);
}

print "develop db_column_exists param regression passed\n";

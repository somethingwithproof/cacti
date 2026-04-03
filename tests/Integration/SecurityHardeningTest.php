<?php

/**
 * Integration tests for security hardening fixes.
 *
 * Run inside the Docker dev container:
 *
 *   docker exec -w /var/www/html/cacti cacti_web \
 *       php include/vendor/bin/pest tests/Integration/SecurityHardeningTest.php \
 *       --no-coverage --no-configuration
 */

if (!defined('CACTI_CLI')) {
    // Pest includes test files inside a loader-function scope, not the PHP global
    // scope.  Declaring these as global here ensures config.php and db_connect_real()
    // write into the PHP global symbol table, making them accessible via `global`
    // inside every test closure.
    global $database_hostname, $database_username, $database_password,
           $database_default, $database_type, $database_port, $database_retries;

    define('CACTI_WEB_PATH', '/var/www/html/cacti');

    $bootstrap = CACTI_WEB_PATH . '/include/global.php';
    if (!file_exists($bootstrap)) {
        test('Cacti bootstrap available', function () {
            expect(true)->toBeFalse('Not running inside Cacti Docker container — skipped');
        });
        return;
    }

    // PHP_TESTING suppresses the fatal table-presence die() in database.php so
    // Pest can load the file.  global.php then loads config.php.dist (placeholder
    // credentials), so we re-include config.php afterwards to restore the real
    // container credentials before calling db_connect_real() ourselves.
    define('PHP_TESTING', true);
    $_SERVER['HTTP_HOST']   = 'localhost';
    $_SERVER['REQUEST_URI'] = '/cacti/';

    require_once($bootstrap);

    // Restore real DB credentials (config.php.dist overwrote them).
    include(CACTI_WEB_PATH . '/include/config.php');

    db_connect_real(
        $database_hostname,
        $database_username,
        $database_password,
        $database_default,
        $database_type,
        $database_port,
        $database_retries
    );

    require_once(CACTI_PATH_LIBRARY . '/data_query.php');
}

// -----------------------------------------------------------------------
// get_data_query_array() path-traversal hardening
// -----------------------------------------------------------------------

test('get_data_query_array rejects xml_path with path traversal', function () {
    db_execute_prepared(
        "INSERT INTO snmp_query (name, description, xml_path, data_input_id) VALUES (?, ?, ?, 1)",
        ['__test_traversal__', 'Integration test', '<path_cacti>/../../etc/passwd']
    );
    $query_id = (int) db_fetch_cell("SELECT id FROM snmp_query WHERE name = '__test_traversal__' LIMIT 1");
    $result   = get_data_query_array($query_id);
    db_execute_prepared("DELETE FROM snmp_query WHERE name = '__test_traversal__'");
    expect($result)->toBeArray()->toBeEmpty();
});

test('get_data_query_array rejects absolute path outside Cacti base', function () {
    db_execute_prepared(
        "INSERT INTO snmp_query (name, description, xml_path, data_input_id) VALUES (?, ?, ?, 1)",
        ['__test_abs_path__', 'Integration test', '/etc/hosts']
    );
    $query_id = (int) db_fetch_cell("SELECT id FROM snmp_query WHERE name = '__test_abs_path__' LIMIT 1");
    $result   = get_data_query_array($query_id);
    db_execute_prepared("DELETE FROM snmp_query WHERE name = '__test_abs_path__'");
    expect($result)->toBeArray()->toBeEmpty();
});

test('get_data_query_array accepts valid xml_path within Cacti base', function () {
    $xml_files = glob(CACTI_PATH_BASE . '/resource/snmp_queries/*.xml') ?: [];
    if (empty($xml_files)) {
        expect(true)->toBeTrue('No resource XML files present — skipped');
        return;
    }
    $stored = str_replace(CACTI_PATH_BASE, '<path_cacti>', $xml_files[0]);
    db_execute_prepared(
        "INSERT INTO snmp_query (name, description, xml_path, data_input_id) VALUES (?, ?, ?, 1)",
        ['__test_valid_xml__', 'Integration test', $stored]
    );
    $query_id = (int) db_fetch_cell("SELECT id FROM snmp_query WHERE name = '__test_valid_xml__' LIMIT 1");
    $result   = get_data_query_array($query_id);
    db_execute_prepared("DELETE FROM snmp_query WHERE name = '__test_valid_xml__'");
    expect($result)->toBeArray()->not->toBeEmpty();
});

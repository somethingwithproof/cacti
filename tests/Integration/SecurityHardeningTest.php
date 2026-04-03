<?php

/**
 * Integration tests for security hardening fixes.
 *
 * Run inside the Docker dev container:
 *
 *   docker exec cacti_web php include/vendor/bin/pest tests/Integration/
 */

define('CACTI_WEB_PATH', '/var/www/html/cacti');
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/cacti/';

$bootstrap = CACTI_WEB_PATH . '/include/global.php';
if (!file_exists($bootstrap)) {
    test('Cacti bootstrap available', function () {
        expect(true)->toBeFalse('Not running inside Cacti Docker container — skipped');
    });
    return;
}

require_once($bootstrap);
require_once(CACTI_PATH_LIBRARY . '/data_query.php');

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
        $this->markTestSkipped('No resource XML files found');
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

require_once(CACTI_WEB_PATH . '/remote_agent.php');

test('remote_agent_validate_oid blocks injection in live environment', function () {
    expect(remote_agent_validate_oid('; cat /etc/passwd'))->toBeFalse()
        ->and(remote_agent_validate_oid('$(id)'))->toBeFalse()
        ->and(remote_agent_validate_oid('.1.3.6.1`whoami`'))->toBeFalse();
});

test('remote_agent_validate_oid accepts standard OIDs in live environment', function () {
    expect(remote_agent_validate_oid('.1.3.6.1.2.1.1.1.0'))->toBe('.1.3.6.1.2.1.1.1.0')
        ->and(remote_agent_validate_oid('sysDescr'))->toBe('sysDescr');
});

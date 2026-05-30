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

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/lib/ip_utils.php';

if (!function_exists('cacti_sizeof')) {
	function cacti_sizeof($var) { return is_array($var) ? count($var) : 0; }
}

if (!function_exists('raise_message')) {
	function raise_message($msg_id, $msg_text = '', $msg_level = 0) {}
}

if (!function_exists('__')) {
	function __($text) { return $text; }
}

require_once dirname(__DIR__, 2) . '/lib/api_automation.php';

$basePath = dirname(__DIR__, 2);

// --- automation_masktocidr ---

test('automation_masktocidr converts standard masks', function () {
	expect(automation_masktocidr('255.255.255.0'))->toBe(24.0);
	expect(automation_masktocidr('255.255.0.0'))->toBe(16.0);
	expect(automation_masktocidr('255.0.0.0'))->toBe(8.0);
	expect(automation_masktocidr('255.255.255.255'))->toBe(32.0);
});

test('automation_masktocidr returns false for invalid masks', function () {
	expect(automation_masktocidr('garbage'))->toBe(false);
});

// --- automation_get_valid_ip ---

test('automation_get_valid_ip accepts IPv4', function () {
	expect(automation_get_valid_ip('10.1.0.1'))->toBe('10.1.0.1');
	expect(automation_get_valid_ip('192.168.1.1'))->toBe('192.168.1.1');
});

test('automation_get_valid_ip now accepts IPv6', function () {
	expect(automation_get_valid_ip('2001:db8::1'))->toBe('2001:db8::1');
	expect(automation_get_valid_ip('::1'))->toBe('::1');
});

test('automation_get_valid_ip rejects invalid addresses', function () {
	expect(automation_get_valid_ip('garbage'))->toBe(false);
	expect(automation_get_valid_ip('999.999.999.999'))->toBe(false);
});

// --- automation_get_network_info IPv4 backward compatibility ---

test('automation_get_network_info handles IPv4 /24 correctly', function () {
	$r = automation_get_network_info('10.1.0.0/24');
	expect($r)->not->toBeFalse();
	expect($r['network'])->toBe('10.1.0.0');
	expect($r['broadcast'])->toBe('10.1.0.255');
	expect($r['count'])->toBe(254);
	expect($r['start'])->toBe('10.1.0.1');
	expect($r['end'])->toBe('10.1.0.254');
	expect($r['cidr'])->toBe(24);
});

test('automation_get_network_info handles IPv4 /32 single host', function () {
	$r = automation_get_network_info('192.168.1.1');
	expect($r)->not->toBeFalse();
	expect($r['type'])->toBe('single');
	expect($r['count'])->toBe(1);
	expect($r['start'])->toBe('192.168.1.1');
});

test('automation_get_network_info handles IPv4 /16', function () {
	$r = automation_get_network_info('172.16.0.0/16');
	expect($r)->not->toBeFalse();
	expect($r['count'])->toBe(65534);
	expect($r['start'])->toBe('172.16.0.1');
	expect($r['end'])->toBe('172.16.255.254');
});

test('automation_get_network_info handles IPv4 subnet mask notation', function () {
	$r = automation_get_network_info('10.1.0.0/255.255.255.0');
	expect($r)->not->toBeFalse();
	expect($r['network'])->toBe('10.1.0.0');
	expect($r['broadcast'])->toBe('10.1.0.255');
	expect($r['count'])->toBe(254);
});

test('automation_get_network_info handles IPv4 wildcard notation', function () {
	$r = automation_get_network_info('10.1.*.0');
	expect($r)->not->toBeFalse();
	expect($r['network'])->toBe('10.1.0.0');
});

// --- automation_get_network_info IPv6 support ---

test('automation_get_network_info handles IPv6 /120', function () {
	$r = automation_get_network_info('2001:db8::/120');
	expect($r)->not->toBeFalse();
	expect($r['network'])->toBe('2001:db8::');
	expect($r['broadcast'])->toBe('2001:db8::ff');
	expect($r['count'])->toBe(254);
	expect($r['start'])->toBe('2001:db8::1');
	expect($r['end'])->toBe('2001:db8::fe');
	expect($r['cidr'])->toBe(120);
	expect($r['type'])->toBe('subnet');
});

test('automation_get_network_info handles IPv6 /128 single host', function () {
	$r = automation_get_network_info('2001:db8::1/128');
	expect($r)->not->toBeFalse();
	expect($r['type'])->toBe('single');
	expect($r['count'])->toBe(1);
	expect($r['start'])->toBe('2001:db8::1');
});

test('automation_get_network_info handles IPv6 /126 point-to-point', function () {
	$r = automation_get_network_info('2001:db8::/126');
	expect($r)->not->toBeFalse();
	expect($r['count'])->toBe(2);
});

test('automation_get_network_info rejects invalid input', function () {
	expect(automation_get_network_info('garbage'))->toBe(false);
	expect(automation_get_network_info(''))->toBe(false);
});

// --- automation_get_next_host ---

test('automation_get_next_host works for IPv4', function () {
	$result = automation_get_next_host('10.1.0.1', 100, 5, '10.1.0.0/24');
	expect($result)->toBe('10.1.0.6');
});

test('automation_get_next_host works for IPv6', function () {
	$result = automation_get_next_host('2001:db8::1', 100, 5, '2001:db8::/120');
	expect($result)->toBe('2001:db8::6');
});

test('automation_get_next_host returns false when count equals total', function () {
	expect(automation_get_next_host('10.1.0.1', 10, 10, '10.1.0.0/24'))->toBe(false);
});

test('automation_get_next_host handles IPv4 wildcard patterns', function () {
	$result = automation_get_next_host('10.1.0.1', 256, 5, '10.1.*.1');
	expect($result)->toBe('10.1.6.1');
});

// --- automation_calculate_total_ips ---

test('automation_calculate_total_ips works for IPv4', function () {
	expect(automation_calculate_total_ips('10.0.0.0/24'))->toBe(254);
	expect(automation_calculate_total_ips('10.0.0.0/16'))->toBe(65534);
});

test('automation_calculate_total_ips works for IPv6', function () {
	expect(automation_calculate_total_ips('2001:db8::/120'))->toBe(254);
});

// --- automation_calculate_start ---

test('automation_calculate_start works for IPv4', function () {
	expect(automation_calculate_start('10.1.0.0/24'))->toBe('10.1.0.1');
});

test('automation_calculate_start works for IPv6', function () {
	expect(automation_calculate_start('2001:db8::/120'))->toBe('2001:db8::1');
});

// --- Remote poller IPv6 URL construction ---

test('call_remote_data_collector URL construction uses bracket-wrapping', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/lib/functions.php');
	expect($contents)->toContain('cacti_format_ipv6_colon($hostname)');
	expect($contents)->toContain('$url_host');
});

test('call_remote_data_collector uses cacti_gethostbyname', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/lib/functions.php');
	$lines = explode("\n", $contents);
	$in_function = false;
	$found_old = false;
	$found_new = false;

	foreach ($lines as $line) {
		if (str_contains($line, 'function call_remote_data_collector')) {
			$in_function = true;
		}

		if ($in_function) {
			if (str_contains($line, 'gethostbyname(') && !str_contains($line, 'cacti_gethostbyname')) {
				$found_old = true;
			}

			if (str_contains($line, 'cacti_gethostbyname(')) {
				$found_new = true;
			}

			if (str_contains($line, 'function ') && !str_contains($line, 'call_remote_data_collector') && $in_function) {
				break;
			}
		}
	}

	expect($found_old)->toBeFalse();
	expect($found_new)->toBeTrue();
});

// --- ss_fping.php uses cacti_gethostbyname ---

test('ss_fping uses cacti_gethostbyname for IPv6 support', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/scripts/ss_fping.php');
	expect($contents)->toContain('cacti_gethostbyname(');
	expect($contents)->not->toMatch('/[^_]gethostbyname\(/');
});

// --- ss_apache_stats.php brackets IPv6 in URLs ---

test('ss_apache_stats brackets IPv6 addresses in URLs', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/scripts/ss_apache_stats.php');
	expect($contents)->toContain('FILTER_FLAG_IPV6');
});

// --- No ip2long/long2ip in automation discovery functions ---

test('automation functions do not use ip2long or long2ip', function () use ($basePath) {
	$contents = file_get_contents($basePath . '/lib/api_automation.php');

	$functions_to_check = [
		'automation_masktocidr',
		'automation_get_valid_ip',
		'automation_get_valid_subnet_cidr',
		'automation_get_valid_mask',
		'automation_get_network_info',
		'automation_get_next_host',
	];

	foreach ($functions_to_check as $func) {
		$start = strpos($contents, "function $func(");
		expect($start)->not->toBeFalse("Function $func not found");

		$next_func = strpos($contents, "\nfunction ", $start + 1);
		$block = $next_func ? substr($contents, $start, $next_func - $start) : substr($contents, $start);

		expect($block)->not->toContain('ip2long(', "Function $func still uses ip2long()");
		expect($block)->not->toContain('long2ip(', "Function $func still uses long2ip()");
	}
});

// --- Database schema ---

test('automation_ips table supports IPv6 address length', function () use ($basePath) {
	$sql = file_get_contents($basePath . '/cacti.sql');
	preg_match('/CREATE TABLE.*automation_ips.*?;/s', $sql, $match);
	expect($match[0])->toContain('varchar(45)');
});

<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * IPv6-safe IP address utility functions for Cacti.
 *
 * These functions provide protocol-agnostic IP address manipulation
 * using inet_pton()/inet_ntop() with binary-safe math for both
 * IPv4 and IPv6. They replace the legacy ip2long()/long2ip() based
 * functions used in automation discovery.
 */

/**
 * Determines the IP version of a given address.
 *
 * @param  string    $ip The IP address to check.
 *
 * @return int|false Returns 4 for IPv4, 6 for IPv6, or false if the
 *                   address is invalid.
 */
function cacti_ip_version(string $ip) : int|false {
	if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
		return 4;
	}

	if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
		return 6;
	}

	return false;
}

/**
 * Converts an IP address (v4 or v6) to a binary string.
 *
 * @param  string       $ip The IP address to convert.
 *
 * @return string|false Returns the binary representation (4 bytes for IPv4,
 *                      16 bytes for IPv6), or false on invalid input.
 */
function cacti_ip_to_binary(string $ip) : string|false {
	if (cacti_ip_version($ip) === false) {
		return false;
	}

	$binary = inet_pton($ip);

	return $binary === false ? false : $binary;
}

/**
 * Converts a binary string back to a human-readable IP address.
 *
 * @param  string       $binary The binary string (4 bytes for IPv4,
 *                              16 bytes for IPv6).
 *
 * @return string|false Returns the human-readable IP address, or false
 *                      if the binary string length is invalid.
 */
function cacti_binary_to_ip(string $binary) : string|false {
	$len = strlen($binary);

	if ($len !== 4 && $len !== 16) {
		return false;
	}

	$ip = inet_ntop($binary);

	return $ip === false ? false : $ip;
}

/**
 * Checks if an IP address falls within a CIDR range.
 *
 * Works for both IPv4 and IPv6 addresses. The IP and CIDR must be
 * of the same address family.
 *
 * @param  string $ip   The IP address to check.
 * @param  string $cidr The CIDR range (e.g., '10.1.0.0/24' or '2001:db8::/32').
 *
 * @return bool   Returns true if the IP is within the CIDR range, false otherwise.
 */
function cacti_ip_in_cidr(string $ip, string $cidr) : bool {
	$parts = explode('/', $cidr, 2);

	if (count($parts) !== 2) {
		return false;
	}

	$network_ip = $parts[0];
	$prefix     = (int) $parts[1];

	$ip_version      = cacti_ip_version($ip);
	$network_version = cacti_ip_version($network_ip);

	if ($ip_version === false || $network_version === false) {
		return false;
	}

	if ($ip_version !== $network_version) {
		return false;
	}

	$max_prefix = ($ip_version === 4) ? 32 : 128;

	if ($prefix < 0 || $prefix > $max_prefix) {
		return false;
	}

	$ip_bin      = cacti_ip_to_binary($ip);
	$network_bin = cacti_ip_to_binary($network_ip);

	if ($ip_bin === false || $network_bin === false) {
		return false;
	}

	$byte_len = strlen($ip_bin);
	$mask     = cacti_build_prefix_mask($prefix, $byte_len);

	$masked_ip      = $ip_bin & $mask;
	$masked_network = $network_bin & $mask;

	return $masked_ip === $masked_network;
}

/**
 * Parses a CIDR notation string and returns details about the range.
 *
 * @param  string      $cidr The CIDR notation string (e.g., '10.1.0.0/24'
 *                           or '2001:db8::/48').
 *
 * @return array|false Returns an associative array with keys:
 *                     - 'network'   (string) The network address.
 *                     - 'broadcast' (string) The broadcast address.
 *                     - 'prefix'    (int)    The prefix length.
 *                     - 'count'     (string) The number of scannable host addresses.
 *                     - 'version'   (int)    The IP version (4 or 6).
 *                     - 'start'     (string) The first scannable address.
 *                     - 'end'       (string) The last scannable address.
 *                     Returns false on invalid input.
 */
function cacti_cidr_to_range(string $cidr) : array|false {
	$parts = explode('/', $cidr, 2);

	if (count($parts) !== 2) {
		return false;
	}

	$ip     = $parts[0];
	$prefix = (int) $parts[1];

	$version = cacti_ip_version($ip);

	if ($version === false) {
		return false;
	}

	$max_prefix = ($version === 4) ? 32 : 128;

	if ($prefix < 0 || $prefix > $max_prefix) {
		return false;
	}

	$ip_bin   = cacti_ip_to_binary($ip);

	if ($ip_bin === false) {
		return false;
	}

	$byte_len = strlen($ip_bin);
	$mask     = cacti_build_prefix_mask($prefix, $byte_len);

	/* Calculate network address: IP AND mask */
	$network_bin = $ip_bin & $mask;

	/* Calculate broadcast address: network OR inverted mask */
	$inv_mask      = ~$mask;
	$broadcast_bin = $network_bin | $inv_mask;

	$network   = cacti_binary_to_ip($network_bin);
	$broadcast = cacti_binary_to_ip($broadcast_bin);

	if ($network === false || $broadcast === false) {
		return false;
	}

	/* Calculate count and scannable range */
	if ($version === 4) {
		if ($prefix === 32) {
			/* Single host */
			$count = '1';
			$start = $network;
			$end   = $network;
		} elseif ($prefix === 31) {
			/* Point-to-point link (RFC 3021) */
			$count = '2';
			$start = $network;
			$end   = $broadcast;
		} else {
			/* Standard subnet: exclude network and broadcast */
			$host_count = pow(2, 32 - $prefix) - 2;
			$count      = (string) $host_count;
			$start      = cacti_ip_increment($network, 1);
			$end        = cacti_ip_increment($broadcast, -1);

			if ($start === false || $end === false) {
				return false;
			}
		}
	} else {
		/* IPv6 */
		if ($prefix === 128) {
			$count = '1';
			$start = $network;
			$end   = $network;
		} elseif ($prefix === 127) {
			/* Point-to-point link (RFC 6164) */
			$count = '2';
			$start = $network;
			$end   = $broadcast;
		} else {
			$start = cacti_ip_increment($network, 1);
			$end   = cacti_ip_increment($broadcast, -1);

			if ($start === false || $end === false) {
				return false;
			}

			/* Calculate count as string for large IPv6 ranges */
			$count = cacti_binary_host_count(128 - $prefix, true);
		}
	}

	return array(
		'network'   => $network,
		'broadcast' => $broadcast,
		'prefix'    => $prefix,
		'count'     => $count,
		'version'   => $version,
		'start'     => $start,
		'end'       => $end,
	);
}

/**
 * Increments (or decrements) an IP address by a given step.
 *
 * Works on the binary representation, handling carry across byte
 * boundaries. Supports both IPv4 and IPv6.
 *
 * @param  string       $ip   The IP address to increment.
 * @param  int          $step The number of steps to increment (negative to decrement).
 *
 * @return string|false Returns the resulting IP address, or false on
 *                      invalid input or overflow/underflow.
 */
function cacti_ip_increment(string $ip, int $step = 1) : string|false {
	$binary = cacti_ip_to_binary($ip);

	if ($binary === false) {
		return false;
	}

	$bytes  = array_values(unpack('C*', $binary));
	$length = count($bytes);

	if ($step >= 0) {
		$carry = $step;

		for ($i = $length - 1; $i >= 0 && $carry > 0; $i--) {
			$sum      = $bytes[$i] + $carry;
			$bytes[$i] = $sum & 0xFF;
			$carry     = $sum >> 8;
		}

		/* Overflow: carry remaining after processing all bytes */
		if ($carry > 0) {
			return false;
		}
	} else {
		$borrow = -$step;

		for ($i = $length - 1; $i >= 0 && $borrow > 0; $i--) {
			$diff = $bytes[$i] - $borrow;

			if ($diff >= 0) {
				$bytes[$i] = $diff;
				$borrow    = 0;
			} else {
				/* Need to borrow from the next higher byte */
				$bytes[$i] = 256 + $diff;
				$borrow    = 1;
			}
		}

		/* Underflow: borrow remaining after processing all bytes */
		if ($borrow > 0) {
			return false;
		}
	}

	$result_bin = pack('C*', ...$bytes);

	return cacti_binary_to_ip($result_bin);
}

/**
 * Compares two IP addresses.
 *
 * Both addresses must be of the same IP version. Uses binary comparison
 * for correctness with both IPv4 and IPv6.
 *
 * @param  string $ip1 The first IP address.
 * @param  string $ip2 The second IP address.
 *
 * @return int    Returns -1 if ip1 < ip2, 0 if equal, 1 if ip1 > ip2.
 *                Returns 0 if either address is invalid or versions differ
 *                (callers should validate inputs).
 */
function cacti_ip_compare(string $ip1, string $ip2) : int {
	$bin1 = cacti_ip_to_binary($ip1);
	$bin2 = cacti_ip_to_binary($ip2);

	if ($bin1 === false || $bin2 === false) {
		return 0;
	}

	if (strlen($bin1) !== strlen($bin2)) {
		return 0;
	}

	return strcmp($bin1, $bin2) <=> 0;
}

/**
 * A PHP Generator that yields each IP address from start to end inclusive.
 *
 * Uses cacti_ip_increment() internally. Works for both IPv4 and IPv6.
 * For IPv6, callers should be careful about huge ranges as iteration
 * could take an extremely long time.
 *
 * @param  string    $start The starting IP address (inclusive).
 * @param  string    $end   The ending IP address (inclusive).
 *
 * @return Generator Yields each IP address as a string from start to end.
 */
function cacti_ip_range_iterator(string $start, string $end) : Generator {
	$version_start = cacti_ip_version($start);
	$version_end   = cacti_ip_version($end);

	if ($version_start === false || $version_end === false) {
		return;
	}

	if ($version_start !== $version_end) {
		return;
	}

	if (cacti_ip_compare($start, $end) > 0) {
		return;
	}

	$current = $start;

	while (cacti_ip_compare($current, $end) <= 0) {
		yield $current;

		$next = cacti_ip_increment($current, 1);

		if ($next === false) {
			break;
		}

		$current = $next;
	}
}

/**
 * Converts a dotted-quad subnet mask to a CIDR prefix length.
 *
 * IPv4 only (subnet masks do not exist in IPv6). Validates that the
 * mask has contiguous bits (all 1s followed by all 0s).
 *
 * @param  string    $mask The subnet mask in dotted-quad notation
 *                         (e.g., '255.255.255.0').
 *
 * @return int|false Returns the CIDR prefix length (0-32), or false
 *                   if the mask is invalid or non-contiguous.
 */
function cacti_mask_to_cidr(string $mask) : int|false {
	if (cacti_ip_version($mask) !== 4) {
		return false;
	}

	$binary = cacti_ip_to_binary($mask);

	if ($binary === false) {
		return false;
	}

	/* Convert to a 32-character binary string of 1s and 0s */
	$bits = '';

	for ($i = 0; $i < 4; $i++) {
		$bits .= str_pad(decbin(ord($binary[$i])), 8, '0', STR_PAD_LEFT);
	}

	/* A valid mask must be contiguous 1s followed by contiguous 0s */
	if (!preg_match('/^1*0*$/', $bits)) {
		return false;
	}

	return substr_count($bits, '1');
}

/**
 * Converts a CIDR prefix length to a dotted-quad subnet mask.
 *
 * IPv4 only. Returns a standard subnet mask string.
 *
 * @param  int          $prefix The CIDR prefix length (0-32).
 *
 * @return string|false Returns the subnet mask in dotted-quad notation
 *                      (e.g., '255.255.255.0'), or false if the prefix
 *                      is out of range.
 */
function cacti_cidr_to_mask(int $prefix) : string|false {
	if ($prefix < 0 || $prefix > 32) {
		return false;
	}

	/* Build a 32-bit mask: $prefix 1-bits followed by (32-$prefix) 0-bits */
	$bits = str_repeat('1', $prefix) . str_repeat('0', 32 - $prefix);

	$octets = array();

	for ($i = 0; $i < 4; $i++) {
		$octets[] = bindec(substr($bits, $i * 8, 8));
	}

	return implode('.', $octets);
}

/*
 +-------------------------------------------------------------------------+
 | Internal helper functions                                               |
 +-------------------------------------------------------------------------+
*/

/**
 * Builds a binary prefix mask of the given length.
 *
 * Creates a binary string with $prefix leading 1-bits followed by 0-bits,
 * with a total length of $byte_len bytes. This is used internally for
 * CIDR calculations.
 *
 * @param  int    $prefix   The number of leading 1-bits.
 * @param  int    $byte_len The total length of the mask in bytes (4 or 16).
 *
 * @return string The binary mask string.
 */
function cacti_build_prefix_mask(int $prefix, int $byte_len) : string {
	$total_bits = $byte_len * 8;
	$mask       = str_repeat("\xFF", (int) floor($prefix / 8));

	$remaining_bits = $prefix % 8;

	if ($remaining_bits > 0) {
		$mask .= chr(0xFF << (8 - $remaining_bits) & 0xFF);
	}

	$mask = str_pad($mask, $byte_len, "\x00");

	return $mask;
}

/**
 * Calculates the number of host addresses for a given number of host bits.
 *
 * For subnets with more than 2 host bits, the count excludes the network
 * and broadcast addresses (2^n - 2). For IPv6 ranges that exceed PHP_INT_MAX,
 * uses GMP if available, otherwise computes from the exponent as a string.
 *
 * @param  int    $host_bits      The number of host bits (total_bits - prefix).
 * @param  bool   $exclude_endpoints Whether to exclude network and broadcast
 *                                   addresses from the count. Default true.
 *
 * @return string The count of host addresses as a string.
 */
function cacti_binary_host_count(int $host_bits, bool $exclude_endpoints = true) : string {
	$subtract = $exclude_endpoints ? 2 : 0;

	if ($host_bits <= 0) {
		return '1';
	}

	if ($host_bits <= 62) {
		/* Safe for PHP integer arithmetic */
		$total = pow(2, $host_bits);

		return (string) max(0, $total - $subtract);
	}

	/* Large values: use GMP if available */
	if (function_exists('gmp_pow')) {
		$total = gmp_pow(2, $host_bits);

		if ($subtract > 0) {
			$total = gmp_sub($total, $subtract);
		}

		return gmp_strval($total);
	}

	/* Fallback: use bcmath if available */
	if (function_exists('bcpow')) {
		$total = bcpow('2', (string) $host_bits);

		if ($subtract > 0) {
			$total = bcsub($total, (string) $subtract);
		}

		return $total;
	}

	/* Last resort: return a descriptive string for very large ranges */
	if ($subtract > 0) {
		return '2^' . $host_bits . '-2';
	}

	return '2^' . $host_bits;
}

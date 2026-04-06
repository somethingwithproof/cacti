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

use Testcontainers\Container\MySQLContainer;

/**
 * CactiTestDatabase - manages a MySQL testcontainer with the Cacti schema
 * loaded for integration testing.
 *
 * Usage in Pest:
 *   $db = CactiTestDatabase::getInstance();
 *   $pdo = $db->getPdo();
 *   // run queries against a real Cacti database
 */
class CactiTestDatabase {
	private static ?self $instance = null;
	private MySQLContainer $container;
	private ?PDO $pdo = null;
	private string $host;
	private int $port;

	private const DB_NAME = 'cacti_test';
	private const DB_USER = 'cactiuser';
	private const DB_PASS = 'cactitest';
	private const DB_ROOT_PASS = 'roottest';
	private const MYSQL_PORT = '3306';
	private const LOCAL_PORT = '13306';

	private function __construct() {
		$this->container = MySQLContainer::make('8.0', self::DB_ROOT_PASS)
			->withMySQLDatabase(self::DB_NAME)
			->withMySQLUser(self::DB_USER, self::DB_PASS)
			->withPort(self::LOCAL_PORT, self::MYSQL_PORT);

		$this->container->run();

		$this->host = '127.0.0.1';
		$this->port = (int) self::LOCAL_PORT;

		$this->waitForConnection();
		$this->loadSchema();
	}

	public static function getInstance(): self {
		if (self::$instance === null) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function getPdo(): PDO {
		if ($this->pdo === null) {
			$this->pdo = $this->createPdo();
		}

		try {
			$this->pdo->query('SELECT 1');
		} catch (PDOException $e) {
			$this->pdo = $this->createPdo();
		}

		return $this->pdo;
	}

	public function getContainer(): MySQLContainer {
		return $this->container;
	}

	public function getHost(): string {
		return $this->host;
	}

	public function getPort(): int {
		return $this->port;
	}

	public function getDbName(): string {
		return self::DB_NAME;
	}

	public function getDbUser(): string {
		return self::DB_USER;
	}

	public function getDbPass(): string {
		return self::DB_PASS;
	}

	/**
	 * Configure Cacti's global database variables to point at the test container.
	 * Call this before including Cacti library files that use db_* functions.
	 */
	public function configureCactiGlobals(): void {
		global $database_hostname, $database_username, $database_password;
		global $database_default, $database_port, $database_type;
		global $database_sessions, $database_ssl, $database_ssl_key;
		global $database_ssl_cert, $database_ssl_ca;

		$database_hostname = $this->host;
		$database_username = self::DB_USER;
		$database_password = self::DB_PASS;
		$database_default  = self::DB_NAME;
		$database_port     = $this->port;
		$database_type     = 'mysqli';
		$database_ssl      = false;
		$database_ssl_key  = '';
		$database_ssl_cert = '';
		$database_ssl_ca   = '';

		/* Register the PDO connection in Cacti's session array */
		$key = "$database_hostname:$database_port:$database_default";
		$database_sessions[$key] = $this->getPdo();
	}

	public function tearDown(): void {
		$this->pdo = null;

		try {
			$this->container->remove();
		} catch (\Exception $e) {
			/* container may already be removed */
		}

		self::$instance = null;
	}

	/**
	 * Reset the database to a clean state between tests.
	 * Truncates all data tables but preserves schema.
	 */
	public function resetData(): void {
		$pdo = $this->getPdo();
		$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

		$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

		foreach ($tables as $table) {
			/* Preserve schema/config tables, truncate data tables */
			if (!in_array($table, ['settings', 'version'])) {
				$pdo->exec("TRUNCATE TABLE `$table`");
			}
		}

		$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
	}

	private function createPdo(): PDO {
		$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
			$this->host, $this->port, self::DB_NAME);

		return new PDO($dsn, self::DB_USER, self::DB_PASS, [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		]);
	}

	private function waitForConnection(int $maxAttempts = 30): void {
		for ($i = 0; $i < $maxAttempts; $i++) {
			try {
				$this->pdo = $this->createPdo();

				return;
			} catch (PDOException $e) {
				usleep(500000);
			}
		}

		throw new RuntimeException('MySQL testcontainer failed to accept connections after ' . $maxAttempts . ' attempts');
	}

	private function loadSchema(): void {
		$schemaFile = dirname(__DIR__, 2) . '/cacti.sql';

		if (!file_exists($schemaFile)) {
			throw new RuntimeException('cacti.sql not found at: ' . $schemaFile);
		}

		/* Grant full privileges to test user via PDO */
		$rootDsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
			$this->host, $this->port, self::DB_NAME);

		$rootPdo = new PDO($rootDsn, 'root', self::DB_ROOT_PASS, [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		]);

		$rootPdo->exec("GRANT ALL PRIVILEGES ON `" . self::DB_NAME . "`.* TO '" . self::DB_USER . "'@'%'");
		$rootPdo->exec("FLUSH PRIVILEGES");

		/* cacti.sql uses DELIMITER // for stored procedures, which PDO
		   cannot handle. Use the mysql CLI inside the container instead. */
		$process = new \Symfony\Component\Process\Process([
			'docker', 'exec', '-i', $this->container->getId(),
			'mysql', '-uroot', '-p' . self::DB_ROOT_PASS, self::DB_NAME
		]);

		$process->setInput(file_get_contents($schemaFile));
		$process->setTimeout(60);
		$process->mustRun();
	}
}

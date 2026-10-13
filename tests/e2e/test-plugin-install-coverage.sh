#!/usr/bin/env bash
# Run the real plugin API and CLI fixture with coverage in a separate test DB.
set -euo pipefail
cd "$(dirname "$0")"

docker compose exec -T mariadb mariadb -uroot -pcactiroot -e "
  CREATE DATABASE cacti_plugin_test;
  GRANT ALL PRIVILEGES ON cacti_plugin_test.* TO 'cactiuser'@'%';
"
docker compose exec -T mariadb mariadb -uroot -pcactiroot cacti_plugin_test < ../../cacti.sql

# Restore the browser fixture config even when an assertion fails. The coverage
# files retain checkout-relative paths and are merged with request coverage.
docker compose exec -T \
  -e CACTI_PLUGIN_INSTALL_TEST=1 \
  -e CACTI_PLUGIN_INSTALL_COVERAGE=/usr/local/lib/cacti-e2e-coverage.php \
  php sh -eu -c '
    cd /var/www/html/cacti
    backup=$(mktemp)
    cp include/config.php "$backup"
    trap '\''cp "$backup" include/config.php; rm -f "$backup"'\'' EXIT
    printf '\''\n$database_default = "cacti_plugin_test";\n'\'' >> include/config.php
    php -r '\''
      require "include/config.php";
      $db = new PDO("mysql:host=" . $database_hostname . ";dbname=cacti_plugin_test", $database_username, $database_password);
      $db->prepare("UPDATE version SET cacti = ?")->execute([trim(file_get_contents("include/cacti_version"))]);
      $db->exec("REPLACE INTO settings (name,value) VALUES (" . $db->quote("install_complete") . "," . $db->quote("1") . ")");
    '\''
    php -d auto_prepend_file=/usr/local/lib/cacti-e2e-coverage.php tests/integration/fixtures/plugin_install_lifecycle.php
  '

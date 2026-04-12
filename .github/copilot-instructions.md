<<<<<<< HEAD
Copilot instructions for this repo (Cacti 1.2.x)

Use these notes to navigate and contribute productively to this PHP codebase.

## Big picture
- Cacti is a PHP web app + CLI poller/daemon that stores state in MySQL/MariaDB and graphs via RRDtool.
- Bootstrap: `include/global.php` loads config, DB connection (`lib/database.php`), core libs, and sets `$config` globals. Web pages typically include `include/auth.php` first; CLI scripts include `include/cli_check.php`.
- Major areas:
  - Web UI (top-level `*.php`, helpers in `lib/html*.php`, `include/top_*_header.php`/`bottom_footer.php`).
  - Core APIs in `lib/api_*.php` and domain modules in `lib/*.php` (devices, data sources, graphs, templates, poller, plugins).
  - CLI tools in `cli/` (install, manage, poller operations), used by CI and admins.
  - Poller runners: `poller.php` (single run) and `cactid.php` (systemd-friendly loop; see `service/README.md`).
  - Plugin framework in `lib/plugins.php` with hooks; plugins live under `plugins/`.

## Data access and patterns
- Always use DB helpers from `lib/database.php`. Prefer prepared variants: `db_fetch_cell_prepared`, `db_fetch_assoc_prepared`, `db_execute_prepared`. For inserts/updates, use `sql_save($array, $table)` when possible.
- Under the hood uses PDO; `global.php` decides between local/remote DB for remote pollers.
- Logging: `cacti_log($msg, $echo=false, $subsystem='SYSTEM', $verbosity=POLLER_VERBOSITY_MEDIUM)`; log path via `cacti_log_file()`.

## Web page conventions
- Start with `include('./include/auth.php');` to enforce auth/session/CSRF.
- Flow pattern: `set_default_action(); switch (get_request_var('action')) { ... }` with helpers like `top_header()` and `bottom_footer()` for layout (see `data_input.php`).
- Request/validation: use `get_request_var/get_filter_request_var/get_nfilter_request_var`, `form_input_validate(...)`, and utilities like `sanitize_unserialize_selected_items(...)`. Don’t read `$_REQUEST` directly.
- CSRF: AJAX posts include `__csrf_magic: csrfMagicToken` (see usages in `host_templates.php`, `data_queries.php`).
- i18n: wrap UI strings with `__('...')`.

## CLI and daemon workflows
- Install/upgrade: `php -q cli/install_cacti.php --accept-eula --install --force`; DB upgrade when needed: `php -q cli/upgrade_database.php --forcever=$(cat include/cacti_version)` (see README).
- Polling:
  - One-shot: `php poller.php --poller=1 --force --debug`.
  - Daemon loop: `./cactid.php --foreground --debug` or systemd via `tests/tools/cactid.service` and `service/README.md`.
- Common maintenance: `cli/rebuild_poller_cache.php`, `cli/poller_reindex_hosts.php`, `cli/plugin_manage.php`, etc.

## Plugin framework
- Hooks are declared in DB and executed via `api_plugin_hook(...)` and `api_plugin_hook_function(...)` (see `lib/plugins.php`).
- Plugins must reside in `plugins/<name>/` with `setup.php` and `INFO`; enabled/ordered via `plugin_config` table. CI fetches core plugins (`.github/workflows/syntax.yml`).
- Use hooks like `page_head`, `poller_top`, `device_remove`, `create_complete_graph_from_template` to integrate (grep for `api_plugin_hook_function` in `lib/`).

## Testing, CI, and local checks
- No PHPUnit; CI runs syntax checks and an end-to-end smoke: sets up Apache+MySQL, installs Cacti, enables plugins, runs poller, and spiders pages (see `.github/workflows/syntax.yml`, scripts in `tests/tools/`).
- Local quick checks:
  - PHP lint: `find . -name '*.php' -exec php -l {} \; | grep -iv 'no syntax errors detected'` (CI uses similar).
  - Minimal smoke: create `include/config.php` from `.dist`, import `cacti.sql`, then run install + `poller.php` as above; tail `log/cacti.log` for `SYSTEM STATS`.

## Coding do’s in this repo
- Use prepared DB helpers and input validators; follow patterns in `data_input.php`, `host_templates.php`.
- Reuse HTML helpers (`lib/html_*.php`) for forms, filters, pagination.
- Raise UI messages via `raise_message(...)` and redirect with `header('Location: ...')`.
- Respect remote poller modes and `$config['is_web']`/CLI guards (`$no_http_header_files` in `include/global.php`).

## Coding standards
- Follow PHP PSR standards (PSR-12/PSR-1) for new or standalone code where practical, but match the file’s existing conventions.
  - Preserve the file’s indentation (tabs vs spaces) and brace style; do not reformat unrelated code.
  - Keep the Cacti GPL header block at the top of PHP files.
  - Use snake_case functions and procedural structure consistent with the codebase; avoid introducing namespaces unless integrating vendor code.
  - Maintain PHP 5.4+ compatibility (CI tests 7.0–8.4). Avoid using features requiring >7.0 (e.g., union types, attributes, typed properties) in core code.
  - Don’t change public function signatures in `lib/api_*.php` or widely used helpers without auditing usages.
  - For dependencies, prefer Composer-managed libs under `include/vendor` and keep versions pinned by `composer.lock`.

## Useful references
- Bootstrap/config: `include/global.php`, `include/config.php.dist`.
- Core libs: `lib/database.php`, `lib/functions.php`, `lib/poller.php`, `lib/template.php`, `lib/plugins.php`.
- Exemplars: `data_input.php` (full CRUD page), `host_templates.php` (AJAX + CSRF + validation), `cactid.php` (daemon loop), `cli/install_cacti.php` (installer flow).
||||||| 7dd05ee12
=======
# Copilot instructions (Cacti `develop`)

## Big Picture
**Cacti** is a robust, extensible network monitoring and graphing tool built on the LAMP stack.

- **Core Purpose**: Polls devices (SNMP, scripts), stores time-series data in **RRDtool**, and renders historical graphs for network operations.

- **Architecture**:
  - **Frontend**: PHP 8.1+ web UI for configuration and visualization.
  - **Backend**: MySQL/MariaDB for metadata/state/configuration/statistics; 
                 RRD files for metrics.
  - **Polling**: Scalable data collection via `cmd.php` (PHP) or `spine` (C), supporting remote pollers.
- **Key Components**:
  - **Templates**: Abstraction for Graphs, Data Sources, and Devices to simplify management.
  - **Plugins**: Extension system for adding features (e.g., Thold, Weathermap).
  - **Automation**: Rules for discovering and adding devices automatically.

## Entry points & Architecture
- **Web**: Pages start with `include/auth.php` (loads `include/global.php`, enforces auth).
- **CLI**: Scripts load `include/cli_check.php` (enforces CLI context).
- **Polling & Data Flow** (Principles of Operation):
  - **Hierarchy**: Sites > Data Collectors > Devices > Graphs.
  - **Collection**: `poller.php` (cron) or `cactid.php` (daemon) fetch data via SNMP, `script_server.php` (resident PHP pool), or external scripts.
  - **Storage**: Metadata in MySQL/MariaDB; Metrics in RRDfiles (`.rrd`).
  - **Visualization**: RRDtool renders graphs (see `graph_image.php`).
- **API**: REST endpoints in `api/` (Slim framework).
- **CLI Scripts**: Administrative scripts located in `cli/`
- **SNMP Query Templates**: XML templates with snmp OID's to poll located in `resource\snmp_queries`

## Project conventions (follow these)
- **Coding Style**:
  - Indentation: Tabs (4 spaces width).
  - Braces: Start on the same line (`if (...) {`).
  - Quotes: Single quotes `'` preferred over double `"` unless interpolating.
  - Constants: `true`, `false`, `null` should be lowercase.
  - Arrays: Use `foreach` over `while(list() = each())`.
- **Database (MariaDB/Mysql)**:
  - Use helpers in `lib/database.php`; prefer prepared calls like `db_fetch_row_prepared()` / `db_fetch_assoc_prepared()` / `db_execute_prepared()`.
  - **Do NOT quote numeric values** in SQL. Use single quotes for strings.
  - Always use `AS` for aliases.
  - Use explicit `INNER JOIN`, `LEFT JOIN`, etc. (no implicit joins).
- **Request handling (web)**:
  - Avoid direct `$_GET/$_POST/$_REQUEST`.
  - Use `get_request_var()` / `get_filter_request_var()` and `form_input_validate()`.
  - Validation: Use `validate_store_request_vars()` where possible.
  - **Request variable caching** (aliases in `lib/html_utility.php`):
    - `gfrv($name)` / `get_filter_request_var()` — validates input (default: integer via `filter_var`), stores the sanitized value in `$_CACTI_REQUEST`, and **returns** it.
    - `grv($name)` / `get_request_var()` — retrieves from `$_CACTI_REQUEST` cache. Returns pre-validated data if `gfrv()` was called first for that variable.
    - `gnrv($name)` / `get_nfilter_request_var()` — retrieves from cache without filtering. Safe if `gfrv()` was called first.
    - `srv($name, $value)` / `set_request_var()` — stores a value in `$_CACTI_REQUEST`, `$_REQUEST`, `$_POST`, and `$_GET`.
    - **Do NOT flag `grv()`/`gnrv()` calls as unsanitized when `gfrv()` was already called for that variable in the same request.** The validation happens once at the `gfrv()` call site; subsequent retrievals return cached, pre-validated data.
    - Adding redundant `(int)` casts or parameterization after `gfrv()` has validated is unnecessary.
- **Common page flow**: `set_default_action(); switch (get_request_var('action')) { ... }` and render via `top_header()`/`bottom_footer()`.
- **CSRF**: AJAX posts include `__csrf_magic: csrfMagicToken`.
- **Logging**: use `cacti_log(...)` and `cacti_log_file()`.
- **i18n**: wrap UI strings with `__('...')`.
- **Plugins**:
  - Hooks via `api_plugin_hook(...)` in `lib/plugins.php`.
  - Must have an `INFO` file (INI format).
  - Use `top_header()`/`bottom_footer()` (no direct includes).
  - Add `&header=false` to URLs for AJAX requests.

## Workflows you’ll actually use
- Install deps: `composer install` (CI validates via `.github/workflows/syntax.yml`).
- Install/upgrade DB: `php -q cli/install_cacti.php --accept-eula --install --force` and `php -q cli/upgrade_database.php --forcever=$(cat include/cacti_version)`.
- Run poller: `php poller.php --poller=1 --force --debug` (daemon debug: `./cactid.php --foreground --debug`).
- Repo checks: `composer lint`, `composer phpstan`, `composer phpcsfixer` (dry-run).

## Database Optimization (DBA Mode)
- **Context**: The full schema (DDL/DML) is in `cacti.sql`. Check it for table structures and indexes.
- **Optimize**: Proactively look for slow query patterns (e.g., missing indexes, non-sargable `WHERE` clauses).
- **Best Practices**:
  - Prefer `JOIN` over subqueries where efficient.
  - Ensure columns in `WHERE`/`JOIN` clauses are indexed (check `cacti.sql`).
  - Avoid `SELECT *` in production code; list columns explicitly.

## Security Advisor (AppSec Mode)
- **Context**: Cacti Takes Security Seriously consider the security implications when reviewing current and new code base
- **Vulnerability Checks**:
  - **SQL Injection**: STRICTLY enforce prepared statements (`db_*_prepared`). Flag any variable interpolation in SQL strings.
  - **XSS**: Ensure user input is sanitized/escaped before output (use `htmlspecialchars` or Cacti's wrappers).
  - **CSRF**: Verify forms/AJAX include `__csrf_magic`.
  - **Command Injection**: Scrutinize `shell_exec`, `exec`, `passthru`. Ensure arguments are escaped (`escapeshellarg`).
  - **Auth**: Verify `include/auth.php` is loaded and permissions are checked (`api_user_realm_auth`).

## Clean-as-you-code
- While implementing a change, do small, behavior-preserving refactors in the *touched area* (dedupe logic, extract helpers, simplify conditions).
- **Formatting**: Enforce tabs for indentation (no spaces for indentation).
- **Legacy**: Avoid `preg_*` if simple string functions work. Use `preg_*` over `ereg_*`.
- Avoid drive-by rewrites: no repo-wide formatting, no unrelated cleanup, and preserve existing file conventions (keep the GPL header blocks).

>>>>>>> origin/fix/jquery-deprecations

# Cacti

[![Cacti Commit Audit](https://github.com/Cacti/cacti/actions/workflows/syntax.yml/badge.svg)](https://github.com/Cacti/cacti/actions/workflows/syntax.yml)
<<<<<<< HEAD
[![Project Status](http://opensource.box.com/badges/active.svg)](http://opensource.box.com/badges)
[![Translation Status](https://translate.cacti.net/widgets/cacti/-/core/svg-badge.svg)](https://translate.cacti.net
"Translation Status")
[![Average time to resolve an issue](http://isitmaintained.com/badge/resolution/cacti/cacti.svg)](http://isitmaintained.com/project/cacti/cacti
"Average time to resolve an issue")
[![Percentage of open issues](http://isitmaintained.com/badge/open/cacti/cacti.svg)](http://isitmaintained.com/project/cacti/cacti
"Percentage of issues still open")
||||||| 7dd05ee12
[![Build Status -
Develop](https://travis-ci.org/Cacti/cacti.svg?branch=develop)](https://travis-ci.org/Cacti/cacti)
[![Project
Status](http://opensource.box.com/badges/active.svg)](http://opensource.box.com/badges)
[![Translation
Status](https://translate.cacti.net/widgets/cacti/-/core/svg-badge.svg)](https://translate.cacti.net
"Translation Status") [![Average time to resolve an
issue](http://isitmaintained.com/badge/resolution/cacti/cacti.svg)](http://isitmaintained.com/project/cacti/cacti
"Average time to resolve an issue") [![Percentage of open
issues](http://isitmaintained.com/badge/open/cacti/cacti.svg)](http://isitmaintained.com/project/cacti/cacti
"Percentage of issues still open")
=======
[![Project Status](https://opensource.box.com/badges/active.svg)](https://opensource.box.com/badges)
[![Translation Status](https://translate.cacti.net/widgets/cacti/-/core/svg-badge.svg)](https://translate.cacti.net)
[![Average issue resolution time](https://isitmaintained.com/badge/resolution/cacti/cacti.svg)](https://isitmaintained.com/project/cacti/cacti)
[![Open issues](https://isitmaintained.com/badge/open/cacti/cacti.svg)](https://isitmaintained.com/project/cacti/cacti)
>>>>>>> origin/fix/jquery-deprecations

---

## Overview

Cacti is an open-source network monitoring and graphing platform built on RRDtool.

It provides a scalable framework to collect, store, and visualize time-series data from network devices, servers, and applications.

Core capabilities include:

- Automated device discovery
- Local and remote data collection
- Graph, data source, and RRA templating
- SNMP polling (v1/v2/v3) and IPv6 support
- Role-based access control (RBAC)
- Plugin framework
- Dynamic graph viewing and export options

---

<<<<<<< HEAD
With that said, there are several changes that you MUST perform to MySQL/MariaDB
before you upgrade, and a service restart is required.  Depending on your release
of MariaDB or MySQL, the following settings will either be required, or already
enabled as default:
||||||| 7dd05ee12
With that said, there are several changes that you MUST perform to MySQL/MariaDB 
before you upgrade, and a service restart is required.  Depending on your release
of MariaDB or MySQL, the following settings will either be required, or already
enabled as default:
=======
## Release Branches
>>>>>>> origin/fix/jquery-deprecations

Cacti maintains two primary branches:

| Branch | Purpose |
|---|---|
| `1.2.x` | Stable long-lived release series |
| `develop` | Active development toward Cacti 1.3.x |

For the latest published version, see [GitHub Releases](https://github.com/Cacti/cacti/releases).

<<<<<<< HEAD
# important for compatibility
sql_mode=NO_ENGINE_SUBSTITUTION
||||||| 7dd05ee12
# important for compatibility
sql_mode=NO_ENGINE_SUBSTITUTION,NO_AUTO_CREATE_USER
=======
---
>>>>>>> origin/fix/jquery-deprecations

## System Requirements

Minimum supported dependencies by branch:

| Dependency | Cacti `1.2.x` | Cacti `develop` (1.3.x) |
|---|---|---|
| MariaDB | 5.6+ | 10.2.x+ |
| MySQL | 5.6+ | 8.0+ |
| PHP | 8.1+ | 8.1+ |
| RRDtool | 1.4+ | 1.8+ |
| Net-SNMP | 5.5+ | 5.8+ |

Notes:

- RRDtool 1.9+ is recommended for newer dynamic graph features in 1.3.x.
- Net-SNMP 5.9+ is recommended for broader SNMPv3 protocol coverage.
- A web server with PHP support is required.
- PHP should be available as CLI or CGI for scheduled polling and maintenance scripts.
- `php-snmp` is optional; validate behavior carefully if you depend on IPv6 and SNMPv3.

Operating system guidance:

- `1.2.x`: RHEL/Rocky/Alma 8+ (or equivalent) is a common baseline.
- `1.3.x`: RHEL/Rocky/Alma 9+ or CentOS Stream 9+ is preferred for modern PHP packaging.
- Debian and Ubuntu are also well supported.

---

## Installation (Source Checkout)

Clone the repository:

```bash
git clone https://github.com/Cacti/cacti.git
cd cacti
```

Dependency management:

- For a source checkout on both `1.2.x` and `develop`, install dependencies with Composer.
- Use `composer update` only when intentionally refreshing dependency versions.

<<<<<<< HEAD
```
for table in `mysql -e "SELECT TABLE_NAME FROM information_schema.TABLES WHERE table_schema='cacti' AND engine!='MEMORY'" cacti | grep -v TABLE_NAME`;
do
   echo "Converting $table";
   mysql -e "ALTER TABLE $table ENGINE=InnoDB ROW_FORMAT=Dynamic CHARSET=utf8mb4" cacti;
done
||||||| 7dd05ee12
```
for table in `mysql -e "SELECT TABLE_NAME FROM information_schema.TABLES WHERE table_schema='cacti' AND engine!='MEMORY'" cacti | grep -v TABLE_NAME`;
do 
   echo "Converting $table";
   mysql -e "ALTER TABLE $table ENGINE=InnoDB ROW_FORMAT=Dynamic CHARSET=utf8mb4" cacti;
done
=======
Install dependencies:

```bash
composer install
>>>>>>> origin/fix/jquery-deprecations
```

Windows users may need:

```bash
composer install --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
```

Then configure your database and web server, and complete setup using the official docs:

https://github.com/Cacti/documentation

---

## Database Upgrades and Schema Checks

When running from source (especially `develop`), schema updates may be required after pulling changes.

Upgrade the database schema:

```bash
sudo -u cacti php -q cli/upgrade_database.php --forcever=`cat include/cacti_version`
```

If needed, force a re-upgrade from an earlier version:

```bash
php -q cli/upgrade_database.php --forcever=<older_version>
```

Audit schema consistency:

```bash
php cli/audit_database.php --report
```

---

## Data Collection and Polling

Cacti collects data through data sources, which can use:

- SNMP
- Scripts
- Command output
- Databases
- Custom input methods

Polling engines:

| Poller | Description |
|---|---|
| PHP Poller | Built-in polling engine |
| Spine | High-performance C-based poller |

Spine supports SNMPv1/v2 and SNMPv3 with IPv6, with some advanced protocol support depending on how Net-SNMP is compiled on your platform.

---

## Features

- Device discovery and automation workflows
- Reusable templates for graphs and data sources
- Distributed remote data collectors
- Plugin architecture for extensibility
- Dynamic graph interactions (time navigation, realtime view, CSV export)
- Fine-grained user and group permissions using RBAC
- Broad RRDtool graphing support (including VDEFs and stacked lines)

---

## Documentation

Official documentation is maintained in a separate repository:

https://github.com/Cacti/documentation

---

## Contributing

Contributions are welcome.

1. Fork this repository.
2. Create a branch for your change.
3. Submit a pull request.

You can also help improve docs in:

https://github.com/Cacti/documentation

---

## Community

Community support is available on the Cacti forums:

https://forums.cacti.net

---

## License

Cacti is licensed under the GNU General Public License v2.0.

See [LICENSE](./LICENSE) for details.

<<<<<<< HEAD
Bringing it all together, Cacti uses and extensive template system that allows
for the creation and consumption of portable templates. Graph, data source, and
RRA templates allow for the easy creation of graphs and data sources out of the
box.  Along with the Cacti community support, templates have become the standard
way to support graphing any number of devices in use in today computing and
networking environments.

### Data Collection (The Poller)

Local and remote data collection support with the ability to set collection
intervals. Check out ***Data Source Profile*** with in Cacti for more
information. Data Source Profiles can be applied to graphs at creation time or
at the data template level.

Remote data collection has been made easy through replication of resources to
remote data collectors. Even when connectivity to the main Cacti installation is
lost from remote data collector, it will store collected data until connectivity
is restored. Remote data collection only requires MySQL and HTTP/HTTPS access
back to the main Cacti installation location.

### Network Discovery and Automation

Cacti provides administrators a series of network automation functionality in
order to reduce the time and effort it takes to setup and manage devices.

- Multiple definable network discovery rules

- Automation templates that specify how devices are configured

### Plugin Framework

Cacti is more than a network monitoring system, it is an operations framework
that allows the extension and augmentation of Cacti functionality. The Cacti
Group continues to maintain an assortment of plugins.  If you are looking to add
features to Cacti, there is quite a bit of reference material to choose from on
GitHub.

### Dynamic Graph Viewing Experience

Cacti allows for many runtime augmentations while viewing graphs:

- Dynamically loaded tree and graph view

- Searching by string, graph and template types

- Viewing augmentation

- Simple time span adjustments

- Convenient sliding time window buttons

- Single click realtime graph option

- Easy graph export to csv

- RRA view with just a click

### User, Groups and Permissions

Support for per user and per group permissions at a per realm (area of Cacti),
per graph, per graph tree, per device, etc... The permission model in Cacti is
role based access control (RBAC) to allow for flexible assignment of
permissions. Support for enforcement of password complexity, password age and
changing of expired passwords.

## RRDtool Graph Options

Cacti supports most RRDtool graphing abilities including:

### Graph Options

- Full right axis

- Shift

- Dash and dash offset

- Alt y-grid

- No grid fit

- Units length

- Tab width

- Dynamic labels

- Rules legend

- Legend position

### Graph Items

- VDEFs

- Stacked lines

- User definable line widths

- Text alignment

-----------------------------------------------------------------------------
Copyright (c) 2004-2026 - The Cacti Group, Inc.
||||||| 7dd05ee12
Bringing it all together, Cacti uses and extensive template system that allows
for the creation and consumption of portable templates. Graph, data source, and
RRA templates allow for the easy creation of graphs and data sources out of the
box.  Along with the Cacti community support, templates have become the standard
way to support graphing any number of devices in use in today computing and
networking environments.

### Data Collection (The Poller)

Local and remote data collection support with the ability to set collection
intervals. Check out ***Data Source Profile*** with in Cacti for more
information. Data Source Profiles can be applied to graphs at creation time or
at the data template level.

Remote data collection has been made easy through replication of resources to
remote data collectors. Even when connectivity to the main Cacti installation is
lost from remote data collector, it will store collected data until connectivity
is restored. Remote data collection only requires MySQL and HTTP/HTTPS access
back to the main Cacti installation location.

### Network Discovery and Automation

Cacti provides administrators a series of network automation functionality in
order to reduce the time and effort it takes to setup and manage devices.

- Multiple definable network discovery rules

- Automation templates that specify how devices are configured

### Plugin Framework

Cacti is more than a network monitoring system, it is an operations framework
that allows the extension and augmentation of Cacti functionality. The Cacti
Group continues to maintain an assortment of plugins.  If you are looking to add
features to Cacti, there is quite a bit of reference material to choose from on
GitHub.

### Dynamic Graph Viewing Experience

Cacti allows for many runtime augmentations while viewing graphs:

- Dynamically loaded tree and graph view

- Searching by string, graph and template types

- Viewing augmentation

- Simple time span adjustments

- Convenient sliding time window buttons

- Single click realtime graph option

- Easy graph export to csv

- RRA view with just a click

### User, Groups and Permissions

Support for per user and per group permissions at a per realm (area of Cacti),
per graph, per graph tree, per device, etc... The permission model in Cacti is
role based access control (RBAC) to allow for flexible assignment of
permissions. Support for enforcement of password complexity, password age and
changing of expired passwords.

## RRDtool Graph Options

Cacti supports most RRDtool graphing abilities including:

### Graph Options

- Full right axis

- Shift

- Dash and dash offset

- Alt y-grid

- No grid fit

- Units length

- Tab width

- Dynamic labels

- Rules legend

- Legend position

### Graph Items

- VDEFs

- Stacked lines

- User definable line widths

- Text alignment

-----------------------------------------------------------------------------
Copyright (c) 2021 - The Cacti Group, Inc.
=======
Copyright (c) 2004-2026 The Cacti Group, Inc.
>>>>>>> origin/fix/jquery-deprecations

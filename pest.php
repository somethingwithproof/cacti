<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Pest 3 Configuration
 *
 * Suites:
 *   pest --testsuite=Unit            # fast, no Docker, <5s target
 *   pest --testsuite=Integration     # requires Docker (Testcontainers)
 *   pest --testsuite=Architecture    # static codebase constraint analysis
 *   pest --parallel                  # all suites, parallel workers
 */

pest()->parallel();

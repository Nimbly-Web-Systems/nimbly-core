<?php

/**
 * Prints the Apache lines that go with TRUSTED_PROXIES in .env, so that the
 * web server's own access log, and what reads it, shows the visitor and not
 * the load balancer.
 *
 * Usage: php core/cli/nimbly.php host:proxy-conf
 *
 * Prints nothing when TRUSTED_PROXIES is not set. Needs mod_remoteip
 * (a2enmod remoteip); put the lines in the server config, not in .htaccess.
 */

require_once __DIR__ . '/cli_bootstrap.inc';
load_libraries(['env', 'trusted-proxy']);
echo trusted_proxy_apache_config((string)env('TRUSTED_PROXIES', ''));

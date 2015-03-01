<?php

/**
 * Copy this file to config.php and edit it: set the port and interface you need or leave default values.
 */

return array(
    'listen' => [
        'port' => 8910,             // 31111 is default port non-standard, use 80 to avoid conflicts with corporate
                                    // firewalls, or proxy traffic from 80 to 31111 using nginx or haproxy
        'interface' => 'localhost', // localhost for local connections only (development), 0.0.0.0 for all interfaces,
                                    // or any concrete address if one of your network interfaces (i.e. 192.168.0.20)
    ],
);
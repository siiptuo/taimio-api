<?php

require 'config.php';

return [
    'paths' => [
        'migrations' => 'migrations',
        'seeds' => 'seeds',
    ],
    'environments' => [
        'default_migration_table' => DB_PREFIX.'phinxlog',
        'default_database' => 'production',
        'production' => [
            'adapter' => 'pgsql',
            'host' => DB_HOST,
            'name' => DB_NAME,
            'user' => DB_USER,
            'pass' => DB_PASS,
            'charset' => 'utf8',
            'table_prefix' => DB_PREFIX,
        ],
    ],
];

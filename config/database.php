<?php
/**
 * Database Configuration
 * Store sensitive credentials in environment variables in production
 */

return [
    'host' => 'localhost',
    'database' => 'mrp_erp',
    'username' => 'root',
    'password' => 'passgas1989',
    'charset' => 'utf8mb4',
    'port' => 3306,
    'options' => [
        'error_mode' => MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT,
    ]
];
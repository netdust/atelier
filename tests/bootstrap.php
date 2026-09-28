<?php // tests/bootstrap.php
PHP_SAPI === 'cli' || exit;
require_once __DIR__ . '/../vendor/autoload.php';
define('ABSPATH', '/tmp/wordpress/');

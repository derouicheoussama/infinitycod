<?php
$root = $_SERVER['DOCUMENT_ROOT'];
$path = $root . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== $root . '/' && file_exists($path) && !is_dir($path)) { return false; }
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $root . '/index.php';

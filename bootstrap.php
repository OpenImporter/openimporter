<?php

require_once(__DIR__ . '/importer/OpenImporter/SplClassLoader.php');
$classLoader = new SplClassLoader(null, __DIR__ . '/importer/OpenImporter');
$classLoader->register();

if (!defined('BASEDIR'))
	define('BASEDIR', __DIR__ . '/importer');
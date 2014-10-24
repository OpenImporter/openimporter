<?php

require_once(__DIR__ . '/importer/OpenImporter/SplClassLoader.php');
$classLoader = new SplClassLoader(null, __DIR__ . '/importer/OpenImporter');
$classLoader->register();

define('BASEDIR', __DIR__ . '/importer');
<?php

require_once(__DIR__ . '/../SplClassLoader.php');
$classLoader = new SplClassLoader(null, __DIR__ . '/..');
$classLoader->register();

if (!defined('BASEDIR'))
	define('BASEDIR', __DIR__ . '/../..');
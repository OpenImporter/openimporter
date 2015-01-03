<?php

define('BASEDIR', __DIR__ . '/../../..');

require_once(BASEDIR . '/OpenImporter/SplClassLoader.php');
$classLoader = new SplClassLoader(null, BASEDIR . '/OpenImporter');
$classLoader->register();
$classLoader2 = new SplClassLoader(null, BASEDIR . '/Importers');
$classLoader2->register();
// Composer stuff
require_once(BASEDIR . '/vendor/autoload.php');

require_once(BASEDIR . '/Importers/sources/Tests/DummyDb.php');
require_once(BASEDIR . '/Importers/sources/Tests/DummyConfig.php');
require_once(BASEDIR . '/Importers/sources/Tests/CustomDb.php');

@set_time_limit(600);
@set_exception_handler(array('ImportException', 'exception_handler'));
@set_error_handler(array('ImportException', 'error_handler_callback'), E_ALL);

// Clean up after unfriendly php.ini settings.
if (function_exists('set_magic_quotes_runtime') && version_compare(PHP_VERSION, '5.3.0') < 0)
	@set_magic_quotes_runtime(0);

error_reporting(E_ALL);
ignore_user_abort(true);
umask(0);
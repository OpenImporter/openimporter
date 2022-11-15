<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD https://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 */

/**
 * Bootstraps the autoloader for the testing with phpunit.
 */
require_once(__DIR__ . '/../SplClassLoader.php');

$classLoader = new SplClassLoader(null, __DIR__ . '/../..');
$classLoader->register();

if (!defined('BASEDIR'))
	define('BASEDIR', __DIR__ . '/../..');

if (!defined('TESTDIR'))
	define('TESTDIR', __DIR__);
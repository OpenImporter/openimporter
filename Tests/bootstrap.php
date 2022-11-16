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
require_once(__DIR__ . '/../importer/OpenImporter/SplClassLoader.php');

$classLoader = new SplClassLoader(null, __DIR__ . '/../importer');
$classLoader->register();

if (!defined('BASEDIR'))
{
	define('BASEDIR', __DIR__ . '/../importer');
}

if (!defined('TESTDIR'))
{
	define('TESTDIR', __DIR__);
}
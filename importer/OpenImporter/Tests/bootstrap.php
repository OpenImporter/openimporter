<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

if (!defined('BASEDIR'))
	define('BASEDIR', __DIR__ . '/../..');

/**
 * Bootstraps the autoloader for the testing with phpunit.
 */
require_once(__DIR__ . '/../SplClassLoader.php');
$classLoader = new SplClassLoader('OpenImporter', BASEDIR . '/OpenImporter');
$classLoader->register();
<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

use Symfony\Component\ClassLoader\Psr4ClassLoader;

if (!defined('BASEDIR'))
	define('BASEDIR', __DIR__ . '/../..');

/**
 * Bootstraps the autoloader for the testing with phpunit.
 */
// Composer stuff
require_once(BASEDIR . '/vendor/autoload.php');

$loader = new Psr4ClassLoader();
$loader->addPrefix('OpenImporter\\Core\\', BASEDIR . '/OpenImporter');
$loader->addPrefix('OpenImporter\\Importers\\', BASEDIR . '/Importers');
$loader->register();
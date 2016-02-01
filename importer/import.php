<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

// Handy shortcut
define('BASEDIR', __DIR__);

// Autoload our classes from the OpenImporter and Importers directory's
require_once(BASEDIR . '/OpenImporter/SplClassLoader.php');
$classLoader = new SplClassLoader('OpenImporter', BASEDIR);
$classLoader->register();
$classLoader2 = new SplClassLoader('Importers', BASEDIR);
$classLoader2->register();

// Can always ask, but whats taking 10mins?
@set_time_limit(600);

// Lets catch those errors and exceptions
error_reporting(E_ALL);
set_exception_handler(array('OpenImporter\ImportException', 'exception_handler'));
set_error_handler(array('OpenImporter\ImportException', 'error_handler_callback'), E_ALL);

// Clean up after unfriendly php.ini settings.
if (function_exists('set_magic_quotes_runtime') && version_compare(PHP_VERSION, '5.3.0') < 0)
{
	@set_magic_quotes_runtime(0);
}

// User aborts are not a good thing
ignore_user_abort(true);

// Let try to create files as 0666 and directories as 0777.
umask(0);

// Disable gzip compression if possible
if (is_callable('apache_setenv'))
{
	apache_setenv('no-gzip', '1');
}

if (@ini_get('session.save_handler') === 'user')
{
	@ini_set('session.save_handler', 'files');
}

ob_start();
@session_start();
global $import;

// Add slashes, as long as they aren't already being added.
if (function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc() != 0)
{
	$_POST = stripslashes_recursive($_POST);
}

// Start a configurator values container for use
$config = new OpenImporter\Configurator();
$config->lang_dir = BASEDIR . '/Languages';

// Load our language strings, can't say much without them
try
{
	// Load the users language based on detected browser language settings
	$lng = new OpenImporter\Lang();
	$lng->loadLang($config->lang_dir);
}
catch (Exception $e)
{
	OpenImporter\ImportException::exception_handler($e);
}

// Template, import and response engine
$template = new OpenImporter\Template($lng);
$importer = new OpenImporter\Importer($config, $lng, $template);
$response = new OpenImporter\HttpResponse(new OpenImporter\ResponseHeader());

$template->setResponse($response);
$import = new OpenImporter\ImportManager($config, $importer, $template, new OpenImporter\Cookie(), $response);

// Lets get this show on the road
try
{
	$import->process();
}
catch (Exception $e)
{
	// Debug, remember to remove before GA
	echo '<br>' . $e->getMessage() . '<br>';
	echo $e->getFile() . '<br>';
	echo $e->getLine() . '<br>';
	// If an error is not caught, it means it's fatal and the script should die.
}
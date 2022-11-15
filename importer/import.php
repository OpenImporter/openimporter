<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD https://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 */

// Handy shortcut
const BASEDIR = __DIR__;

// Autoload our classes from the OpenImporter and Importers directory's
require_once(BASEDIR . '/OpenImporter/SplClassLoader.php');
$oi_classLoader = new SplClassLoader('OpenImporter', BASEDIR);
$oi_classLoader->register();
$oi_classLoader2 = new SplClassLoader('Importers', BASEDIR);
$oi_classLoader2->register();

// Can always ask, but what's taking 10 mins?
@set_time_limit(600);

// Catch those errors and exceptions
error_reporting(E_ALL);
set_exception_handler(array('OpenImporter\ImportException', 'exception_handler'));
set_error_handler(array('OpenImporter\ImportException', 'error_handler_callback'), E_ALL);

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
global $oi_import;

// Resetting to another import combination from the UI
if ((isset($_GET['import_script']) && trim($_GET['import_script']) === '') || empty($_GET))
{
	unset($_SESSION['importer_data'], $_SESSION['do_steps'], $_SESSION['import_progress']);
}

// Add slashes, as long as they aren't already being added.
if (function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc() != 0)
{
	$_POST = stripslashes_recursive($_POST);
}

// Start a configurator values container for use
$oi_config = new OpenImporter\Configurator();
$oi_config->lang_dir = BASEDIR . '/Languages';
$oi_language = '';

// Load our language strings, can't say much without them
try
{
	// Load the users language based on detected browser language settings
	$oi_language = new OpenImporter\Lang();
	$oi_language->loadLang($oi_config->lang_dir);
}
catch (\Exception $e)
{
	OpenImporter\ImportException::exception_handler($e);
}

// Template, import and response engine
$oi_template = new OpenImporter\Template($oi_language);
$oi_importer = new OpenImporter\Importer($oi_config, $oi_language, $oi_template);
$oi_response = new OpenImporter\HttpResponse(new OpenImporter\ResponseHeader());

$oi_template->setResponse($oi_response);
$oi_import = new OpenImporter\ImportManager($oi_config, $oi_importer, $oi_template, new OpenImporter\Cookie(), $oi_response);

// Get this show on the road
try
{
	$oi_import->process();
}
catch (\Exception $e)
{
	// Debug, remember to remove before GA (ROFL)
	echo '<br>' . $e->getMessage() . '<br>';
	echo $e->getFile() . '<br>';
	echo $e->getLine() . '<br>';
	// If an error is not caught, it means it's fatal and the script should die.
}

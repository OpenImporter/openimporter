<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

use Symfony\Component\ClassLoader\Psr4ClassLoader;
use OpenImporter\Core\Configurator;
use OpenImporter\Core\Lang;
use OpenImporter\Core\Cookie;
use OpenImporter\Core\Template;
use OpenImporter\Core\Importer;
use OpenImporter\Core\HttpResponse;
use OpenImporter\Core\ResponseHeader;
use OpenImporter\Core\ImportManager;
use OpenImporter\Core\ImportException;
use OpenImporter\Core\PasttimeException;

define('BASEDIR', __DIR__);
// A shortcut
define('DS', DIRECTORY_SEPARATOR);

// Composer stuff
require_once(BASEDIR . '/vendor/autoload.php');
require_once(BASEDIR . '/OpenImporter/Utils.php');

$loader = new Psr4ClassLoader();
$loader->addPrefix('OpenImporter\\Core\\', BASEDIR . '/OpenImporter');
$loader->addPrefix('OpenImporter\\Importers\\', BASEDIR . '/Importers');
$loader->register();

@set_time_limit(600);
@set_exception_handler(array('ImportException', 'exception_handler'));
@set_error_handler(array('ImportException', 'error_handler_callback'), E_ALL);

error_reporting(E_ALL);
ignore_user_abort(true);
umask(0);

ob_start();

// disable gzip compression if possible
if (is_callable('apache_setenv'))
	apache_setenv('no-gzip', '1');

if (@ini_get('session.save_handler') == 'user')
	@ini_set('session.save_handler', 'files');
@session_start();

// Add slashes, as long as they aren't already being added.
if (function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc() != 0)
	$_POST = stripslashes_recursive($_POST);

$OI_configurator = new Configurator();
$OI_configurator->lang_dir = BASEDIR . DIRECTORY_SEPARATOR . 'Languages';
$OI_configurator->importers_dir = BASEDIR . DIRECTORY_SEPARATOR . 'Importers';

try
{
	$lng = new Lang();
	$lng->loadLang($OI_configurator->lang_dir);
}
catch (\Exception $e)
{
	ImportException::exception_handler($e);
}

$template = new Template($lng);

global $import;

try
{
	$importer = new Importer($OI_configurator, $lng, $template);
	$response = new HttpResponse(new ResponseHeader());

	$template->setResponse($response);

	$import = new ImportManager($OI_configurator, $importer, $template, new Cookie(), $response);

	$import->process();
}
catch (ImportException $e)
{
	$e->doExit($template);
}
catch (PasttimeException $e)
{
	$e->doExit();
}
catch (StepException $e)
{
	$e->doExit();
}
catch (\Exception $e)
{
	// Debug, remember to remove before PR
	echo '<br>' . $e->getMessage() . '<br>';
	echo $e->getFile() . '<br>';
	echo $e->getLine() . '<br>';
	// If an error is not catched, it means it's fatal and the script should die.
}
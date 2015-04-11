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
use OpenImporter\Core\Strings;
use OpenImporter\Core\HttpResponse;
use OpenImporter\Core\ResponseHeader;
use OpenImporter\Core\ImportManager;
use OpenImporter\Core\ImportException;
use OpenImporter\Core\PasttimeException;
use OpenImporter\Core\ProgressTracker;

define('BASEDIR', __DIR__);
// A shortcut
define('DS', DIRECTORY_SEPARATOR);

// Composer stuff
require_once(BASEDIR . '/vendor/autoload.php');

$loader = new Psr4ClassLoader();
$loader->addPrefix('OpenImporter\\Core\\', BASEDIR . '/OpenImporter');
$loader->addPrefix('OpenImporter\\Importers\\', BASEDIR . '/Importers');
$loader->register();

@set_exception_handler(array('ImportException', 'exceptionHandler'));
@set_error_handler(array('ImportException', 'errorHandlerCallback'), E_ALL);

error_reporting(E_ALL);
ignore_user_abort(true);
umask(0);

ob_start();

// disable gzip compression if possible
if (is_callable('apache_setenv'))
	apache_setenv('no-gzip', '1');

@session_start();

// Add slashes, as long as they aren't already being added.
if (function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc() != 0)
	$_POST = Strings::stripslashes_recursive($_POST);

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
	ImportException::exceptionHandler($e);
}

$template = new Template($lng);
$OI_configurator->progress = new ProgressTracker($template);

try
{
	if (ini_get('session.save_handler') == 'user')
	{
		throw new \Exception('Please set \'session.save_handler\' to \'files\' before continue');
	}

	$importer = new Importer($OI_configurator, $lng, $template);
	$response = new HttpResponse(new ResponseHeader());

	$template->setResponse($response);

	$import = new ImportManager($OI_configurator, $importer, $template, new Cookie(), $response);
	$import->setupScripts();

	ImportException::setImportManager($import);

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
	$response->template_error = true;
	$response->is_page = true;
	$response->use_template = 'emptyPage';
	$response->params_template = array();
	$import->populateResponseDetails();
	$response->addErrorParam($e->getMessage());

	$template->render();
}
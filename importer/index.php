<?php

/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *  
 * @version 1.0 Alpha
 */

// basic configuration
define('OPENIMPORTER', 1);
define('COREDIR', dirname(__FILE__) . '/core');
define('LANGDIR', dirname(__FILE__) . '/language');
define('PATTERN', dirname(__FILE__) . '/pattern');

require_once(COREDIR . '/autoloader.php');

//set time limit, exception and error handlers
@set_time_limit(600);
@set_exception_handler(array('import_exception', 'exception_handler'));
@set_error_handler(array('import_exception', 'error_handler_callback'), E_ALL);

//initialize the importer
$import = new Importer();

if (method_exists($import, 'doStep' . $_GET['step']))
	call_user_func(array($import, 'doStep' . $_GET['step']));
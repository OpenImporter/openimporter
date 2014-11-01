<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 * This file contains code based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:	BSD, See included LICENSE.TXT for terms and conditions.
 */

require_once(BASEDIR . '/OpenImporter/Utils.php');
// A shortcut
if (!defined('DS'))
	define('DS', DIRECTORY_SEPARATOR);

/**
 * Object ImportManager loads the main importer.
 * It handles all steps to completion.
 * @todo path_to should be source-specific (i.e. in /Importers/whatever/source_importer.php
 * @todo path_from should be destination-specific (i.e. in /Importers/whatever/whatever_importer.php
 *
 */
class ImportManager
{
	/**
	 * The importer that will act as interface between the manager and the
	 * files that will do the actual import
	 * @var object
	 */
	public $importer;

	/**
	 * Our cookie settings
	 * @var object
	 */
	protected $cookie;

	/**
	 * The configurator that holds all the settings
	 * @var object
	 */
	protected $config;

	/**
	 * The template, basically our UI.
	 * @var object
	 */
	public $template;

	/**
	 * The response object.
	 * @var object
	 */
	protected $response;

	/**
	 * The language object.
	 * @var object
	 */
	protected $lng;

	/**
	 * An array of possible importer scripts
	 * @var array
	 */
	public $sources;

	/**
	 * Data used by the script and stored in session between a reload and the
	 * following one.
	 * @var mixed[]
	 */
	public $data = array();

	/**
	 * The path to the source forum.
	 * @var string
	 */
	protected $path_from = null;

	/**
	 * The path to the destination forum.
	 * @var string
	 */
	protected $path_to = null;

	/**
	 * The importer script which will be used for the import.
	 * @var string
	 */
	protected $_script = null;

	/**
	 * This is the URL from our Installation.
	 * @var string
	 */
	protected $_boardurl = '';

	/**
	 * The database password?
	 * @var string
	 */
	protected $db_pass = '';

	/**
	 * initialize the main Importer object
	 */
	public function __construct($config, $importer, $template, $cookie, $response)
	{
		global $time_start;

		$time_start = time();

		$this->loadFromSession();

		$this->config = $config;
		$this->importer = $importer;
		$this->cookie = $cookie;
		$this->template = $template;
		$this->response = $response;
		$this->lng = $importer->lng;
		$this->response->lng = $importer->lng;

		$this->_findScript();

		// The current step - starts at 0.
		$this->response->step = $_GET['step'] = isset($_GET['step']) ? (int) $_GET['step'] : 0;
		$_REQUEST['start'] = isset($_REQUEST['start']) ? (int) @$_REQUEST['start'] : 0;

		$this->loadPass();

		$this->loadPaths();

		if (!empty($this->config->script))
		{
			$this->importer->reloadImporter();
		}
	}

	public function __destruct()
	{
		$this->saveInSession();
	}

	protected function loadPass()
	{
		// Check for the password...
		if (isset($_POST['db_pass']))
			$this->data['db_pass'] = $_POST['db_pass'];

		if (isset($this->data['db_pass']))
			$this->db_pass = $this->data['db_pass'];
	}

	protected function loadPaths()
	{
		if (isset($_POST['path_from']) || isset($_POST['path_to']))
		{
			if (isset($_POST['path_from']))
				$this->config->path_from = rtrim($_POST['path_from'], '\\/');
			if (isset($_POST['path_to']))
				$this->config->path_to = rtrim($_POST['path_to'], '\\/');

			$this->data['import_paths'] = array($this->config->path_from, $this->config->path_to);
		}
		elseif (isset($this->data['import_paths']))
			list ($this->config->path_from, $this->config->path_to) = $this->data['import_paths'];

		if (!empty($this->data))
			$this->importer->setData($this->data);
	}

	protected function loadFromSession()
	{
		if (empty($_SESSION['importer_data']))
			return;

		$this->data = $_SESSION['importer_data'];
	}

	protected function saveInSession()
	{
		$_SESSION['importer_data'] = $this->data;
	}

	/**
	 * Finds the script either in the session or in request
	 */
	protected function _findScript()
	{
		// Save here so it doesn't get overwritten when sessions are restarted.
		if (isset($_REQUEST['import_script']))
			$_SESSION['import_script'] = $this->config->script = (string) $_REQUEST['import_script'];
		elseif (isset($_SESSION['import_script']))
		{
			$this->config->script = $_SESSION['import_script'] = $this->validateScript($_SESSION['import_script']);
		}
		else
		{
			$this->config->script = '';
			$_SESSION['import_script'] = null;
		}
	}

	/**
	 * Prepares the response to send to the template system
	 */
	public function process()
	{
		// This is really quite simple; if ?delete is on the URL, delete the importer...
		if (isset($_GET['delete']))
		{
			$this->uninstall();

			$this->response->no_template = true;
		}

		$this->populateResponseDetails();

		if (isset($_GET['xml']))
		{
			$this->response->addHeader('Content-Type', 'text/xml');
			$this->response->is_xml = true;
		}
		else
			$this->template->header();

		if (isset($_GET['action']) && $_GET['action'] == 'validate')
			$this->validateFields();
		elseif (method_exists($this, 'doStep' . $_GET['step']))
			call_user_func(array($this, 'doStep' . $_GET['step']));
		else
			call_user_func(array($this, 'doStep0'));

		$this->populateResponseDetails();

		$this->template->render();

		if (!isset($_GET['xml']))
			$this->template->footer();
	}

	protected function validateFields()
	{
		$this->_detect_scripts();

		try
		{
			$this->importer->reloadImporter();
		}
		catch(Exception $e)
		{
			// Do nothing, let the code die
		}

		if (isset($_GET['path_to']))
		{
			$this->response->valid = $this->config->destination->checkSettingsPath($_GET['path_to']);
		}
		elseif (isset($_GET['path_from']))
		{
			$this->response->valid = $this->config->source->loadSettings($_GET['path_from'], true);
		}
		else
		{
			$this->response->valid = false;
		}
	}

	protected function populateResponseDetails()
	{
		if (isset($this->importer->xml->general->name))
			$this->response->page_title = $this->importer->xml->general->name . ' to ' . $this->config->destination->getName();
		else
			$this->response->page_title = 'OpenImporter';

// 		$this->response->from = $this->importer->settings : null
		$this->response->script = $this->config->script;
// 		$this->response->
// 		$this->response->
// 		$this->response->
	}

	/**
	 * Deletes the importer files from the server
	 * @todo doesn't know yet about the new structure.
	 */
	protected function uninstall()
	{
		@unlink(__FILE__);
		if (preg_match('~_importer\.xml$~', $_SESSION['import_script']) != 0)
			@unlink(BASEDIR . DS . $_SESSION['import_script']);
		$_SESSION['import_script'] = null;
	}

	protected function validateScript($script)
	{
		$script = preg_replace('~[\.]+~', '.', $script);

		if (file_exists(BASEDIR . DS . 'Importers' . DS . $script) && preg_match('~_importer\.xml$~', $script) != 0)
			return $script;
		else
			return false;
	}

	/**
	 * - checks,  if we have already specified an importer script
	 * - checks the file system for importer definition files
	 * @return boolean
	 */
	private function _detect_scripts()
	{
		if ($this->config->script !== null)
		{
			$this->config->script = $_SESSION['import_script'] = $this->validateScript($_SESSION['import_script']);
		}

		$dir = BASEDIR . DS . 'Importers' . DS;
		$sources = glob($dir . '*', GLOB_ONLYDIR);
		$all_scripts = array();
		$scripts = array();
		foreach ($sources as $source)
		{
			$from = basename($source);
			$scripts[$from] = array();
			$possible_scripts = glob($source . DS . '*_importer.xml');

			// Silence simplexml errors
			libxml_use_internal_errors(true);
			foreach ($possible_scripts as $entry)
			{
				// If a script is broken simply skip it.
				if (!$xmlObj = simplexml_load_file($entry, 'SimpleXMLElement', LIBXML_NOCDATA))
				{
					continue;
				}

				$scripts[$from][] = array('path' => $from . DS . basename($entry), 'name' => $xmlObj->general->name);
				$all_scripts[] = array('path' => $from . DS . basename($entry), 'name' => $xmlObj->general->name);
			}
		}

		if (!empty($_SESSION['import_script']))
		{
			if (count($all_scripts) > 1)
				$this->sources[$from] = $scripts[$from];
			return false;
		}

		if (count($all_scripts) == 1)
		{
			$_SESSION['import_script'] = basename($scripts[$from][0]['path']);
			if (substr($_SESSION['import_script'], -4) == '.xml')
			{
				try
				{
					$this->importer->reloadImporter();
				}
				catch (Exception $e)
				{
					$_SESSION['import_script'] = null;
				}
			}
			return false;
		}

		$this->response->use_template = 'select_script';
		$this->response->params_template = array($scripts);

		return true;
	}

	/**
	 * collects all the important things, the importer can't do anything
	 * without this information.
	 *
	 * @global object $import
	 * @param string|null $error_message
	 * @param object|null|false $object
	 * @return boolean|null
	 */
	public function doStep0($error_message = null, $object = false)
	{
		global $import;

		$import = isset($object) ? $object : false;
		$this->cookie->destroy();
		//previously imported? we need to clean some variables ..
		unset($_SESSION['import_overall'], $_SESSION['import_steps']);

		if ($this->_detect_scripts())
			return true;

		try
		{
			$this->importer->reloadImporter();
		}
		catch(Exception $e)
		{
			$this->response->template_error = true;
			$this->response->addErrorParam($e->getMessage());
		}

		$form = new Form($this->lng);
		$this->_prepareStep0Form($form);

		$this->response->use_template = 'step0';
		$this->response->params_template = array($this, $form);

		if ($error_message !== null)
		{
			$this->template->footer();
			exit;
		}

		return;
	}

	protected function _prepareStep0Form($form)
	{
		$form->action_url = $_SERVER['PHP_SELF'] . '?step=1';

		$this->importer->populateFormFields($form);

		return $form;
	}

	protected function testFiles($files, $path)
	{
		$files = (array) $files;

		$test = empty($files);

		foreach ($files as $file)
			$test |= @file_exists($path . DS . $file);

		return $test;
	}

	/**
	 * the important one, transfer the content from the source forum to our
	 * destination system
	 *
	 * @throws Exception
	 * @return boolean
	 */
	public function doStep1()
	{
		$this->cookie->set(array($this->config->path_to, $this->config->path_from));

		$do_steps = $this->step1Progress();

		try
		{
			$this->importer->doStep1($do_steps);
		}
		catch (DatabaseException $e)
		{
			$trace = $e->getTrace();
			$this->template->error($e->getMessage(), isset($trace[0]['args'][1]) ? $trace[0]['args'][1] : null, $e->getLine(), $e->getFile());

			// Forward back to the original caller to terminate the script
			throw new Exception($e->getMessage());
		}

		$_GET['substep'] = 0;
		$_REQUEST['start'] = 0;

		return $this->doStep2();
	}

	protected function step1Progress()
	{

		$_GET['substep'] = isset($_GET['substep']) ? (int) @$_GET['substep'] : 0;
		// @TODO: check if this is needed
		//$progress = ($_GET['substep'] ==  0 ? 1 : $_GET['substep']);

		// Skipping steps?
		if (isset($_SESSION['do_steps']))
			$do_steps = $_SESSION['do_steps'];
		else
			$do_steps = array();

		//calculate our overall time and create the progress bar
		if(!isset($_SESSION['import_overall']))
			list ($_SESSION['import_overall'], $_SESSION['import_steps']) = $this->importer->determineProgress();

		if(!isset($_SESSION['import_progress']))
			$_SESSION['import_progress'] = 0;

		return $do_steps;
	}

	/**
	 * we have imported the old database, let's recalculate the forum statistics.
	 *
	 * @throws Exception
	 * @return boolean
	 */
	public function doStep2()
	{
		$this->response->step = $_GET['step'] = '2';

		$this->template->step2();

		try
		{
			$key = $this->importer->doStep2($_GET['substep']);
		}
		catch (DatabaseException $e)
		{
			$trace = $e->getTrace();
			$this->template->error($e->getMessage(), isset($trace[0]['args'][1]) ? $trace[0]['args'][1] : null, $e->getLine(), $e->getFile());

			// Forward back to the original caller to terminate the script
			throw new Exception($e->getMessage());
		}

		$this->template->status($key + 1, 1, false, true);

		return $this->doStep3();
	}

	/**
	 * we are done :)
	 *
	 * @return boolean
	 */
	public function doStep3()
	{
		$this->importer->doStep3();

		$writable = (is_writable(BASEDIR) && is_writable(__FILE__));

		$this->response->use_template = 'step3';
		$this->response->params_template = array($this->importer->xml->general->name, $this->_boardurl, $writable);

		unset ($_SESSION['import_steps'], $_SESSION['import_progress'], $_SESSION['import_overall']);

		return true;
	}
}
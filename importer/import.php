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

@set_time_limit(600);
@set_exception_handler(array('ImportException', 'exception_handler'));
@set_error_handler(array('ImportException', 'error_handler_callback'), E_ALL);

// Clean up after unfriendly php.ini settings.
if (function_exists('set_magic_quotes_runtime') && version_compare(PHP_VERSION, '5.3.0') < 0)
	@set_magic_quotes_runtime(0);

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

require_once(__DIR__ . '/OpenImporter/SplClassLoader.php');
$classLoader = new SplClassLoader(null, __DIR__ . '/OpenImporter');
$classLoader->register();
$template = new Template();

$import = new Importer(new Lang(__DIR__ . '/Languages'), $template, new Cookie(), new ResponseHeader());

$response = $import->getResponse();
$template->render($response);

die();

/**
 * Object Importer creates the main XML object.
 * It detects and initializes the script to run.
 * It handles all steps to completion.
 *
 */
class Importer
{
	/**
	 * This is our main database object.
	 * @var object
	 */
	protected $db;

	/**
	 * The "translator" (i.e. the Lang object)
	 * @var object
	 */
	public $lng;

	/**
	 * Our cookie settings
	 * @var object
	 */
	protected $cookie;

	/**
	 * The template, basically our UI.
	 * @var object
	 */
	public $template;

	/**
	 * The headers of the response.
	 * @var object
	 */
	protected $headers;

	/**
	 * The template to use.
	 * @var string
	 */
	public $use_template = '';

	/**
	 * Any param needed by the template
	 * @var mixed[]
	 */
	public $params_template = array();

	/**
	 * If set to true the template should not render anything
	 * @var bool
	 */
	public $no_template = false;

	/**
	 * An array of possible importer scripts
	 * @var array
	 */
	public $sources;

	/**
	 * The table prefix for our destination database
	 * @var string
	 */
	public $to_prefix;

	/**
	 * The table prefix for our source database
	 * @var string
	 */
	public $from_prefix;

	/**
	 * The XML file which will be used from the importer.
	 * @var Object
	 */
	public $xml;

	/**
	 * Is an XML response expected?
	 * @var bool
	 */
	public $is_xml = false;

	/**
	 * If render a full page or just a bit
	 * @var bool
	 */
	public $is_page = true;

	/**
	 * Is there an error?
	 * @var bool
	 */
	public $template_error = false;

	/**
	 * List of error messages
	 * @var mixed[]
	 */
	public $error_params = array();

	/**
	 * Data used by the script and stored in session between a reload and the
	 * following one.
	 * @var mixed[]
	 */
	public $data = array();

	/**
	 * Used to decide if the database query is INSERT or INSERT IGNORE
	 * @var boolean
	 */
	private $ignore = true;

	/**
	 * Used to switch between INSERT and REPLACE
	 * @var boolean
	 */
	private $replace = false;

	/**
	 * The path to the source forum.
	 * @var string
	 */
	protected $path_from = '';

	/**
	 * The path to the destination forum.
	 * @var string
	 */
	protected $path_to = '';

	/**
	 * The importer script which will be used for the import.
	 * @var string
	 */
	private $_script = '';

	/**
	 * This is the URL from our Installation.
	 * @var string
	 */
	private $_boardurl = '';

	/**
	 * initialize the main Importer object
	 */
	public function __construct($lang, $template, $cookie, $headers)
	{
		$this->lng = $lang;

		// Load the language file and create an importer cookie.
		$this->lng->loadLang();

		// initialize some objects
		$this->cookie = $cookie;
		$this->template = $template;
		$this->headers = $headers;

		$this->_findScript();

		// The current step - starts at 0.
		$_GET['step'] = isset($_GET['step']) ? (int) @$_GET['step'] : 0;
		$_REQUEST['start'] = isset($_REQUEST['start']) ? (int) @$_REQUEST['start'] : 0;

		$this->loadPass();

		$this->loadPaths();

		if (!empty($this->_script))
			$this->_loadImporter(dirname(__FILE__) . DIRECTORY_SEPARATOR . $this->_script);
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
		if (isset($this->data['import_paths']) && !isset($_POST['path_from']) && !isset($_POST['path_to']))
			list ($this->path_from, $this->path_to) = $this->data['import_paths'];
		elseif (isset($_POST['path_from']) || isset($_POST['path_to']))
		{
			if (isset($_POST['path_from']))
				$this->path_from = rtrim($_POST['path_from'], '\\/');
			if (isset($_POST['path_to']))
				$this->path_to = rtrim($_POST['path_to'], '\\/');

			$this->data['import_paths'] = array($this->path_from, $this->path_to);
		}
	}

	protected function loadFromSession()
	{
		if (empty($_SESSION['importer_data']))
			return;

		$this->data = $_SESSION['importer_data']
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
			$this->_script = (string) $_REQUEST['import_script'];
		elseif (isset($_SESSION['import_script']) && file_exists(dirname(__FILE__) . DIRECTORY_SEPARATOR . $_SESSION['import_script']) && preg_match('~_importer\.xml$~', $_SESSION['import_script']) != 0)
			$this->_script = (string) $_SESSION['import_script'];
		else
		{
			$this->_script = '';
			unset($_SESSION['import_script']);
		}
	}

	/**
	 * Prepares the response to send to the template system
	 */
	public function getResponse()
	{
		// This is really quite simple; if ?delete is on the URL, delete the importer...
		if (isset($_GET['delete']))
		{
			$this->uninstall();

			$this->no_template = true;
		}
		elseif (isset($_GET['xml']))
			$this->is_xml = true;
		elseif (method_exists($this, 'doStep' . $_GET['step']))
			call_user_func(array($this, 'doStep' . $_GET['step']));
		else
			call_user_func(array($this, 'doStep0'));

		return $this;
	}

	/**
	 * Deletes the importer files from the server
	 * @todo doesn't know yet about the new structure.
	 */
	protected function uninstall()
	{
		@unlink(__FILE__);
		if (preg_match('~_importer\.xml$~', $_SESSION['import_script']) != 0)
			@unlink(dirname(__FILE__) . DIRECTORY_SEPARATOR . $_SESSION['import_script']);
		$_SESSION['import_script'] = null;
	}

	protected function _loadImporter($file)
	{
		$this->_preparse_xml($file);

		// This is the helper class
		$php_helper = str_replace('.xml', '.php', $file);
		require_once($php_helper);

		// Maybe the "destination" comes with php helper functions?
		$path = dirname($file);
		$possible_php = $path . '/' . basename($path) . '_importer.php';
		
		if (file_exists($possible_php))
			require_once($possible_php);

		if (isset($this->path_to) && !empty($_GET['step']))
			$this->_loadSettings();
	}

	/**
	 * loads the _importer.xml files
	 * @param string $file
	 * @throws ImportException
	 */
	private function _preparse_xml($file)
	{
		try
		{
			if (!$this->xml = simplexml_load_file($file, 'SimpleXMLElement', LIBXML_NOCDATA))
				throw new ImportException('XML-Syntax error in file: ' . $file);

			$this->xml = simplexml_load_file($file, 'SimpleXMLElement', LIBXML_NOCDATA);
		}
		catch (Exception $e)
		{
			ImportException::exception_handler($e);
		}
	}

	/**
	 * - checks,  if we have already specified an importer script
	 * - checks the file system for importer definition files
	 * @return boolean
	 * @throws ImportException
	 */
	private function _detect_scripts()
	{
		if (isset($_REQUEST['import_script']))
		{
			if ($_REQUEST['import_script'] != '' && preg_match('~^[a-z0-9\-_\.]*_importer\.xml$~i', $_REQUEST['import_script']) != 0)
				$_SESSION['import_script'] = preg_replace('~[\.]+~', '.', $_REQUEST['import_script']);
			else
				$_SESSION['import_script'] = null;
		}

		$dir = dirname(__FILE__) . '/Importers/';
		$sources = glob($dir . '*', GLOB_ONLYDIR);
		$all_scripts = array();
		$scripts = array();
		foreach ($sources as $source)
		{
			$from = basename($source);
			$scripts[$from] = array();
			$possible_scripts = glob($source . '/*_importer.xml');

			foreach ($possible_scripts as $entry)
			{
				try
				{
					if (!$xmlObj = simplexml_load_file($entry, 'SimpleXMLElement', LIBXML_NOCDATA))
						throw new ImportException('XML-Syntax error in file: ' . $entry);

					$xmlObj = simplexml_load_file($entry, 'SimpleXMLElement', LIBXML_NOCDATA);
					$scripts[$from][] = array('path' => $from . DIRECTORY_SEPARATOR . basename($entry), 'name' => $xmlObj->general->name);
					$all_scripts[] = array('path' => $from . DIRECTORY_SEPARATOR . basename($entry), 'name' => $xmlObj->general->name);
				}
				catch (Exception $e)
				{
					ImportException::exception_handler($e);
				}
			}
		}

		if (isset($_SESSION['import_script']))
		{
			if (count($all_scripts) > 1)
				$this->sources[$from] = $scripts[$from];
			return false;
		}

		if (count($all_scripts) == 1)
		{
			$_SESSION['import_script'] = basename($scripts[$from][0]['path']);
			if (substr($_SESSION['import_script'], -4) == '.xml')
				$this->_loadImporter(dirname(__FILE__) . DIRECTORY_SEPARATOR . $_SESSION['import_script']);
			return false;
		}

		$this->use_template = 'select_script';
		$this->params_template = array($scripts);

		return true;
	}

	/**
	 * prepare the importer with custom settings stuff
	 *
	 * @global Database $db
	 * @global type $to_prefix
	 * @global type $global
	 * @global type $varname
	 * @global type $global
	 * @return boolean|null
	 */
	private function _loadSettings()
	{
		global $db, $to_prefix;

		$this->settings = new $this->xml->general->className();

		if (method_exists($this->settings, 'setDefines'))
			$this->settings->setDefines();

		if (method_exists($this->settings, 'setGlobals'))
			$this->settings->setGlobals();

		//Dirty hack
		if (isset($_SESSION['store_globals']))
		{
			foreach ($_SESSION['store_globals'] as $varname => $value)
			{
				global $$varname;
				$$varname = $value;
			}
		}

		// catch form elements and globalize them for later use..
		if ($this->xml->general->form)
		{
			foreach ($this->xml->general->form->children() as $global)
				global $$global;
		}

		$found = $this->settings->loadSettings($this->path_from);

		if (!$found)
		{
			if (@ini_get('open_basedir') != '')
				return $this->doStep0(array($this->lng->get('imp.open_basedir'), (string) $this->xml->general->name));

			return $this->doStep0(array($this->lng->get('imp.config_not_found'), (string) $this->xml->general->name));
		}

		// Any custom form elements to speak of?
		if ($this->xml->general->form && !empty($_SESSION['import_parameters']))
		{
			foreach ($this->xml->general->form->children() as $param)
			{
				if (isset($_POST['field' . $param['id']]))
				{
					$var = (string) $param;
					$_SESSION['import_parameters']['field' .$param['id']][$var] = $_POST['field' .$param['id']];
				}
			}

			// Should already be global'd.
			foreach ($_SESSION['import_parameters'] as $id)
			{
				foreach ($id as $k => $v)
					$$k = $v;
			}
		}
		elseif ($this->xml->general->form)
		{
			$_SESSION['import_parameters'] = array();
			foreach ($this->xml->general->form->children() as $param)
			{
				$var = (string) $param;

				if (isset($_POST['field' .$param['id']]))
					$_SESSION['import_parameters']['field' .$param['id']][$var] = $_POST['field' .$param['id']];
				else
					$_SESSION['import_parameters']['field' .$param['id']][$var] = null;
			}

			foreach ($_SESSION['import_parameters'] as $id)
			{
				foreach ($id as $k => $v)
					$$k = $v;
			}
		}

		// Cannot find Settings.php?
		if (!file_exists($this->path_to . '/Settings.php'))
			return $this->doStep0($this->lng->get('imp.settings_not_found'));

		// Everything should be alright now... no cross server includes, we hope...
		require_once($this->path_to . '/Settings.php');
		$this->_boardurl = $boardurl;

		if ($this->data['db_pass'] != $db_passwd)
			return $this->doStep0($this->lng->get('imp.password_incorrect'), $this);

		// Check the steps that we have decided to go through.
		if (!isset($_POST['do_steps']) && !isset($_SESSION['do_steps']))
			return $this->doStep0($this->lng->get('imp.select_step'));

		elseif (isset($_POST['do_steps']))
		{
			unset($_SESSION['do_steps']);
			foreach ($_POST['do_steps'] as $key => $step)
				$_SESSION['do_steps'][$key] = $step;
		}
		try
		{
			$this->db = new Database($db_server, $db_user, $db_passwd, $db_persist);
			//We want UTF8 only, let's set our mysql connetction to utf8
			$this->db->query('SET NAMES \'utf8\'');
		}
		catch(Exception $e)
		{
			ImportException::exception_handler($e);
			die();
		}

		if (strpos($db_prefix, '.') === false)
		{
			$this->to_prefix = is_numeric(substr($db_prefix, 0, 1)) ? $db_name . '.' . $db_prefix : '`' . $db_name . '`.' . $db_prefix;
			$to_prefix = is_numeric(substr($db_prefix, 0, 1)) ? $db_name . '.' . $db_prefix : '`' . $db_name . '`.' . $db_prefix;
		}
		else
		{
			$to_prefix = $db_prefix;
			$this->to_prefix = $db_prefix;
		}
		// Custom functions? we need eval.

		if (isset($this->xml->general->custom_functions))
			eval($this->xml->general->custom_functions);

		// Custom variables from our importer?
		if (isset($this->xml->general->variables))
		{
			foreach ($this->xml->general->variables as $eval_me)
				eval($eval_me);
		}
		// Load the settings file.
		if (isset($this->xml->general->settings))
		{
			foreach ($this->xml->general->settings as $file)
			{
				if (file_exists($this->path_from . $file))
					require_once($this->path_from . $file);
			}
		}

		$this->from_prefix = $this->settings->getPrefix();

		if (preg_match('~^`[^`]+`.\d~', $this->from_prefix) != 0)
		{
			$this->from_prefix = strtr($this->from_prefix, array('`' => ''));
		}

		if ($_REQUEST['start'] == 0 && empty($_GET['substep']) && ($_GET['step'] == 1 || $_GET['step'] == 2) && isset($this->xml->general->table_test))
		{
			$result = $this->db->query('
				SELECT COUNT(*)
				FROM "' . $this->settings->getTableTest() . '"', true);

			if ($result === false)
				$this->doStep0($this->lng->get('imp.permission_denied') . mysqli_error($this->db->con), (string) $this->xml->general->name);

			$this->db->free_result($result);
		}

		$results = $this->db->query("SELECT @@SQL_BIG_SELECTS, @@MAX_JOIN_SIZE");
		list ($big_selects, $sql_max_join) = $this->db->fetch_row($results);

		// Only waste a query if its worth it.
		if (empty($big_selects) || ($big_selects != 1 && $big_selects != '1'))
			$this->db->query("SET @@SQL_BIG_SELECTS = 1");

		// Let's set MAX_JOIN_SIZE to something we should
		if (empty($sql_max_join) || ($sql_max_join == '18446744073709551615' && $sql_max_join == '18446744073709551615'))
			$this->db->query("SET @@MAX_JOIN_SIZE = 18446744073709551615");

	}

	/**
	 * Looks at the importer and returns the steps that it's able to make.
	 * @return int
	 */
	private function _find_steps()
	{
		$steps = array();
		$steps_count = 0;

		foreach ($this->xml->steps1->step as $xml_steps)
		{
			$steps_count++;

			$steps[$steps_count] = array(
				'name' => (string) $xml_steps->title,
				'count' => $steps_count,
				'mandatory' => (string) $xml_steps->attributes()->{'type'},
				'checked' => (string) $xml_steps->attributes()->{'checked'} == 'false' ? '' : 'checked="checked"',
			);
		}
		return $steps;
	}

	/**
	* used to replace {$from_prefix} and {$to_prefix} with its real values.
	*
	* @param string string string in which parameters are replaced
	* @return string
	*/
	private function _fix_params($string)
	{
		if (isset($_SESSION['import_parameters']))
		{
			foreach ($_SESSION['import_parameters'] as $param)
			{
				foreach ($param as $key => $value)
					$string = strtr($string, array('{$' . $key . '}' => $value));
			}
		}
		$string = strtr($string, array('{$from_prefix}' => $this->from_prefix, '{$to_prefix}' => $this->to_prefix));

		return $string;
	}

	/**
	* placehoder function to convert IPV4 to IPV6
	* @TODO convert IPV4 to IPV6
	* @param string $ip
	* @return string $ip
	*/
	private function _prepare_ipv6($ip)
	{
		return $ip;
	}

	/**
	 * collects all the important things, the importer can't do anything
	 * without this information.
	 *
	 * @global Database $db
	 * @global type $to_prefix
	 * @global type $import_script
	 * @global type $cookie
	 * @global type $import
	 * @param type $error_message
	 * @param type $object
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

		// If these aren't set (from an error..) default to the current directory.
		if (!isset($this->path_from))
			$this->path_from = dirname(__FILE__);
		if (!isset($this->path_to))
			$this->path_to = dirname(__FILE__);

		$test_from = $this->testFiles($this->xml->general->settings, $this->path_from);
		$test_to = $this->testFiles('Settings.php', $this->path_to);

		// Was an error message specified?
		if ($error_message !== null)
		{
			$this->template_error = true;
			$this->error_params[] = $error_message;
		}

		$this->use_template = 'step0';
		$this->params_template = array($this, $this->_find_steps(), $test_from, $test_to);

		if ($error_message !== null)
		{
			$this->template->footer();
			exit;
		}

		return;
	}

	protected function testFiles($files, $path)
	{
		$files = (array) $files;

		$test = empty($files);

		foreach ($files as $file)
			$test |= @file_exists($path . DIRECTORY_SEPARATOR . $file);

		return $test;
	}

	/**
	 * the important one, transfer the content from the source forum to our
	 * destination system
	 *
	 * @global type $to_prefix
	 * @global type $global
	 * @return boolean
	 */
	public function doStep1()
	{
		global $to_prefix;

		if ($this->xml->general->globals)
			foreach (explode(',', $this->xml->general->globals) as $global)
				global $$global;

		$this->cookie->set(array($this->path_to, $this->path_from));
		$substep = 0;
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
		{
			$progress_counter = 0;
			$counter_current_step = 0;

			// loop through each step
			foreach ($this->xml->steps1->step as $counts)
			{
				if ($counts->detect)
				{
					$count = $this->_fix_params((string) $counts->detect);
					$request = $this->db->query("
						SELECT COUNT(*)
						FROM $count", true);

					if (!empty($request))
					{
						list ($current) = $this->db->fetch_row($request);
						$this->db->free_result($request);
					}

					$progress_counter = $progress_counter + $current;

					$_SESSION['import_steps'][$counter_current_step]['counter'] = $current;
				}
				$counter_current_step++;
			}
			$_SESSION['import_overall'] = $progress_counter;
		}
		if(!isset($_SESSION['import_progress']))
			$_SESSION['import_progress'] = 0;

		foreach ($this->xml->steps1->step as $step)
			$this->_processSteps($step, $substep, $do_steps);

		$_GET['substep'] = 0;
		$_REQUEST['start'] = 0;

		return $this->doStep2();
	}

	protected function _processSteps($step, &$substep, &$do_steps)
	{
		$to_prefix = $this->to_prefix;

		// Reset some defaults
		$current_data = '';
		$special_table = null;
		$special_code = null;

		// Increase the substep slightly...
		pastTime(++$substep);

		$_SESSION['import_steps'][$substep]['title'] = (string) $step->title;
		if (!isset($_SESSION['import_steps'][$substep]['status']))
			$_SESSION['import_steps'][$substep]['status'] = 0;

		// any preparsing code here?
		if (isset($step->preparsecode) && !empty($step->preparsecode))
			$special_code = $this->_fix_params((string) $step->preparsecode);

		$do_current = $substep >= $_GET['substep'];

		if (!in_array($substep, $do_steps))
		{
			$_SESSION['import_steps'][$substep]['status'] = 2;
			$_SESSION['import_steps'][$substep]['presql'] = true;
		}
		// Detect the table, then count rows.. 
		elseif ($step->detect)
		{
			$count = $this->_fix_params((string) $step->detect);
			$table_test = $this->db->query("
				SELECT COUNT(*)
				FROM $count", true);

			if ($table_test === false)
			{
				$_SESSION['import_steps'][$substep]['status'] = 3;
				$_SESSION['import_steps'][$substep]['presql'] = true;
			}
		}

		$this->template->status($substep, $_SESSION['import_steps'][$substep]['status'], $_SESSION['import_steps'][$substep]['title']);

		// do we need to skip this step?
		if ((isset($table_test) && $table_test === false) || !in_array($substep, $do_steps))
		{
			// reset some defaults
			$current_data = '';
			$special_table = null;
			$special_code = null;
		}

		// pre sql queries first!!
		if (isset($step->presql) && !isset($_SESSION['import_steps'][$substep]['presql']))
		{
			$presql = $this->_fix_params((string) $step->presql);
			$presql_array = explode(';', $presql);
			if (isset($presql_array) && is_array($presql_array))
			{
				array_pop($presql_array);
				foreach ($presql_array as $exec)
					$this->db->query($exec . ';');
			}
			else
				$this->db->query($presql);
			// don't do this twice..
			$_SESSION['import_steps'][$substep]['presql'] = true;
		}

		if ($special_table === null)
		{
			$special_table = strtr(trim((string) $step->destination), array('{$to_prefix}' => $this->to_prefix));
			$special_limit = 500;
		}
		else
			$special_table = null;

		if (isset($step->query))
			$current_data = substr(rtrim($this->_fix_params((string) $step->query)), 0, -1);

		if (isset($step->options->limit))
			$special_limit = $step->options->limit;

		if (!$do_current)
		{
			$current_data = '';
		}

		// codeblock?
		if (isset($step->code))
		{
			// execute our code block
			$special_code = $this->_fix_params((string) $step->code);
			eval($special_code);
			// reset some defaults
			$current_data = '';
			$special_table = null;
			$special_code = null;
			if ($_SESSION['import_steps'][$substep]['status'] == 0)
				$this->template->status($substep, 1, false, true);
			$_SESSION['import_steps'][$substep]['status'] = 1;
			flush();
		}

		// sql block?
		if (!empty($step->query))
		{
			if (strpos($current_data, '{$') !== false)
				$current_data = eval('return "' . addcslashes($current_data, '\\"') . '";');

			if (isset($step->detect))
			{
				$counter = 0;

				$count = $this->_fix_params((string) $step->detect);
				$result2 = $this->db->query("
					SELECT COUNT(*)
					FROM $count");
				list ($counter) = $this->db->fetch_row($result2);
				//$this->count->$substep = $counter;
				$this->db->free_result($result2);
			}

			// create some handy shortcuts
			$ignore = ((isset($step->options->ignore) && $step->options->ignore == false) || isset($step->options->replace)) ? false : true;
			$replace = (isset($step->options->replace) && $step->options->replace == true) ? true : false;
			$no_add = (isset($step->options->no_add) && $step->options->no_add == true) ? true : false;
			$ignore_slashes = (isset($step->options->ignore_slashes) && $step->options->ignore_slashes == true) ? true : false;

			if (isset($import_table) && $import_table !== null && strpos($current_data, '%d') !== false)
			{
				preg_match('~FROM [(]?([^\s,]+)~i', (string) $step->detect, $match);
				if (!empty($match))
				{
					$result = $this->db->query("
						SELECT COUNT(*)
						FROM $match[1]");
					list ($special_max) = $this->db->fetch_row($result);
					$this->db->free_result($result);
				}
				else
					$special_max = 0;
			}
			else
				$special_max = 0;

			if ($special_table === null)
				$this->db->query($current_data);

			else
			{
				// Are we doing attachments? They're going to want a few things...
				if ($special_table == $this->to_prefix . 'attachments')
				{
					if (!isset($id_attach, $attachmentUploadDir, $avatarUploadDir))
					{
						$result = $this->db->query("
							SELECT MAX(id_attach) + 1
							FROM {$to_prefix}attachments");
						list ($id_attach) = $this->db->fetch_row($result);
						$this->db->free_result($result);

						$result = $this->db->query("
							SELECT value
							FROM {$to_prefix}settings
							WHERE variable = 'attachmentUploadDir'
							LIMIT 1");
						list ($attachmentdir) = $this->db->fetch_row($result);
						$attachment_UploadDir = @unserialize($attachmentdir);
						$attachmentUploadDir = !empty($attachment_UploadDir[1]) && is_array($attachment_UploadDir[1]) ? $attachment_UploadDir[1] : $attachmentdir;

						$result = $this->db->query("
							SELECT value
							FROM {$to_prefix}settings
							WHERE variable = 'custom_avatar_dir'
							LIMIT 1");
						list ($avatarUploadDir) = $this->db->fetch_row($result);
						$this->db->free_result($result);

						if (empty($avatarUploadDir))
							$avatarUploadDir = $attachmentUploadDir;

						if (empty($id_attach))
							$id_attach = 1;
					}
				}

				while (true)
				{
					pastTime($substep);

					if (strpos($current_data, '%d') !== false)
						$special_result = $this->db->query(sprintf($current_data, $_REQUEST['start'], $_REQUEST['start'] + $special_limit - 1) . "\n" . 'LIMIT ' . $special_limit);
					else
						$special_result = $this->db->query($current_data . "\n" . 'LIMIT ' . $_REQUEST['start'] . ', ' . $special_limit);

					$rows = array();
					$keys = array();

					if (isset($step->detect))
						$_SESSION['import_progress'] += $special_limit;

					while ($row = $this->db->fetch_assoc($special_result))
					{
						if ($special_code !== null)
							eval($special_code);

						// Here we have various bits of custom code for some known problems global to all importers.
						if ($special_table == $this->to_prefix . 'members')
						{
							// Let's ensure there are no illegal characters.
							$row['member_name'] = preg_replace('/[<>&"\'=\\\]/is', '', $row['member_name']);
							$row['real_name'] = trim($row['real_name'], " \t\n\r\x0B\0\xA0");

							if (strpos($row['real_name'], '<') !== false || strpos($row['real_name'], '>') !== false || strpos($row['real_name'], '& ') !== false)
								$row['real_name'] = htmlspecialchars($row['real_name'], ENT_QUOTES);
							else
								$row['real_name'] = strtr($row['real_name'], array('\'' => '&#039;'));
						}

						// this is wedge specific stuff and will move at some point.
						// prepare ip address conversion
						if (isset($this->xml->general->ip_to_ipv6))
						{
							$convert_ips = explode(',', $this->xml->general->ip_to_ipv6);
							foreach ($convert_ips as $ip)
							{
								$ip = trim($ip);
								if (array_key_exists($ip, $row))
									$row[$ip] = $this->_prepare_ipv6($row[$ip]);
							}
						}
						// prepare ip address conversion to a pointer
						if (isset($this->xml->general->ip_to_pointer))
						{
							$ips_to_pointer = explode(',', $this->xml->general->ip_to_pointer);
							foreach ($ips_to_pointer as $ip)
							{
								$ip = trim($ip);
								if (array_key_exists($ip, $row))
								{
									$ipv6ip = $this->_prepare_ipv6($row[$ip]);

									$request2 = $this->db->query("
										SELECT id_ip
										FROM {$to_prefix}log_ips
										WHERE member_ip = '" . $ipv6ip . "'
										LIMIT 1");
									// IP already known?
									if ($this->db->num_rows($request2) != 0)
									{
										list ($id_ip) = $this->db->fetch_row($request2);
										$row[$ip] = $id_ip;
									}
									// insert the new ip
									else
									{
										$this->db->query("
											INSERT INTO {$to_prefix}log_ips
												(member_ip)
											VALUES ('$ipv6ip')");
										$pointer = $this->db->insert_id();
										$row[$ip] = $pointer;
									}

									$this->db->free_result($request2);
								}
							}
						}
						// fixing the charset, we need proper utf-8
						$row = fix_charset($row);

						// If we have a message here, we'll want to convert <br /> to <br>.
						if (isset($row['body']))
							$row['body'] = str_replace(array(
									'<br />', '&#039;', '&#39;', '&quot;'
								), array(
									'<br>', '\'', '\'', '"'
								), $row['body']
							);

						if (empty($no_add) && empty($ignore_slashes))
							$rows[] = "'" . implode("', '", addslashes_recursive($row)) . "'";
						elseif (empty($no_add) && !empty($ignore_slashes))
							$rows[] = "'" . implode("', '", $row) . "'";
						else
							$no_add = false;

						if (empty($keys))
							$keys = array_keys($row);
					}

					$insert_ignore = (isset($ignore) && $ignore == true) ? 'IGNORE' : '';
					$insert_replace = (isset($replace) && $replace == true) ? 'REPLACE' : 'INSERT';

					if (!empty($rows))
						$this->db->query("
							$insert_replace $insert_ignore INTO $special_table
								(" . implode(', ', $keys) . ")
							VALUES (" . implode('),
								(', $rows) . ")");
					$_REQUEST['start'] += $special_limit;
					if (empty($special_max) && $this->db->num_rows($special_result) < $special_limit)
						break;
					elseif (!empty($special_max) && $this->db->num_rows($special_result) == 0 && $_REQUEST['start'] > $special_max)
						break;
					$this->db->free_result($special_result);
				}
			}
			$_REQUEST['start'] = 0;
			$special_code = null;
			$current_data = '';
		}
		if ($_SESSION['import_steps'][$substep]['status'] == 0)
			$this->template->status($substep, 1, false, true);

		$_SESSION['import_steps'][$substep]['status'] = 1;
		flush();
	}

	/**
	 * we have imported the old database, let's recalculate the forum statistics.
	 *
	 * @global Database $db
	 * @global type $to_prefix
	 * @return boolean
	 */
	public function doStep2()
	{
		$_GET['step'] = '2';

		$this->template->step2();

		if ($this->xml->steps2->className !== null)
		{
			$instance = new $this->xml->steps2->className($this->db, $this->to_prefix);

			$methods = get_class_methods($instance);
			$substeps = array();
			$substep = 0;
			foreach ($methods as $method)
			{
				if (substr($method, 0, 7) !== 'substep')
					continue;

				$substeps[substr($method, 7)] = $method;
			}
			ksort($substeps);

			foreach ($substeps as $key => $method)
			{
				if ($_GET['substep'] <= $key)
				{
					call_user_func(array($instance, $method));
				}

				$substep++;
				pastTime($substep);
			}

			$this->template->status($key + 1, 1, false, true);
		}

		return $this->doStep3();
	}

	/**
	 * we are done :)
	 *
	 * @global Database $db
	 * @global type $boardurl
	 * @return boolean
	 */
	public function doStep3()
	{
		global $boardurl;

		foreach ($this->xml->steps3->step as $step)
		{
			$this->_processSteps($step);
		}

		$writable = (is_writable(dirname(__FILE__)) && is_writable(__FILE__));

		$this->use_template = 'step3';
		$this->params_template = array($this->xml->general->name, $boardurl, $writable);

		unset ($_SESSION['import_steps'], $_SESSION['import_progress'], $_SESSION['import_overall']);
		return true;
	}

}

/**
 * Checks if we've passed a time limit..
 *
 * @param int $substep
 * @param int $stop_time
 * @return null
 */
function pastTime($substep = null, $stop_time = 5)
{
	global $import, $time_start;

	if (isset($_GET['substep']) && $_GET['substep'] < $substep)
		$_GET['substep'] = $substep;

	// some details for our progress bar
	if (isset($import->count->$substep) && $import->count->$substep > 0 && isset($_REQUEST['start']) && $_REQUEST['start'] > 0 && isset($substep))
		$bar = round($_REQUEST['start'] / $import->count->$substep * 100, 0);
	else
		$bar = false;

	@set_time_limit(300);
	if (is_callable('apache_reset_timeout'))
		apache_reset_timeout();

	if (time() - $time_start < $stop_time)
		return;

	$import->template->time_limit($bar, $_SESSION['import_progress'], $_SESSION['import_overall']);
	$import->template->footer();
	exit;
}

/**
 * helper function, simple file copy at all
 *
 * @param string $filename
 * @return boolean
 */
function copy_file($source, $destination)
{
	if (is_file($source))
	{
		copy($source, $destination);
		return false;
	}
	return true;
}

/**
 * // Add slashes recursively...
 *
 * @param array $var
 * @return array
 */
function addslashes_recursive($var)
{
	if (!is_array($var))
		return addslashes($var);
	else
	{
		foreach ($var as $k => $v)
			$var[$k] = addslashes_recursive($v);
		return $var;
	}
}

/**
 * Remove slashes recursively...
 *
 * @param array $var
 * @return array
 */
function stripslashes_recursive($var, $level = 0)
{
	if (!is_array($var))
		return stripslashes($var);

	// Reindex the array without slashes, this time.
	$new_var = array();

	// Strip the slashes from every element.
	foreach ($var as $k => $v)
		$new_var[stripslashes($k)] = $level > 25 ? null : stripslashes_recursive($v, $level + 1);

	return $new_var;
}

/**
 * function copy_dir copies a directory
 * @param string $source
 * @param string $dest
 * @return type
 */
function copy_dir($source, $dest)
{
	if (!is_dir($source) || !($dir = opendir($source)))
		return;
		while ($file = readdir($dir))
	{
		if ($file == '.' || $file == '..')
			continue;
			// If we have a directory create it on the destination and copy contents into it!
		if (is_dir($source . DIRECTORY_SEPARATOR. $file))
		{
			if (!is_dir($dest))
				@mkdir($dest, 0777);
			copy_dir($source . DIRECTORY_SEPARATOR . $file, $dest . DIRECTORY_SEPARATOR . $file);
		}
		else
		{
			if (!is_dir($dest))
				@mkdir($dest, 0777);
			copy($source . DIRECTORY_SEPARATOR . $file, $dest . DIRECTORY_SEPARATOR . $file);
		}
	}
	closedir($dir);
}

/**
 * detects, if a string is utf-8 or not
 * @param type $string
 * @return boolean
 */
 function is_utf8($string)
{
	return utf8_encode(utf8_decode($string)) == $string;
}

/**
 * Function fix based on ForceUTF8 by Sebastián Grignoli <grignoli@framework2.com.ar>
 * @link http://www.framework2.com.ar/dzone/forceUTF8-es/
 * This function leaves UTF8 characters alone, while converting almost all non-UTF8 to UTF8.
 *
 * It may fail to convert characters to unicode if they fall into one of these scenarios:
 *
 * 1) when any of these characters:   ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖ×ØÙÚÛÜÝÞß
 *    are followed by any of these:  ("group B")
 *                                    ¡¢£¤¥¦§¨©ª«¬­®¯°±²³´µ¶•¸¹º»¼½¾¿
 * For example:   %ABREPR%C9SENT%C9%BB. «REPRÉSENTÉ»
 * The "«" (%AB) character will be converted, but the "É" followed by "»" (%C9%BB)
 * is also a valid unicode character, and will be left unchanged.
 *
 * 2) when any of these: àáâãäåæçèéêëìíîï  are followed by TWO chars from group B,
 * 3) when any of these: ðñòó  are followed by THREE chars from group B.
 *
 * @name fix
 * @param string $text  Any string.
 * @return string  The same string, UTF8 encoded
 */
function fix_charset($text)
{
	if (is_array($text))
	{
		foreach ($text as $k => $v)
			$text[$k] = fix_charset($v);
		return $text;
	}
	// numeric? There's nothing to do, we simply return our input.
	if (is_numeric($text))
		return $text;

	$max = strlen($text);
	$buf = '';

	for ($i = 0; $i < $max; $i++)
	{
		$c1 = $text{$i};
		if ($c1 >= "\xc0")
		{
			// Should be converted to UTF8, if it's not UTF8 already
			$c2 = $i+1 >= $max? "\x00" : $text{$i+1};
			$c3 = $i+2 >= $max? "\x00" : $text{$i+2};
			$c4 = $i+3 >= $max? "\x00" : $text{$i+3};
			if ($c1 >= "\xc0" & $c1 <= "\xdf")
			{
				// looks like 2 bytes UTF8
				if ($c2 >= "\x80" && $c2 <= "\xbf")
				{
					// yeah, almost sure it's UTF8 already
					$buf .= $c1 . $c2;
					$i++;
				}
				else
				{
					// not valid UTF8. Convert it.
					$cc1 = (chr(ord($c1) / 64) | "\xc0");
					$cc2 = ($c1 & "\x3f") | "\x80";
					$buf .= $cc1 . $cc2;
				}
			}
			elseif ($c1 >= "\xe0" & $c1 <= "\xef")
			{
				// looks like 3 bytes UTF8
				if ($c2 >= "\x80" && $c2 <= "\xbf" && $c3 >= "\x80" && $c3 <= "\xbf")
				{
					// yeah, almost sure it's UTF8 already
					$buf .= $c1 . $c2 . $c3;
					$i = $i + 2;
				}
				else
				{
					// not valid UTF8. Convert it.
					$cc1 = (chr(ord($c1) / 64) | "\xc0");
					$cc2 = ($c1 & "\x3f") | "\x80";
					$buf .= $cc1 . $cc2;
				}
			}
			elseif ($c1 >= "\xf0" & $c1 <= "\xf7")
			{
				// Looks like 4-byte UTF8
				if ($c2 >= "\x80" && $c2 <= "\xbf" && $c3 >= "\x80" && $c3 <= "\xbf" && $c4 >= "\x80" && $c4 <= "\xbf")
				{
					// Yeah, almost sure it's UTF8 already
					$buf .= $c1 . $c2 . $c3;
					$i = $i + 2;
				}
				else
				{
					// Not valid UTF8. Convert it.
					$cc1 = (chr(ord($c1) / 64) | "\xc0");
					$cc2 = ($c1 & "\x3f") | "\x80";
					$buf .= $cc1 . $cc2;
				}
			}
			else
			{
				// Doesn't look like UTF8, but should be converted
				$cc1 = (chr(ord($c1) / 64) | "\xc0");
				$cc2 = (($c1 & "\x3f") | "\x80");
				$buf .= $cc1 . $cc2;
			}
		}
		elseif (($c1 & "\xc0") == "\x80")
		{
			// Needs conversion
			$cc1 = (chr(ord($c1) / 64) | "\xc0");
			$cc2 = (($c1 & "\x3f") | "\x80");
			$buf .= $cc1 . $cc2;
		}
		else
			// Doesn't need conversion
			$buf .= $c1;
	}
	if (function_exists('mb_decode_numericentity'))
		$buf = mb_decode_numericentity($buf, array(0x80, 0x2ffff, 0, 0xffff), 'UTF-8');
	else
	{
		// Take care of html entities..
		$entity_replace = create_function('$num', '
			return $num < 0x20 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF) ? \'\' :
				  ($num < 0x80 ? \'&#\' . $num . \';\' : ($num < 0x800 ? chr(192 | $num >> 6) . chr(128 | $num & 63) :
				  ($num < 0x10000 ? chr(224 | $num >> 12) . chr(128 | $num >> 6 & 63) . chr(128 | $num & 63) :
				  chr(240 | $num >> 18) . chr(128 | $num >> 12 & 63) . chr(128 | $num >> 6 & 63) . chr(128 | $num & 63))));');

		// @todo use preg_replace_callback
		$buf = preg_replace('~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~e', '$entity_replace(\\2)', $buf);
		$buf = preg_replace('~(&#x(\d{1,7}|x[0-9a-fA-F]{1,6});)~e', '$entity_replace(0x\\2)', $buf);
	}

	// surprise, surprise... the string
	return $buf;
}

/**
 * helper function for storing vars that need to be global
 *
 * @param string $variable
 * @param string $value
 */
function store_global($variable, $value)
{
	$_SESSION['store_globals'][$variable] = $value;
}

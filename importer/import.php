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
	 *
	 * @var string The importer script which will be used for the import.
	 */
	private $_script;

	/**
	 *
	 * @var string This is the URL from our Installation. 
	 */
	private $_boardurl;

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

		$this->_cleanupServerSettings();

		$this->_findScript();

		// The current step - starts at 0.
		$_GET['step'] = isset($_GET['step']) ? (int) @$_GET['step'] : 0;
		$_REQUEST['start'] = isset($_REQUEST['start']) ? (int) @$_REQUEST['start'] : 0;

		// Check for the password...
		if (isset($_POST['db_pass']))
			$_SESSION['import_db_pass'] = $_POST['db_pass'];
		elseif (isset($_SESSION['import_db_pass']))
			$_POST['db_pass'] = $_SESSION['import_db_pass'];

		if (isset($_SESSION['import_paths']) && !isset($_POST['path_from']) && !isset($_POST['path_to']))
			list ($_POST['path_from'], $_POST['path_to']) = $_SESSION['import_paths'];
		elseif (isset($_POST['path_from']) || isset($_POST['path_to']))
		{
			if (isset($_POST['path_from']))
				$_POST['path_from'] = substr($_POST['path_from'], -1) == DIRECTORY_SEPARATOR ? substr($_POST['path_from'], 0, -1) : $_POST['path_from'];
			if (isset($_POST['path_to']))
				$_POST['path_to'] = substr($_POST['path_to'], -1) == DIRECTORY_SEPARATOR ? substr($_POST['path_to'], 0, -1) : $_POST['path_to'];

			$_SESSION['import_paths'] = array(@$_POST['path_from'], @$_POST['path_to']);
		}

		if (!empty($this->_script))
			$this->_preparse_xml(dirname(__FILE__) . DIRECTORY_SEPARATOR . $this->_script);
	}

	/**
	 * Some serevr settings require more work to be fit for the purpose
	 */
	protected function _cleanupServerSettings()
	{
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

		if (isset($_POST['path_to']) && !empty($_GET['step']))
			$this->_loadSettings();
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
				$this->_preparse_xml(dirname(__FILE__) . DIRECTORY_SEPARATOR . $_SESSION['import_script']);
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

		if ($this->xml->general->defines)
			foreach ($this->xml->general->defines as $define)
			{
				$define = explode('=', $define);
				define($define[0], isset($define[1]) ? $define[1] : '1');
			}

		if ($this->xml->general->globals)
			foreach (explode(',', $this->xml->general->globals) as $global)
				global $$global;

		//Dirty hack
		if (isset($_SESSION['store_globals']))
			foreach ($_SESSION['store_globals'] as $varname => $value)
			{
				global $$varname;
				$$varname = $value;
			}

		// catch form elements and globalize them for later use..
		if ($this->xml->general->form)
			foreach ($this->xml->general->form->children() as $global)
				global $$global;

		// Cannot find Settings.php?
		if (!file_exists($_POST['path_to'] . '/Settings.php'))
			return $this->doStep0($this->lng->get('imp.settings_not_found'));

		$found = empty($this->xml->general->settings);

		foreach ($this->xml->general->settings as $file)
			$found |= @file_exists($_POST['path_from'] . stripslashes($file));

		if (@ini_get('open_basedir') != '' && !$found)
			return $this->doStep0(array($this->lng->get('imp.open_basedir'), (string) $this->xml->general->name));

		if (!$found)
			return $this->doStep0(array($this->lng->get('imp.config_not_found'), (string) $this->xml->general->name));

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
		// Everything should be alright now... no cross server includes, we hope...
		require_once($_POST['path_to'] . '/Settings.php');
		$this->_boardurl = $boardurl;

		if ($_SESSION['import_db_pass'] != $db_passwd)
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
				if (file_exists($_POST['path_from'] . $file))
					require_once($_POST['path_from'] . $file);
			}
		}

		if (isset($this->xml->general->from_prefix))
		{
			$from_prefix = eval('return "' . $this->xml->general->from_prefix . '";');
			$this->from_prefix = eval('return "' . $this->xml->general->from_prefix . '";');
		}
		if (preg_match('~^`[^`]+`.\d~', $this->from_prefix) != 0)
		{
			$from_prefix = strtr($from_prefix, array('`' => ''));
			$this->from_prefix = strtr($this->from_prefix, array('`' => ''));
		}

		if ($_REQUEST['start'] == 0 && empty($_GET['substep']) && ($_GET['step'] == 1 || $_GET['step'] == 2) && isset($this->xml->general->table_test))
		{
			$result = $this->db->query("
				SELECT COUNT(*)
				FROM " . eval('return "' . $this->xml->general->table_test . '";'), true);
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
		$this->cookie -> destroy();
		//previously imported? we need to clean some variables ..
		unset($_SESSION['import_overall'], $_SESSION['import_steps']);

		if ($this->_detect_scripts())
			return true;

		// If these aren't set (from an error..) default to the current directory.
		if (!isset($_POST['path_from']))
			$_POST['path_from'] = dirname(__FILE__);
		if (!isset($_POST['path_to']))
			$_POST['path_to'] = dirname(__FILE__);

		$test_from = $this->testFiles($this->xml->general->settings, $_POST['path_from']);
		$test_to = $this->testFiles('Settings.php', $_POST['path_to']);

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
			$template->footer();
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

		$this->cookie->set(array($_POST['path_to'], $_POST['path_from']));
		$current_data = '';
		$substep = 0;
		$special_table = null;
		$special_code = null;
		$_GET['substep'] = isset($_GET['substep']) ? (int) @$_GET['substep'] : 0;
		// @TODO: check if this is needed
		//$progress = ($_GET['substep'] ==  0 ? 1 : $_GET['substep']);

		// Skipping steps?
		if (isset($_SESSION['do_steps']))
			$do_steps = $_SESSION['do_steps'];

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

		$this->_processSteps($this->xml->steps1->step);

		$_GET['substep'] = 0;
		$_REQUEST['start'] = 0;

		return $this->doStep2();
	}

	protected function _processSteps($steps)
	{
		foreach ($steps as $step)
		{
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
				continue;
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
				continue;
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
				continue;
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
		global $db, $to_prefix;

		$_GET['step'] = '2';

		$this->template->step2();

		if ($_GET['substep'] <= 0)
		{
			// Get all members with wrong number of personal messages.
			$request = $this->db->query("
				SELECT mem.id_member, COUNT(pmr.id_pm) AS real_num, mem.personal_messages
				FROM {$to_prefix}members AS mem
					LEFT JOIN {$to_prefix}pm_recipients AS pmr ON (mem.id_member = pmr.id_member AND pmr.deleted = 0)
				GROUP BY mem.id_member
				HAVING real_num != personal_messages");
			while ($row = $this->db->fetch_assoc($request))
			{
				$this->db->query("
					UPDATE {$to_prefix}members
					SET personal_messages = $row[real_num]
					WHERE id_member = $row[id_member]
					LIMIT 1");

				pastTime(0);
			}
			$this->db->free_result($request);

			$request = $this->db->query("
				SELECT mem.id_member, COUNT(pmr.id_pm) AS real_num, mem.unread_messages
				FROM {$to_prefix}members AS mem
					LEFT JOIN {$to_prefix}pm_recipients AS pmr ON (mem.id_member = pmr.id_member AND pmr.deleted = 0 AND pmr.is_read = 0)
				GROUP BY mem.id_member
				HAVING real_num != unread_messages");
			while ($row = $this->db->fetch_assoc($request))
			{
				$this->db->query("
					UPDATE {$to_prefix}members
					SET unread_messages = $row[real_num]
					WHERE id_member = $row[id_member]
					LIMIT 1");

				pastTime(0);
			}
			$this->db->free_result($request);

			pastTime(1);
		}

		if ($_GET['substep'] <= 1)
		{
			$request = $this->db->query("
				SELECT id_board, MAX(id_msg) AS id_last_msg, MAX(modified_time) AS last_edited
				FROM {$to_prefix}messages
				GROUP BY id_board");
			$modifyData = array();
			$modifyMsg = array();
			while ($row = $this->db->fetch_assoc($request))
			{
				$this->db->query("
					UPDATE {$to_prefix}boards
					SET id_last_msg = $row[id_last_msg], id_msg_updated = $row[id_last_msg]
					WHERE id_board = $row[id_board]
					LIMIT 1");
				$modifyData[$row['id_board']] = array(
					'last_msg' => $row['id_last_msg'],
					'last_edited' => $row['last_edited'],
				);
				$modifyMsg[] = $row['id_last_msg'];
			}
			$this->db->free_result($request);

			// Are there any boards where the updated message is not the last?
			if (!empty($modifyMsg))
			{
				$request = $this->db->query("
					SELECT id_board, id_msg, modified_time, poster_time
					FROM {$to_prefix}messages
					WHERE id_msg IN (" . implode(',', $modifyMsg) . ")");
				while ($row = $this->db->fetch_assoc($request))
				{
					// Have we got a message modified before this was posted?
					if (max($row['modified_time'], $row['poster_time']) < $modifyData[$row['id_board']]['last_edited'])
					{
						// Work out the ID of the message (This seems long but it won't happen much.
						$request2 = $this->db->query("
							SELECT id_msg
							FROM {$to_prefix}messages
							WHERE modified_time = " . $modifyData[$row['id_board']]['last_edited'] . "
							LIMIT 1");
						if ($this->db->num_rows($request2) != 0)
						{
							list ($id_msg) = $this->db->fetch_row($request2);

							$this->db->query("
								UPDATE {$to_prefix}boards
								SET id_msg_updated = $id_msg
								WHERE id_board = $row[id_board]
								LIMIT 1");
						}
						$this->db->free_result($request2);
					}
				}
				$this->db->free_result($request);
			}

			pastTime(2);
		}

		if ($_GET['substep'] <= 2)
		{
			$request = $this->db->query("
				SELECT id_group
				FROM {$to_prefix}membergroups
				WHERE min_posts = -1");
			$all_groups = array();
			while ($row = $this->db->fetch_assoc($request))
				$all_groups[] = $row['id_group'];
			$this->db->free_result($request);

			$request = $this->db->query("
				SELECT id_board, member_groups
				FROM {$to_prefix}boards
				WHERE FIND_IN_SET(0, member_groups)");
			while ($row = $this->db->fetch_assoc($request))
				$this->db->query("
					UPDATE {$to_prefix}boards
					SET member_groups = '" . implode(',', array_unique(array_merge($all_groups, explode(',', $row['member_groups'])))) . "'
					WHERE id_board = $row[id_board]
					LIMIT 1");
			$this->db->free_result($request);

			pastTime(3);
		}

		if ($_GET['substep'] <= 3)
		{
			// Get the number of messages...
			$result = $this->db->query("
				SELECT COUNT(*) AS totalMessages, MAX(id_msg) AS maxMsgID
				FROM {$to_prefix}messages");
			$row = $this->db->fetch_assoc($result);
			$this->db->free_result($result);

			// Update the latest member. (Highest ID_MEMBER)
			$result = $this->db->query("
				SELECT id_member AS latestMember, real_name AS latestreal_name
				FROM {$to_prefix}members
				ORDER BY id_member DESC
				LIMIT 1");
			if ($this->db->num_rows($result))
				$row += $this->db->fetch_assoc($result);
			$this->db->free_result($result);

			// Update the member count.
			$result = $this->db->query("
				SELECT COUNT(*) AS totalMembers
				FROM {$to_prefix}members");
			$row += $this->db->fetch_assoc($result);
			$this->db->free_result($result);

			// Get the number of topics.
			$result = $this->db->query("
				SELECT COUNT(*) AS totalTopics
				FROM {$to_prefix}topics");
			$row += $this->db->fetch_assoc($result);
			$this->db->free_result($result);

			$this->db->query("
				REPLACE INTO {$to_prefix}settings
					(variable, value)
				VALUES ('latestMember', '$row[latestMember]'),
					('latestreal_name', '$row[latestreal_name]'),
					('totalMembers', '$row[totalMembers]'),
					('totalMessages', '$row[totalMessages]'),
					('maxMsgID', '$row[maxMsgID]'),
					('totalTopics', '$row[totalTopics]'),
					('disableHashTime', " . (time() + 7776000) . ")");

			pastTime(4);
		}

		if ($_GET['substep'] <= 4)
		{
			$request = $this->db->query("
				SELECT id_group, min_posts
				FROM {$to_prefix}membergroups
				WHERE min_posts != -1
				ORDER BY min_posts DESC");
			$post_groups = array();
			while ($row = $this->db->fetch_assoc($request))
				$post_groups[$row['min_posts']] = $row['id_group'];
			$this->db->free_result($request);

			$request = $this->db->query("
				SELECT id_member, posts
				FROM {$to_prefix}members");
			$mg_updates = array();
			while ($row = $this->db->fetch_assoc($request))
			{
				$group = 4;
				foreach ($post_groups as $min_posts => $group_id)
					if ($row['posts'] >= $min_posts)
					{
						$group = $group_id;
						break;
					}

				$mg_updates[$group][] = $row['id_member'];
			}
			$this->db->free_result($request);

			foreach ($mg_updates as $group_to => $update_members)
				$this->db->query("
					UPDATE {$to_prefix}members
					SET id_post_group = $group_to
					WHERE id_member IN (" . implode(', ', $update_members) . ")
					LIMIT " . count($update_members));

			pastTime(5);
		}

		if ($_GET['substep'] <= 5)
		{
			// Needs to be done separately for each board.
			$result_boards = $this->db->query("
				SELECT id_board
				FROM {$to_prefix}boards");
			$boards = array();
			while ($row_boards = $this->db->fetch_assoc($result_boards))
				$boards[] = $row_boards['id_board'];
			$this->db->free_result($result_boards);

			foreach ($boards as $id_board)
			{
				// Get the number of topics, and iterate through them.
				$result_topics = $this->db->query("
					SELECT COUNT(*)
					FROM {$to_prefix}topics
					WHERE id_board = $id_board");
				list ($num_topics) = $this->db->fetch_row($result_topics);
				$this->db->free_result($result_topics);

				// Find how many messages are in the board.
				$result_posts = $this->db->query("
					SELECT COUNT(*)
					FROM {$to_prefix}messages
					WHERE id_board = $id_board");
				list ($num_posts) = $this->db->fetch_row($result_posts);
				$this->db->free_result($result_posts);

				// Fix the board's totals.
				$this->db->query("
					UPDATE {$to_prefix}boards
					SET num_topics = $num_topics, num_posts = $num_posts
					WHERE id_board = $id_board
					LIMIT 1");
			}

			pastTime(6);
		}

		// Remove all topics that have zero messages in the messages table.
		if ($_GET['substep'] <= 6)
		{
			while (true)
			{
				$resultTopic = $this->db->query("
					SELECT t.id_topic, COUNT(m.id_msg) AS num_msg
					FROM {$to_prefix}topics AS t
						LEFT JOIN {$to_prefix}messages AS m ON (m.id_topic = t.id_topic)
					GROUP BY t.id_topic
					HAVING num_msg = 0
					LIMIT $_REQUEST[start], 200");

				$numRows = $this->db->num_rows($resultTopic);

				if ($numRows > 0)
				{
					$stupidTopics = array();
					while ($topicArray = $this->db->fetch_assoc($resultTopic))
						$stupidTopics[] = $topicArray['id_topic'];
					$this->db->query("
						DELETE FROM {$to_prefix}topics
						WHERE id_topic IN (" . implode(',', $stupidTopics) . ')
						LIMIT ' . count($stupidTopics));
					$this->db->query("
						DELETE FROM {$to_prefix}log_topics
						WHERE id_topic IN (" . implode(',', $stupidTopics) . ')');
				}
				$this->db->free_result($resultTopic);

				if ($numRows < 200)
					break;

				$_REQUEST['start'] += 200;
				pastTime(6);
			}

			$_REQUEST['start'] = 0;
			pastTime(7);
		}

		// Get the correct number of replies.
		if ($_GET['substep'] <= 7)
		{
			while (true)
			{
				$resultTopic = $this->db->query("
					SELECT
						t.id_topic, MIN(m.id_msg) AS myid_first_msg, t.id_first_msg,
						MAX(m.id_msg) AS myid_last_msg, t.id_last_msg, COUNT(m.id_msg) - 1 AS my_num_replies,
						t.num_replies
					FROM {$to_prefix}topics AS t
						LEFT JOIN {$to_prefix}messages AS m ON (m.id_topic = t.id_topic)
					GROUP BY t.id_topic
					HAVING id_first_msg != myid_first_msg OR id_last_msg != myid_last_msg OR num_replies != my_num_replies
					LIMIT $_REQUEST[start], 200");

				$numRows = $this->db->num_rows($resultTopic);

				while ($topicArray = $this->db->fetch_assoc($resultTopic))
				{
					$memberStartedID = getMsgMemberID($topicArray['myid_first_msg']);
					$memberUpdatedID = getMsgMemberID($topicArray['myid_last_msg']);

					$this->db->query("
						UPDATE {$to_prefix}topics
						SET id_first_msg = '$topicArray[myid_first_msg]',
							id_member_started = '$memberStartedID', id_last_msg = '$topicArray[myid_last_msg]',
							id_member_updated = '$memberUpdatedID', num_replies = '$topicArray[my_num_replies]'
						WHERE id_topic = $topicArray[id_topic]
						LIMIT 1");
				}
				$this->db->free_result($resultTopic);

				if ($numRows < 200)
					break;

				$_REQUEST['start'] += 100;
				pastTime(7);
			}

			$_REQUEST['start'] = 0;
			pastTime(8);
		}

		// Fix id_cat, id_parent, and child_level.
		if ($_GET['substep'] <= 8)
		{
			// First, let's get an array of boards and parents.
			$request = $this->db->query("
				SELECT id_board, id_parent, id_cat
				FROM {$to_prefix}boards");
			$child_map = array();
			$cat_map = array();
			while ($row = $this->db->fetch_assoc($request))
			{
				$child_map[$row['id_parent']][] = $row['id_board'];
				$cat_map[$row['id_board']] = $row['id_cat'];
			}
			$this->db->free_result($request);

			// Let's look for any boards with obviously invalid parents...
			foreach ($child_map as $parent => $dummy)
			{
				if ($parent != 0 && !isset($cat_map[$parent]))
				{
					// Perhaps it was supposed to be their id_cat?
					foreach ($dummy as $board)
					{
						if (empty($cat_map[$board]))
							$cat_map[$board] = $parent;
					}

					$child_map[0] = array_merge(isset($child_map[0]) ? $child_map[0] : array(), $dummy);
					unset($child_map[$parent]);
				}
			}

			// The above id_parents and id_cats may all be wrong; we know id_parent = 0 is right.
			$solid_parents = array(array(0, 0));
			$fixed_boards = array();
			while (!empty($solid_parents))
			{
				list ($parent, $level) = array_pop($solid_parents);
				if (!isset($child_map[$parent]))
					continue;

				// Fix all of this board's children.
				foreach ($child_map[$parent] as $board)
				{
					if ($parent != 0)
						$cat_map[$board] = $cat_map[$parent];
					$fixed_boards[$board] = array($parent, $cat_map[$board], $level);
					$solid_parents[] = array($board, $level + 1);
				}
			}

			foreach ($fixed_boards as $board => $fix)
			{
				$this->db->query("
					UPDATE {$to_prefix}boards
					SET id_parent = " . (int) $fix[0] . ", id_cat = " . (int) $fix[1] . ", child_level = " . (int) $fix[2] . "
					WHERE id_board = " . (int) $board . "
					LIMIT 1");
			}

			// Leftovers should be brought to the root. They had weird parents we couldn't find.
			if (count($fixed_boards) < count($cat_map))
			{
				$this->db->query("
					UPDATE {$to_prefix}boards
					SET child_level = 0, id_parent = 0" . (empty($fixed_boards) ? '' : "
					WHERE id_board NOT IN (" . implode(', ', array_keys($fixed_boards)) . ")"));
			}

			// Last check: any boards not in a good category?
			$request = $this->db->query("
				SELECT id_cat
				FROM {$to_prefix}categories");
			$real_cats = array();
			while ($row = $this->db->fetch_assoc($request))
				$real_cats[] = $row['id_cat'];
			$this->db->free_result($request);

			$fix_cats = array();
			foreach ($cat_map as $board => $cat)
			{
				if (!in_array($cat, $real_cats))
					$fix_cats[] = $cat;
			}

			if (!empty($fix_cats))
			{
				$this->db->query("
					INSERT INTO {$to_prefix}categories
						(name)
					VALUES ('General Category')");
				$catch_cat = mysqli_insert_id($this->db->con);

				$this->db->query("
					UPDATE {$to_prefix}boards
					SET id_cat = " . (int) $catch_cat . "
					WHERE id_cat IN (" . implode(', ', array_unique($fix_cats)) . ")");
			}

			pastTime(9);
		}

		if ($_GET['substep'] <= 9)
		{
			$request = $this->db->query("
				SELECT c.id_cat, c.cat_order, b.id_board, b.board_order
				FROM {$to_prefix}categories AS c
					LEFT JOIN {$to_prefix}boards AS b ON (b.id_cat = c.id_cat)
				ORDER BY c.cat_order, b.child_level, b.board_order, b.id_board");
			$cat_order = -1;
			$board_order = -1;
			$curCat = -1;
			while ($row = $this->db->fetch_assoc($request))
			{
				if ($curCat != $row['id_cat'])
				{
					$curCat = $row['id_cat'];
					if (++$cat_order != $row['cat_order'])
						$this->db->query("
							UPDATE {$to_prefix}categories
							SET cat_order = $cat_order
							WHERE id_cat = $row[id_cat]
							LIMIT 1");
				}
				if (!empty($row['id_board']) && ++$board_order != $row['board_order'])
					$this->db->query("
						UPDATE {$to_prefix}boards
						SET board_order = $board_order
						WHERE id_board = $row[id_board]
						LIMIT 1");
			}
			$this->db->free_result($request);

			pastTime(10);
		}

		if ($_GET['substep'] <= 10)
		{
			$this->db->query("
				ALTER TABLE {$to_prefix}boards
				ORDER BY board_order");

			$this->db->query("
				ALTER TABLE {$to_prefix}smileys
				ORDER BY code DESC");

			pastTime(11);
		}

		if ($_GET['substep'] <= 11)
		{
			$request = $this->db->query("
				SELECT COUNT(*)
				FROM {$to_prefix}attachments");
			list ($attachments) = $this->db->fetch_row($request);
			$this->db->free_result($request);

			while ($_REQUEST['start'] < $attachments)
			{
				$request = $this->db->query("
					SELECT id_attach, filename, attachment_type
					FROM {$to_prefix}attachments
					WHERE id_thumb = 0
						AND (RIGHT(filename, 4) IN ('.gif', '.jpg', '.png', '.bmp') OR RIGHT(filename, 5) = '.jpeg')
						AND width = 0
						AND height = 0
					LIMIT $_REQUEST[start], 500");
				if ($this->db->num_rows($request) == 0)
					break;
				while ($row = $this->db->fetch_assoc($request))
				{
					if ($row['attachment_type'] == 1)
					{
						$request2 = $this->db->query("
							SELECT value
							FROM {$to_prefix}settings
							WHERE variable = 'custom_avatar_dir'
							LIMIT 1");
						list ($custom_avatar_dir) = $this->db->fetch_row($request2);
						$this->db->free_result($request2);

						$filename = $custom_avatar_dir . DIRECTORY_SEPARATOR . $row['filename'];
					}
					else
						$filename = getLegacyAttachmentFilename($row['filename'], $row['id_attach']);

					// Probably not one of the imported ones, then?
					if (!file_exists($filename))
						continue;

					$size = @getimagesize($filename);
					$filesize = @filesize($filename);
					if (!empty($size) && !empty($size[0]) && !empty($size[1]) && !empty($filesize))
						$this->db->query("
							UPDATE {$to_prefix}attachments
							SET
								size = " . (int) $filesize . ",
								width = " . (int) $size[0] . ",
								height = " . (int) $size[1] . "
							WHERE id_attach = $row[id_attach]
							LIMIT 1");
				}
				$this->db->free_result($request);

				// More?
				// We can't keep importing the same files over and over again!
				$_REQUEST['start'] += 500;
				pastTime(11);
			}

			$_REQUEST['start'] = 0;
			pastTime(12);
		}

		$this->template->status(12, 1, false, true);

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
		global $db, $boardurl;

		$to_prefix = $this->to_prefix;

		// add some importer information.
		$this->db->query("
			REPLACE INTO {$to_prefix}settings (variable, value)
				VALUES ('import_time', " . time() . "),
					('enable_password_conversion', '1'),
					('imported_from', '" . $_SESSION['import_script'] . "')");

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

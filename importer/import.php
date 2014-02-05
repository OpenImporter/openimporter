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
@set_exception_handler(array('import_exception', 'exception_handler'));
@set_error_handler(array('import_exception', 'error_handler_callback'), E_ALL);

$import = new Importer();

// XML ajax feedback? We can just skip everything else
if (isset($_GET['xml']))
{
	$import->template->xml();
	die();
}

if (method_exists($import, 'doStep' . $_GET['step']))
	call_user_func(array($import, 'doStep' . $_GET['step']));

/**
 * Object Importer creates the main XML object.
 * It detects and initializes the script to run.
 * It handles all steps to completion.
 *
 */
class Importer
{
	/**
	 * main database object
	 * @var object
	 */
	public $db;

	/**
	 * our cookie settings
	 * @var object
	 */
	public $cookie;

	/**
	 * the template
	 * @var object
	 */
	public $template;

	/**
	 * an array of possible importer scripts
	 * @var array
	 */
	public $possible_scripts;

	/**
	 * prefix for our destination database
	 * @var type
	 */
	public $to_prefix;

	/**
	 * prefix for our source database
	 * @var type
	 */
	public $from_prefix;

	/**
	 * used to decide if the database query is INSERT or INSERT IGNORE
	 * @var type
	 */
	private $ignore = true;

	/**
	 *use to switch between INSERT and REPLACE
	 * @var type
	 */
	private $replace = false;

	/**
	 * initialize the main Importer object
	 */
	public function __construct()
	{

		// Load the language file and create an importer cookie.
		lng::loadLang();

		// initialize some objects
		$this->cookie = new Cookie();
		$this->template = new template();

		// Save here so it doesn't get overwritten when sessions are restarted.
		if (isset($_REQUEST['import_script']))
			$this->script = @$_REQUEST['import_script'];

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

		// This is really quite simple; if ?delete is on the URL, delete the importer...
		if (isset($_GET['delete']))
		{
			@unlink(__FILE__);
			if (preg_match('~_importer\.xml$~', $_SESSION['import_script']) != 0)
				@unlink(dirname(__FILE__) . '/' . $_SESSION['import_script']);
			$_SESSION['import_script'] = null;

			exit;
		}

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
				$_POST['path_from'] = substr($_POST['path_from'], -1) == '/' ? substr($_POST['path_from'], 0, -1) : $_POST['path_from'];
			if (isset($_POST['path_to']))
				$_POST['path_to'] = substr($_POST['path_to'], -1) == '/' ? substr($_POST['path_to'], 0, -1) : $_POST['path_to'];

			$_SESSION['import_paths'] = array(@$_POST['path_from'], @$_POST['path_to']);
		}

		// If we have our script then set it to the session.
		if (!empty($this->script))
			$_SESSION['import_script'] = (string) $this->script;
		if (isset($_SESSION['import_script']) && file_exists(dirname(__FILE__) . '/' . $_SESSION['import_script']) && preg_match('~_importer\.xml$~', $_SESSION['import_script']) != 0)
			$this->_preparse_xml(dirname(__FILE__) . '/' . $_SESSION['import_script']);
		else
			unset($_SESSION['import_script']);

		// UI and worker process comes next..
		if (!isset($_GET['xml']))
			$this->template->header();
	}

	/**
	 * destructor
	 */
	public function __destruct()
	{
		if (!isset($_GET['xml']))
			$this->template->footer();
	}

	/**
	 * loads the _importer.xml files
	 * @param string $file
	 * @throws import_exception
	 */
	private function _preparse_xml($file)
	{
		try
		{
			if (!$this->xml = simplexml_load_file($file, 'SimpleXMLElement', LIBXML_NOCDATA))
				throw new import_exception('XML-Syntax error in file: ' . $file);

			$this->xml = simplexml_load_file($file, 'SimpleXMLElement', LIBXML_NOCDATA);
		}
		catch (Exception $e)
		{
			import_exception::exception_handler($e);
		}

		if (isset($_POST['path_to']) && !empty($_GET['step']))
			$this->_loadSettings();
	}

	/**
	 * - checks,  if we have already specified an importer script
	 * - checks the file system for importer definition files
	 * @return boolean
	 * @throws import_exception
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

		$dir = dir(dirname(__FILE__));
		$scripts = array();
		while ($entry = $dir->read())
		{
			if (substr($entry, -13) != '_importer.xml')
				continue;

			if (substr($entry, -13) == '_importer.xml')
			{
				try
				{
					if (!$xmlObj = simplexml_load_file($entry, 'SimpleXMLElement', LIBXML_NOCDATA))
						throw new import_exception('XML-Syntax error in file: ' . $entry);

					$xmlObj = simplexml_load_file($entry, 'SimpleXMLElement', LIBXML_NOCDATA);
				}
				catch (Exception $e)
				{
					import_exception::exception_handler($e);
				}
				$scripts[] = array('path' => $entry, 'name' => $xmlObj->general->name);
			}
		}
		$dir->close();

		if (isset($_SESSION['import_script']))
		{
			if (count($scripts) > 1)
				$this->possible_scripts = $scripts;
			return false;
		}

		if (count($scripts) == 1)
		{
			$_SESSION['import_script'] = basename($scripts[0]['path']);
			if (substr($_SESSION['import_script'], -4) == '.xml')
				$this->_preparse_xml(dirname(__FILE__) . '/' . $_SESSION['import_script']);
			return false;
		}

		$this->template->select_script($scripts);

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
			return $this->doStep0(lng::get('imp.settings_not_found'));

		$found = empty($this->xml->general->settings);

		foreach ($this->xml->general->settings as $file)
			$found |= @file_exists($_POST['path_from'] . stripslashes($file));

		if (@ini_get('open_basedir') != '' && !$found)
			return $this->doStep0(array(lng::get('imp.open_basedir'), (string) $this->xml->general->name));

		if (!$found)
			return $this->doStep0(array(lng::get('imp.config_not_found'), (string) $this->xml->general->name));

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
		$this->boardurl = $boardurl;

		if ($_SESSION['import_db_pass'] != $db_passwd)
			return $this->doStep0(lng::get('imp.password_incorrect'), $this);

		// Check the steps that we have decided to go through.
		if (!isset($_POST['do_steps']) && !isset($_SESSION['do_steps']))
			return $this->doStep0(lng::get('imp.select_step'));

		elseif (isset($_POST['do_steps']))
		{
			unset($_SESSION['do_steps']);
			foreach ($_POST['do_steps'] as $key => $step)
				$_SESSION['do_steps'][$key] = $step;
		}
		try
		{
			$db = new Database($db_server, $db_user, $db_passwd, $db_persist);
			//We want UTF8 only, let's set our mysql connetction to utf8
			$db->query('SET NAMES \'utf8\'');
		}
		catch(Exception $e)
		{
			import_exception::exception_handler($e);
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
			$result = $db->query("
				SELECT COUNT(*)
				FROM " . eval('return "' . $this->xml->general->table_test . '";'), true);
			if ($result === false)
				$this->doStep0(lng::get('imp.permission_denied') . mysqli_error($db->con), (string) $this->xml->general->name);

			$db->free_result($result);
		}

		$results = $db->query("SELECT @@SQL_BIG_SELECTS, @@MAX_JOIN_SIZE");
		list ($big_selects, $sql_max_join) = $db->fetch_row($results);

		// Only waste a query if its worth it.
		if (empty($big_selects) || ($big_selects != 1 && $big_selects != '1'))
			$db->query("SET @@SQL_BIG_SELECTS = 1");

		// Let's set MAX_JOIN_SIZE to something we should
		if (empty($sql_max_join) || ($sql_max_join == '18446744073709551615' && $sql_max_join == '18446744073709551615'))
			$db->query("SET @@MAX_JOIN_SIZE = 18446744073709551615");

	}

	/**
	 * Looks at the importer and returns the steps that it's able to make.
	 * @return int
	 */
	private function _find_steps()
	{
		$steps = array();
		$steps_count = 0;

		foreach ($this->xml->step as $xml_steps)
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

		$test_from = empty($this->xml->general->settings);

		foreach ($this->xml->general->settings as $settings_file)
			$test_from |= @file_exists($_POST['path_from'] . $settings_file);

		$test_to = @file_exists($_POST['path_to'] . '/Settings.php');

		// Was an error message specified?
		if ($error_message !== null)
		{
			$template = new template();
			$template->header(false);
			$template->error($error_message);
		}

		$this->template->step0($this, $this->_find_steps(), $test_from, $test_to);

		if ($error_message !== null)
		{
			$template->footer();
			exit;
		}

		return;
	}

	/**
	 * the important one, transfer the content from the source forum to our
	 * destination system
	 *
	 * @global Database $db
	 * @global type $to_prefix
	 * @global type $global
	 * @return boolean
	 */
	public function doStep1()
	{
		global $db, $to_prefix;

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
			foreach ($this->xml->step as $counts)
			{
				if ($counts->detect)
				{
					$count = $this->_fix_params((string) $counts->detect);
					$request = $db->query("
						SELECT COUNT(*)
						FROM $count", true);

					if (!empty($request))
					{
						list ($current) = $db->fetch_row($request);
						$db->free_result($request);
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

		foreach ($this->xml->step as $steps)
		{
			// Reset some defaults
			$current_data = '';
			$special_table = null;
			$special_code = null;

			// Increase the substep slightly...
			pastTime(++$substep);

			$_SESSION['import_steps'][$substep]['title'] = (string) $steps->title;
			if (!isset($_SESSION['import_steps'][$substep]['status']))
				$_SESSION['import_steps'][$substep]['status'] = 0;

			// any preparsing code here?
			if (isset($steps->preparsecode) && !empty($steps->preparsecode))
				$special_code = $this->_fix_params((string) $steps->preparsecode);

			$do_current = $substep >= $_GET['substep'];

			if (!in_array($substep, $do_steps))
			{
				$_SESSION['import_steps'][$substep]['status'] = 2;
				$_SESSION['import_steps'][$substep]['presql'] = true;
			}
			// Detect the table, then count rows.. 
			elseif ($steps->detect)
			{
				$count = $this->_fix_params((string) $steps->detect);
				$table_test = $db->query("
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
			if (isset($steps->presql) && !isset($_SESSION['import_steps'][$substep]['presql']))
			{
				$presql = $this->_fix_params((string) $steps->presql);
				$presql_array = explode(';', $presql);
				if (isset($presql_array) && is_array($presql_array))
				{
					array_pop($presql_array);
					foreach ($presql_array as $exec)
						$db->query($exec . ';');
				}
				else
					$db->query($presql);
				// don't do this twice..
				$_SESSION['import_steps'][$substep]['presql'] = true;
			}

			if ($special_table === null)
			{
				$special_table = strtr(trim((string) $steps->destination), array('{$to_prefix}' => $this->to_prefix));
				$special_limit = 500;
			}
			else
				$special_table = null;

			if (isset($steps->query))
				$current_data = substr(rtrim($this->_fix_params((string) $steps->query)), 0, -1);

			if (isset($steps->options->limit))
				$special_limit = $steps->options->limit;

			if (!$do_current)
			{
				$current_data = '';
				continue;
			}

			// codeblock?
			if (isset($steps->code))
			{
				// execute our code block
				$special_code = $this->_fix_params((string) $steps->code);
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
			if (!empty($steps->query))
			{
				if (strpos($current_data, '{$') !== false)
					$current_data = eval('return "' . addcslashes($current_data, '\\"') . '";');

				if (isset($steps->detect))
				{
					$count = $this->_fix_params((string) $steps->detect);
					$result2 = $db->query("
						SELECT COUNT(*)
						FROM $count");
					list ($counter) = $db->fetch_row($result2);
					//$this->count->$substep = $counter;
					$db->free_result($result2);
				}

				// create some handy shortcuts
				$ignore = ((isset($steps->options->ignore) && $steps->options->ignore == false) || isset($steps->options->replace)) ? false : true;
				$replace = (isset($steps->options->replace) && $steps->options->replace == true) ? true : false;
				$no_add = (isset($steps->options->no_add) && $steps->options->no_add == true) ? true : false;
				$ignore_slashes = (isset($steps->options->ignore_slashes) && $steps->options->ignore_slashes == true) ? true : false;

				if (isset($import_table) && $import_table !== null && strpos($current_data, '%d') !== false)
				{
					preg_match('~FROM [(]?([^\s,]+)~i', (string) $steps->detect, $match);
					if (!empty($match))
					{
						$result = $db->query("
							SELECT COUNT(*)
							FROM $match[1]");
						list ($special_max) = $db->fetch_row($result);
						$db->free_result($result);
					}
					else
						$special_max = 0;
				}
				else
					$special_max = 0;

				if ($special_table === null)
					$db->query($current_data);

				else
				{
					// Are we doing attachments? They're going to want a few things...
					if ($special_table == $this->to_prefix . 'attachments')
					{
						if (!isset($id_attach, $attachmentUploadDir, $avatarUploadDir))
						{
							$result = $db->query("
								SELECT MAX(id_attach) + 1
								FROM {$to_prefix}attachments");
							list ($id_attach) = $db->fetch_row($result);
							$db->free_result($result);

							$result = $db->query("
								SELECT value
								FROM {$to_prefix}settings
								WHERE variable = 'attachmentUploadDir'
								LIMIT 1");
							list ($attachmentdir) = $db->fetch_row($result);
							$attachment_UploadDir = unserialize($attachmentdir);
							$attachmentUploadDir = $attachment_UploadDir[1];

							$result = $db->query("
								SELECT value
								FROM {$to_prefix}settings
								WHERE variable = 'custom_avatar_dir'
								LIMIT 1");
							list ($avatarUploadDir) = $db->fetch_row($result);
							$db->free_result($result);

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
							$special_result = $db->query(sprintf($current_data, $_REQUEST['start'], $_REQUEST['start'] + $special_limit - 1) . "\n" . 'LIMIT ' . $special_limit);
						else
							$special_result = $db->query($current_data . "\n" . 'LIMIT ' . $_REQUEST['start'] . ', ' . $special_limit);

						$rows = array();
						$keys = array();

						if (isset($steps->detect))
							$_SESSION['import_progress'] += $special_limit;

						while ($row = $db->fetch_assoc($special_result))
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

										$request2 = $db->query("
											SELECT id_ip
											FROM {$to_prefix}log_ips
											WHERE member_ip = '" . $ipv6ip . "'
											LIMIT 1");
										// IP already known?
										if ($db->num_rows($request2) != 0)
										{
											list ($id_ip) = $db->fetch_row($request2);
											$row[$ip] = $id_ip;
										}
										// insert the new ip
										else
										{
											$db->query("
												INSERT INTO {$to_prefix}log_ips
													(member_ip)
												VALUES ('$ipv6ip')");
											$pointer = $db->insert_id();
											$row[$ip] = $pointer;
										}

										$db->free_result($request2);
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
							$db->query("
								$insert_replace $insert_ignore INTO $special_table
									(" . implode(', ', $keys) . ")
								VALUES (" . implode('),
									(', $rows) . ")");
						$_REQUEST['start'] += $special_limit;
						if (empty($special_max) && $db->num_rows($special_result) < $special_limit)
							break;
						elseif (!empty($special_max) && $db->num_rows($special_result) == 0 && $_REQUEST['start'] > $special_max)
							break;
						$db->free_result($special_result);
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

		$_GET['substep'] = 0;
		$_REQUEST['start'] = 0;

		return $this->doStep2();
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
			$request = $db->query("
				SELECT mem.id_member, COUNT(pmr.id_pm) AS real_num, mem.personal_messages
				FROM {$to_prefix}members AS mem
					LEFT JOIN {$to_prefix}pm_recipients AS pmr ON (mem.id_member = pmr.id_member AND pmr.deleted = 0)
				GROUP BY mem.id_member
				HAVING real_num != personal_messages");
			while ($row = $db->fetch_assoc($request))
			{
				$db->query("
					UPDATE {$to_prefix}members
					SET personal_messages = $row[real_num]
					WHERE id_member = $row[id_member]
					LIMIT 1");

				pastTime(0);
			}
			$db->free_result($request);

			$request = $db->query("
				SELECT mem.id_member, COUNT(pmr.id_pm) AS real_num, mem.unread_messages
				FROM {$to_prefix}members AS mem
					LEFT JOIN {$to_prefix}pm_recipients AS pmr ON (mem.id_member = pmr.id_member AND pmr.deleted = 0 AND pmr.is_read = 0)
				GROUP BY mem.id_member
				HAVING real_num != unread_messages");
			while ($row = $db->fetch_assoc($request))
			{
				$db->query("
					UPDATE {$to_prefix}members
					SET unread_messages = $row[real_num]
					WHERE id_member = $row[id_member]
					LIMIT 1");

				pastTime(0);
			}
			$db->free_result($request);

			pastTime(1);
		}

		if ($_GET['substep'] <= 1)
		{
			$request = $db->query("
				SELECT id_board, MAX(id_msg) AS id_last_msg, MAX(modified_time) AS last_edited
				FROM {$to_prefix}messages
				GROUP BY id_board");
			$modifyData = array();
			$modifyMsg = array();
			while ($row = $db->fetch_assoc($request))
			{
				$db->query("
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
			$db->free_result($request);

			// Are there any boards where the updated message is not the last?
			if (!empty($modifyMsg))
			{
				$request = $db->query("
					SELECT id_board, id_msg, modified_time, poster_time
					FROM {$to_prefix}messages
					WHERE id_msg IN (" . implode(',', $modifyMsg) . ")");
				while ($row = $db->fetch_assoc($request))
				{
					// Have we got a message modified before this was posted?
					if (max($row['modified_time'], $row['poster_time']) < $modifyData[$row['id_board']]['last_edited'])
					{
						// Work out the ID of the message (This seems long but it won't happen much.
						$request2 = $db->query("
							SELECT id_msg
							FROM {$to_prefix}messages
							WHERE modified_time = " . $modifyData[$row['id_board']]['last_edited'] . "
							LIMIT 1");
						if ($db->num_rows($request2) != 0)
						{
							list ($id_msg) = $db->fetch_row($request2);

							$db->query("
								UPDATE {$to_prefix}boards
								SET id_msg_updated = $id_msg
								WHERE id_board = $row[id_board]
								LIMIT 1");
						}
						$db->free_result($request2);
					}
				}
				$db->free_result($request);
			}

			pastTime(2);
		}

		if ($_GET['substep'] <= 2)
		{
			$request = $db->query("
				SELECT id_group
				FROM {$to_prefix}membergroups
				WHERE min_posts = -1");
			$all_groups = array();
			while ($row = $db->fetch_assoc($request))
				$all_groups[] = $row['id_group'];
			$db->free_result($request);

			$request = $db->query("
				SELECT id_board, member_groups
				FROM {$to_prefix}boards
				WHERE FIND_IN_SET(0, member_groups)");
			while ($row = $db->fetch_assoc($request))
				$db->query("
					UPDATE {$to_prefix}boards
					SET member_groups = '" . implode(',', array_unique(array_merge($all_groups, explode(',', $row['member_groups'])))) . "'
					WHERE id_board = $row[id_board]
					LIMIT 1");
			$db->free_result($request);

			pastTime(3);
		}

		if ($_GET['substep'] <= 3)
		{
			// Get the number of messages...
			$result = $db->query("
				SELECT COUNT(*) AS totalMessages, MAX(id_msg) AS maxMsgID
				FROM {$to_prefix}messages");
			$row = $db->fetch_assoc($result);
			$db->free_result($result);

			// Update the latest member. (Highest ID_MEMBER)
			$result = $db->query("
				SELECT id_member AS latestMember, real_name AS latestreal_name
				FROM {$to_prefix}members
				ORDER BY id_member DESC
				LIMIT 1");
			if ($db->num_rows($result))
				$row += $db->fetch_assoc($result);
			$db->free_result($result);

			// Update the member count.
			$result = $db->query("
				SELECT COUNT(*) AS totalMembers
				FROM {$to_prefix}members");
			$row += $db->fetch_assoc($result);
			$db->free_result($result);

			// Get the number of topics.
			$result = $db->query("
				SELECT COUNT(*) AS totalTopics
				FROM {$to_prefix}topics");
			$row += $db->fetch_assoc($result);
			$db->free_result($result);

			$db->query("
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
			$request = $db->query("
				SELECT id_group, min_posts
				FROM {$to_prefix}membergroups
				WHERE min_posts != -1
				ORDER BY min_posts DESC");
			$post_groups = array();
			while ($row = $db->fetch_assoc($request))
				$post_groups[$row['min_posts']] = $row['id_group'];
			$db->free_result($request);

			$request = $db->query("
				SELECT id_member, posts
				FROM {$to_prefix}members");
			$mg_updates = array();
			while ($row = $db->fetch_assoc($request))
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
			$db->free_result($request);

			foreach ($mg_updates as $group_to => $update_members)
				$db->query("
					UPDATE {$to_prefix}members
					SET id_post_group = $group_to
					WHERE id_member IN (" . implode(', ', $update_members) . ")
					LIMIT " . count($update_members));

			pastTime(5);
		}

		if ($_GET['substep'] <= 5)
		{
			// Needs to be done separately for each board.
			$result_boards = $db->query("
				SELECT id_board
				FROM {$to_prefix}boards");
			$boards = array();
			while ($row_boards = $db->fetch_assoc($result_boards))
				$boards[] = $row_boards['id_board'];
			$db->free_result($result_boards);

			foreach ($boards as $id_board)
			{
				// Get the number of topics, and iterate through them.
				$result_topics = $db->query("
					SELECT COUNT(*)
					FROM {$to_prefix}topics
					WHERE id_board = $id_board");
				list ($num_topics) = $db->fetch_row($result_topics);
				$db->free_result($result_topics);

				// Find how many messages are in the board.
				$result_posts = $db->query("
					SELECT COUNT(*)
					FROM {$to_prefix}messages
					WHERE id_board = $id_board");
				list ($num_posts) = $db->fetch_row($result_posts);
				$db->free_result($result_posts);

				// Fix the board's totals.
				$db->query("
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
				$resultTopic = $db->query("
					SELECT t.id_topic, COUNT(m.id_msg) AS num_msg
					FROM {$to_prefix}topics AS t
						LEFT JOIN {$to_prefix}messages AS m ON (m.id_topic = t.id_topic)
					GROUP BY t.id_topic
					HAVING num_msg = 0
					LIMIT $_REQUEST[start], 200");

				$numRows = $db->num_rows($resultTopic);

				if ($numRows > 0)
				{
					$stupidTopics = array();
					while ($topicArray = $db->fetch_assoc($resultTopic))
						$stupidTopics[] = $topicArray['id_topic'];
					$db->query("
						DELETE FROM {$to_prefix}topics
						WHERE id_topic IN (" . implode(',', $stupidTopics) . ')
						LIMIT ' . count($stupidTopics));
					$db->query("
						DELETE FROM {$to_prefix}log_topics
						WHERE id_topic IN (" . implode(',', $stupidTopics) . ')');
				}
				$db->free_result($resultTopic);

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
				$resultTopic = $db->query("
					SELECT
						t.id_topic, MIN(m.id_msg) AS myid_first_msg, t.id_first_msg,
						MAX(m.id_msg) AS myid_last_msg, t.id_last_msg, COUNT(m.id_msg) - 1 AS my_num_replies,
						t.num_replies
					FROM {$to_prefix}topics AS t
						LEFT JOIN {$to_prefix}messages AS m ON (m.id_topic = t.id_topic)
					GROUP BY t.id_topic
					HAVING id_first_msg != myid_first_msg OR id_last_msg != myid_last_msg OR num_replies != my_num_replies
					LIMIT $_REQUEST[start], 200");

				$numRows = $db->num_rows($resultTopic);

				while ($topicArray = $db->fetch_assoc($resultTopic))
				{
					$memberStartedID = getMsgMemberID($topicArray['myid_first_msg']);
					$memberUpdatedID = getMsgMemberID($topicArray['myid_last_msg']);

					$db->query("
						UPDATE {$to_prefix}topics
						SET id_first_msg = '$topicArray[myid_first_msg]',
							id_member_started = '$memberStartedID', id_last_msg = '$topicArray[myid_last_msg]',
							id_member_updated = '$memberUpdatedID', num_replies = '$topicArray[my_num_replies]'
						WHERE id_topic = $topicArray[id_topic]
						LIMIT 1");
				}
				$db->free_result($resultTopic);

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
			$request = $db->query("
				SELECT id_board, id_parent, id_cat
				FROM {$to_prefix}boards");
			$child_map = array();
			$cat_map = array();
			while ($row = $db->fetch_assoc($request))
			{
				$child_map[$row['id_parent']][] = $row['id_board'];
				$cat_map[$row['id_board']] = $row['id_cat'];
			}
			$db->free_result($request);

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
				$db->query("
					UPDATE {$to_prefix}boards
					SET id_parent = " . (int) $fix[0] . ", id_cat = " . (int) $fix[1] . ", child_level = " . (int) $fix[2] . "
					WHERE id_board = " . (int) $board . "
					LIMIT 1");
			}

			// Leftovers should be brought to the root. They had weird parents we couldn't find.
			if (count($fixed_boards) < count($cat_map))
			{
				$db->query("
					UPDATE {$to_prefix}boards
					SET child_level = 0, id_parent = 0" . (empty($fixed_boards) ? '' : "
					WHERE id_board NOT IN (" . implode(', ', array_keys($fixed_boards)) . ")"));
			}

			// Last check: any boards not in a good category?
			$request = $db->query("
				SELECT id_cat
				FROM {$to_prefix}categories");
			$real_cats = array();
			while ($row = $db->fetch_assoc($request))
				$real_cats[] = $row['id_cat'];
			$db->free_result($request);

			$fix_cats = array();
			foreach ($cat_map as $board => $cat)
			{
				if (!in_array($cat, $real_cats))
					$fix_cats[] = $cat;
			}

			if (!empty($fix_cats))
			{
				$db->query("
					INSERT INTO {$to_prefix}categories
						(name)
					VALUES ('General Category')");
				$catch_cat = mysqli_insert_id($db->con);

				$db->query("
					UPDATE {$to_prefix}boards
					SET id_cat = " . (int) $catch_cat . "
					WHERE id_cat IN (" . implode(', ', array_unique($fix_cats)) . ")");
			}

			pastTime(9);
		}

		if ($_GET['substep'] <= 9)
		{
			$request = $db->query("
				SELECT c.id_cat, c.cat_order, b.id_board, b.board_order
				FROM {$to_prefix}categories AS c
					LEFT JOIN {$to_prefix}boards AS b ON (b.id_cat = c.id_cat)
				ORDER BY c.cat_order, b.child_level, b.board_order, b.id_board");
			$cat_order = -1;
			$board_order = -1;
			$curCat = -1;
			while ($row = $db->fetch_assoc($request))
			{
				if ($curCat != $row['id_cat'])
				{
					$curCat = $row['id_cat'];
					if (++$cat_order != $row['cat_order'])
						$db->query("
							UPDATE {$to_prefix}categories
							SET cat_order = $cat_order
							WHERE id_cat = $row[id_cat]
							LIMIT 1");
				}
				if (!empty($row['id_board']) && ++$board_order != $row['board_order'])
					$db->query("
						UPDATE {$to_prefix}boards
						SET board_order = $board_order
						WHERE id_board = $row[id_board]
						LIMIT 1");
			}
			$db->free_result($request);

			pastTime(10);
		}

		if ($_GET['substep'] <= 10)
		{
			$db->query("
				ALTER TABLE {$to_prefix}boards
				ORDER BY board_order");

			$db->query("
				ALTER TABLE {$to_prefix}smileys
				ORDER BY code DESC");

			pastTime(11);
		}

		if ($_GET['substep'] <= 11)
		{
			$request = $db->query("
				SELECT COUNT(*)
				FROM {$to_prefix}attachments");
			list ($attachments) = $db->fetch_row($request);
			$db->free_result($request);

			while ($_REQUEST['start'] < $attachments)
			{
				$request = $db->query("
					SELECT id_attach, filename, attachment_type
					FROM {$to_prefix}attachments
					WHERE id_thumb = 0
						AND (RIGHT(filename, 4) IN ('.gif', '.jpg', '.png', '.bmp') OR RIGHT(filename, 5) = '.jpeg')
						AND width = 0
						AND height = 0
					LIMIT $_REQUEST[start], 500");
				if ($db->num_rows($request) == 0)
					break;
				while ($row = $db->fetch_assoc($request))
				{
					if ($row['attachment_type'] == 1)
					{
						$request2 = $db->query("
							SELECT value
							FROM {$to_prefix}settings
							WHERE variable = 'custom_avatar_dir'
							LIMIT 1");
						list ($custom_avatar_dir) = $db->fetch_row($request2);
						$db->free_result($request2);

						$filename = $custom_avatar_dir . '/' . $row['filename'];
					}
					else
						$filename = getLegacyAttachmentFilename($row['filename'], $row['id_attach']);

					// Probably not one of the imported ones, then?
					if (!file_exists($filename))
						continue;

					$size = @getimagesize($filename);
					$filesize = @filesize($filename);
					if (!empty($size) && !empty($size[0]) && !empty($size[1]) && !empty($filesize))
						$db->query("
							UPDATE {$to_prefix}attachments
							SET
								size = " . (int) $filesize . ",
								width = " . (int) $size[0] . ",
								height = " . (int) $size[1] . "
							WHERE id_attach = $row[id_attach]
							LIMIT 1");
				}
				$db->free_result($request);

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
		$db->query("
			REPLACE INTO {$to_prefix}settings (variable, value)
				VALUES ('import_time', " . time() . "),
					('enable_password_conversion', '1'),
					('imported_from', '" . $_SESSION['import_script'] . "')");

		$writable = (is_writable(dirname(__FILE__)) && is_writable(__FILE__));
		$this->template->step3($this->xml->general->name, $boardurl, $writable);

		unset ($_SESSION['import_steps'], $_SESSION['import_progress'], $_SESSION['import_overall']);
		return true;
	}

}

/**
 * the database class.
 * This class provides an easy wrapper around the common database
 *  functions we work with.
 */
class Database
{

	/**
	 * constructor, connects to the database
	 * @param type $db_server
	 * @param type $db_user
	 * @param type $db_password
	 * @param type $db_persist
	 */
	var $con;
	
	/**
	 * 
	 * @param string $db_server
	 * @param string $db_user
	 * @param string $db_password
	 * @param bool $db_persist
	 */
	public function __construct($db_server, $db_user, $db_password, $db_persist)
	{
		$this->con = mysqli_connect(($db_persist == 1 ? 'p:' : '') . $db_server, $db_user, $db_password);
 
		if (mysqli_connect_error())
 			die('Database error: ' . mysqli_connect_error());
	}

	/**
	 * remove old attachments
	 *
	 * @global type $to_prefix
	 */
	private function _removeAttachments()
	{
		global $to_prefix;

		$result = $this->query("
			SELECT value
			FROM {$to_prefix}settings
			WHERE variable = 'attachmentUploadDir'
			LIMIT 1");
		list ($attachmentUploadDir) = $this->fetch_row($result);
		$this->free_result($result);

		// !!! This should probably be done in chunks too.
		$result = $this->query("
			SELECT id_attach, filename
			FROM {$to_prefix}attachments");
		while ($row = $this->fetch_assoc($result))
		{
			// We're duplicating this from below because it's slightly different for getting current ones.
			$clean_name = strtr($row['filename'], 'ŠŽšžŸÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÑÒÓÔÕÖØÙÚÛÜÝàáâãäåçèéêëìíîïñòóôõöøùúûüýÿ', 'SZszYAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy');
			$clean_name = strtr($clean_name, array('Þ' => 'TH', 'þ' => 'th', 'Ð' => 'DH', 'ð' => 'dh', 'ß' => 'ss', 'Œ' => 'OE', 'œ' => 'oe', 'Æ' => 'AE', 'æ' => 'ae', 'µ' => 'u'));
			$clean_name = preg_replace(array('/\s/', '/[^\w_\.\-]/'), array('_', ''), $clean_name);
			$enc_name = $row['id_attach'] . '_' . strtr($clean_name, '.', '_') . md5($clean_name) . '.ext';
			$clean_name = preg_replace('~\.[\.]+~', '.', $clean_name);

			if (file_exists($attachmentUploadDir . '/' . $enc_name))
				$filename = $attachmentUploadDir . '/' . $enc_name;
			else
				$filename = $attachmentUploadDir . '/' . $clean_name;

			if (is_file($filename))
				unlink($filename);
		}
		$this->free_result($result);
	}

	/**
	 * execute an SQL query
	 *
	 * @global type $import
	 * @global type $to_prefix
	 * @param type $string
	 * @param type $return_error
	 * @return type
	 */
	public function query($string, $return_error = false)
	{
		global $import, $to_prefix;

		// Debugging?
		if (isset($_REQUEST['debug']))
			$_SESSION['import_debug'] = !empty($_REQUEST['debug']);

		if (trim($string) == 'TRUNCATE ' . $to_prefix . 'attachments;')
			$this->_removeAttachments();

		$result = @mysqli_query($this->con, $string);

		if ($result !== false || $return_error)
			return $result;

		$mysql_error = mysqli_error($this->con);
		$mysql_errno = mysqli_errno($this->con);

		if ($mysql_errno == 1016)
		{
			if (preg_match('~(?:\'([^\.\']+)~', $mysql_error, $match) != 0 && !empty($match[1]))
				mysqli_query($this->con, "
					REPAIR TABLE $match[1]");

			$result = mysql_query($string);

			if ($result !== false)
				return $result;
		}
		elseif ($mysql_errno == 2013)
		{
			$result = mysqli_query($this->con, $string);

			if ($result !== false)
				return $result;
		}

		// Get the query string so we pass everything.
		if (isset($_REQUEST['start']))
			$_GET['start'] = $_REQUEST['start'];
		$query_string = '';
		foreach ($_GET as $k => $v)
			$query_string .= '&' . $k . '=' . $v;
		if (strlen($query_string) != 0)
			$query_string = '?' . strtr(substr($query_string, 1), array('&' => '&amp;'));

		echo '
				<b>Unsuccessful!</b><br />
				This query:<blockquote>' . nl2br(htmlspecialchars(trim($string))) . ';</blockquote>
				Caused the error:<br />
				<blockquote>' . nl2br(htmlspecialchars($mysql_error)) . '</blockquote>
				<form action="', $_SERVER['PHP_SELF'], $query_string, '" method="post">
					<input type="submit" value="Try again" />
				</form>
			</div>';

		$import->template->footer();
		die;
	}


	/**
	 * wrapper for mysql_free_result
	 * @param type $result
	 */
	public function free_result($result)

	{
		mysqli_free_result($result);
	}

	/**
	 * wrapper for mysql_fetch_assoc
	 * @param type $result
	 * @return string
	 */
	public function fetch_assoc($result)
	{
		return mysqli_fetch_assoc($result);
	}

	/**
	 * wrapper for mysql_fetch_row
	 * @param type $result
	 * @return type
	 */
	public function fetch_row($result)
	{
		return mysqli_fetch_row($result);
	}

	/**
	 * wrapper for mysql_num_rows
	 * @param type $result
	 * @return integer
	 */
	public function num_rows($result)
	{
		return mysqli_num_rows($result);
	}

	/**
	 * wrapper for mysql_insert_id
	 * @return integer
	 */
	public function insert_id()
	{
		return mysql_insert_id($this->con);
	}
}

/**
* Class lng loads the appropriate language file(s)
* if they exist. The default import_en.xml file
* contains the English strings used by the importer.
*
* @var array $lang
*/
class lng
{
	private static $_lang = array();

	/**
	* Adds a new variable to lang.
	*
	* @param string $key Name of the variable
	* @param string $value Value of the variable
	* @throws Exception
	* @return boolean|null
	*/
	protected static function set($key, $value)
	{
		try
		{
				if (!self::has($key))
				{
					self::$_lang[$key] = $value;
					return true;
				}
				else
					throw new Exception('Unable to set language string for <em>' . $key . '</em>. It was already set.');
		}
		catch(Exception $e)
		{
			import_exception::exception_handler($e);
		}
	}

	/**
	* load the language xml in lang
	*
	* @return null
	*/
	public static function loadLang()
	{
		// detect the browser language
		$language = self::detect_browser_language();

		// loop through the preferred languages and try to find the related language file
		foreach ($language as $key => $value)
		{
			if (file_exists(dirname(__FILE__) . '/import_' . $key . '.xml'))
			{
				$lngfile = dirname(__FILE__) . '/import_' . $key . '.xml';
				break;
			}
		}
		// english is still better than nothing
		if (!isset($lngfile))
		{
			if (file_exists(dirname(__FILE__) . '/import_en.xml'))
				$lngfile = dirname(__FILE__) . '/import_en.xml';
		}
		// ouch, we really should never arrive here..
		if (!$lngfile)
			throw new Exception('Unable to detect language file!');

				try
		{
			if (!$langObj = simplexml_load_file($lngfile, 'SimpleXMLElement', LIBXML_NOCDATA))
				throw new import_exception('XML-Syntax error in file: ' . $lngfile);

			$langObj = simplexml_load_file($lngfile, 'SimpleXMLElement', LIBXML_NOCDATA);
		}
		catch (Exception $e)
		{
			import_exception::exception_handler($e);
		}


		foreach ($langObj as $strings)
			self::set((string) $strings->attributes()->{'name'}, (string) $strings);

		return null;
	}

	/**
	* Tests if given $key exists in lang
	*
	* @param string $key
	* @return bool
	*/
	public static function has($key)
	{
		if (isset(self::$_lang[$key]))
			return true;

		return false;
	}

	/**
	* Returns the value of the specified $key in lang.
	*
	* @param string $key Name of the variable
	* @return string|null Value of the specified $key
	*/
	public static function get($key)
	{
		if (self::has($key))
			return self::$_lang[$key];

		return null;
	}

	/**
	* Returns the whole lang as an array.
	*
	* @return array Whole lang
	*/
	public static function getAll()
	{
		return self::$_lang;
	}

	protected static function detect_browser_language()
	{
		if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']))
		{
			// break up string into pieces (languages and q factors)
			preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']), $lang_parse);

			if (count($lang_parse[1]))
			{
				// create a list like "en" => 0.8
				$preferred = array_combine($lang_parse[1], $lang_parse[4]);

				// set default to 1 for any without q factor (IE fix)
				foreach ($preferred as $lang => $val)
				{
					if ($val === '')
						$preferred[$lang] = 1;
				}

				// sort list based on value
				arsort($preferred, SORT_NUMERIC);
			}
		}
		return $preferred;
	}

}

/**
* this is our UI
*
*/
class template
{
	/**
	* Display a specific error message.
	*
	* @param string $error_message
	* @param int $trace
	* @param int $line
	* @param string $file
	*/
	public function error($error_message, $trace = false, $line = false, $file = false)
	{
		echo '
			<div class="error_message">
				<div class="error_text">', isset($trace) && !empty($trace) ? 'Message: ' : '', is_array($error_message) ? sprintf($error_message[0], $error_message[1]) : $error_message , '</div>';
		if (isset($trace) && !empty($trace))
			echo '<div class="error_text">Trace: ', $trace , '</div>';
		if (isset($line) && !empty($line))
			echo '<div class="error_text">Line: ', $line , '</div>';
		if (isset($file) && !empty($file))
			echo '<div class="error_text">File: ', $file , '</div>';
		echo '
			</div>';
	}

	/**
	* Show the footer.
	*
	* @param bol $inner
	*/
	public function footer($inner = true)
	{
		if (!empty($_GET['step']) && ($_GET['step'] == 1 || $_GET['step'] == 2) && $inner == true)
			echo '
				</p>
			</div>';
		echo '
		</div>
	</body>
</html>';
	}
	/**
	* Show the header.
	*
	* @param bol $inner
	*/
	public function header($inner = true)
	{
		global $import, $time_start;
		$time_start = time();

		echo '<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="', lng::get('imp.locale'), '" lang="', lng::get('imp.locale'), '">
	<head>
		<meta charset="UTF-8" />
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<title>', isset($import->xml->general->name) ? $import->xml->general->name . ' to ' : '', 'OpenImporter</title>
		<script type="text/javascript">
			function AJAXCall(url, callback, string)
			{
				var req = init();
				var string = string;
				req.onreadystatechange = processRequest;

				function init()
				{
					if (window.XMLHttpRequest)
						return new XMLHttpRequest();
					else if (window.ActiveXObject)
						return new ActiveXObject("Microsoft.XMLHTTP");
				}

				function processRequest()
				{
					// readyState of 4 signifies request is complete
					if (req.readyState == 4)
					{
						// status of 200 signifies sucessful HTTP call
						if (req.status == 200)
							if (callback) callback(req.responseXML, string);
					}
				}

				// make a HTTP GET request to the URL asynchronously
				this.doGet = function () {
					req.open("GET", url, true);
					req.send(null);
				};
			}

			function validateField(string)
			{
				var target = document.getElementById(string);
				var from = "', isset($import->xml->general->settings) ? $import->xml->general->settings : null , '";
				var to = "/Settings.php";
				var url = "import.php?xml=true&" + string + "=" + target.value.replace(/\/+$/g, "") + (string == "path_to" ? to : from);
				var ajax = new AJAXCall(url, validateCallback, string);
				ajax.doGet();
			}

			function validateCallback(responseXML, string)
			{
				var msg = responseXML.getElementsByTagName("valid")[0].firstChild.nodeValue;
				if (msg == "false")
				{
					var field = document.getElementById(string);
					var validate = document.getElementById(\'validate_\' + string);
					field.className = "invalid_field";
					validate.innerHTML = "', lng::get('imp.invalid') , '";
					// set the style on the div to invalid
					var submitBtn = document.getElementById("submit_button");
					submitBtn.disabled = true;
				}
				else
				{
					var field = document.getElementById(string);
					var validate = document.getElementById(\'validate_\' + string);
					field.className = "valid_field";
					validate.innerHTML = "installation validated!";
					var submitBtn = document.getElementById("submit_button");
					submitBtn.disabled = false;
				}
			}
		</script>
		<style type="text/css">
			body
			{
				background-color: #cbd9e7;
				margin: 0px;
				padding: 0px;
			}
			body, td
			{
				color: #000;
				font-size: small;
				font-family: arial;
			}
			a
			{
				color: #2a4259;
				text-decoration: none;
				border-bottom: 1px dashed #789;
			}
			#header
			{
				background-color: #809ab3;
				padding: 22px 4% 12px 4%;
				color: #fff;
				text-shadow: 0 0 8px #333;
				font-size: xx-large;
				border-bottom: 1px solid #fff;
				height: 40px;
			}
			#main
			{
				padding: 20px 30px;
				background-color: #fff;
				border-radius: 5px;
				margin: 7px;
				border: 1px solid #abadb3;
			}
			#path_from, #path_to
			{
				width: 480px;
			}
			.error_message, blockquote, .error
			{
				border: 1px dashed red;
				border-radius: 5px;
				background-color: #fee;
				padding: 1.5ex;
			}
			.error_text
			{
				color: red;
			}
			.content
			{
				border-radius: 3px;
				background-color: #eee;
				color: #444;
				margin: 1ex 0;
				padding: 1.2ex;
				border: 1px solid #abadb3;
			}
			.button
			{
				margin: 0 0.8em 0.8em 0.8em;
			}
			#submit_button
			{
				cursor: pointer;
			}
			h1
			{
				margin: 0;
				padding: 0;
				font-size: 24pt;
			}
			h2
			{
				font-size: 15pt;
				color: #809ab3;
				font-weight: bold;
			}
			form
			{
				margin: 0;
			}
			.textbox
			{
				padding-top: 2px;
				white-space: nowrap;
				padding-right: 1ex;
			}
			.bp_invalid
			{
				color:red;
				font-weight: bold;
			}
			.bp_valid
			{
				color:green;
			}
			.validate
			{
				font-style: italic;
				font-size: smaller;
			}
			.valid_field
			{
				background-color: #DEFEDD;
				border: 1px solid green;
			}
			.invalid_field
			{
				background-color: #fee;;
				border: 1px solid red;
			}
			#progressbar
			{
				position: relative;
				top: -28px;
				left: 255px;
			}
			progress
			{
				width: 300px;
			}
			dl
			{
				clear: right;
				overflow: auto;
				margin: 0 0 0 0;
				padding: 0;
			}
			dt
			{
				width: 20%;
				float: left;
				margin: 6px 5px 10px 0;
				padding: 0;
				clear: both;
			}
			dd
			{
				width: 78%;
				float: right;
				margin: 6px 0 3px 0;
				padding: 0;
			}
			#arrow_up
			{
				display: none;
			}
			#toggle_button
			{
				display: block;
				color: #2a4259;
				margin-bottom: 4px;
				cursor: pointer;
			}
			.arrow
			{
				font-size: 8pt;
			}
		</style>
	</head>
	<body>
		<div id="header">
			<h1 title="SMF is dead. The forks are your future :-P">', isset($import->xml->general->{'name'}) ? $import->xml->general->{'name'} . ' to ' : '', 'OpenImporter</h1>
		</div>
		<div id="main">';

		if (!empty($_GET['step']) && ($_GET['step'] == 1 || $_GET['step'] == 2) && $inner == true)
			echo '
			<h2 style="margin-top: 2ex">', lng::get('imp.importing'), '...</h2>
			<div class="content"><p>';
	}

	public function select_script($scripts)
	{
		echo '
			<h2>', lng::get('imp.which_software'), '</h2>
			<div class="content">';

		if (!empty($scripts))
		{
			echo '
				<p>', lng::get('imp.multiple_files'), '</p>
				<ul>';

			foreach ($scripts as $script)
				echo '
					<li>
						<a href="', $_SERVER['PHP_SELF'], '?import_script=', $script['path'], '">', $script['name'], '</a>
						<span>(', $script['path'], ')</span>
					</li>';

			echo '
				</ul>
			</div>
			<h2>', lng::get('imp.not_here'), '</h2>
			<div class="content">
				<p>', lng::get('imp.check_more'), '</p>
				<p>', lng::get('imp.having_problems'), '</p>';
		}
		else
		{
			echo '
				<p>', lng::get('imp.not_found'), '</p>
				<p>', lng::get('imp.not_found_download'), '</p>
				<a href="', $_SERVER['PHP_SELF'], '?import_script=">', lng::get('imp.try_again'), '</a>';
		}

		echo '
			</div>';
	}

	public function step0($object, $steps, $test_from, $test_to)
	{
		echo '
			<h2>', lng::get('imp.before_continue'), '</h2>
			<div class="content">
				<p>', sprintf(lng::get('imp.before_details'), (string) $object->xml->general->name ), '</p>
			</div>';
		echo '
			<h2>', lng::get('imp.where'), '</h2>
			<div class="content">
				<form action="', $_SERVER['PHP_SELF'], '?step=1', isset($_REQUEST['debug']) ? '&amp;debug=' . $_REQUEST['debug'] : '', '" method="post">
					<p>', lng::get('imp.locate_destination'), '</p>
					<div id="toggle_button">', lng::get('imp.advanced_options'), ' <span id="arrow_down" class="arrow">&#9660</span><span id="arrow_up" class="arrow">&#9650</span></div>
					<dl id="advanced_options" style="display: none; margin-top: 5px">
						<dt><label for="path_to">', lng::get('imp.path_to_destination'), ':</label></dt>
						<dd>
							<input type="text" name="path_to" id="path_to" value="', $_POST['path_to'], '" onblur="validateField(\'path_to\')" />
							<div id="validate_path_to" class="validate">', $test_to ? lng::get('imp.right_path') : lng::get('imp.change_path'), '</div>
						</dd>
					</dl>
					<dl>';

		if ($object->xml->general->settings)
			echo '
						<dt><label for="path_from">', lng::get('imp.path_to_source'),' ', $object->xml->general->name, ':</label></dt>
						<dd>
							<input type="text" name="path_from" id="path_from" value="', $_POST['path_from'], '" onblur="validateField(\'path_from\')" />
							<div id="validate_path_from" class="validate">', $test_from ? lng::get('imp.right_path') : lng::get('imp.change_path'), '</div>
						</dd>';

		// Any custom form elements?
		if ($object->xml->general->form)
		{
			foreach ($object->xml->general->form->children() as $field)
			{
				if ($field->attributes()->{'type'} == 'text')
					echo '
						<dt><label for="field', $field->attributes()->{'id'}, '">', $field->attributes()->{'label'}, ':</label></dt>
						<dd><input type="text" name="field', $field->attributes()->{'id'}, '" id="field', $field->attributes()->{'id'}, '" value="', isset($field->attributes()->{'default'}) ? $field->attributes()->{'default'} :'' ,'" size="', $field->attributes()->{'size'}, '" /></dd>';

				elseif ($field->attributes()->{'type'}== 'checked' || $field->attributes()->{'type'} == 'checkbox')
					echo '
						<dt></dt>
						<dd>
							<label for="field', $field->attributes()->{'id'}, '">
								<input type="checkbox" name="field', $field->attributes()->{'id'}, '" id="field', $field->attributes()->{'id'}, '" value="1"', $field->attributes()->{'type'} == 'checked' ? ' checked="checked"' : '', ' /> ', $field->attributes()->{'label'}, '
							</label>
						</dd>';
			}
		}

		echo '
						<dt><label for="db_pass">', lng::get('imp.database_passwd'),':</label></dt>
						<dd>
							<input type="password" name="db_pass" size="30" class="text" />
							<div style="font-style: italic; font-size: smaller">', lng::get('imp.database_verify'),'</div>
						</dd>';


		// Now for the steps.
		if (!empty($steps))
		{
			echo '
						<dt>', lng::get('imp.selected_only'),':</dt>
						<dd>';
			foreach ($steps as $key => $step)
				echo '
							<label><input type="checkbox" name="do_steps[', $key, ']" id="do_steps[', $key, ']" value="', $step['count'], '"', $step['mandatory'] ? 'readonly="readonly" ' : ' ', $step['checked'], '" /> ', ucfirst(str_replace('importing ', '', $step['name'])), '</label><br />';

			echo '
						</dd>';
		}

		echo '
					</dl>
					<div class="button"><input id="submit_button" name="submit_button" type="submit" value="', lng::get('imp.continue'),'" class="submit" /></div>
				</form>
			</div>';

		if (!empty($object->possible_scripts))
			echo '
			<h2>', lng::get('imp.not_this'),'</h2>
			<div class="content">
				<p>', sprintf(lng::get('imp.pick_different'), $_SERVER['PHP_SELF']), '</p>
			</div>';
		echo '
			<script type="text/javascript">
				document.getElementById(\'toggle_button\').onclick = function ()
				{
					var elem = document.getElementById(\'advanced_options\');
					var arrow_up = document.getElementById(\'arrow_up\');
					var arrow_down = document.getElementById(\'arrow_down\');
					if (!elem)
						return true;

					if (elem.style.display == \'none\')
					{
						elem.style.display = \'block\';
						arrow_down.style.display = \'none\';
						arrow_up.style.display = \'inline\';
					}
					else
					{
						elem.style.display = \'none\';
						arrow_down.style.display = \'inline\';
						arrow_up.style.display = \'none\';
					}

					return true;
				}
			</script>';
	}

	/**
	 * Display notification with the given status
	 *
	 * @param int $substep
	 * @param int $status
	 * @param string $title
	 * @param bool $hide = false
	 */
	public function status($substep, $status, $title, $hide = false)
	{
		if (isset($title) && $hide == false)
			echo '<span style="width: 250px; display: inline-block">' . $title . '...</span> ';

		if ($status == 1)
			echo '<span style="color: green">&#x2714</span>';

		if ($status == 2)
			echo '<span style="color: grey">&#x2714</span> (', lng::get('imp.skipped'),')';

		if ($status == 3)
			echo '<span style="color: red">&#x2718</span> (', lng::get('imp.not_found_skipped'),')';

		if ($status != 0)
			echo '<br />';
	}

	/**
	 * Display information related to step2
	 */
	public function step2()
	{
		echo '
				<span style="width: 250px; display: inline-block">', lng::get('imp.recalculate'), '...</span> ';
	}

	/**
	 * Display last step UI, completion status and allow eventually
	 * to delete the scripts
	 *
	 * @param string $name
	 * @param string $boardurl
	 * @param bool $writable if the files are writable, the UI will allow deletion
	 */
	public function step3($name, $boardurl, $writable)
	{
		echo '
			</div>
			<h2 style="margin-top: 2ex">', lng::get('imp.complete'), '</h2>
			<div class="content">
			<p>', lng::get('imp.congrats'),'</p>';

		if ($writable)
			echo '
				<div style="margin: 1ex; font-weight: bold">
					<label for="delete_self"><input type="checkbox" id="delete_self" onclick="doTheDelete()" />', lng::get('imp.check_box'), '</label>
				</div>
				<script type="text/javascript"><!-- // --><![CDATA[
					function doTheDelete()
					{
						new Image().src = "', $_SERVER['PHP_SELF'], '?delete=1&" + (+Date());
						(document.getElementById ? document.getElementById("delete_self") : document.all.delete_self).disabled = true;
					}
				// ]]></script>';
		echo '
				<p>', sprintf(lng::get('imp.all_imported'), $name), '</p>
				<p>', lng::get('imp.smooth_transition'), '</p>';
	}

	/**
	 * Display the progress bar,
	 * and inform the user about when the script is paused and re-run.
	 *
	 * @param int $bar
	 * @param int $value
	 * @param int $max
	 */
	public function time_limit($bar, $value, $max)
	{
		if (!empty($bar))
			echo '
			<div id="progressbar">
				<progress value="', $bar, '" max="100">', $bar, '%</progress>
			</div>';

		echo '
		</div>
		<h2 style="margin-top: 2ex">', lng::get('imp.not_done'),'</h2>
		<div class="content">
			<div style="margin-bottom: 15px; margin-top: 10px;"><span style="width: 250px; display: inline-block">', lng::get('imp.overall_progress'),'</span><progress value="', $value, '" max="', $max, '"></progress></div>
			<p>', lng::get('imp.importer_paused'), '</p>

			<form action="', $_SERVER['PHP_SELF'], '?step=', $_GET['step'], isset($_GET['substep']) ? '&amp;substep=' . $_GET['substep'] : '', '&amp;start=', $_REQUEST['start'], '" method="post" name="autoSubmit">
				<div align="right" style="margin: 1ex"><input name="b" type="submit" value="', lng::get('imp.continue'),'" /></div>
			</form>

			<script type="text/javascript"><!-- // --><![CDATA[
				var countdown = 3;
				window.onload = doAutoSubmit;

				function doAutoSubmit()
				{
					if (countdown == 0)
						document.autoSubmit.submit();
					else if (countdown == -1)
						return;

					document.autoSubmit.b.value = "', lng::get('imp.continue'),' (" + countdown + ")";
					countdown--;

					setTimeout("doAutoSubmit();", 1000);
				}
			// ]]></script>';
	}

	/**
	 * ajax response, whether the paths to the source and destination
	 * software are correctly set.
	 */
	public function xml()
	{
		if (isset($_GET['path_to']))
			$test_to = file_exists($_GET['path_to']);
		elseif (isset($_GET['path_from']))
			$test_to = file_exists($_GET['path_from']);
		else
			$test_to = false;

		header('Content-Type: text/xml');
		echo '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
	<valid>', $test_to ? 'true' : 'false' ,'</valid>';
	}
}

/**
* class import_exception extends the build-in Exception class and
* catches potential errors
*/
class import_exception extends Exception
{
	public static function error_handler_callback($code, $string, $file, $line)
	{
		$e = new self($string, $code);
		$e->line = $line;
		$e->file = $file;
		throw $e;
	}

	/**
	 * @param Exception $exception
	 */
	public static function exception_handler($exception)
	{
		global $import;

		$message = $exception->getMessage();
		$trace = $exception->getTrace();
		$line = $exception->getLine();
		$file = $exception->getFile();
		$import->template->error($message, $trace[0]['args'][1], $line, $file);
	}
}

/**
 * we need Cooooookies..
 */
class Cookie
{
	/**
	 * Constructor
	 * @return boolean
	 */
	public function Cookie()
	{
		return true;
	}

	/**
	 * set a cookie
	 * @param type $data
	 * @param type $name
	 * @return boolean
	 */
	public function set($data, $name = 'openimporter_cookie')
	{
		if (!empty($data))
		{
			setcookie($name, serialize($data), time()+ 86400);
			$_COOKIE[$name] = serialize($data);
			return true;
		}
		return false;
	}

	/**
	 * get our cookie
	 * @param type $name
	 * @return boolean
	 */
	public function get($name = 'openimporter_cookie')
	{
		if (isset($_COOKIE[$name]))
		{
			$cookie = unserialize($_COOKIE[$name]);
			return $cookie;
		}

		return false;
	}

	/**
	 * once we are done, we should destroy our cookie
	 * @param type $name
	 * @return boolean
	 */
	public function destroy($name = 'openimporter_cookie')
	{
		setcookie($name, '');
		unset($_COOKIE[$name]);

		return true;
	}

	/**
	 * extend the cookie with new infos
	 * @param type $data
	 * @param type $name
	 * @return boolean
	 */
	public function extend($data, $name = 'openimporter_cookie')
	{
		$cookie = unserialize($_COOKIE[$name]);
		if (!empty($cookie) && isset($data))
			$merged = array_merge((array)$cookie, (array) $data);

		$this->set($merged);
		$_COOKIE[$name] = serialize($merged);

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
* helper function for old attachments
*
* @param string $filename
* @param int $attachment_id
* @return string
*/
function getLegacyAttachmentFilename($filename, $attachment_id)
{
	// Remove special accented characters - ie. sí (because they won't write to the filesystem well.)
	$clean_name = strtr($filename, 'ŠŽšžŸÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÑÒÓÔÕÖØÙÚÛÜÝàáâãäåçèéêëìíîïñòóôõöøùúûüýÿ', 'SZszYAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy');
	$clean_name = strtr($clean_name, array('Þ' => 'TH', 'þ' => 'th', 'Ð' => 'DH', 'ð' => 'dh', 'ß' => 'ss', 'Œ' => 'OE', 'œ' => 'oe', 'Æ' => 'AE', 'æ' => 'ae', 'µ' => 'u'));

	// Get rid of dots, spaces, and other weird characters.
	$clean_name = preg_replace(array('/\s/', '/[^\w_\.\-]/'), array('_', ''), $clean_name);
		return $attachment_id . '_' . strtr($clean_name, '.', '_') . md5($clean_name);
}

/**
 * helper function to create an encrypted attachment name
 *
 * @param string $filename
 * @return string
 */
function createAttachmentFilehash($filename)
{
	return sha1(md5($filename . time()) . mt_rand());
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
 * function copy_smileys is used to copy smileys from a source to destination.
 * @param type $source
 * @param type $dest
 * @return type
 */
function copy_smileys($source, $dest)
{
	if (!is_dir($source) || !($dir = opendir($source)))
		return;

	while ($file = readdir($dir))
	{
		if ($file == '.' || $file == '..')
			continue;

		// If we have a directory create it on the destination and copy contents into it!
		if (is_dir($source . '/' . $file))
		{
			if (!is_dir($dest))
				@mkdir($dest . '/' . $file, 0777);
			copy_dir($source . '/' . $file, $dest . '/' . $file);
		}
		else
		{
			if (!is_dir($dest))
				@mkdir($dest . '/' . $file, 0777);
			copy($source . '/' . $file, $dest . '/' . $file);
		}
	}
	closedir($dir);
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
		if (is_dir($source . '/'. $file))
		{
			if (!is_dir($dest))
				@mkdir($dest, 0777);
			copy_dir($source . '/' . $file, $dest . '/' . $file);
		}
		else
		{
			if (!is_dir($dest))
				@mkdir($dest, 0777);
			copy($source . '/' . $file, $dest . '/' . $file);
		}
	}
	closedir($dir);
}

/**
 *
 * Get the id_member associated with the specified message.
 * @global type $to_prefix
 * @global type $db
 * @param type $messageID
 * @return int
 */
function getMsgMemberID($messageID)
{
	global $to_prefix, $db;

	// Find the topic and make sure the member still exists.
	$result = $db->query("
		SELECT IFNULL(mem.id_member, 0)
		FROM {$to_prefix}messages AS m
		LEFT JOIN {$to_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_msg = " . (int) $messageID . "
		LIMIT 1");
	if ($db->num_rows($result) > 0)
		list ($memberID) = $db->fetch_row($result);
	// The message doesn't even exist.
	else
		$memberID = 0;
	$db->free_result($result);

		return $memberID;
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

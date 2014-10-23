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

/**
 * Object Importer creates the main XML object.
 * It detects and initializes the script to run.
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
	 * The template, basically our UI.
	 * @var object
	 */
	public $template;

	/**
	 * The headers of the response.
	 * @var object
	 * @todo probably not necessary
	 */
	protected $headers;

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
	public function __construct($lang, $template, $headers)
	{
		$this->lng = $lang;

		// initialize some objects
		$this->template = $template;
		$this->headers = $headers;

		// The current step - starts at 0.
		$_GET['step'] = isset($_GET['step']) ? (int) @$_GET['step'] : 0;
		$_REQUEST['start'] = isset($_REQUEST['start']) ? (int) @$_REQUEST['start'] : 0;

		if (!empty($this->_script))
			$this->_loadImporter(dirname(__FILE__) . DIRECTORY_SEPARATOR . $this->_script);
	}

	public function setScript($script)
	{
		$this->_script = $script;
	}

	public function reloadImporter()
	{
		if (!empty($this->_script))
			$this->_loadImporter(dirname(__FILE__) . DIRECTORY_SEPARATOR . $this->_script);
	}

	protected function _loadImporter($file)
	{
		$this->_preparse_xml($file);

		// This is the helper class
		$source_helper = str_replace('.xml', '.php', $file);
		require_once($source_helper);

		// Maybe the "destination" comes with php helper functions?
		$path = dirname($file);
		$dest_helper = $path . '/' . basename($path) . '_importer.php';
		require_once($dest_helper);

		$this->_importer_base_class_name = str_replace('.', '_', basename($dest_helper));
		$this->destination = new $this->_importer_base_class_name();

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
			ImportException::exception_handler($e, $this->template);
		}
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
		global $to_prefix;

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

		$this->_boardurl = $this->destination->getDestinationURL();

		if ($this->_boardurl === false)
			return $this->doStep0($this->lng->get('imp.settings_not_found'), $this);

		if (!$this->destination->verifyDbPass($this->data['db_pass']))
			return $this->doStep0($this->lng->get('imp.password_incorrect'), $this);

		// Check the steps that we have decided to go through.
		if (!isset($_POST['do_steps']) && !isset($_SESSION['do_steps']))
		{
			return $this->doStep0($this->lng->get('imp.select_step'));
		}
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
			ImportException::exception_handler($e, $this->template);
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

		// @todo What is the use-case for these?
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
				FROM "' . $this->from_prefix . $this->settings->getTableTest() . '"', true);

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

	public function determineProgress()
	{
		$progress_counter = 0;
		$counter_current_step = 0;
		$import_steps = array();

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

				$import_steps[$counter_current_step]['counter'] = $current;
			}
			$counter_current_step++;
		}
		return array($progress_counter, $import_steps);
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

		$step1_importer_class = $this->_importer_base_class_name . '_step1';
		$step1_importer = new $step1_importer_class($this->db, $this->to_prefix);

		if ($this->xml->general->globals)
			foreach (explode(',', $this->xml->general->globals) as $global)
				global $$global;

		$substep = 0;

		foreach ($this->xml->steps1->step as $step)
			$this->_processSteps($step, $substep, $do_steps, $step1_importer);
	}

	protected function _processSteps($step, &$substep, &$do_steps, $step1_importer)
	{
		// These are temporarily needed to support the current xml importers
		// a.k.a. There is more important stuff to do.
		// a.k.a. I'm too lazy to change all of the now. :P
		// @todo remove
		$to_prefix = $this->to_prefix;
		$db = $this->db;

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

			if (isset($step->presqlMethod))
				$step1_importer->beforeSql($step->presqlMethod);

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
				$step1_importer->doSpecialTable($special_table);

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

						$step1_importer->doSpecialTable($special_table, $row);

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

						$row = $step1_importer->fixTexts($row);

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
	public function doStep2($substep = 0)
	{
		$step2_importer_class = $this->_importer_base_class_name . '_step2';
		$instance = new $step2_importer_class($this->db, $this->to_prefix);

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
			if ($substep <= $key)
			{
				call_user_func(array($instance, $method));
			}

			$substep++;
			pastTime($substep);
		}

		return $key;
	}

	/**
	 * we are done :)
	 *
	 * @global Database $db
	 * @global type $boardurl
	 * @return boolean
	 */
	public function doStep3($import_steps)
	{
		global $boardurl;

		$step3_importer_class = $this->_importer_base_class_name . '_step3';
		$instance = new $step3_importer_class($this->db, $this->to_prefix);

		$instance->run($import_steps);
	}
}
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
	 * The destination object.
	 * @var object
	 */
	public $destination;

	/**
	 * The template, basically our UI.
	 * @var object
	 */
	public $template;

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
	private $_script = '';

	/**
	 * This is the URL from our Installation.
	 * @var string
	 */
	private $_boardurl = '';

	/**
	 * initialize the main Importer object
	 */
	public function __construct($lang, $template)
	{
		// initialize some objects
		$this->lng = $lang;
		$this->template = $template;

		// The current step - starts at 0.
		$_GET['step'] = isset($_GET['step']) ? (int) @$_GET['step'] : 0;
		$_REQUEST['start'] = isset($_REQUEST['start']) ? (int) @$_REQUEST['start'] : 0;

		if (!empty($this->_script))
			$this->_loadImporter(BASEDIR . DIRECTORY_SEPARATOR . 'Importers' . DIRECTORY_SEPARATOR . $this->_script);
	}

	public function setScript($script)
	{
		$this->_script = $script;
	}

	public function reloadImporter()
	{
		if (!empty($this->_script))
			$this->_loadImporter(BASEDIR . DIRECTORY_SEPARATOR . 'Importers' . DIRECTORY_SEPARATOR . $this->_script);
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

		$this->_importer_base_class_name = str_replace('.', '_', basename($dest_helper, '.php'));
		$this->destination = new $this->_importer_base_class_name();

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

	public function getFormSettings()
	{
		$options = array();
		$class = (string) $this->xml->general->className;
		$settings = new $class();
		if (!isset($this->path_from))
			$this->path_from = BASEDIR;

		if (method_exists($settings, 'loadSettings'))
		{
			$options = array(
				array(
					'id' => 'path_from',
					'label' => $this->lng->get('imp.path_to_source') . ' ' . $this->xml->general->name,
					'type' => 'text',
					'correct' => $settings->loadSettings($this->path_from, true) ? $this->lng->get('imp.change_path') : $this->lng->get('imp.right_path'),
					'validate' => true,
				),
			);
		}

		$options[] = array();

		// Any custom form elements?
		if ($this->xml->general->form)
		{
			foreach ($this->xml->general->form->children() as $field)
			{
				if ($field->attributes()->{'type'} == 'text')
				{
					$options[] = array(
						'id' => 'field' . $field->attributes()->{'id'},
						'label' => $field->attributes()->{'label'},
						'value' => isset($field->attributes()->{'default'}) ? $field->attributes()->{'default'} : '',
						'correct' => '',
						'type' => 'text',
					);
				}
				else
				{
					$options[] = array(
						'id' => 'field' . $field->attributes()->{'id'},
						'label' => $field->attributes()->{'label'},
						'value' => 1,
						'attributes' => $field->attributes()->{'type'} == 'checked' ? ' checked="checked"' : '',
						'type' => 'checkbox',
					);
				}
			}
		}

		$options[] = array(
			'id' => 'db_pass',
			'label' => $this->lng->get('imp.database_passwd'),
			'correct' => $this->lng->get('imp.database_verify'),
			'type' => 'password',
		);

		$steps = $this->_find_steps();

		if (!empty($steps))
		{
			foreach ($steps as $key => $step)
				$steps[$key]['label'] = ucfirst(str_replace('importing ', '', $step['name']));

			$options[] = array(
				'id' => 'do_steps',
				'label' => $this->lng->get('imp.selected_only'),
				'value' => $steps,
				'type' => 'steps',
			);
		}

		return $options;
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
		$class = (string) $this->xml->general->className;
		$this->settings = new $class();

		if (method_exists($this->settings, 'setDefines'))
			$this->settings->setDefines();

		if (method_exists($this->settings, 'setGlobals'))
			$this->settings->setGlobals();

		//Dirty hack
		if (isset($_SESSION['store_globals']))
		{
			foreach ($_SESSION['store_globals'] as $varname => $value)
			{
				$GLOBALS[$varname] = $value;
			}
		}

		// catch form elements and globalize them for later use..
		if ($this->xml->general->form)
		{
			foreach ($this->xml->general->form->children() as $global)
				global $$global;
		}

		if (method_exists($this->settings, 'loadSettings') && !empty($this->path_from))
			$found = $this->settings->loadSettings($this->path_from);
		else
			$found = true;

		if (!$found)
		{
			if (@ini_get('open_basedir') != '')
				throw new Exception($this->lng->get(array('imp.open_basedir', (string) $this->xml->general->name)));

			throw new Exception($this->lng->get(array('imp.config_not_found', (string) $this->xml->general->name)));
		}

		// Any custom form elements to speak of?
		$this-init_form_data();

		$this->_boardurl = $this->destination->getDestinationURL($this->path_to);

		if ($this->_boardurl === false)
			throw new Exception($this->lng->get(array('imp.settings_not_found', $this->destination->getName())));

		if (!$this->destination->verifyDbPass($this->data['db_pass']))
			throw new Exception($this->lng->get('imp.password_incorrect'));

		// Check the steps that we have decided to go through.
		if (!isset($_POST['do_steps']) && !isset($_SESSION['do_steps']))
		{
			throw new Exception($this->lng->get('imp.select_step'));
		}
		elseif (isset($_POST['do_steps']))
		{
			$_SESSION['do_steps'] = array();
			foreach ($_POST['do_steps'] as $key => $step)
				$_SESSION['do_steps'][$key] = $step;
		}

		$this->init_db();

		// @todo What is the use-case for these?
		// Custom variables from our importer?
		if (isset($this->xml->general->variables))
		{
			foreach ($this->xml->general->variables as $eval_me)
				eval($eval_me);
		}

		if ($_REQUEST['start'] == 0 && empty($_GET['substep']) && ($_GET['step'] == 1 || $_GET['step'] == 2) && isset($this->xml->general->table_test))
		{
			$result = $this->db->query('
				SELECT COUNT(*)
				FROM "' . $this->from_prefix . $this->settings->getTableTest() . '"', true);

			if ($result === false)
				throw new Exception(sprintf($this->lng->get('imp.permission_denied') . mysqli_error($this->db->con), (string) $this->xml->general->name));

			$this->db->free_result($result);
		}
	}

	protected function init_db()
	{
		try
		{
			list ($db_server, $db_user, $db_passwd, $db_persist, $db_prefix, $db_name) = $this->destination->dbConnectionData();

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

		$this->from_prefix = $this->settings->getPrefix();

		if (preg_match('~^`[^`]+`.\d~', $this->from_prefix) != 0)
		{
			$this->from_prefix = strtr($this->from_prefix, array('`' => ''));
		}

		// SQL_BIG_SELECTS: If set to 0, MySQL aborts SELECT statements that are
		// likely to take a very long time to execute (that is, statements for
		// which the optimizer estimates that the number of examined rows exceeds
		// the value of max_join_size)
		// Source:
		// https://dev.mysql.com/doc/refman/5.5/en/server-system-variables.html#sysvar_sql_big_selects
		$this->db->query("SET @@SQL_BIG_SELECTS = 1");
		$this->db->query("SET @@MAX_JOIN_SIZE = 18446744073709551615");
	}

	protected function init_form_data()
	{
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
					$GLOBALS[$k] = $v;
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
					$GLOBALS[$k] = $v;
			}
		}
	}

	/**
	 * Looks at the importer and returns the steps that it's able to make.
	 * @return int
	 */
	protected function _find_steps()
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

		$xmlParser = new XmlProcessor($this->db, $this->to_prefix, $this->from_prefix);

		// loop through each step
		foreach ($this->xml->steps1->step as $counts)
		{
			if ($counts->detect)
			{
				$count = $xmlParser->fix_params((string) $counts->detect);
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
	 * The important one, transfer the content from the source forum to our
	 * destination system.
	 *
	 * @param int $do_steps
	 * @return boolean
	 */
	public function doStep1($do_steps)
	{
		$step1_importer_class = $this->_importer_base_class_name . '_step1';
		$step1_importer = new $step1_importer_class($this->db, $this->to_prefix);

		if ($this->xml->general->globals)
			foreach (explode(',', $this->xml->general->globals) as $global)
				global $$global;

		$substep = 0;

		$xmlParser = new XmlProcessor($this->db, $this->to_prefix, $this->from_prefix);

		foreach ($this->xml->steps1->step as $step)
			$xmlParser->processSteps($step, $substep, $do_steps, $step1_importer);
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
		$step3_importer_class = $this->_importer_base_class_name . '_step3';
		$instance = new $step3_importer_class($this->db, $this->to_prefix);

		$instance->run($import_steps);
	}
}
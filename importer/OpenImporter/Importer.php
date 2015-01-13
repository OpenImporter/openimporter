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

use Symfony\Component\Yaml\Parser;

if (!defined('DS'))
	define('DS', DIRECTORY_SEPARATOR);

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
	 * Contains any kind of configuration.
	 * @var object
	 */
	public $config;

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
	protected $ignore = true;

	/**
	 * Used to switch between INSERT and REPLACE
	 * @var boolean
	 */
	protected $replace = false;

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
	protected $_script = '';

	/**
	 * This is the URL from our Installation.
	 * @var string
	 */
	protected $_boardurl = '';

	/**
	 * The "base" class name of the destination system.
	 * @var string
	 */
	protected $_importer_base_class_name = '';

	/**
	 * Holds the object that contains the settings of the source system
	 * @var object
	 */
	public $settings  = null;

	/**
	 * initialize the main Importer object
	 */
	public function __construct($config, $lang, $template)
	{
		// initialize some objects
		$this->config = $config;
		$this->lng = $lang;
		$this->template = $template;

		// The current step - starts at 0.
		$this->config->step = $_GET['step'] = isset($_GET['step']) ? (int) $_GET['step'] : 0;
		$this->config->start = $_REQUEST['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;

		if (!empty($this->config->script))
			$this->_loadImporter($this->config->importers_dir . DS . $this->config->script);
	}

	public function setData($data)
	{
		$this->data = $data;
	}

	public function reloadImporter()
	{
		if (!empty($this->config->script))
			$this->_loadImporter($this->config->script);
	}

	protected function _loadImporter($files)
	{
		$this->_loadSource($files['source']);
		$this->_loadDestination($files['destination']);
	}

	protected function _loadSource($file)
	{
		$full_path = $this->config->importers_dir . DS . 'sources' . DS . $file;
		$this->_preparse_xml($full_path);

		// This is the helper class
		$source_helper = str_replace('.xml', '.php', $full_path);
		require_once($source_helper);
	}

	protected function _loadDestination($file)
	{
		$full_path = $this->config->importers_dir . DS . 'destinations' . DS . $file;

		require_once($full_path);

		$this->_importer_base_class_name = str_replace('.', '_', basename($file, '.php'));

		$this->config->destination = new $this->_importer_base_class_name();

		$this->_loadSettings();

		$this->config->destination->setParam($this->db, $this->config);
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

	public function populateFormFields($form)
	{
		$form_path = isset($this->config->path_to) ? $this->config->path_to : BASEDIR;
		$form->addOption($this->config->destination->getFormFields($form_path));

		$path_from = $this->hasSettingFile();
		if ($path_from !== null)
		{
			$form->addOption(array(
				'id' => 'path_from',
				'label' => array('path_from', $this->xml->general->name),
				'default' => $this->config->path_from,
				'type' => 'text',
				'correct' => $path_from ? 'change_path' : 'right_path',
				'validate' => true,
			));
		}

		// Any custom form elements?
		if ($this->xml->general->form)
		{
			foreach ($this->xml->general->form->children() as $field)
				$form->addField($field);
		}

		$form->addOption(array(
			'id' => 'db_pass',
			'label' => 'database_passwd',
			'correct' => 'database_verify',
			'type' => 'password',
		));

		$form->addSeparator();

		$steps = $this->_find_steps();

		if (!empty($steps))
		{
			foreach ($steps as $key => $step)
				$steps[$key]['label'] = ucfirst(str_replace('importing ', '', $step['name']));

			$form->addOption(array(
				'id' => 'do_steps',
				'label' => 'selected_only',
				'default' => $steps,
				'type' => 'steps',
			));
		}
	}

	/**
	 * Verifies that a configuration file exists.
	 *
	 * @return boolean|null
	 */
	protected function hasSettingFile()
	{
		$class = (string) $this->xml->general->className;
		$settings = new $class();

		if (!isset($this->config->path_from))
			$this->config->path_from = BASEDIR;

		$path_from = $settings->loadSettings($this->config->path_from, true);

		return $path_from;
	}

	/**
	 * Prepare the importer with custom settings of the source
	 *
	 * @throws Exception
	 * @return boolean|null
	 */
	private function _loadSettings()
	{
		$class = (string) $this->xml->general->className;
		$this->config->source = new $class();

		$this->config->source->setDefines();

		$this->config->source->setGlobals();

		//Dirty hack
		if (isset($_SESSION['store_globals']))
		{
			foreach ($_SESSION['store_globals'] as $varname => $value)
			{
				$GLOBALS[$varname] = $value;
			}
		}

		$this->loadSettings();

		// Any custom form elements to speak of?
		$this->init_form_data();

		if (empty($this->config->path_to))
			return;
		$this->config->boardurl = $this->config->destination->getDestinationURL($this->config->path_to);

		if ($this->config->boardurl === false)
			throw new Exception($this->lng->get(array('settings_not_found', $this->config->destination->getName())));

		if (!$this->config->destination->verifyDbPass($this->data['db_pass']))
			throw new Exception($this->lng->get('password_incorrect'));

		// Check the steps that we have decided to go through.
		if (!isset($_POST['do_steps']) && !isset($_SESSION['do_steps']))
		{
			throw new Exception($this->lng->get('select_step'));
		}
		elseif (isset($_POST['do_steps']))
		{
			$_SESSION['do_steps'] = array();
			foreach ($_POST['do_steps'] as $key => $step)
				$_SESSION['do_steps'][$key] = $step;
		}

		$this->init_db();
		$this->config->source->setUtils($this->db, $this->config);

		$this->testTable();
	}

	/**
	 * This method is supposed to run a spot-test on a single table to verify...
	 * What?
	 * Dunno exactly, maybe that the converter is not wrong, but in that case, one
	 * table may not be enough...
	 *
	 * @todo make it useful or remove it?
	 */
	protected function testTable()
	{
		if ($_REQUEST['start'] == 0 && empty($_GET['substep']) && ($_GET['step'] == 1 || $_GET['step'] == 2))
		{
			$result = $this->db->query('
				SELECT COUNT(*)
				FROM ' . $this->config->from_prefix . $this->config->source->getTableTest(), true);

			if ($result === false)
				throw new Exception($this->lng->get(array('permission_denied', $this->db->getLastError(), (string) $this->xml->general->name)));

			$this->db->free_result($result);
		}
	}

	protected function loadSettings()
	{
		if (!empty($this->config->path_from))
			$found = $this->config->source->loadSettings($this->config->path_from);
		else
			$found = true;

		if ($found === false)
		{
			if (@ini_get('open_basedir') != '')
				throw new Exception($this->lng->get(array('open_basedir', (string) $this->xml->general->name)));

			throw new Exception($this->lng->get(array('config_not_found', (string) $this->xml->general->name)));
		}
	}

	protected function init_db()
	{
		try
		{
			list ($db_server, $db_user, $db_passwd, $db_persist, $db_prefix, $db_name) = $this->config->destination->dbConnectionData();

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
			// @todo ???
			if (is_numeric(substr($db_prefix, 0, 1)))
				$this->config->to_prefix = $db_name . '.' . $db_prefix;
			else
				$this->config->to_prefix = '`' . $db_name . '`.' . $db_prefix;
		}
		else
		{
			$this->config->to_prefix = $db_prefix;
		}

		$this->config->from_prefix = $this->config->source->getPrefix();

		if (preg_match('~^`[^`]+`.\d~', $this->config->from_prefix) != 0)
		{
			$this->config->from_prefix = strtr($this->config->from_prefix, array('`' => ''));
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
	 * @return mixed[]
	 */
	protected function _find_steps()
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

	public function determineProgress()
	{
		$progress_counter = 0;
		$counter_current_step = 0;
		$import_steps = array();

		$xmlParser = new XmlProcessor($this->db, $this->config, $this->template, $this->xml);

		// loop through each step
		foreach ($this->xml->step as $counts)
		{
			if ($counts->detect)
			{
				$current = $xmlParser->getCurrent((string) $counts->detect);

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
		$step1_importer = new $step1_importer_class($this->db, $this->config);

		if ($this->xml->general->globals)
			foreach (explode(',', $this->xml->general->globals) as $global)
				global $$global;

		$substep = 0;

		$skeleton = new Parser();
		$skeleton_parsed = $skeleton->parse(file_get_contents($this->config->importers_dir . '/importer_skeleton.yml'));

		$xmlParser = new XmlProcessor($this->db, $this->config, $this->template, $this->xml);
		$xmlParser->setImporter($step1_importer);
		$xmlParser->setSkeleton($skeleton_parsed);

		foreach ($this->xml->step as $step)
			$xmlParser->processSteps($step, $substep, $do_steps);
	}

	/**
	 * we have imported the old database, let's recalculate the forum statistics.
	 *
	 * @return boolean
	 */
	public function doStep2()
	{
		$step2_importer_class = $this->_importer_base_class_name . '_step2';
		$instance = new $step2_importer_class($this->db, $this->config);

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
	 * @return boolean
	 */
	public function doStep3()
	{
		$step3_importer_class = $this->_importer_base_class_name . '_step3';
		$instance = new $step3_importer_class($this->db, $this->config);

		$instance->run($this->lng->get(array('imported_from', $this->xml->general->name)));
	}
}
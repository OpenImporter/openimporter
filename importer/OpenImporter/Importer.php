<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD https://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 *
 * This file contains code based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:	BSD, See included LICENSE.TXT for terms and conditions.
 */

namespace OpenImporter;

if (!defined('DS'))
{
	define('DS', DIRECTORY_SEPARATOR);
}

/**
 * Object Importer creates the main XML object.
 * It detects and initializes the script to run.
 *
 * @class Importer
 */
class Importer
{
	/** @var Lang The "translator" (i.e. the Lang object) */
	public $lng;

	/** @var Configurator Contains any kind of configuration. */
	public $config;

	/** @var object The destination object. */
	public $destination;

	/** @var Template The template, basically our UI. */
	public $template;

	/** @var object The XML file which will be used from the importer (output from SimpleXMLElement) */
	public $xml;

	/** @var array Data used by the script and stored in session between reload and the following one. */
	public $data = array();

	/** @var object Holds the object that contains the settings of the source system */
	public $settings;

	/** @var Database This is our main database object. */
	protected $db;

	/** @var string The "base" class name of the destination system. */
	protected $_importer_base_class_name = '';

	/**
	 * Importer constructor.
	 * Initialize the main Importer object
	 *
	 * @param Configurator $config
	 * @param Lang $lang
	 * @param Template $template
	 */
	public function __construct($config, $lang, $template)
	{
		// initialize some objects
		$this->config = $config;
		$this->lng = $lang;
		$this->template = $template;

		// The current step - starts at 0.
		$_GET['step'] = isset($_GET['step']) ? (int) $_GET['step'] : 0;
		$this->config->step = $_GET['step'];
		$_REQUEST['start'] = isset($_REQUEST['start']) ? (int) $_REQUEST['start'] : 0;
		$this->config->start = $_REQUEST['start'];

		if (!empty($this->config->script))
		{
			$this->_loadImporter(BASEDIR . DS . 'Importers' . DS . $this->config->script);
		}
	}

	protected function _loadImporter($file)
	{
		$this->_preparse_xml($file);

		// This is the helper class
		$source_helper = str_replace('.xml', '.php', $file);
		require_once($source_helper);

		// The "destination" php helper functions
		$path = dirname($file);
		$destination_helper = $path . DS . basename($path) . '_importer.php';
		require_once($destination_helper);

		// Initiate the class
		$this->_importer_base_class_name = str_replace('.', '_', basename($destination_helper, '.php'));
		$this->config->destination = new $this->_importer_base_class_name();

		$this->_loadSettings();

		$this->config->destination->setParam($this->db, $this->config);
	}

	/**
	 * loads the _importer.xml files
	 *
	 * @param string $file
	 * @throws ImportException
	 */
	private function _preparse_xml($file)
	{
		try
		{
			if (!$this->xml = simplexml_load_string(file_get_contents($file), 'SimpleXMLElement', LIBXML_NOCDATA))
			{
				throw new ImportException('XML-Syntax error in file: ' . $file);
			}

			$this->xml = simplexml_load_string(file_get_contents($file), 'SimpleXMLElement', LIBXML_NOCDATA);
		}
		catch (\Exception $e)
		{
			ImportException::exception_handler($e, $this->template);
		}
	}

	/**
	 * Prepare the importer with custom settings of the source
	 *
	 * @throws \Exception
	 */
	private function _loadSettings()
	{
		// Initiate the source class
		$class = (string) $this->xml->general->className;
		$this->config->source = new $class();

		// Defines / Globals
		$this->config->source->setDefines();
		$this->config->source->setGlobals();

		// Dirty hack
		if (isset($_SESSION['store_globals']))
		{
			foreach ($_SESSION['store_globals'] as $var_name => $value)
			{
				$GLOBALS[$var_name] = $value;
			}
		}

		// Load the settings from the source forum
		$this->loadSettings();

		// Any custom form elements to speak of?
		$this->init_form_data();

		// Check passwords, and paths?
		if (empty($this->config->path_to))
		{
			return;
		}

		$this->config->boardurl = $this->config->destination->getDestinationURL($this->config->path_to);
		if ($this->config->boardurl === false)
		{
			throw new \Exception($this->lng->get(array('settings_not_found', $this->config->destination->getName())));
		}

		if (!$this->config->destination->verifyDbPass($this->data['db_pass']))
		{
			throw new \Exception($this->lng->get('password_incorrect'));
		}

		// Check the steps that we have decided to go through.
		if (!isset($_POST['do_steps']) && !isset($_SESSION['do_steps']))
		{
			throw new \Exception($this->lng->get('select_step'));
		}

		if (isset($_POST['do_steps']))
		{
			$_SESSION['do_steps'] = array();
			foreach ($_POST['do_steps'] as $key => $step)
			{
				$_SESSION['do_steps'][$key] = $step;
			}
		}

		$this->init_db();
		$this->config->source->setUtils($this->db, $this->config);

		// @todo What is the use-case for these?
		// Custom variables from our importer?
		if (isset($this->xml->general->variables))
		{
			foreach ($this->xml->general->variables as $eval_me)
			{
				eval($eval_me);
			}
		}

		$this->testTable();
	}

	/**
	 * Calls the loadSettings function of AbstractSourceImporter
	 * Used to load source forum settings
	 *
	 * @throws \Exception
	 */
	protected function loadSettings()
	{
		$found = true;
		if (!empty($this->config->path_from))
		{
			$found = $this->config->source->loadSettings($this->config->path_from);
		}

		if ($found === false)
		{
			if (@ini_get('open_basedir') !== '')
			{
				throw new \Exception($this->lng->get(array('open_basedir', (string) $this->xml->general->name)));
			}

			throw new \Exception($this->lng->get(array('config_not_found', (string) $this->xml->general->name)));
		}
	}

	protected function init_form_data()
	{
		if ($this->xml->general->form && !empty($_SESSION['import_parameters']))
		{
			foreach ($this->xml->general->form->children() as $param)
			{
				$check = $_POST['field' . $param['id']];
				if (isset($check))
				{
					$var = (string) $param;
					$_SESSION['import_parameters']['field' . $param['id']][$var] = $check;
				}
			}

			// Should already be global'd.
			foreach ($_SESSION['import_parameters'] as $id)
			{
				foreach ($id as $k => $v)
				{
					$GLOBALS[$k] = $v;
				}
			}
		}
		elseif ($this->xml->general->form)
		{
			$_SESSION['import_parameters'] = array();
			foreach ($this->xml->general->form->children() as $param)
			{
				$var = (string) $param;
				$check = $_POST['field' . $param['id']];
				if (isset($check))
				{
					$_SESSION['import_parameters']['field' . $param['id']][$var] = $check;
				}
				else
				{
					$_SESSION['import_parameters']['field' . $param['id']][$var] = null;
				}
			}

			foreach ($_SESSION['import_parameters'] as $id)
			{
				foreach ($id as $k => $v)
				{
					$GLOBALS[$k] = $v;
				}
			}
		}
	}

	protected function init_db()
	{
		try
		{
			list ($db_server, $db_user, $db_passwd, $db_persist, $db_prefix, $db_name) = $this->config->destination->dbConnectionData();

			$this->db = new Database($db_server, $db_user, $db_passwd, $db_persist);

			// We want UTF8 only, let's set our mysql connection to utf8
			$this->db->query('SET NAMES \'utf8\'');
		}
		catch (\Exception $e)
		{
			ImportException::exception_handler($e, $this->template);
			die();
		}

		if (strpos($db_prefix, '.') !== false)
		{
			$this->config->to_prefix = $db_prefix;
		}
		elseif (is_numeric(substr($db_prefix, 0, 1)))
		{
			$this->config->to_prefix = $db_name . '.' . $db_prefix;
		}
		else
		{
			$this->config->to_prefix = '`' . $db_name . '`.' . $db_prefix;
		}

		$this->config->from_prefix = $this->config->source->getPrefix();

		if (preg_match('~^`[^`]+`.\d~', $this->config->from_prefix) !== 0)
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

	protected function testTable()
	{
		if ((int) $_REQUEST['start'] === 0
			&& empty($_GET['substep']) && ((int) $_GET['step'] === 1 || (int) $_GET['step'] === 2))
		{
			$result = $this->db->query('
				SELECT COUNT(*)
				FROM ' . $this->config->from_prefix . $this->config->source->getTableTest(), true);

			if ($result === false)
			{
				throw new \Exception($this->lng->get(array('permission_denied', $this->db->getLastError(), (string) $this->xml->general->name)));
			}

			$this->db->free_result($result);
		}
	}

	public function setData($data)
	{
		$this->data = $data;
	}

	public function reloadImporter()
	{
		if (!empty($this->config->script))
		{
			$this->_loadImporter(BASEDIR . DS . 'Importers' . DS . $this->config->script);
		}
	}

	/**
	 * @param Form $form
	 */
	public function populateFormFields($form)
	{
		// From forum path
		$form_path = $this->config->path_to ?? dirname(BASEDIR);
		$form->addOption($this->config->destination->getFormFields($form_path));

		$class = (string) $this->xml->general->className;
		$settings = new $class();

		// To path
		if (!isset($this->config->path_from))
		{
			$this->config->path_from = dirname(BASEDIR);
		}

		// Check if we can load the settings given the path from
		$path_from = $settings->loadSettings($this->config->path_from, true);
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
			{
				$form->addField($field);
			}
		}

		// We will want the db access password
		$form->addOption(array(
			'id' => 'db_pass',
			'label' => 'database_passwd',
			'correct' => 'database_verify',
			'type' => 'password',
		));

		$form->addSeparator();

		// How many steps are involved in this forum conversion
		$steps = $this->_find_steps();

		// Give the option to not perform certain steps
		if (!empty($steps))
		{
			foreach ($steps as $key => $step)
			{
				$steps[$key]['label'] = ucfirst(str_replace('importing ', '', $step['name']));
			}

			$form->addOption(array(
				'id' => 'do_steps',
				'label' => 'selected_only',
				'default' => $steps,
				'type' => 'steps',
			));
		}
	}

	/**
	 * Looks at the importer and returns the steps that it's able to make.
	 *
	 * @return array
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

	/**
	 * Determines where we are in the overall process to update the UI
	 *
	 * @return array
	 */
	public function determineProgress()
	{
		$progress_counter = 0;
		$counter_current_step = 0;
		$current = 0;
		$import_steps = array();

		$xmlParser = new XmlProcessor($this->db, $this->config, $this->template, $this->xml);

		// Loop through each step
		foreach ($this->xml->step as $counts)
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

				$progress_counter += $current;

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
		{
			foreach (explode(',', $this->xml->general->globals) as $global)
			{
				global $$global;
			}
		}

		$substep = 0;

		$xmlParser = new XmlProcessor($this->db, $this->config, $this->template, $this->xml);
		$xmlParser->setImporter($step1_importer);

		foreach ($this->xml->step as $step)
		{
			$xmlParser->processSteps($step, $substep, $do_steps);
		}
	}

	/**
	 * We have imported the old database, let's recalculate the forum statistics.
	 *
	 * @return boolean
	 * @global string $to_prefix
	 *
	 * @global Database $db
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
			if (strpos($method, 'substep') !== 0)
			{
				continue;
			}

			$substeps[substr($method, 7)] = $method;
		}
		ksort($substeps);

		foreach ($substeps as $key => $method)
		{
			if ($substep <= $key)
			{
				$instance->$method();
			}

			$substep++;
			pastTime($substep);
		}

		return $key ?? null;
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

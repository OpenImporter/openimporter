<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 *
 * This file contains code based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:	BSD, See included LICENSE.TXT for terms and conditions.
 */

namespace OpenImporter\Core;

use Symfony\Component\Yaml\Parser;
use OpenImporter\Core\Database;
use OpenImporter\Core\XmlProcessor;
use OpenImporter\Core\ImportException;

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
	 * This is the connection to the source database.
	 * @var object
	 */
	protected $source_db;

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
	 * The "base" class name of the destination system.
	 * @var string
	 */
	protected $_importer_base_class_name = '';

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

		$this->reloadImporter();
	}

	public function setData($data)
	{
		$this->data = $data;
	}

	public function reloadImporter()
	{
		if (!empty($this->config->script))
			$this->loadImporter($this->config->script);
	}

	protected function loadImporter($files)
	{
		$setup = new ImporterSetup($files, $this->config, $this->lng, $this->data);
		
		$this->xml = $setup->getXml();
		$this->db = $setup->getDb();
		$this->source_db = $setup->getSourceDb();
		$this->_importer_base_class_name = $setup->getBaseClass();
		$this->initFormData();
	}

	public function populateFormFields(Form $form)
	{
		$form_path = isset($this->config->path_to) ? $this->config->path_to : BASEDIR;
		$form->addOption($this->config->destination->getFormFields($form_path, $this->config->destination->scriptname));

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

		$steps = $this->findSteps();

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
		if (!isset($this->config->path_from))
			$this->config->path_from = BASEDIR;

		$path_from = $this->config->source->loadSettings($this->config->path_from, true);

		return $path_from;
	}

	protected function initFormData()
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
	protected function findSteps()
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
		$xmlParser = new XmlProcessor($this->db, $this->source_db, $this->config, $this->template, $this->xml);

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

		if ($this->xml->general->globals)
			foreach (explode(',', $this->xml->general->globals) as $global)
				global $$global;

		$substep = 0;

		$skeleton = new Parser();
		$this->skeleton = $skeleton->parse(file_get_contents($this->config->importers_dir . '/importer_skeleton.yml'));

		$xmlParser = new XmlProcessor($this->db, $this->source_db, $this->config, $this->template, $this->xml);
		$xmlParser->setImporter($this->stepInstance('Step1'));
		$xmlParser->setSkeleton($this->skeleton);

		foreach ($this->xml->step as $step)
		{
			if (isset($step->detect))
				$this->config->progress->count[$substep] = $xmlParser->detect((string) $step->detect);

			do
			{
				$this->config->progress->pastTime($substep);

				$rows = $xmlParser->processSource($step, $substep, $do_steps);

				$rows = $this->stepDefaults($rows, (string) $step['id']);

				$rows = $xmlParser->processDestination($step['id'], $rows);

				$xmlParser->insertRows($rows);

				$this->advanceSubstep($substep);
			} while ($xmlParser->stillRunning());

			$_REQUEST['start'] = 0;
		}
	}

	protected function stepInstance($step)
	{
		$step1_importer_class = $this->_importer_base_class_name . $step;
		$step1_importer = new $step1_importer_class($this->db, $this->config);

		return $step1_importer;
	}

	protected function advanceSubstep($substep)
	{
		if ($_SESSION['import_steps'][$substep]['status'] == 0)
			$this->template->status($substep, 1, false, true);

		$_SESSION['import_steps'][$substep]['status'] = 1;
		flush();
	}

	protected function stepDefaults($rows, $id)
	{
		if (empty($rows))
			return array();

		foreach ($this->skeleton[$id]['query'] as $index => $default)
		{
			// No default, use an empty string
			if (is_array($default))
			{
				$index = key($default);
				$default = $default[$index];
			}
			else
			{
				$index = $default;
				$default = '';
			}

			foreach ($rows as $key => $row)
			{
				if (!isset($row[$index]))
					$rows[$key][$index] = $default;
			}
		}

		return $rows;
	}

	/**
	 * we have imported the old database, let's recalculate the forum statistics.
	 *
	 * @return boolean
	 */
	public function doStep2()
	{
		$instance = $this->stepInstance('Step2');

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
			$this->config->progress->pastTime($substep);
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
		$instance = $this->stepInstance('Step3');

		$instance->run($this->lng->get(array('imported_from', $this->xml->general->name)));
	}
}
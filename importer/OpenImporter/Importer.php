<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Core;

use Symfony\Component\Yaml\Parser;

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
	 * Contains any kind of configuration.
	 * @var object
	 */
	public $config;

	/**
	 * The data sent to the template.
	 * @var object
	 */
	public $response;

	/**
	 * The intermediate data structure.
	 * @var object
	 */
	public $skeleton;

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
	public function __construct(Configurator $config, HttpResponse $response)
	{
		// initialize some objects
		$this->config = $config;
		$this->response = $response;

		$this->reloadImporter();
	}

	/**
	 * initialize the $data variable
	 * @param mixed[] $data
	 */
	public function setData($data)
	{
		$this->data = $data;
	}

	/**
	 * Runs this::loadImporter if there is a script to load.
	 */
	public function reloadImporter()
	{
		if (!empty($this->config->script))
			$this->loadImporter($this->config->script);
	}

	/**
	 * Starts up the importer using ImporterSetup and sets the variables needed.
	 *
	 * @param string[] $files
	 */
	protected function loadImporter($files)
	{
		try
		{
			$setup = new ImporterSetup($this->config, $this->response->lng, $this->data);
			$setup->setNamespace('\\OpenImporter\\Importers\\');
			$setup->loadImporter($files);
		}
		catch (\Exception $e)
		{
			$this->response->template_error = true;
			$this->response->addErrorParam($e->getMessage());
		}

		$this->xml = $setup->getXml();
		$this->db = $setup->getDb();
		$this->source_db = $setup->getSourceDb();
		$this->_importer_base_class_name = $setup->getBaseClass();
	}

	/**
	 * Sets up the Form object with the standard fields and those required by
	 * the configuration file.
	 *
	 * @param Form $form
	 */
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

	/**
	 * The important one, transfer the content from the source forum to our
	 * destination system.
	 *
	 * @return boolean
	 */
	public function doStep1()
	{
		$skeleton = new Parser();
		$this->skeleton = $skeleton->parse(file_get_contents($this->config->importers_dir . '/importer_skeleton.yml'));

		$xmlParser = new XmlProcessor($this->db, $this->source_db, $this->config, $this->xml);
		$xmlParser->setImporter($this->stepInstance('Step1'));
		$xmlParser->setSkeleton($this->skeleton);

		$count = 0;
		foreach ($this->xml->step as $step)
		{
			$substep = 0;

			// Having the counter here ensures it is always increased no matter what.
			$count++;

			$this->config->progress->setStep($count);

			if ($this->config->progress->isStepCompleted())
				continue;

			// If there is a table to detect, and it's not there... guess?
			if (!$xmlParser->detect($step))
				continue;

			$this->config->progress->max = $xmlParser->getCurrent($step);
			// @todo do detection on destination side (e.g. friendly urls)

			// pre sql queries first!!
			$xmlParser->doPreSqlStep(Ucfirst($step['id']));

			do
			{
				// Time is up?
				$this->config->progress->pastTime($substep);

				// Get the data
				$rows = $xmlParser->processSource($step, $count);

				// This is done here because we count substeps based on the number of rows
				// of the source database, though when processed the count may change for
				// example because of a different database schema.
				$substep_increment = count($rows);

				// Adds the defaults
				$rows = $this->stepDefaults($rows, (string) $step['id']);

				// Prepares for insertion
				$rows = $xmlParser->processDestination($step['id'], $rows);

				// Dumps data into the database
				$xmlParser->insertRows($rows);

				// Next round!
				$this->config->progress->advanceSubstep($substep_increment, (string) $step->title);
			} while ($xmlParser->stillRunning());

			$substep++;
			$this->config->progress->stepCompleted();
		}
	}

	/**
	 * Sets up an instance of a step object for the destination script.
	 *
	 * @param string $step
	 * @return object
	 */
	protected function stepInstance($step)
	{
		$step1_importer_class = $this->_importer_base_class_name . $step;
		$step1_importer = new $step1_importer_class($this->db, $this->config);

		return $step1_importer;
	}

	/**
	 * Adds default values for rows of data from a certain step
	 *
	 * @param mixed[] $rows
	 * @param string $id
	 */
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
	 */
	public function doStep2()
	{
		$instance = $this->stepInstance('Step2');

		$methods = get_class_methods($instance);
		$substeps = array();
		$substep = -1;
		foreach ($methods as $method)
		{
			if (substr($method, 0, 7) !== 'substep')
				continue;

			$substeps[substr($method, 7)] = $method;
		}
		ksort($substeps);

		foreach ($substeps as $method)
		{
			$substep++;
			$this->config->progress->setStep($substep);

			$this->config->progress->pastTime($substep);

			if ($this->config->progress->isStepCompleted())
				continue;

			call_user_func(array($instance, $method));

			$this->config->progress->stepCompleted();
		}
	}

	/**
	 * we are done :)
	 *
	 * @return boolean
	 */
	public function doStep3()
	{
		$instance = $this->stepInstance('Step3');

		$instance->run($this->response->lng->get(array('imported_from', $this->xml->general->name)));
	}
}
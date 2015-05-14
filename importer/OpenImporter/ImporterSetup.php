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

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;

/**
 * Object Importer creates the main XML object.
 * It detects and initializes the script to run.
 *
 */
class ImporterSetup
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
	 * The namespace for the importers.
	 * @var string
	 */
	protected $i_namespace = '';

	/**
	 * initialize the main Importer object
	 */
	public function __construct(Configurator $config, Lang $lang, $data)
	{
		// initialize some objects
		$this->config = $config;
		$this->lng = $lang;
		$this->data = $data;
	}

	public function setNamespace($namespace)
	{
		$this->i_namespace = $namespace;
	}

	public function getXml()
	{
		return $this->xml;
	}

	public function getDb()
	{
		return $this->db;
	}

	public function getSourceDb()
	{
		return $this->source_db;
	}

	public function getBaseClass()
	{
		return $this->_importer_base_class_name;
	}

	public function loadImporter($files)
	{
		$this->loadSource($files['source']);
		$this->loadDestination($files['destination']);
		$this->prepareSettings();
		$this->loadFormFields();

		// If the paths are unknown it's useless to proceed.
		if (empty($this->config->path_to))
			return;

		$this->initDb();
		$this->config->source->setUtils($this->source_db, $this->config);
		$this->config->destination->setUtils($this->db, $this->config);
	}

	protected function loadFormFields()
	{
		if (!empty($_POST['field']))
		{
			foreach ($_POST['field'] as $key => $val)
			{
				$this->data['fields'][$key] = $val;
			}
		}

		if (!empty($this->data['fields']))
		{
			foreach ($this->data['fields'] as $key => $val)
			{
				$this->config->source->setField($key, $val);
			}
		}
	}

	protected function loadSource($file)
	{
		$full_path = $this->config->importers_dir . DS . 'sources' . DS . $file;
		$this->preparseXml($full_path);

		$class = $this->i_namespace . 'sources\\' . (string) $this->xml->general->className . '_Importer';
		$this->config->source = new $class();
	}

	protected function loadDestination($file)
	{
		$this->_importer_base_class_name = $this->i_namespace . 'destinations\\' . $file . '\\Importer';

		$this->config->destination = new $this->_importer_base_class_name();
	}

	/**
	 * loads the _importer.xml files
	 * @param string $file
	 * @throws ImportException
	 */
	protected function preparseXml($file)
	{
		$this->xml = simplexml_load_file($file, 'SimpleXMLElement', LIBXML_NOCDATA);

		if (!$this->xml)
			throw new ImportException('XML-Syntax error in file: ' . $file);
	}

	/**
	 * Prepare the importer with custom settings of the source
	 *
	 * @throws \Exception
	 * @return boolean|null
	 */
	protected function prepareSettings()
	{
		$this->config->source->setDefines();

		$this->config->source->setGlobals();

		$this->loadSettings();

		if (empty($this->config->path_to))
			return;

		$this->config->boardurl = $this->config->destination->getDestinationURL($this->config->path_to);

		if ($this->config->boardurl === false)
			throw new \Exception($this->lng->get(array('settings_not_found', $this->config->destination->getName())));

		if ($this->config->destination->verifyDbPass($this->data['db_pass']) === false)
			throw new \Exception($this->lng->get('password_incorrect'));

		// Check the steps that we have decided to go through.
		if (!empty($_POST['do_steps']))
		{
			$this->config->progress->doSteps($_POST['do_steps']);
		}

		if (!$this->config->progress->doStepsDefined())
		{
			throw new \Exception($this->lng->get('select_step'));
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
			if (ini_get('open_basedir') != '')
				throw new \Exception($this->lng->get(array('open_basedir', (string) $this->xml->general->name)));

			throw new \Exception($this->lng->get(array('config_not_found', (string) $this->xml->general->name)));
		}
	}

	protected function initDb()
	{
		$DestConnectionParams = $this->config->destination->dbConnectionData();

		list ($this->db, $this->config->to_prefix) = $this->setupDbConnection(
			$DestConnectionParams,
			$this->config->destination->getDbPrefix()
		);

		list ($this->source_db, $this->config->from_prefix) = $this->setupDbConnection(
			$this->config->source->dbConnectionData(),
			$this->config->source->getDbPrefix(),
			$DestConnectionParams
		);
	}

	protected function setupDbConnection($connectionParams, $prefix, $fallbackParams = null)
	{
		try
		{
			$config = new Configuration();
			$con = DriverManager::getConnection($connectionParams, $config);
			$db = new Database($con);

			if (empty($db))
				throw new \Exception($this->lng->get(array('permission_denied', $db->getLastError(), $connectionParams['system_name'])));

			//We want UTF8 only, let's set our mysql connetction to utf8
			$db->query('SET NAMES \'utf8\'');

			// SQL_BIG_SELECTS: If set to 0, MySQL aborts SELECT statements that are
			// likely to take a very long time to execute (that is, statements for
			// which the optimizer estimates that the number of examined rows exceeds
			// the value of max_join_size)
			// Source:
			// https://dev.mysql.com/doc/refman/5.5/en/server-system-variables.html#sysvar_sql_big_selects
			$db->query("SET @@SQL_BIG_SELECTS = 1");
			$db->query("SET @@MAX_JOIN_SIZE = 18446744073709551615");
			$prefix = $this->setupPrefix($connectionParams['dbname'], $prefix);

			// This should be set, but better safe than sorry as usual.
			if (isset($connectionParams['test_table']))
			{
				$test_table = str_replace('{db_prefix}', $prefix, $connectionParams['test_table']);

				// This should throw an exception
				$result = $db->query("
					SELECT COUNT(*)
					FROM {$test_table}", true);

				// This instead should not be necessary because Doctrine should take care of it (I think)
				if ($result === false)
				{
					throw new \Exception($this->lng->get(array('permission_denied', $db->getLastError(), $connectionParams['system_name'])));
				}
			}
		}
		catch(\Exception $e)
		{
			if ($fallbackParams === null)
			{
				throw $e;
			}
			else
			{
				$connectionParams['user'] = $fallbackParams['user'];
				$connectionParams['password'] = $fallbackParams['password'];

				return $this->setupDbConnection($connectionParams, $prefix);
			}
		}

		return array($db, $prefix);
	}

	protected function setupPrefix($db_name, $db_prefix)
	{
		$prefix = $db_prefix;

		if (strpos($db_prefix, '.') === false)
		{
			// @todo ???
			if (is_numeric(substr($db_prefix, 0, 1)))
				$prefix = $db_name . '.' . $db_prefix;
			else
				$prefix = '`' . $db_name . '`.' . $db_prefix;
		}

		// @todo again ???
		if (preg_match('~^`[^`]+`.\d~', $prefix) != 0)
		{
			$prefix = strtr($prefix, array('`' => ''));
		}

		return $prefix;
	}
}
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

use OpenImporter\Core\Strings;

/**
 * Object Importer creates the main XML object.
 * It detects and initializes the script to run.
 *
 */
class XmlProcessor
{
	/**
	 * This is the database object of the destination system.
	 * @var object
	 */
	protected $db;

	/**
	 * This is the database object of the source system.
	 * @var object
	 */
	protected $source_db;

	/**
	 * Contains any kind of configuration.
	 * @var object
	 */
	public $config;

	/**
	 * The xml object containing the settings.
	 * Required (for now) to convert IPs (v4/6)
	 * @var object
	 */
	public $xml;

	/**
	 * The step running in this very moment.
	 * @var object
	 */
	public $current_step;

	/**
	 * Holds all the methods required to perform the conversion.
	 * @var object
	 */
	public $step1_importer;

	/**
	 * The object defining the intermediate array between source and destination.
	 * @var object
	 */
	public $skeleton;

	/**
	 * If the step is completed of not.
	 * @var bool
	 */
	public $completed;

	/**
	 * initialize the main Importer object
	 */
	public function __construct(Database $db, Database $source_db, Configurator $config, \SimpleXMLElement $xml)
	{
		$this->db = $db;
		$this->source_db = $source_db;
		$this->config = $config;
		$this->xml = $xml;
	}

	public function setImporter($step1_importer)
	{
		$this->step1_importer = $step1_importer;
	}

	public function setSkeleton($skeleton)
	{
		$this->skeleton = $skeleton;
	}

	public function processSource($step, $key)
	{
		$this->current_step = $step;

		$from_code = $this->doCode();

		// Codeblock? Then no query.
		if (!empty($from_code))
		{
			// @todo consider delegate the complete definition to some code in the source importer
			$this->completed = true;
			return $from_code;
		}
		// sql block?
		elseif (isset($this->current_step->query))
		{
			return $this->doSql();
		}

		return array();
	}

	public function getStepTable($id)
	{
		return strtr(trim((string) $this->step1_importer->callMethod('table' . $id)), array('{$to_prefix}' => $this->config->to_prefix));
	}

	public function processDestination($id, $rows)
	{
		return $this->step1_importer->callMethod('preparse' . $id, $rows);
	}

	protected function doSql()
	{
		$current_data = rtrim(trim($this->fixParams((string) $this->current_step->query)), ';');
		$id = ucFirst($this->current_step['id']);

		$special_limit = isset($this->current_step->options->limit) ? $this->current_step->options->limit : 500;

		$special_result = $this->prepareSpecialResult($current_data, $special_limit);

		$newrow = array();

		$_SESSION['import_progress'] += $special_limit;
		$this->config->progress->start += $special_limit;

		while ($row = $this->source_db->fetch_assoc($special_result))
		{
			$newrow[] = $row;
		}

		$rows = $this->config->source->callMethod('preparse' . $id, $newrow);

		$this->completed = $this->source_db->num_rows($special_result) < $special_limit;

		$this->source_db->free_result($special_result);

		return $rows;
	}

	public function stillRunning()
	{
		return empty($this->completed);
	}

	protected function getPreparsecode()
	{
		if (!empty($this->current_step->preparsecode))
			return $this->fixParams((string) $this->current_step->preparsecode);
		else
			return null;
	}

	/**
	 * used to replace {$from_prefix} and {$to_prefix} with its real values.
	 *
	 * @param string string in which parameters are replaced
	 * @return string
	 */
	protected function fixParams($string)
	{
		foreach ($this->config->source->getAllFields() as $key => $value)
		{
			$string = strtr($string, array('{$' . $key . '}' => $value));
		}

		$string = strtr($string, array('{$from_prefix}' => $this->config->from_prefix, '{$to_prefix}' => $this->config->to_prefix));

		return $string;
	}

	/**
	 * Counts the records in a table of the source database
	 * @todo move to ProgressTracker
	 *
	 * @param object $step
	 * @return int the number of records in the table
	 */
	public function getCurrent($step)
	{
		if (!isset($step->detect))
			return false;

		$table = $this->fixParams((string) $step->detect);

		$count = $this->fixParams($table);
		$request = $this->source_db->query("
			SELECT COUNT(*)
			FROM $count", true);

		$current = 0;
		if (!empty($request))
		{
			list ($current) = $this->source_db->fetch_row($request);
			$this->source_db->free_result($request);
		}

		return $current;
	}

	public function doPreSqlStep($id)
	{
		if ($this->config->progress->isPreSqlDone())
			return;

		$this->step1_importer->callMethod('before' . ucFirst($id));
		$this->config->source->callMethod('before' . ucFirst($id));

		// don't do this twice..
		$this->config->progress->preSqlDone();
	}

	protected function doCode()
	{
		$id = ucFirst($this->current_step['id']);

		$rows = $this->config->source->callMethod('code' . $id);

		if (!empty($rows))
		{
			return $rows;
		}

		return false;
	}

	public function detect($step)
	{
		if (!isset($step->detect))
			return false;

		$table = $this->fixParams((string) $step->detect);
		$table = preg_replace('/^`[\w\d]*`\./i', '', $this->fixParams($table));

		$db_name_str = $this->config->source->getDbName();

		$result = $this->db->query("
			SHOW TABLES
			FROM `{$db_name_str}`
			LIKE '{$table}'");

		if (!($result instanceof \Doctrine\DBAL\Driver\Statement) || $this->db->num_rows($result) == 0)
			return false;
		else
			return true;
	}

	public function insertRows($rows)
	{
		$special_table = $this->getStepTable($this->current_step['id']);

		if (empty($rows) || empty($special_table))
			return;

		$insert_statement = $this->insertStatement($this->current_step->options);
		$ignore_slashes = $this->ignoreSlashes($this->current_step->options);

		foreach ($rows as $row)
		{
			if (empty($ignore_slashes))
				$row = Strings::addslashes_recursive($row);

			$this->db->insert($special_table, $row, $insert_statement);
		}
	}

	protected function shouldIgnore($options)
	{
		if (isset($options->ignore) && (bool) $options->ignore === false)
			return false;

		return isset($options->ignore) && !isset($options->replace);
	}

	protected function shouldReplace($options)
	{
		return isset($options->replace) && (bool) $options->replace === true;
	}

	protected function shoudNotAdd($options)
	{
		return isset($options->no_add) && (bool) $options->no_add === true;
	}

	protected function ignoreSlashes($options)
	{
		return isset($options->ignore_slashes) && (bool) $options->ignore_slashes === true;
	}

	protected function insertStatement($options)
	{
		if ($this->shouldIgnore($options))
			return 'ignore';
		elseif ($this->shouldReplace($options))
			return 'replace';
		else
			return 'insert';
	}

	protected function prepareSpecialResult($current_data, $special_limit)
	{
		$start = $this->config->progress->start;
		$stop = $this->config->progress->start + $special_limit - 1;

		if (strpos($current_data, '%d') !== false)
			return $this->source_db->query(sprintf($current_data, $start, $stop) . "\n" . 'LIMIT ' . $special_limit);
		else
		{
			return $this->source_db->query($current_data . "\n" . 'LIMIT ' . $start . ', ' . $special_limit);
		}

	}
}
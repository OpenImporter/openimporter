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
 * copyright:    2011 Simple Machines (http://www.simplemachines.org)
 * license:    BSD, See included LICENSE.TXT for terms and conditions.
 */

namespace OpenImporter;

/**
 * Class XmlProcessor
 * Object Importer creates the main XML object.
 * It detects and initializes the script to run.
 *
 * @class XmlProcessor
 *
 */
class XmlProcessor
{
	/** @var \OpenImporter\Database This is our main database object. */
	protected $db;

	/** @var \OpenImporter\Configurator Contains any kind of configuration. */
	public $config;

	/** @var \OpenImporter\Template The template */
	public $template;

	/** @var object The xml object containing the settings. Required (for now) to convert IPs (v4/6) */
	public $xml;

	/** @var object The step running in this very moment, right here, right now. */
	public $current_step;

	/** @var object Holds all the methods required to perform the conversion. */
	public $step1_importer;

	/** @var string[] Holds the row result from the query */
	public $row;

	/** @var mixed Holds the processed rows */
	public $rows;

	/** @var mixed Holds the (processed) row keys */
	public $keys;

	/**
	 * XmlProcessor constructor.
	 *
	 * Initialize the main Importer object
	 *
	 * @param Database $db
	 * @param Configurator $config
	 * @param Template $template
	 * @param object $xml (output from SimpleXMLElement)
	 */
	public function __construct($db, $config, $template, $xml)
	{
		$this->db = $db;
		$this->config = $config;
		$this->template = $template;
		$this->xml = $xml;
	}

	public function setImporter($step1_importer)
	{
		$this->step1_importer = $step1_importer;
	}

	/**
	 * Loop through each of the steps in the XML file
	 *
	 * @param int $step
	 * @param int $substep
	 * @param array $do_steps
	 */
	public function processSteps($step, &$substep, $do_steps)
	{
		$this->current_step = $step;
		$table_test = $this->updateStatus($substep, $do_steps);

		// Do we need to skip this step?
		if ($table_test === false || !in_array($substep, $do_steps))
		{
			return;
		}

		// Pre sql queries (<presql> & <presqlMethod>) first!!
		$this->doPresqlStep($substep);

		// Codeblock? (<code></code>) Then no query.
		if ($this->doCode($substep))
		{
			$this->advanceSubstep($substep);

			return;
		}

		// sql (<query></query>) block?
		// @todo $_GET
		if ($substep >= $_GET['substep'] && isset($this->current_step->query))
		{
			$this->doSql($substep);

			$_REQUEST['start'] = 0;
		}

		$this->advanceSubstep($substep);
	}

	/**
	 * Perform query's as defined in the XML
	 *
	 * @param int $substep
	 */
	protected function doSql($substep)
	{
		// These are temporarily needed to support the current xml importers
		// a.k.a. There is more important stuff to do.
		// a.k.a. I'm too lazy to change all of them now. :P
		// @todo remove
		// Both used in eval'ed code
		$to_prefix = $this->config->to_prefix;
		$db = $this->db;

		// Update {$from_prefix} and {$to_prefix} in the query
		$current_data = substr(rtrim($this->fix_params((string) $this->current_step->query)), 0, -1);
		$current_data = $this->fixCurrentData($current_data);

		$this->doDetect($substep);

		if (isset($this->current_step->destination))
		{
			// Prepare the <query>
			$special_table = strtr(trim((string) $this->current_step->destination), array('{$to_prefix}' => $this->config->to_prefix));

			// Any specific LIMIT set
			$special_limit = $this->current_step->options->limit ?? 500;

			// Any <preparsecode> code? Loaded here to be used later.
			$special_code = $this->getPreparsecode();

			// Create some handy shortcuts
			$no_add = $this->shoudNotAdd($this->current_step->options);

			$this->step1_importer->doSpecialTable($special_table);

			while (true)
			{
				pastTime($substep);

				$special_result = $this->prepareSpecialResult($current_data, $special_limit);

				$this->rows = array();
				$this->keys = array();

				if (isset($this->current_step->detect))
				{
					$_SESSION['import_progress'] += $special_limit;
				}

				while ($this->row = $this->db->fetch_assoc($special_result))
				{
					if ($no_add)
					{
						eval($special_code);
					}
					else
					{
						// Process the row (preparse, charset, etc)
						$this->prepareRow($special_code, $special_table);
						$this->rows[] = $this->row;

						if (empty($this->keys))
						{
							$this->keys = array_keys($this->row);
						}
					}
				}

				$this->insertRows($special_table);

				$_REQUEST['start'] += $special_limit;

				if ($this->db->num_rows($special_result) < $special_limit)
				{
					break;
				}

				$this->db->free_result($special_result);
			}
		}
		else
		{
			$this->db->query($current_data);
		}
	}

	protected function fixCurrentData($current_data)
	{
		if (strpos($current_data, '{$') !== false)
		{
			$current_data = eval('return "' . addcslashes($current_data, '\\"') . '";');
		}

		return $current_data;
	}

	protected function insertRows($special_table)
	{
		// Nothing to insert?
		if (empty($this->rows))
		{
			return;
		}

		$insert_statement = $this->insertStatement($this->current_step->options);
		$ignore_slashes = $this->ignoreSlashes($this->current_step->options);

		// Build the insert
		$insert_rows = array();
		foreach ($this->rows as $row)
		{
			if (empty($ignore_slashes))
			{
				$insert_rows[] = "'" . implode("', '", addslashes_recursive($row)) . "'";
			}
			else
			{
				$insert_rows[] = "'" . implode("', '", $row) . "'";
			}
		}

		$this->db->query("
			$insert_statement $special_table
				(" . implode(', ', $this->keys) . ")
			VALUES (" . implode('),
				(', $insert_rows) . ")");
	}

	protected function prepareRow($special_code, $special_table)
	{
		// Take case of preparsecode
		if ($special_code !== null)
		{
			$row = $this->row;
			eval($special_code);
			$this->row = $row;
		}

		$this->row = $this->step1_importer->doSpecialTable($special_table, $this->row);

		// Fixing the charset, we need proper utf-8
		$this->row = fix_charset($this->row);

		$this->row = $this->step1_importer->fixTexts($this->row);
	}

	/**
	 * Return any <preparsecode></preparsecode> for the step
	 *
	 * @return null|string
	 */
	protected function getPreparsecode()
	{
		if (!empty($this->current_step->preparsecode))
		{
			return $this->fix_params((string) $this->current_step->preparsecode);
		}

		return null;
	}

	protected function advanceSubstep($substep)
	{
		if ($_SESSION['import_steps'][$substep]['status'] == 0)
		{
			$this->template->status($substep, 1, false, true);
		}

		$_SESSION['import_steps'][$substep]['status'] = 1;
		flush();
	}

	/**
	 * Used to replace {$from_prefix} and {$to_prefix} with its real values.
	 *
	 * @param string string in which parameters are replaced
	 *
	 * @return string
	 */
	public function fix_params($string)
	{
		if (isset($_SESSION['import_parameters']))
		{
			foreach ($_SESSION['import_parameters'] as $param)
			{
				foreach ($param as $key => $value)
				{
					$string = strtr($string, array('{$' . $key . '}' => $value));
				}
			}
		}

		$string = strtr($string, array('{$from_prefix}' => $this->config->from_prefix, '{$to_prefix}' => $this->config->to_prefix));

		return trim($string);
	}

	protected function updateStatus(&$substep, $do_steps)
	{
		$table_test = true;

		// Increase the substep slightly...
		pastTime(++$substep);

		$_SESSION['import_steps'][$substep]['title'] = (string) $this->current_step->title;
		if (!isset($_SESSION['import_steps'][$substep]['status']))
		{
			$_SESSION['import_steps'][$substep]['status'] = 0;
		}

		if (!in_array($substep, $do_steps))
		{
			$_SESSION['import_steps'][$substep]['status'] = 2;
			$_SESSION['import_steps'][$substep]['presql'] = true;
		}
		// Detect the table, then count rows..
		elseif ($this->current_step->detect)
		{
			$table_test = $this->detect((string) $this->current_step->detect);

			if ($table_test === false)
			{
				$_SESSION['import_steps'][$substep]['status'] = 3;
				$_SESSION['import_steps'][$substep]['presql'] = true;
			}
		}

		$this->template->status($substep, $_SESSION['import_steps'][$substep]['status'], $_SESSION['import_steps'][$substep]['title']);

		return $table_test;
	}

	/**
	 * Runs any presql commands or calls any presqlMethods
	 *
	 * @param array $substep
	 */
	protected function doPresqlStep($substep)
	{
		if (!isset($this->current_step->presql))
		{
			return;
		}

		if (isset($_SESSION['import_steps'][$substep]['presql']))
		{
			return;
		}

		// Calling a pre method?
		if (isset($this->current_step->presqlMethod))
		{
			$this->step1_importer->beforeSql((string) $this->current_step->presqlMethod);
		}

		// Prepare presql commands for the query
		$presql = $this->fix_params((string) $this->current_step->presql);
		$presql_array = array_filter(explode(';', $presql));

		foreach ($presql_array as $exec)
		{
			$this->db->query($exec . ';');
		}

		// Don't do this twice.
		$_SESSION['import_steps'][$substep]['presql'] = true;
	}

	/**
	 * Process a <detect> statement
	 * @param $substep
	 */
	protected function doDetect($substep)
	{
		global $oi_import;

		if (isset($this->current_step->detect, $oi_import->count))
		{
			$oi_import->count->$substep = $this->detect((string) $this->current_step->detect);
		}
	}

	/**
	 * Run a <code> block
	 *
	 * @return bool
	 */
	protected function doCode($substep)
	{
		if (isset($this->current_step->code))
		{
			// These are temporarily needed to support the current xml importers
			// a.k.a. There is more important stuff to do.
			// a.k.a. I'm too lazy to change all of them now. :P
			// @todo remove
			// Both used in eval'ed code
			$to_prefix = $this->config->to_prefix;
			$db = $this->db;

			// Execute our code block
			$special_code = $this->fix_params((string) $this->current_step->code);
			eval($special_code);

			return true;
		}

		return false;
	}

	/**
	 * Process <detect> statements from the xml file
	 *
	 * {$from_prefix}node WHERE node_type_id = 'Category'
	 *
	 * @param string $table
	 *
	 * @return bool
	 */
	protected function detect($table)
	{
		// Query substitutes
		$table = $this->fix_params($table);
		$table = preg_replace('/^`[\w\-_]*`\./', '', $this->fix_params($table));

		// Database name
		$db_name_str = $this->config->source->getDbName();

		// Simple table check or something more complex?
		if (strpos($table, 'WHERE') !== false)
		{
			$result = $this->db->query("
				SELECT COUNT(*)
				FROM `{$db_name_str}`.{$table}");
		}
		else
		{
			$result = $this->db->query("
				SHOW TABLES
				FROM `{$db_name_str}`
				LIKE '{$table}'");
		}

		return !($result === false || $this->db->num_rows($result) === 0);
	}

	protected function shouldIgnore($options)
	{
		if (isset($options->ignore) && (string) $options->ignore === "false")
		{
			return false;
		}

		return !isset($options->replace);
	}

	protected function shouldReplace($options)
	{
		return isset($options->replace) && (string) $options->replace === "true";
	}

	protected function shoudNotAdd($options)
	{
		return isset($options->no_add) && (string) $options->no_add === "true";
	}

	protected function ignoreSlashes($options)
	{
		return isset($options->ignore_slashes) && (string) $options->ignore_slashes === "true";
	}

	protected function insertStatement($options)
	{
		if ($this->shouldIgnore($options))
		{
			$ignore = 'IGNORE';
		}
		else
		{
			$ignore = '';
		}

		if ($this->shouldReplace($options))
		{
			$replace = 'REPLACE';
		}
		else
		{
			$replace = 'INSERT';
		}

		return $replace . ' ' . $ignore . ' INTO';
	}

	protected function prepareSpecialResult($current_data, $special_limit)
	{
		// @todo $_REQUEST
		if (strpos($current_data, '%d') !== false)
		{
			return $this->db->query(sprintf($current_data, $_REQUEST['start'], $_REQUEST['start'] + $special_limit - 1) . "\n" . 'LIMIT ' . $special_limit);
		}

		return $this->db->query($current_data . "\n" . 'LIMIT ' . $_REQUEST['start'] . ', ' . $special_limit);
	}
}

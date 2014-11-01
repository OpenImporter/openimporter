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
class XmlProcessor
{
	/**
	 * This is our main database object.
	 * @var object
	 */
	protected $db;

	/**
	 * Contains any kind of configuration.
	 * @var object
	 */
	public $config;

	/**
	 * The template
	 * @var object
	 */
	public $template;

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
	 * initialize the main Importer object
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

	public function processSteps($step, &$substep, &$do_steps)
	{
		$this->current_step = $step;
		$table_test = $this->updateStatus($substep, $do_steps);

		// do we need to skip this step?
		if ($table_test === false || !in_array($substep, $do_steps))
			return;

		// pre sql queries first!!
		$this->doPresqlStep($substep);

		// Codeblock? Then no query.
		if ($this->doCode())
		{
			$this->advanceSubstep($substep);
			return;
		}

		// sql block?
		// @todo $_GET
		if ($substep >= $_GET['substep'] && isset($this->current_step->query))
		{
			$this->doSql($substep);

			$_REQUEST['start'] = 0;
		}

		$this->advanceSubstep($substep);
	}

	protected function doSql($substep)
	{
		// These are temporarily needed to support the current xml importers
		// a.k.a. There is more important stuff to do.
		// a.k.a. I'm too lazy to change all of them now. :P
		// @todo remove
		// Both used in eval'ed code
		$to_prefix = $this->config->to_prefix;
		$db = $this->db;

		$current_data = substr(rtrim($this->fix_params((string) $this->current_step->query)), 0, -1);
		$current_data = $this->fixCurrentData($current_data);

		$this->doDetect($substep);

		if (!isset($this->current_step->destination))
			$this->db->query($current_data);
		else
		{
			$special_table = strtr(trim((string) $this->current_step->destination), array('{$to_prefix}' => $this->config->to_prefix));
			$special_limit = isset($this->current_step->options->limit) ? $this->current_step->options->limit : 500;

			// any preparsing code? Loaded here to be used later.
			$special_code = $this->getPreparsecode();

			// create some handy shortcuts
			$no_add = $this->shoudNotAdd($this->current_step->options);

			$this->step1_importer->doSpecialTable($special_table);

			while (true)
			{
				pastTime($substep);

				$special_result = $this->prepareSpecialResult($current_data, $special_limit);

				$rows = array();
				$keys = array();

				if (isset($this->current_step->detect))
					$_SESSION['import_progress'] += $special_limit;

				while ($row = $this->db->fetch_assoc($special_result))
				{
					if ($no_add)
					{
						eval($special_code);
					}
					else
					{
						$rows[] = $this->prepareRow($row, $special_code, $special_table);

						if (empty($keys))
							$keys = array_keys($row);
					}
				}

				$this->insertRows($rows, $keys, $special_table);

				// @todo $_REQUEST
				$_REQUEST['start'] += $special_limit;

				if ($this->db->num_rows($special_result) < $special_limit)
					break;

				$this->db->free_result($special_result);
			}
		}
	}

	protected function fixCurrentData($current_data)
	{
		if (strpos($current_data, '{$') !== false)
			$current_data = eval('return "' . addcslashes($current_data, '\\"') . '";');

		return $current_data;
	}

	protected function insertRows($rows, $keys, $special_table)
	{
		if (empty($rows))
			return;

		$insert_statement = $this->insertStatement($this->current_step->options);
		$ignore_slashes = $this->ignoreSlashes($this->current_step->options);

		$insert_rows = array();
		foreach ($rows as $row)
		{
			if (empty($ignore_slashes))
				$insert_rows[] = "'" . implode("', '", addslashes_recursive($row)) . "'";
			else
				$insert_rows[] = "'" . implode("', '", $row) . "'";
		}

		$this->db->query("
			$insert_statement $special_table
				(" . implode(', ', $keys) . ")
			VALUES (" . implode('),
				(', $insert_rows) . ")");
	}

	protected function prepareRow($row, $special_code, $special_table)
	{
		if ($special_code !== null)
			eval($special_code);

		$row = $this->step1_importer->doSpecialTable($special_table, $row);

		// fixing the charset, we need proper utf-8
		$row = fix_charset($row);

		$row = $this->step1_importer->fixTexts($row);

		return $row;
	}

	protected function getPreparsecode()
	{
		if (!empty($this->current_step->preparsecode))
			return $this->fix_params((string) $this->current_step->preparsecode);
		else
			return null;
	}

	protected function advanceSubstep($substep)
	{
		if ($_SESSION['import_steps'][$substep]['status'] == 0)
			$this->template->status($substep, 1, false, true);

		$_SESSION['import_steps'][$substep]['status'] = 1;
		flush();
	}

	/**
	 * used to replace {$from_prefix} and {$to_prefix} with its real values.
	 *
	 * @param string string string in which parameters are replaced
	 * @return string
	 */
	public function fix_params($string)
	{
		if (isset($_SESSION['import_parameters']))
		{
			foreach ($_SESSION['import_parameters'] as $param)
			{
				foreach ($param as $key => $value)
					$string = strtr($string, array('{$' . $key . '}' => $value));
			}
		}
		$string = strtr($string, array('{$from_prefix}' => $this->config->from_prefix, '{$to_prefix}' => $this->config->to_prefix));

		return $string;
	}

	protected function updateStatus(&$substep, &$do_steps)
	{
		$table_test = true;

		// Increase the substep slightly...
		pastTime(++$substep);

		$_SESSION['import_steps'][$substep]['title'] = (string) $this->current_step->title;
		if (!isset($_SESSION['import_steps'][$substep]['status']))
			$_SESSION['import_steps'][$substep]['status'] = 0;

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

	protected function doPresqlStep($substep)
	{
		if (!isset($this->current_step->presql))
			return;

		if (isset($_SESSION['import_steps'][$substep]['presql']))
			return;

		$presql = $this->fix_params((string) $this->current_step->presql);
		$presql_array = array_filter(explode(';', $presql));

		foreach ($presql_array as $exec)
			$this->db->query($exec . ';');

		if (isset($this->current_step->presqlMethod))
			$this->step1_importer->beforeSql((string) $this->current_step->presqlMethod);

		// don't do this twice..
		$_SESSION['import_steps'][$substep]['presql'] = true;
	}

	protected function doDetect($substep)
	{
		global $import;

		if (isset($this->current_step->detect) && isset($import->count))
			$import->count->$substep = $this->detect((string) $this->current_step->detect);
	}

	protected function doCode()
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

			// execute our code block
			$special_code = $this->fix_params((string) $this->current_step->code);
			eval($special_code);

			return true;
		}

		return false;
	}

	protected function detect($table)
	{
		$count = $this->fix_params($table);

		$result = $this->db->query("
			SELECT COUNT(*)
			FROM $count");

		if ($result === false)
			return false;

		list ($counter) = $this->db->fetch_row($result);
		$this->db->free_result($result);

		return $counter;
	}

	protected function shouldIgnore($options)
	{
		if (isset($options->ignore) && $options->ignore == false)
			return false;

		return !isset($options->replace);
	}

	protected function shouldReplace($options)
	{
		return isset($options->replace) && $options->replace == true;
	}

	protected function shoudNotAdd($options)
	{
		return isset($options->no_add) && $options->no_add == true;
	}

	protected function ignoreSlashes($options)
	{
		return isset($options->ignore_slashes) && $options->ignore_slashes == true;
	}

	protected function insertStatement($options)
	{
		if ($this->shouldIgnore($options))
			$ignore = 'IGNORE';
		else
			$ignore = '';

		if ($this->shouldReplace($options))
			$replace = 'REPLACE';
		else
			$replace = 'INSERT';

		return $replace . ' ' . $ignore . ' INTO';
	}

	protected function prepareSpecialResult($current_data, $special_limit)
	{
		// @todo $_REQUEST
		if (strpos($current_data, '%d') !== false)
			return $this->db->query(sprintf($current_data, $_REQUEST['start'], $_REQUEST['start'] + $special_limit - 1) . "\n" . 'LIMIT ' . $special_limit);
		else
			return $this->db->query($current_data . "\n" . 'LIMIT ' . $_REQUEST['start'] . ', ' . $special_limit);

	}
}
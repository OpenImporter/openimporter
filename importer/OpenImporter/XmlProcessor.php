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
	 * Holds all the methods required to perform the conversion.
	 * @var object
	 */
	public $step1_importer;

	/**
	 * initialize the main Importer object
	 */
	public function __construct($db, $to_prefix, $from_prefix, $template, $xml)
	{
		$this->db = $db;
		$this->to_prefix = $to_prefix;
		$this->from_prefix = $from_prefix;
		$this->template = $template;
		$this->xml = $xml;
	}

	public function setImporter($step1_importer)
	{
		$this->step1_importer = $step1_importer;
	}

	public function processSteps($step, &$substep, &$do_steps)
	{
		global $import;

		// These are temporarily needed to support the current xml importers
		// a.k.a. There is more important stuff to do.
		// a.k.a. I'm too lazy to change all of them now. :P
		// @todo remove
		// Both used in eval'ed code
		$to_prefix = $this->to_prefix;
		$db = $this->db;

		// Reset some defaults
		$current_data = '';
		$special_table = null;
		$special_code = null;

		// any preparsing code here?
		// @todo any idea why this is not eval'ed here
		// and later could be overwritten? ($step->code)
		if (isset($step->preparsecode) && !empty($step->preparsecode))
			$special_code = $this->fix_params((string) $step->preparsecode);

		// @todo $_GET
		$do_current = $substep >= $_GET['substep'];

		$table_test = $this->updateStatus($step, $substep, $do_steps);

		// do we need to skip this step?
		if ($table_test === false || !in_array($substep, $do_steps))
			return;

		// pre sql queries first!!
		if (isset($step->presql))
		{
			$presqlMethod = isset($step->presqlMethod) ? $step->presqlMethod : false;
			$this->doPresqlStep((string) $step->presql, $substep, $presqlMethod);
		}

		if ($special_table === null)
		{
			$special_table = strtr(trim((string) $step->destination), array('{$to_prefix}' => $this->to_prefix));
			$special_limit = 500;
		}
		else
			$special_table = null;

		if (isset($step->query))
			$current_data = substr(rtrim($this->fix_params((string) $step->query)), 0, -1);

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
			$special_code = $this->fix_params((string) $step->code);
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
				$import->count->$substep = $this->detect((string) $step->detect);

			// create some handy shortcuts
			$insert_statement = $this->insertStatement($step->options);
			$no_add = $this->shoudNotAdd($step->options);
			$ignore_slashes = $this->ignoreSlashes($step->options);

			if ($special_table === null)
				$this->db->query($current_data);
			else
			{
				$this->step1_importer->doSpecialTable($special_table);

				while (true)
				{
					pastTime($substep);

					$special_result = $this->prepareSpecialResult($current_data, $special_limit);

					$rows = array();
					$keys = array();

					if (isset($step->detect))
						$_SESSION['import_progress'] += $special_limit;

					while ($row = $this->db->fetch_assoc($special_result))
					{
						if ($special_code !== null)
							eval($special_code);

						$row = $this->step1_importer->doSpecialTable($special_table, $row);

						// ip_to_ipv6 and ip_to_pointer are Wedge-specific cases,
						// once a Wedge importer will be ready, the two should be merged
						// into a more neutral "ip_processing" and the decision when to act
						// will be delegated to the importing method.

						// prepare ip address conversion
						if (isset($this->xml->general->ip_to_ipv6))
							$row = $this->doIpConvertion($row, explode(',', $this->xml->general->ip_to_ipv6));

						// prepare ip address conversion to a pointer
						if (isset($this->xml->general->ip_to_pointer))
							$row = $this->doIpPointer($row, explode(',', $this->xml->general->ip_to_pointer));

						// fixing the charset, we need proper utf-8
						$row = fix_charset($row);

						$row = $this->step1_importer->fixTexts($row);

						if (empty($no_add) && empty($ignore_slashes))
							$rows[] = "'" . implode("', '", addslashes_recursive($row)) . "'";
						elseif (empty($no_add) && !empty($ignore_slashes))
							$rows[] = "'" . implode("', '", $row) . "'";
						else
							$no_add = false;

						if (empty($keys))
							$keys = array_keys($row);
					}

					if (!empty($rows))
						$this->db->query("
							$insert_statement INTO $special_table
								(" . implode(', ', $keys) . ")
							VALUES (" . implode('),
								(', $rows) . ")");

					$_REQUEST['start'] += $special_limit;

					if ($this->db->num_rows($special_result) < $special_limit)
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
		$string = strtr($string, array('{$from_prefix}' => $this->from_prefix, '{$to_prefix}' => $this->to_prefix));

		return $string;
	}

	protected function updateStatus($step, &$substep, &$do_steps)
	{
		$table_test = true;

		// Increase the substep slightly...
		pastTime(++$substep);

		$_SESSION['import_steps'][$substep]['title'] = (string) $step->title;
		if (!isset($_SESSION['import_steps'][$substep]['status']))
			$_SESSION['import_steps'][$substep]['status'] = 0;

		if (!in_array($substep, $do_steps))
		{
			$_SESSION['import_steps'][$substep]['status'] = 2;
			$_SESSION['import_steps'][$substep]['presql'] = true;
		}
		// Detect the table, then count rows.. 
		elseif ($step->detect)
		{
			$table_test = $this->detect((string) $step->detect);

			if ($table_test === false)
			{
				$_SESSION['import_steps'][$substep]['status'] = 3;
				$_SESSION['import_steps'][$substep]['presql'] = true;
			}
		}

		$this->template->status($substep, $_SESSION['import_steps'][$substep]['status'], $_SESSION['import_steps'][$substep]['title']);

		return $table_test;
	}

	protected function doPresqlStep($presql, $substep, $presqlMethod)
	{
		if (isset($_SESSION['import_steps'][$substep]['presql']))
			return;

		$presql = $this->fix_params($presql);
		$presql_array = explode(';', $presql);
		if (isset($presql_array) && is_array($presql_array))
		{
			array_pop($presql_array);
			foreach ($presql_array as $exec)
				$this->db->query($exec . ';');
		}
		else
			$this->db->query($presql);

		if ($presqlMethod)
			$this->step1_importer->beforeSql($presqlMethod);

		// don't do this twice..
		$_SESSION['import_steps'][$substep]['presql'] = true;
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

		if (isset($options->replace))
			return false;

		return true;
	}

	protected function shouldReplace($options)
	{
		if (isset($options->replace) && $options->replace == true)
			return true;
		else
			return false;
	}

	protected function shoudNotAdd($options)
	{
		if (isset($options->no_add) && $options->no_add == true)
			return true;
		else
			return false;
	}

	protected function ignoreSlashes($options)
	{
		if (isset($options->ignore_slashes) && $options->ignore_slashes == true)
			return true;
		else
			return false;
	}

	protected function insertStatement($options)
	{
		$ignore = $this->shouldIgnore($options);
		$replace = $this->shouldReplace($options);

		$insert_ignore = (isset($ignore) && $ignore == true) ? 'IGNORE' : '';
		$insert_replace = (isset($replace) && $replace == true) ? 'REPLACE' : 'INSERT';

		return $insert_replace . ' ' . $insert_ignore;
	}

	protected function prepareSpecialResult($current_data, $special_limit)
	{
		// @todo $_REQUEST
		if (strpos($current_data, '%d') !== false)
			return $this->db->query(sprintf($current_data, $_REQUEST['start'], $_REQUEST['start'] + $special_limit - 1) . "\n" . 'LIMIT ' . $special_limit);
		else
			return $this->db->query($current_data . "\n" . 'LIMIT ' . $_REQUEST['start'] . ', ' . $special_limit);

	}

	/**
	 * @todo Wedge-specific code, to be moved outside when Wedge is imlemented
	 */
	protected function doIpConvertion($row, $convert_ips)
	{
		foreach ($convert_ips as $ip)
		{
			$ip = trim($ip);
			if (array_key_exists($ip, $row))
				$row[$ip] = $this->_prepare_ipv6($row[$ip]);
		}

		return $row;
	}

	/**
	 * @todo Wedge-specific code, to be moved outside when Wedge is imlemented
	 */
	protected function doIpPointer($row, $ips_to_pointer)
	{
		$to_prefix = $this->to_prefix;

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

		return $row;
	}

	/**
	 * placehoder function to convert IPV4 to IPV6
	 * @todo convert IPV4 to IPV6
	 * @todo move to source file, because it depends on the source for any specific destination
	 * @param string $ip
	 * @return string $ip
	 */
	private function _prepare_ipv6($ip)
	{
		return $ip;
	}
}
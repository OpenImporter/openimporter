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
class XMLProcessor
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
	 * initialize the main Importer object
	 */
	public function __construct($db, $to_prefix, $from_prefix)
	{
		$this->db = $db;
		$this->to_prefix = $to_prefix;
		$this->from_prefix = $from_prefix;
	}

	public function processSteps($step, &$substep, &$do_steps, $step1_importer)
	{
		// These are temporarily needed to support the current xml importers
		// a.k.a. There is more important stuff to do.
		// a.k.a. I'm too lazy to change all of the now. :P
		// @todo remove
		$to_prefix = $this->to_prefix;
		$db = $this->db;

		// Reset some defaults
		$current_data = '';
		$special_table = null;
		$special_code = null;

		// Increase the substep slightly...
		pastTime(++$substep);

		$_SESSION['import_steps'][$substep]['title'] = (string) $step->title;
		if (!isset($_SESSION['import_steps'][$substep]['status']))
			$_SESSION['import_steps'][$substep]['status'] = 0;

		// any preparsing code here?
		if (isset($step->preparsecode) && !empty($step->preparsecode))
			$special_code = $this->fix_params((string) $step->preparsecode);

		$do_current = $substep >= $_GET['substep'];

		if (!in_array($substep, $do_steps))
		{
			$_SESSION['import_steps'][$substep]['status'] = 2;
			$_SESSION['import_steps'][$substep]['presql'] = true;
		}
		// Detect the table, then count rows.. 
		elseif ($step->detect)
		{
			$count = $this->fix_params((string) $step->detect);
			$table_test = $this->db->query("
				SELECT COUNT(*)
				FROM $count", true);

			if ($table_test === false)
			{
				$_SESSION['import_steps'][$substep]['status'] = 3;
				$_SESSION['import_steps'][$substep]['presql'] = true;
			}
		}

		$this->template->status($substep, $_SESSION['import_steps'][$substep]['status'], $_SESSION['import_steps'][$substep]['title']);

		// do we need to skip this step?
		if ((isset($table_test) && $table_test === false) || !in_array($substep, $do_steps))
		{
			// reset some defaults
			$current_data = '';
			$special_table = null;
			$special_code = null;
		}

		// pre sql queries first!!
		if (isset($step->presql) && !isset($_SESSION['import_steps'][$substep]['presql']))
		{
			$presql = $this->fix_params((string) $step->presql);
			$presql_array = explode(';', $presql);
			if (isset($presql_array) && is_array($presql_array))
			{
				array_pop($presql_array);
				foreach ($presql_array as $exec)
					$this->db->query($exec . ';');
			}
			else
				$this->db->query($presql);

			if (isset($step->presqlMethod))
				$step1_importer->beforeSql($step->presqlMethod);

			// don't do this twice..
			$_SESSION['import_steps'][$substep]['presql'] = true;
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
			{
				$counter = 0;

				$count = $this->fix_params((string) $step->detect);
				$result2 = $this->db->query("
					SELECT COUNT(*)
					FROM $count");
				list ($counter) = $this->db->fetch_row($result2);
				//$this->count->$substep = $counter;
				$this->db->free_result($result2);
			}

			// create some handy shortcuts
			$ignore = ((isset($step->options->ignore) && $step->options->ignore == false) || isset($step->options->replace)) ? false : true;
			$replace = (isset($step->options->replace) && $step->options->replace == true) ? true : false;
			$no_add = (isset($step->options->no_add) && $step->options->no_add == true) ? true : false;
			$ignore_slashes = (isset($step->options->ignore_slashes) && $step->options->ignore_slashes == true) ? true : false;

			if (isset($import_table) && $import_table !== null && strpos($current_data, '%d') !== false)
			{
				preg_match('~FROM [(]?([^\s,]+)~i', (string) $step->detect, $match);
				if (!empty($match))
				{
					$result = $this->db->query("
						SELECT COUNT(*)
						FROM $match[1]");
					list ($special_max) = $this->db->fetch_row($result);
					$this->db->free_result($result);
				}
				else
					$special_max = 0;
			}
			else
				$special_max = 0;

			if ($special_table === null)
				$this->db->query($current_data);

			else
			{
				$step1_importer->doSpecialTable($special_table);

				while (true)
				{
					pastTime($substep);

					if (strpos($current_data, '%d') !== false)
						$special_result = $this->db->query(sprintf($current_data, $_REQUEST['start'], $_REQUEST['start'] + $special_limit - 1) . "\n" . 'LIMIT ' . $special_limit);
					else
						$special_result = $this->db->query($current_data . "\n" . 'LIMIT ' . $_REQUEST['start'] . ', ' . $special_limit);

					$rows = array();
					$keys = array();

					if (isset($step->detect))
						$_SESSION['import_progress'] += $special_limit;

					while ($row = $this->db->fetch_assoc($special_result))
					{
						if ($special_code !== null)
							eval($special_code);

						$step1_importer->doSpecialTable($special_table, $row);

						// this is wedge specific stuff and will move at some point.
						// prepare ip address conversion
						if (isset($this->xml->general->ip_to_ipv6))
						{
							$convert_ips = explode(',', $this->xml->general->ip_to_ipv6);
							foreach ($convert_ips as $ip)
							{
								$ip = trim($ip);
								if (array_key_exists($ip, $row))
									$row[$ip] = $this->_prepare_ipv6($row[$ip]);
							}
						}
						// prepare ip address conversion to a pointer
						if (isset($this->xml->general->ip_to_pointer))
						{
							$ips_to_pointer = explode(',', $this->xml->general->ip_to_pointer);
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
						}
						// fixing the charset, we need proper utf-8
						$row = fix_charset($row);

						$row = $step1_importer->fixTexts($row);

						if (empty($no_add) && empty($ignore_slashes))
							$rows[] = "'" . implode("', '", addslashes_recursive($row)) . "'";
						elseif (empty($no_add) && !empty($ignore_slashes))
							$rows[] = "'" . implode("', '", $row) . "'";
						else
							$no_add = false;

						if (empty($keys))
							$keys = array_keys($row);
					}

					$insert_ignore = (isset($ignore) && $ignore == true) ? 'IGNORE' : '';
					$insert_replace = (isset($replace) && $replace == true) ? 'REPLACE' : 'INSERT';

					if (!empty($rows))
						$this->db->query("
							$insert_replace $insert_ignore INTO $special_table
								(" . implode(', ', $keys) . ")
							VALUES (" . implode('),
								(', $rows) . ")");
					$_REQUEST['start'] += $special_limit;
					if (empty($special_max) && $this->db->num_rows($special_result) < $special_limit)
						break;
					elseif (!empty($special_max) && $this->db->num_rows($special_result) == 0 && $_REQUEST['start'] > $special_max)
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
}
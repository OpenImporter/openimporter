<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 */

/**
 * Class UBB_7_5
 */
class UBB_7_5 extends Importers\AbstractSourceImporter
{
	protected $setting_file = '/includes/config.inc.php';

	public function getName()
	{
		return 'UBB Threads 7.5.x';
	}

	public function getVersion()
	{
		return 'ElkArte 1.0';
	}

	public function getPrefix()
	{
		global $db_prefix;

		return '`' . $this->getDbName() . '`.' . $db_prefix;
	}

	public function getDbName()
	{
		global $db_name;

		return $db_name;
	}

	public function getTableTest()
	{
		return 'USERS';
	}
}

// Utility functions specific to UBB

/**
 * @param string $string
 * @param bool $new_lines
 *
 * @return string
 */
function fix_quotes($string, $new_lines = true)
{
	if ($new_lines)
	{
		return strtr(htmlspecialchars($string, ENT_QUOTES), array("\n" => '<br />'));
	}

	return htmlspecialchars($string);
}

/**
 * @param int $date
 *
 * @return string
 */
function convert_birthdate($date)
{
	$tmp_birthdate = explode('/', $date);
	if (count($tmp_birthdate) == 3)
	{
		if (strlen($tmp_birthdate[2]) != 4)
		{
			$tmp_birthdate[2] = '0004';
		}

		return $tmp_birthdate[2] . '-' . str_pad($tmp_birthdate[0], 2, "0", STR_PAD_LEFT) . '-' . str_pad($tmp_birthdate[1], 2, "0", STR_PAD_LEFT);
	}

	return '0001-01-01';
}

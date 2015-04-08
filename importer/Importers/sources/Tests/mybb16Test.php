<?php

namespace OpenImporter\Importers\sources\Tests;

use Symfony\Component\Yaml\Yaml;
use OpenImporter\Core\ImportException;
use OpenImporter\Importers\sources\MyBB1_6_Importer;

require_once(__DIR__ . '/EnvInit.php');
require_once(BASEDIR . '/Importers/sources/MyBB1_6_Importer.php');

class mybb16Test extends \PHPUnit_Framework_TestCase
{
	protected static $xml = null;
	protected static $yml = null;
	protected $utils = array();

	protected static function read($file)
	{
		$xml = simplexml_load_file($file, 'SimpleXMLElement', LIBXML_NOCDATA);
		if (!$xml)
			throw new ImportException('XML-Syntax error in file: ' . $file);

		return $xml;
	}

	protected static function getConfig($file)
	{
		return Yaml::parse(file_get_contents($file));
	}

	public static function setUpBeforeClass()
	{
		self::$xml = self::read(BASEDIR . '/Importers/sources/MyBB1_6_Importer.xml');
		self::$yml = self::getConfig(BASEDIR . '/Importers/importer_skeleton.yml');
	}

	protected function getStepConfig($index)
	{
		$conf = array();
		foreach (self::$yml[$index]['query'] as $key => $val)
		{
			if (is_array($val))
				$conf[] = key($val);
			else
				$conf[] = $val;
		}
		return $conf;
	}

	protected function setUp()
	{
		$this->utils['db'] = new DummyDb(new CustomDbValues());
		// @todo this should be detected from the XML?
		$this->utils['importer'] = new MyBB1_6_Importer();
		$this->utils['importer']->setUtils($this->utils['db'], new DummyConfig());
		date_default_timezone_set('America/Los_Angeles');
	}

	protected function stepQueryTester($step)
	{
		$id = (string) $step['id'];

		$this_config = $this->getStepConfig($id);
		$tmp = $this->utils['db']->query($step->query);

		$generated = $this->utils['db']->fetch_assoc($tmp);
		$generated = $this->utils['importer']->callMethod('preparse' . ucFirst($id), array($generated));

		foreach ($generated[0] as $key => $entry)
			$this->assertContains($key, $this_config);
	}

	public function testAll()
	{
		foreach (self::$xml->step as $step)
		{
			if (isset($step->query))
			{
				$this->stepQueryTester($step);
			}
		}
	}
}

class CustomDbValues extends CustomDb
{
	protected $queries = array();

	public function __construct()
	{
		$this->config = new DummyConfig();

		$this->queries = array(
			md5('
			SELECT
				uid AS id_member, SUBSTRING(username, 1, 255) AS member_name,
				SUBSTRING(username, 1, 255) AS real_name, email AS email_address,
				SUBSTRING(password, 1, 64) AS passwd, SUBSTRING(salt, 1, 8) AS password_salt,
				postnum AS posts, SUBSTRING(usertitle, 1, 255) AS usertitle,
				lastvisit AS last_login, IF(usergroup = 4, 1, 0) AS id_group,
				regdate AS date_registered, SUBSTRING(website, 1, 255) AS website_url,
				SUBSTRING(website, 1, 255) AS website_title, \'\' AS message_labels,
				SUBSTRING(signature, 1, 65534) AS signature, hideemail AS hide_email,
				SUBSTRING(buddylist, 1, 255) AS buddy_list, \'\' AS ignore_boards,
				SUBSTRING(regip, 1, 255) AS member_ip, SUBSTRING(regip, 1, 255) AS member_ip2,
				SUBSTRING(ignorelist, 1, 255) AS pm_ignore_list, avatar,
				timeonline AS total_time_logged_in, birthday AS birthdate, avatartype
			FROM {$from_prefix}users;
		') => array(array(
				'date_registered' => 12345678,
				'birthdate' => '',
			)),
			md5("
					SELECT value
					FROM {$this->config->from_prefix}settings
					WHERE name = 'uploadspath'
					LIMIT 1") => array(array(
				'value' => BASEDIR . '/Importers/sources'
			)),
			md5('
			SELECT pid AS id_msg, downloads, filename, filesize, attachname
			FROM {$from_prefix}attachments;
		') => array(array(
				'id_msg' => 1,
				'downloads' => 0,
				'filename' => 'MyBB1_6_Importer.php',
				'filesize' => 0,
				'attachname' => 'MyBB1_6_Importer.php'
			))
		);
	}
}
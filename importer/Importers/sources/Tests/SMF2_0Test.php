<?php

namespace OpenImporter\Importers\sources\Tests;

use Symfony\Component\Yaml\Yaml;
use OpenImporter\Core\ImportException;
use OpenImporter\Importers\sources\SMF2_0_Importer;

require_once(__DIR__ . '/EnvInit.php');
require_once(BASEDIR . '/Importers/sources/SMF2_0_Importer.php');

class SMF2_0Test extends \PHPUnit_Framework_TestCase
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
		self::$xml = self::read(BASEDIR . '/Importers/sources/SMF2_0_Importer.xml');
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
		$this->utils['db'] = new DummyDb(new CustomSmf20Values());
		// @todo this should be detected from the XML?
		$this->utils['importer'] = new SMF2_0_Importer();
		$this->utils['importer']->setUtils($this->utils['db'], new DummyConfig());
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

class CustomSmf20Values extends CustomDb
{
	protected $queries = array();

	public function __construct()
	{
		$this->config = new DummyConfig();

		$this->queries = array(
			md5('
			SELECT
				id_member, member_name, date_registered, posts, id_group, lngfile as language, last_login,
				real_name, unread_messages, unread_messages, new_pm, buddy_list, pm_ignore_list,
				pm_prefs, mod_prefs, message_labels, passwd, email_address, personal_text,
				gender, birthdate, website_url, website_title, location, hide_email, show_online,
				time_format, signature, time_offset, avatar, pm_email_notify,
				usertitle, notify_announcements, notify_regularity, notify_send_body,
				notify_types, member_ip, member_ip2, secret_question, secret_answer, 1 AS id_theme, is_activated,
				validation_code, id_msg_last_visit, additional_groups, smiley_set, id_post_group,
				total_time_logged_in, password_salt, ignore_boards,
				IFNULL(warning, 0) AS warning, passwd_flood,
				pm_receive_from, \'\' as avatartype
			FROM {$from_prefix}members;
		') => array(
				'date_registered' => 12345678,
				'birthdate' => '',
			),
			md5("
					SELECT value
					FROM {$this->config->from_prefix}settings
					WHERE name = 'uploadspath'
					LIMIT 1") => array(
				'value' => BASEDIR . '/Importers/sources'
			),
			md5('
			SELECT pid AS id_msg, downloads, filename, filesize, attachname
			FROM {$from_prefix}attachments;
		') => array(
				'id_msg' => 1,
				'downloads' => 0,
				'filename' => 'MyBB1_6_Importer.php',
				'filesize' => 0,
				'attachname' => 'MyBB1_6_Importer.php'
			),
		);
	}
}
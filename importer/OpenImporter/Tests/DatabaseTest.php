<?php

namespace OpenImporter\Importers\sources\Tests;

use OpenImporter\Core\Database;

class DatabaseTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @covers OpenImporter\Core\Database::query
	 */
	public function testQuery()
	{
		$results = array(
			'select' => 'data1',
			'select;' => 'data2',
		);

		$db = new Database(new MockDatabaseConnection($results));

		$this->assertEquals($results['select;'], $db->query('select'));
		$this->assertEquals($results['select;'], $db->query('select;'));
	}

	/**
	 * @covers OpenImporter\Core\Database::query
	 * @expectedException OpenImporter\Core\DatabaseException
	 */
	public function testQueryError()
	{
		$results = array(
			'select' => 'data1',
		);

		$db = new Database(new MockDatabaseConnection($results));

		$db->query('select');
	}

	/**
	 * @covers OpenImporter\Core\Database::getLastError
	 */
	public function testGetLastError()
	{
		$db = new Database(new MockDatabaseConnection(array()));
		$this->assertEquals(100, $db->getLastError());
	}
}

class MockDatabaseConnection
{
	protected $dummydata = array();

	public function __construct($dummydata)
	{
		$this->dummydata = $dummydata;
	}

	public function query($string)
	{
		return $this->dummydata[$string];
	}

	public function errorCode()
	{
		return 100;
	}

	public function errorInfo()
	{
		return 'error info';
	}
}
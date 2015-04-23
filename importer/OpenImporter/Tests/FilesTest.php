<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Importers\sources\Tests;

use OpenImporter\Core\Files;

class FilesTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @covers OpenImporter\Core\Files::create_folders_recursive
	 */
	public function testCreateFoldersRecursive()
	{
		$path = BASEDIR . '/OpenImporter/Tests/FilesTest/TestDir';
		Files::create_folders_recursive($path);
		$this->assertTrue(file_exists($path));

		$path = BASEDIR . '/OpenImporter/Tests/FilesTest/TestDir/Level1/Level2/Level3';
		Files::create_folders_recursive($path);
		$this->assertTrue(file_exists($path));
	}

	/**
	 * @covers OpenImporter\Core\Files::copy_file
	 */
	public function testCopyFile()
	{
		$source = BASEDIR . '/OpenImporter/Tests/bootstrap.php';
		$destination = BASEDIR . '/OpenImporter/Tests/FilesTest/bootstrap.php';

		$this->assertTrue(Files::copy_file($source, $destination));
		$this->assertTrue(file_exists($destination));
		// Not silenced beacuse if there is a problem the test should fail.
		unlink($destination);

		$source = BASEDIR . '/OpenImporter/Tests/';
		$this->assertFalse(Files::copy_file($source, $destination));
	}

	/**
	 * @covers OpenImporter\Core\Files::get_files_recursive
	 */
	public function testGetFilesRecursive()
	{
		$path = BASEDIR . '/OpenImporter/Tests/FilesTest/TestDir/Level1/Level2/Level3';
		Files::create_folders_recursive($path);

		$source = BASEDIR . '/OpenImporter/Tests/bootstrap.php';
		$destination = $path . '/bootstrap.php';
		Files::copy_file($source, $destination);
		$destination = BASEDIR . '/OpenImporter/Tests/FilesTest/bootstrap.php';
		Files::copy_file($source, $destination);

		$base = BASEDIR . '/OpenImporter/Tests/FilesTest/';
		$files = Files::get_files_recursive($base);
		$this->assertEquals(2, count($files));
	}
}
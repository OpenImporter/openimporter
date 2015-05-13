<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Importers\sources\Tests;

use OpenImporter\Core\PasttimeException;
use OpenImporter\Core\Configurator;
use OpenImporter\Core\Lang;
use OpenImporter\Core\Template;

class PasttimeExceptionTest extends \PHPUnit_Framework_TestCase
{
	public function testDoExit()
	{
		$instance = new PasttimeException('bar', 'import_progress', 'max', 'step', 'start');

		$this->assertEquals(array('bar', 'import_progress', 'max', 'step', 'start'), $instance->getParams());
	}
}
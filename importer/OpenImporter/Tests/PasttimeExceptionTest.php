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
		$template = new DummyTemplatePasttimeExceptionTest();

		$instance = new PasttimeException($template, 'bar', 'import_progress', 'max', 'step', 'start');
		$instance->doExit();

		$this->assertEquals(array('bar', 'import_progress', 'max', 'step', 'start'), $template->called_timeLimit);
		$this->assertTrue($template->called_footer);
	}
}

class DummyTemplatePasttimeExceptionTest extends Template
{
	public $called_timeLimit = false;
	public $called_footer = false;

	public function __construct()
	{
	}

	public function timeLimit($bar, $import_progress, $max, $step, $start)
	{
		$this->called_timeLimit = array($bar, $import_progress, $max, $step, $start);
	}

	public function footer($inner = true)
	{
		$this->called_footer = true;
	}
}
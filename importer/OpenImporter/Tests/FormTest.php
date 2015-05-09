<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Importers\sources\Tests;

use OpenImporter\Core\Form;
use OpenImporter\Core\DummyLang;

class FormTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @covers OpenImporter\Core\Form::__set
	 */
	public function testSet()
	{
		$object = new \ReflectionClass('OpenImporter\\Core\\Form');
		$instance = $object->newInstanceArgs(array(new DummyLang()));
		$property = $object->getProperty('data');
		$property->setAccessible(true);

		$instance->test = 123;

		$this->assertEquals(array('test' => 123), $property->getValue($instance));
	}

	/**
	 * @covers OpenImporter\Core\Form::__set
	 *
	 * @expectedException OpenImporter\Core\FormException
	 * @expectedExceptionMessage Use Form::addOptions or Form::addField to set new fields
	 */
	public function testSetError()
	{
		$object = new \ReflectionClass('OpenImporter\\Core\\Form');
		$instance = $object->newInstanceArgs(array(new DummyLang()));
		$property = $object->getProperty('data');
		$property->setAccessible(true);

		$instance->options = 123;
	}

	/**
	 * @covers OpenImporter\Core\Form::addOption
	 */
	public function testAddOption()
	{
		$tests = array(
			'text' => array(
				array(
					'id' => 'Identifier',
					'label' => 'Text label',
					'value' => 'A devault value',
					'correct' => 'It\'s correct',
					'validate' => true,
				),
				array(
					'id' => 'Identifier',
					'label' => 'Text label',
					'value' => 'A devault value',
					'correct' => 'It\'s correct',
					'validate' => false,
				),
				array(
					'id' => 'Identifier',
					'label' => 'Text label',
					'value' => null,
					'correct' => 'It\'s correct',
					'validate' => true,
				),
				array(
					'id' => 'Identifier',
					'label' => 'Text label',
					'value' => 'A devault value',
					'correct' => null,
					'validate' => true,
				),
				array(
					'id' => 'Identifier',
					'label' => 'Text label',
					'value' => null,
					'correct' => 'It\'s correct',
					'validate' => false,
				),
				array(
					'id' => 'Identifier',
					'label' => 'Text label',
					'value' => 'A devault value',
					'correct' => null,
					'validate' => false,
				),
				array(
					'id' => 'Identifier',
					'label' => 'Text label',
					'value' => null,
					'correct' => null,
					'validate' => true,
				),
				array(
					'id' => 'Identifier',
					'label' => 'Text label',
					'value' => null,
					'correct' => null,
					'validate' => false,
				),
			),
			'password' => array(
				array(
					'id' => 'Identifier',
					'label' => 'Text label',
					'correct' => 'It\'s correct',
				),
			),
			'steps' => array(
				array(
					'id' => 'Identifier',
					'label' => 'Text label',
					'value' => 'Default value',
				),
			),
			'default' => array(
				array(
					'id' => 'Identifier',
					'label' => 'Text label',
					'checked' => 'checked',
				),
				array(
					'id' => 'Identifier',
					'label' => 'Text label',
					'checked' => 'nothing',
				),
			),
		);

		foreach ($tests as $type => $cases)
		{
			foreach ($cases as $run => $case)
			{
				$object = new \ReflectionClass('OpenImporter\\Core\\Form');
				$instance = $object->newInstanceArgs(array(new DummyLang()));
				$property = $object->getProperty('data');
				$property->setAccessible(true);

				$field = $result = array('type' => $type == 'default' ? 'checkbox' : $type);
				foreach ($case as $key => $val)
				{
					if ($val !== null)
					{
						$field[$key == 'value' ? 'default' : $key] = $val;
						$result[$key == 'checked' ? 'attributes' : $key] = $key === 'checked' ? ($val == 'checked' ? ' checked="checked"' : '') : $val;
					}
					else
						$result[$key] = '';
				}
				if ($type == 'default')
					$result['value'] = 1;

				$instance->addOption($field);

				$this->assertEquals(array('options' => array($result)), $property->getValue($instance), 'Test: ' . $type . ' at run: ' . $run);
			}
		}
	}

	/**
	 * @covers OpenImporter\Core\Form::addSeparator
	 */
	public function testAddSeparator()
	{
		$object = new \ReflectionClass('OpenImporter\\Core\\Form');
		$instance = $object->newInstanceArgs(array(new DummyLang()));
		$property = $object->getProperty('data');
		$property->setAccessible(true);

		$instance->addSeparator();

		$this->assertEquals(array('options' => array(array())), $property->getValue($instance));
	}

	/**
	 * @covers OpenImporter\Core\Form::addField
	 */
	public function testAddField()
	{
		$object = new MockFormAddField(new DummyLang());

		$this->assertEquals('field[addOption]', $object->addField(array('id' => 'addOption')));

		$xml = new \SimpleXMLElement('<root><firstLevel>makeFieldArray</firstLevel></root>');
		$this->assertEquals('field[makeFieldArray]', $object->addField($xml->firstLevel));
	}

	/**
	 * @covers OpenImporter\Core\Form::makeFieldArray
	 */
	public function testMakeFieldArray()
	{
		$object = new Form(new DummyLang());

		$xml = new \SimpleXMLElement('<root>
	<test1 type="text" label="text label" default="def value">text_default</test1>
	<test2 type="text" label="text label">text_no_default</test2>
	<test3 type="other" label="text label" checked="checked val">other_type</test3>
</root>');

		$this->assertEquals(array(
			'id' => 'text_default',
			'label' => 'text label',
			'default' => 'def value',
			'type' => 'text',
		), $object->makeFieldArray($xml->test1));
		$this->assertEquals(array(
			'id' => 'text_no_default',
			'label' => 'text label',
			'default' => '',
			'type' => 'text',
		), $object->makeFieldArray($xml->test2));
		$this->assertEquals(array(
			'id' => 'other_type',
			'label' => 'text label',
			'checked' => 'checked val',
			'type' => 'checkbox',
		), $object->makeFieldArray($xml->test3));
	}
}

class MockFormAddField extends Form
{
	public function addOption($field)
	{
		return $field['id'];
	}
}
<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Core;

use OpenImporter\Core\FormException;

/**
 * Just a way to collect a bunch of stuff to be used to build a form.
 *
 * @property string $title
 * @property string $description
 * @property string $submit
 * @property string $options
 */
class Form
{
	protected $data = array();
	protected $lng = null;

	/**
	 * The bare minimum required to have a form: an url to post to.
	 */
	public $action_url = '';

	public function __construct($lng)
	{
		$this->lng = $lng;
	}

	public function __set($key, $val)
	{
		if ($key === 'options')
			throw new FormException('Use Form::addOptions or Form::addField to set new fields');

		$this->data[$key] = $val;
	}

	public function __get($key)
	{
		if (isset($this->data[$key]))
			return $this->data[$key];
		else
			return null;
	}

	public function addOption($field)
	{
		switch ($field['type'])
		{
			case 'text':
			{
				$this->data['options'][] = array(
					'id' => $field['id'],
					'label' => $this->lng->get($field['label']),
					'value' => isset($field['default']) ? $field['default'] : '',
					'correct' => isset($field['correct']) ? $this->lng->get($field['correct']) : '',
					'validate' => !empty($field['validate']),
					'type' => 'text',
				);
				break;
			}
			case 'password':
			{
				$this->data['options'][] = array(
					'id' => $field['id'],
					'label' => $this->lng->get($field['label']),
					'correct' => $this->lng->get($field['correct']),
					'type' => 'password',
				);
				break;
			}
			case 'steps':
			{
				$this->data['options'][] = array(
					'id' => $field['id'],
					'label' => $this->lng->get($field['label']),
					'value' => $field['default'],
					'type' => 'steps',
				);
				break;
			}
			default:
			{
				$this->data['options'][] = array(
					'id' => $field['id'],
					'label' => $this->lng->get($field['label']),
					'value' => 1,
					'attributes' => $field['checked'] == 'checked' ? ' checked="checked"' : '',
					'type' => 'checkbox',
				);
			}
		}
	}

	public function addSeparator()
	{
		$this->data['options'][] = array();
	}

	public function addField($field)
	{
		if (is_object($field))
			return $this->addField($this->makeFieldArray($field));
		else
		{
			$field['id'] = 'field' . $field['id'];
			return $this->addOption($field);
		}
	}

	public function makeFieldArray($field)
	{
		if ($field->attributes()->{'type'} == 'text')
		{
			return array(
				'id' => $field->attributes()->{'id'},
				'label' => $field->attributes()->{'label'},
				'default' => isset($field->attributes()->{'default'}) ? $field->attributes()->{'default'} : '',
				'type' => 'text',
			);
		}
		else
		{
			return array(
				'id' => $field->attributes()->{'id'},
				'label' => $field->attributes()->{'label'},
				'checked' => $field->attributes()->{'checked'},
				'type' => 'checkbox',
			);
		}
	}
}
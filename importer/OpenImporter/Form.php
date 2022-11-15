<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD https://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 */

namespace OpenImporter;

/**
 * Just a way to collect a bunch of stuff to be used to build a form.
 *
 * @class Form
 */
class Form
{
	/** @var string (via magic see Template) */
	public $title;

	/** @var string (via magic see Template) */
	public $description;

	/** @var array (via magic see Template) */
	public $submit;

	/** @var array */
	protected $data = array();

	/** @var null */
	protected $lng;

	public function __construct($lng)
	{
		$this->lng = $lng;
	}

	public function __set($key, $val)
	{
		if ($key === 'options')
		{
			throw new FormException('Use Form::addOptions or Form::addField to set new fields');
		}

		$this->data[$key] = $val;
	}

	public function __get($key)
	{
		return $this->data[$key] ?? null;
	}

	public function addOption($field)
	{
		switch ($field['type'])
		{
			case 'text':
				$this->data['options'][] = array(
					'id' => $field['id'],
					'label' => $this->lng->get($field['label']),
					'value' => $field['default'] ?? '',
					'correct' => isset($field['correct']) ? $this->lng->get($field['correct']) : '',
					'validate' => !empty($field['validate']),
					'type' => 'text',
				);
				break;
			case 'password':
				$this->data['options'][] = array(
					'id' => $field['id'],
					'label' => $this->lng->get($field['label']),
					'correct' => $this->lng->get($field['correct']),
					'type' => 'password',
				);
				break;
			case 'steps':
				$this->data['options'][] = array(
					'id' => $field['id'],
					'label' => $this->lng->get($field['label']),
					'value' => $field['default'],
					'type' => 'steps',
				);
				break;
			default:
				$this->data['options'][] = array(
					'id' => $field['id'],
					'label' => $this->lng->get($field['label']),
					'value' => 1,
					'attributes' => $field['checked'] == 'checked' ? ' checked="checked"' : '',
					'type' => 'checkbox',
				);
		}
	}

	public function addSeparator()
	{
		$this->data['options'][] = array();
	}

	public function addField($field)
	{
		if (is_object($field))
		{
			return $this->addField($this->makeFieldArray($field));
		}

		$field['id'] = 'field' . $field['id'];

		return $this->addOption($field);
	}

	public function makeFieldArray($field)
	{
		if ($field->attributes()->{'type'} === 'text')
		{
			return array(
				'id' => $field->attributes()->{'id'},
				'label' => $field->attributes()->{'label'},
				'default' => $field->attributes()->{'default'} ?? '',
				'type' => 'text',
			);
		}

		return array(
			'id' => $field->attributes()->{'id'},
			'label' => $field->attributes()->{'label'},
			'checked' => $field->attributes()->{'checked'},
			'type' => 'checkbox',
		);
	}
}

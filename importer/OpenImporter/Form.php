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
 * @property mixed[] $options
 */
class Form extends ValuesBag
{
	/**
	 * The "translator" (i.e. the Lang object)
	 * @var object
	 */
	protected $lng = null;

	/**
	 * The bare minimum required to have a form: an url to post to.
	 */
	public $action_url = '';

	/**
	 * The constructor, not much to say.
	 * @param Lang $lng The translator object.
	 */
	public function __construct(Lang $lng)
	{
		parent::__construct();
		$this->lng = $lng;
	}

	/**
	 * Setter
	 *
	 * @param string|int $key
	 * @param mixed[] $val
	 *
	 * @throws FormException
	 */
	public function __set($key, $val)
	{
		if ($key === 'options')
			throw new FormException('Use Form::addOptions or Form::addField to set new fields');

		$this->data[$key] = $val;
	}

	/**
	 * Adds a new entry to the form.
	 *
	 * @param mixed[] $field
	 */
	public function addOption($field)
	{
		switch ($field['type'])
		{
			case 'text':
				$this->data['options'][] = array(
					'id' => $field['id'],
					'label' => $this->lng->get($field['label']),
					'value' => isset($field['default']) ? $field['default'] : '',
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

	/**
	 * Adds space between entries
	 */
	public function addSeparator()
	{
		$this->data['options'][] = array();
	}

	/**
	 * @param mixed[]|\SimpleXMLElement $field
	 */
	public function addField($field)
	{
		if (is_object($field))
			return $this->addField($this->makeFieldArray($field));
		else
		{
			$field['id'] = 'field[' . $field['id'] . ']';
			return $this->addOption($field);
		}
	}

	/**
	 * Converts a \SimpleXMLElement object into an array to feed addField
	 *
	 * @param \SimpleXMLElement $field
	 *
	 * @returns string[]
	 */
	public function makeFieldArray($field)
	{
		if ($field->attributes()->{'type'} == 'text')
		{
			return array(
				'id' => (string) $field,
				'label' => (string) $field->attributes()->{'label'},
				'default' => isset($field->attributes()->{'default'}) ? (string) $field->attributes()->{'default'} : '',
				'type' => 'text',
			);
		}
		else
		{
			return array(
				'id' => (string) $field,
				'label' => (string) $field->attributes()->{'label'},
				'checked' => (string) $field->attributes()->{'checked'},
				'type' => 'checkbox',
			);
		}
	}
}
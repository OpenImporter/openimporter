<?php

/**
 * Just a way to collect a bunch of stuff to be used to build a form.
 */
class Form
{
	protected $data = array();

	/**
	 * The bare minimum required to have a form: an url to post to.
	 */
	public $action_url = '';

	public function __set($key, $val)
	{
		$this->data[$key] = $val;
	}

	public function __get($key)
	{
		if (isset($this->data[$key]))
			return $this->data[$key];
		else
			return null;
	}
}
<?php

/**
 * This should contain the data used by the template.
 */
class HttpResponse
{
	/**
	 * Any kind of data the templates may need.
	 */
	protected $data = array();

	protected $headers = null;

	protected $error_params = array();

	public function __construct($headers)
	{
		$this->headers = $headers;
	}

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

	public function sendHeaders()
	{
		foreach ($this->headers->get() as $val)
			header($val);
	}

	public function addHeader($key, $val)
	{
		$this->headers->set($key, $val);
	}

	public function addErrorParam($error_message)
	{
		$this->error_params[] = $error_message;
	}
}
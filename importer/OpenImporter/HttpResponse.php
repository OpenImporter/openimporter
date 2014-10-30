<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

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

	public $lng = null;

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

	public function getErrors()
	{
		$return = array();
		foreach ($this->error_params as $msg)
		{
			if (is_array($msg))
				$return[] = sprintf($msg[0], $msg[1]);
			else
				$return[] = $msg;
		}

		return $return;
	}
}
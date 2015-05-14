<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 */

namespace OpenImporter\Core;

/**
 * This should contain the data used by the template.
 *
 * @property assets_dir
 * @property lng
 */
class HttpResponse extends ValuesBag
{
	/**
	 * The HTTP response header object.
	 * @var ResponseHeader
	 */
	protected $headers = null;

	/**
	 * Error messages occurred during the import process.
	 * @var string[]
	 */
	protected $error_params = array();

	/**
	 * A bunch of data to set the status of each step.
	 * @var array
	 */
	protected $statuses = array();

	/**
	 * It may be necessary to use more than one template at a time.
	 * @var string[]
	 */
	protected $use_templates = array();

	/**
	 * Constructor
	 *
	 * @param ResponseHeader $headers
	 */
	public function __construct(ResponseHeader $headers)
	{
		$this->headers = $headers;
	}

	/**
	 * Sends out the headers to php using header function
	 */
	public function sendHeaders()
	{
		foreach ($this->headers->get() as $val)
			header($val);
	}

	public function getAll()
	{
		return $this->data;
	}

	/**
	 * Wrapper for ResponseHeader::set
	 *
	 * @param string $key
	 * @param string $val
	 */
	public function addHeader($key, $val)
	{
		$this->headers->set($key, $val);
	}

	/**
	 * Errors happen, this function adds a new one to the list.
	 *
	 * @param mixed|mixed[] $error_message
	 */
	public function addErrorParam($error_message, $trace = false, $line = false, $file = false)
	{
		if ($this->errorExists($error_message)
			return;

		$this->error_params[] = array(
			'message' => $error_message,
			'trace' => $trace,
			'line' => $line,
			'file' => $file
		);
	}

	protected function errorExists($error_message)
	{
		foreach ($this->error_params as $error_param)
		{
			if ($error_param['message'] === $error_message)
				return true;
		}

		return false;
	}

	/**
	 * Returns the error messages sprintf'ed if necessary
	 */
	public function getErrors()
	{
		$return = array();
		foreach ($this->error_params as $msg)
		{
			if (is_array($msg['message']))
			{
				$msg['message'] = sprintf($msg['message'][0], $msg['message'][1]);
			}

			$return[] = $msg;
		}

		return $return;
	}

	public function noTemplates()
	{
		return empty($this->use_templates);
	}

	public function status($status, $title)
	{
		$this->addTemplate('renderStatuses');

		$this->statuses[] = array('status' => $status, 'title' => $title);
	}

	public function getStatuses()
	{
		return $this->statuses;
	}

	public function addTemplate($template, $params = array())
	{
		if ($this->hasTemplate($template))
			return;

		$this->use_templates[] = array('name' => $template, 'params' => $params);
	}

	protected function hasTemplate($name)
	{
		foreach ($this->use_templates as $val)
		{
			if ($val['name'] === $name)
				return true;
		}

		return false;
	}

	public function getTemplates()
	{
		return $this->use_templates;
	}
}
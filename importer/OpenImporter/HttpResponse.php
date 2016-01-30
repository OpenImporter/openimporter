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
 * This contains the data used by the template.
 *
 * @property string assets_dir
 * @property Lang $lng
 * @property string scripturl
 * @property string styles
 * @property string scripts
 * @property string no_template
 * @property bool is_page
 * @property int step
 * @property bool valid
 * @property array template_error
 * @property string page_title
 * @property string source_name
 * @property string destination_name
 *
 * Class HttpResponse
 * @package OpenImporter\Core
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
		parent::__construct();
		$this->headers = $headers;
	}

	/**
	 * Sends out the headers to php using header function
	 */
	public function sendHeaders()
	{
		foreach ($this->headers->get() as $val)
		{
			header($val);
		}
	}

	/**
	 * Returns all the data
	 *
	 * @return array|\mixed[]|null
	 */
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
	 * @param bool $trace
	 * @param bool $line
	 * @param bool $file
	 * @param bool $query
	 */
	public function addErrorParam($error_message, $trace = false, $line = false, $file = false, $query = false)
	{
		if ($this->errorExists($error_message))
		{
			return;
		}

		$this->error_params[] = array(
			'message' => $error_message,
			'trace' => $trace,
			'line' => $line,
			'file' => $file,
			'query' => $query,
		);
	}

	/**
	 * Checks if a specific error exists so we don't load them twice
	 *
	 * @param $error_message
	 *
	 * @return bool
	 */
	protected function errorExists($error_message)
	{
		foreach ($this->error_params as $error_param)
		{
			if ($error_param['message'] === $error_message)
			{
				return true;
			}
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

	/**
	 * Returns if any templates have been loaded
	 *
	 * @return bool
	 */
	public function noTemplates()
	{
		return empty($this->use_templates);
	}

	/**
	 * Adds the step status
	 *
	 * @param string $status
	 * @param string $title
	 */
	public function status($status, $title)
	{
		$this->addTemplate('renderStatuses');

		$this->statuses[] = array('status' => $status, 'title' => $title);
	}

	/**
	 * Add a template for use, checks if its already availalbe
	 *
	 * @param $template
	 * @param array $params
	 */
	public function addTemplate($template, $params = array())
	{
		if ($this->hasTemplate($template))
		{
			return;
		}

		$this->use_templates[] = array('name' => $template, 'params' => $params);
	}

	/**
	 * Checks if a template is in use
	 *
	 * @param $name
	 *
	 * @return bool
	 */
	protected function hasTemplate($name)
	{
		foreach ($this->use_templates as $val)
		{
			if ($val['name'] === $name)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Return current statuses
	 *
	 * @return array
	 */
	public function getStatuses()
	{
		return $this->statuses;
	}

	/**
	 * Return current templates
	 *
	 * @return string[]
	 */
	public function getTemplates()
	{
		return $this->use_templates;
	}
}
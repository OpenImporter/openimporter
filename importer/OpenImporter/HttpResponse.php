<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 */

namespace OpenImporter;

/**
 * Class HttpResponse
 * Contains the data used by the template.
 *
 * @class HttpResponse
 */
class HttpResponse
{
	/** @var int (via magic) the current step  */
	public $step = 0;

	/** @var bool (via magic) the result of a load or check */
	public $valid;

	/** @var string (via magic) title based on script being run */
	public $page_title = 'OpenImporter';

	/** @var string (via magic) the script running */
	public $script;

	/** @var string (via magic see template) name of the template to show, like select_script */
	public $use_template;

	/** @var array (via magic see template) parameters for the call */
	public $params_template;

	/** @var string (via magic see template) */
	public $no_template;

	/** @var bool (via magic see template) */
	public $is_xml;

	/** @var bool (via magic, see template) */
	public $is_page;

	/** @var mixed|null (via magic, see template) */
	public $template_error;

	/** @var array Any kind of data the templates may need. */
	protected $data = array();

	/** @var \OpenImporter\ResponseHeader */
	protected $headers;

	/** @var \OpenImporter\Lang */
	public $lng;

	/** @var array */
	protected $error_params = array();

	/**
	 * HttpResponse constructor.
	 *
	 * @param ResponseHeader $headers
	 */
	public function __construct($headers)
	{
		$this->headers = $headers;
	}

	/**
	 * Set a key / value pair with magic
	 *
	 * @param string $key
	 * @param mixed $val
	 */
	public function __set($key, $val)
	{
		$this->data[$key] = $val;
	}

	/**
	 * Fetch a value for a key via magic
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function __get($key)
	{
		return $this->data[$key] ?? null;
	}

	/**
	 * Output all defined headers
	 */
	public function sendHeaders()
	{
		foreach ($this->headers->get() as $val)
		{
			header($val);
		}
	}

	/**
	 * Add a new header for output later
	 *
	 * @param string $key
	 * @param mixed $val
	 */
	public function addHeader($key, $val)
	{
		$this->headers->set($key, $val);
	}

	/**
	 * Add an error message to the stack
	 *
	 * @param $error_message
	 */
	public function addErrorParam($error_message)
	{
		$this->error_params[] = $error_message;
	}

	/**
	 * Returns the collected errors, if any
	 *
	 * @return string[]
	 */
	public function getErrors()
	{
		$return = array();

		foreach ($this->error_params as $msg)
		{
			if (is_array($msg))
			{
				$return[] = sprintf($msg[0], $msg[1]);
			}
			else
			{
				$return[] = $msg;
			}
		}

		return $return;
	}
}

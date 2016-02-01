<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 */

namespace OpenImporter;

/**
 * Class HttpResponse
 * Contains the data used by the template.
 *
 * @property bool no_template
 * @property bool is_xml
 * @property bool template_error
 * @property bool is_page
 * @property bool use_template
 * @property object valid
 * @property array params_template
 * @property int step
 * @property string page_title
 * @property string script
 * @property object importer
 *
 * @package OpenImporter
 */
class HttpResponse
{
	/**
	 * Any kind of data the templates may need.
	 */
	protected $data = array();

	/**
	 * @var ResponseHeader|null
	 */
	protected $headers = null;

	public $lng = null;

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
	 * @param $key
	 * @param $val
	 */
	public function __set($key, $val)
	{
		$this->data[$key] = $val;
	}

	/**
	 * Fetch a value for a key via magic
	 *
	 * @param $key
	 *
	 * @return null
	 */
	public function __get($key)
	{
		if (isset($this->data[$key]))
		{
			return $this->data[$key];
		}
		else
		{
			return null;
		}
	}

	/**
	 * Output all of the defined headers
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
	 * @param $key
	 * @param $val
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
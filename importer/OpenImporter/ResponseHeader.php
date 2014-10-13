<?php

/**
 * Any page served needs an header.
 */
class ResponseHeader
{
	protected $headers = array();

	/**
	 * Add an header
	 * @param string $key
	 * @param string|null $name (optional)
	 */
	public function set($key, $value = null)
	{
		if ($value === null && strpos($key, ':'))
		{
			$split = array_map('trim', explode(':', $key));
			$key = $split[0];
			$value = $split[1];
		}

		$this->headers[$key] = $value;
	}

	/**
	 * Send the headers
	 * @return string[]
	 */
	public function get()
	{
		$return = array();
		foreach ($this->headers as $key => $value)
			$return[] = $key . ': ' . $value;

		return $return;
	}

	/**
	 * Add an header
	 * @param string $key
	 */
	public function remove($key)
	{
		if (isset($this->headers[$key]))
			unset($this->headers[$key]);
	}
}
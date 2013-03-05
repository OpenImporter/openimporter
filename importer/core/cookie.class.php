<?php

/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *  
 * @version 1.0 Alpha
 */

class cookie
{
	public function cookie()
	{
		return true;
	}

	public function set($data, $name = 'openimporter_cookie')
	{
		if (!empty($data))
		{
			setcookie($name, serialize($data), time()+ 86400);
			$_COOKIE[$name] = serialize($data);
			return true;
		}
		return false;
	}

	public function get($name = 'openimporter_cookie')
	{
		if (isset($_COOKIE[$name]))
		{
			$cookie = unserialize($_COOKIE[$name]);
			return $cookie;
		}

		return false;
	}

	public function destroy($name = 'openimporter_cookie')
	{
		setcookie($name, '');
		unset($_COOKIE[$name]);

		return true;
	}

	public function extend($data, $name = 'openimporter_cookie')
	{
		$cookie = unserialize($_COOKIE[$name]);
		if (!empty($cookie) && isset($data))
			$merged = array_merge((array)$cookie, (array) $data);

		$this->set($merged);
		$_COOKIE[$name] = serialize($merged);

		return true;
	}
}
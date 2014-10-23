<?php

namespace OpenImporter;

/**
 * we need Cooooookies..
 */
class Cookie
{
	/**
	 * Constructor
	 * @return boolean
	 */
	public function Cookie()
	{
		return true;
	}

	/**
	 * set a cookie
	 * @param type $data
	 * @param type $name
	 * @return boolean
	 */
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

	/**
	 * get our cookie
	 * @param type $name
	 * @return boolean
	 */
	public function get($name = 'openimporter_cookie')
	{
		if (isset($_COOKIE[$name]))
		{
			$cookie = unserialize($_COOKIE[$name]);
			return $cookie;
		}

		return false;
	}

	/**
	 * once we are done, we should destroy our cookie
	 * @param type $name
	 * @return boolean
	 */
	public function destroy($name = 'openimporter_cookie')
	{
		setcookie($name, '');
		unset($_COOKIE[$name]);

		return true;
	}

	/**
	 * extend the cookie with new infos
	 * @param type $data
	 * @param type $name
	 * @return boolean
	 */
	public function extend($data, $name = 'openimporter_cookie')
	{
		$cookie = unserialize($_COOKIE[$name]);
		if (!empty($cookie) && isset($data))
			$merged = array_merge((array) $cookie, (array) $data);

		$this->set($merged);
		$_COOKIE[$name] = serialize($merged);

		return true;
	}
}
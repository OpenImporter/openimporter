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
 * We need Cooooookies..
 */
class Cookie
{
	/**
	 * set a cookie
	 * @param mixed[] $data
	 * @param string $name
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
	 * @param string $name
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
	 * @param string $name
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
	 * @param mixed[] $data
	 * @param string $name
	 * @return boolean
	 */
	public function extend($data, $name = 'openimporter_cookie')
	{
		$cookie = $this->get($name);

		if (!empty($data))
		{
			if ($cookie === false)
				$merged = $data;
			else
				$merged = array_merge((array) $cookie, (array) $data);

			return $this->set($merged, $name);
		}

		return false;
	}
}
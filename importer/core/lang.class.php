<?php

/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *  
 * @version 1.0 Alpha
 */

class lang
{
	private static $language = array();

	/**
	* Adds a new variable to lang.
	*
	* @param string $key Name of the variable
	* @param mixed $value Value of the variable
	* @throws Exception
	* @return bool
	*/
	protected static function set($key, $value)
	{
		try
		{
				if (!self::has($key))
				{
					self::$language[$key] = $value;
					return true;
				}
				else
					throw new Exception('Unable to set language string for <em>' . $key . '</em>. It was already set.');
		}
		catch(Exception $e)
		{
			import_exception::exception_handler($e);
		}
	}

	/**
	* load the language xml in lang
	*
	* @return null
	*/
	public static function loadLang()
	{
		// detect the browser language
		$languageuage = self::detect_browser_language();

		// loop through the preferred languages and try to find the related language file
		foreach ($languageuage as $key => $value)
		{
			if (file_exists(LANGDIR . '/import_' . $key . '.xml'))
			{
				$lngfile = LANGDIR . '/import_' . $key . '.xml';
				break;
			}
		}
		// english is still better than nothing
		if (!isset($lngfile))
		{
			if (file_exists(LANGDIR . '/import_en.xml'))
				$lngfile = LANGDIR . '/import_en.xml';
		}
		// ouch, we really should never arrive here..
		if (!$lngfile)
			throw new Exception('Unable to detect language file!');

		$languageObj = simplexml_load_file($lngfile, 'SimpleXMLElement', LIBXML_NOCDATA);

		foreach ($languageObj as $strings)
			self::set((string) $strings->attributes()->{'name'}, (string) $strings);

		return null;
	}
	/**
	* Tests if given $key exists in lang
	*
	* @param string $key
	* @return bool
	*/
	public static function has($key)
	{
		if (isset(self::$language[$key]))
			return true;

		return false;
	}

	/**
	* Returns the value of the specified $key in lang.
	*
	* @param string $key Name of the variable
	* @return mixed Value of the specified $key
	*/
	public static function get($key)
	{
		if (self::has($key))
			return self::$language[$key];

		return null;
	}
	/**
	* Returns the whole lang as an array.
	*
	* @return array Whole lang
	*/
	public static function getAll()
	{
		return self::$language;
	}

	protected static function detect_browser_language()
	{
		if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']))
		{
			// break up string into pieces (languages and q factors)
			preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']), $language_parse);

			if (count($language_parse[1]))
			{
				// create a list like "en" => 0.8
				$preferred = array_combine($language_parse[1], $language_parse[4]);

				// set default to 1 for any without q factor (IE fix)
				foreach ($preferred as $language => $val)
				{
					if ($val === '')
						$preferred[$language] = 1;
				}

				// sort list based on value
				arsort($preferred, SORT_NUMERIC);
			}
		}
		return $preferred;
	}

}
<?php

/**
 * Class Lang loads the appropriate language file(s)
 * if they exist. The default import_en.xml file
 * contains the English strings used by the importer.
 *
 * @var array $lang
 */
class Lang
{
	private $_lang = array();
	protected $_ns = array();

	/**
	 * Adds a new variable to lang.
	 *
	 * @param string $key Name of the variable
	 * @param string $value Value of the variable
	 * @throws Exception
	 * @return boolean|null
	 */
	protected function set($key, $value)
	{
		try
		{
			if (!$this->has($key))
			{
				if (strpos($key, '.') !== false)
				{
					$exp = explode('.', $key);
					$this->registerNamespace($exp[0]);
				}
				$this->_lang[$key] = $value;
				return true;
			}
			else
				throw new Exception('Unable to set language string for <em>' . $key . '</em>. It was already set.');
		}
		catch(Exception $e)
		{
			// @todo this should not be a fatal error
			ImportException::exception_handler($e);
		}
	}

	protected function registerNamespace($key)
	{
		if (!in_array($key, $this->_ns))
			$this->_ns[] = $key;
	}

	/**
	 * Loads the language xml file.
	 *
	 * @return null
	 * @throws ImportException if the XML file has got a corrupted structure.
	 */
	public function loadLang($path)
	{
		// detect the browser language
		$language = $this->detect_browser_language();
		$lngfile = $this->findLanguage($path, $language);

		// ouch, we really should never arrive here..
		if (!$lngfile)
			throw new Exception('Unable to detect language file!');

		if (!$langObj = simplexml_load_file($lngfile, 'SimpleXMLElement', LIBXML_NOCDATA))
			throw new ImportException('XML-Syntax error in file: ' . $lngfile);

		foreach ($langObj as $strings)
			$this->set((string) $strings->attributes()->{'name'}, (string) $strings);

		return null;
	}

	protected function findLanguage($path, $language)
	{
		$lngfile = false;

		// loop through the preferred languages and try to find the related language file
		foreach ($language as $key => $value)
		{
			if (file_exists($path . '/import_' . $key . '.xml'))
			{
				$lngfile = $path . '/import_' . $key . '.xml';
				break;
			}
		}
		// english is still better than nothing
		if (empty($lngfile))
		{
			if (file_exists($path . '/import_en.xml'))
				$lngfile = $path . '/import_en.xml';
		}

		return $lngfile;
	}

	/**
	 * Tests if given $key exists in lang
	 *
	 * @param string $key
	 * @return bool
	 */
	public function has($key)
	{
		return isset($this->_lang[$key]);
	}

	public function __get($key)
	{
		foreach ($this->_ns as $ns)
		{
			if ($this->has($ns . '.' . $key))
				return $this->get($ns . '.' . $key);
		}

		return $this->get($key);
	}

	/**
	 * Returns the value of the specified $key in lang.
	 *
	 * @param string $key Name of the variable
	 * @return string|null Value of the specified $key
	 */
	public function get($key)
	{
		if (is_array($key))
		{
			$l_key = array_shift($key);

			if ($this->has($l_key))
				return vsprintf($this->_lang[$l_key], $key);
		}
		else
		{
			if ($this->has($key))
				return $this->_lang[$key];
		}

		return null;
	}

	/**
	 * Returns the whole lang as an array.
	 *
	 * @return array Whole lang
	 */
	public function getAll()
	{
		return $this->_lang;
	}

	/**
	 * This is used to detect the Client's browser language.
	 *
	 * @return string the shortened string of the browser's language.
	 */
	protected function detect_browser_language()
	{
		if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']))
		{
			// break up string into pieces (languages and q factors)
			// the string looks like: en-GB,en;q=0.9,it;q=0.8
			preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']), $lang_parse);

			if (count($lang_parse[1]))
			{
				// create a list like "en" => 0.8
				$preferred = array_combine($lang_parse[1], $lang_parse[4]);

				// set default to 1 for any without q factor (IE fix)
				foreach ($preferred as $lang => $val)
				{
					if ($val === '')
						$preferred[$lang] = 1;
				}

				// sort list based on value
				arsort($preferred, SORT_NUMERIC);
			}
		}
		else
			$preferred = array();

		return $preferred;
	}
}
<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Alpha
 *
 * This file contains code based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:	BSD, See included LICENSE.TXT for terms and conditions.
 */

/**
 * Object ImportManager loads the main importer.
 * It handles all steps to completion.
 *
 */
class ImportManager
{
	/**
	 * The importer that will act as interface between the manager and the
	 * files that will do the actual import
	 * @var object
	 */
	public $importer;

	/**
	 * Our cookie settings
	 * @var object
	 */
	protected $cookie;

	/**
	 * The template, basically our UI.
	 * @var object
	 */
	public $template;

	/**
	 * The headers of the response.
	 * @var object
	 */
	protected $headers;

	/**
	 * The template to use.
	 * @var string
	 */
	public $use_template = '';

	/**
	 * Any param needed by the template
	 * @var mixed[]
	 */
	public $params_template = array();

	/**
	 * If set to true the template should not render anything
	 * @var bool
	 */
	public $no_template = false;

	/**
	 * An array of possible importer scripts
	 * @var array
	 */
	public $sources;

	/**
	 * The XML file which will be used from the importer.
	 * @var Object
	 */
	public $xml;

	/**
	 * Is an XML response expected?
	 * @var bool
	 */
	public $is_xml = false;

	/**
	 * If render a full page or just a bit
	 * @var bool
	 */
	public $is_page = true;

	/**
	 * Is there an error?
	 * @var bool
	 */
	public $template_error = false;

	/**
	 * List of error messages
	 * @var mixed[]
	 */
	public $error_params = array();

	/**
	 * Data used by the script and stored in session between a reload and the
	 * following one.
	 * @var mixed[]
	 */
	public $data = array();

	/**
	 * The path to the source forum.
	 * @var string
	 */
	protected $path_from = '';

	/**
	 * The path to the destination forum.
	 * @var string
	 */
	protected $path_to = '';

	/**
	 * The importer script which will be used for the import.
	 * @var string
	 */
	private $_script = '';

	/**
	 * This is the URL from our Installation.
	 * @var string
	 */
	private $_boardurl = '';

	/**
	 * initialize the main Importer object
	 */
	public function __construct($importer, $template, $cookie, $headers)
	{
		$this->importer = $importer;
		$this->cookie = $cookie;
		$this->template = $template;
		$this->headers = $headers;
		$this->lng = $importer->lng;

		$this->_findScript();

		// The current step - starts at 0.
		$_GET['step'] = isset($_GET['step']) ? (int) @$_GET['step'] : 0;
		$_REQUEST['start'] = isset($_REQUEST['start']) ? (int) @$_REQUEST['start'] : 0;

		$this->loadPass();

		$this->loadPaths();

		$this->importer->setScript($this->_script);
	}

	public function __destruct()
	{
		$this->saveInSession();
	}

	protected function loadPass()
	{
		// Check for the password...
		if (isset($_POST['db_pass']))
			$this->data['db_pass'] = $_POST['db_pass'];

		if (isset($this->data['db_pass']))
			$this->db_pass = $this->data['db_pass'];
	}

	protected function loadPaths()
	{
		if (isset($this->data['import_paths']) && !isset($_POST['path_from']) && !isset($_POST['path_to']))
			list ($this->path_from, $this->path_to) = $this->data['import_paths'];
		elseif (isset($_POST['path_from']) || isset($_POST['path_to']))
		{
			if (isset($_POST['path_from']))
				$this->path_from = rtrim($_POST['path_from'], '\\/');
			if (isset($_POST['path_to']))
				$this->path_to = rtrim($_POST['path_to'], '\\/');

			$this->data['import_paths'] = array($this->path_from, $this->path_to);
		}
	}

	protected function loadFromSession()
	{
		if (empty($_SESSION['importer_data']))
			return;

		$this->data = $_SESSION['importer_data'];
	}

	protected function saveInSession()
	{
		$_SESSION['importer_data'] = $this->data;
	}

	/**
	 * Finds the script either in the session or in request
	 */
	protected function _findScript()
	{
		// Save here so it doesn't get overwritten when sessions are restarted.
		if (isset($_REQUEST['import_script']))
			$this->_script = (string) $_REQUEST['import_script'];
		elseif (isset($_SESSION['import_script']) && file_exists(BASEDIR . DIRECTORY_SEPARATOR . $_SESSION['import_script']) && preg_match('~_importer\.xml$~', $_SESSION['import_script']) != 0)
			$this->_script = (string) $_SESSION['import_script'];
		else
		{
			$this->_script = '';
			unset($_SESSION['import_script']);
		}
	}

	/**
	 * Prepares the response to send to the template system
	 */
	public function getResponse()
	{
		// This is really quite simple; if ?delete is on the URL, delete the importer...
		if (isset($_GET['delete']))
		{
			$this->uninstall();

			$this->no_template = true;
		}
		elseif (isset($_GET['xml']))
			$this->is_xml = true;
		elseif (method_exists($this, 'doStep' . $_GET['step']))
			call_user_func(array($this, 'doStep' . $_GET['step']));
		else
			call_user_func(array($this, 'doStep0'));

		return $this;
	}

	/**
	 * Deletes the importer files from the server
	 * @todo doesn't know yet about the new structure.
	 */
	protected function uninstall()
	{
		@unlink(__FILE__);
		if (preg_match('~_importer\.xml$~', $_SESSION['import_script']) != 0)
			@unlink(BASEDIR . DIRECTORY_SEPARATOR . $_SESSION['import_script']);
		$_SESSION['import_script'] = null;
	}

	/**
	 * - checks,  if we have already specified an importer script
	 * - checks the file system for importer definition files
	 * @return boolean
	 * @throws ImportException
	 */
	private function _detect_scripts()
	{
		if (isset($_REQUEST['import_script']))
		{
			if ($_REQUEST['import_script'] != '' && preg_match('~^[a-z0-9\-_\.]*_importer\.xml$~i', $_REQUEST['import_script']) != 0)
				$_SESSION['import_script'] = preg_replace('~[\.]+~', '.', $_REQUEST['import_script']);
			else
				$_SESSION['import_script'] = null;
		}

		$dir = BASEDIR . '/Importers/';
		$sources = glob($dir . '*', GLOB_ONLYDIR);
		$all_scripts = array();
		$scripts = array();
		foreach ($sources as $source)
		{
			$from = basename($source);
			$scripts[$from] = array();
			$possible_scripts = glob($source . '/*_importer.xml');

			foreach ($possible_scripts as $entry)
			{
				try
				{
					if (!$xmlObj = simplexml_load_file($entry, 'SimpleXMLElement', LIBXML_NOCDATA))
						throw new ImportException('XML-Syntax error in file: ' . $entry);

					$xmlObj = simplexml_load_file($entry, 'SimpleXMLElement', LIBXML_NOCDATA);
					$scripts[$from][] = array('path' => $from . DIRECTORY_SEPARATOR . basename($entry), 'name' => $xmlObj->general->name);
					$all_scripts[] = array('path' => $from . DIRECTORY_SEPARATOR . basename($entry), 'name' => $xmlObj->general->name);
				}
				catch (Exception $e)
				{
					ImportException::exception_handler($e, $this->template);
				}
			}
		}

		if (isset($_SESSION['import_script']))
		{
			if (count($all_scripts) > 1)
				$this->sources[$from] = $scripts[$from];
			return false;
		}

		if (count($all_scripts) == 1)
		{
			$_SESSION['import_script'] = basename($scripts[$from][0]['path']);
			if (substr($_SESSION['import_script'], -4) == '.xml')
			{
				$this->importer->setScript($_SESSION['import_script']);
				$this->reloadImporter();
			}
			return false;
		}

		$this->use_template = 'select_script';
		$this->params_template = array($scripts);

		return true;
	}

	/**
	 * collects all the important things, the importer can't do anything
	 * without this information.
	 *
	 * @global Database $db
	 * @global type $to_prefix
	 * @global type $import_script
	 * @global type $cookie
	 * @global type $import
	 * @param type $error_message
	 * @param type $object
	 * @return boolean|null
	 */
	public function doStep0($error_message = null, $object = false)
	{
		global $import;

		$import = isset($object) ? $object : false;
		$this->cookie->destroy();
		//previously imported? we need to clean some variables ..
		unset($_SESSION['import_overall'], $_SESSION['import_steps']);

		if ($this->_detect_scripts())
			return true;

		// If these aren't set (from an error..) default to the current directory.
		if (!isset($this->path_from))
			$this->path_from = BASEDIR;
		if (!isset($this->path_to))
			$this->path_to = BASEDIR;

		$test_from = $this->testFiles($this->importer->xml->general->settings, $this->path_from);
		$test_to = $this->testFiles('Settings.php', $this->path_to);

		// Was an error message specified?
		if ($error_message !== null)
		{
			$this->template_error = true;
			$this->error_params[] = $error_message;
		}

		$this->use_template = 'step0';
		$this->params_template = array($this, $this->_find_steps(), $test_from, $test_to);

		if ($error_message !== null)
		{
			$this->template->footer();
			exit;
		}

		return;
	}

	protected function testFiles($files, $path)
	{
		$files = (array) $files;

		$test = empty($files);

		foreach ($files as $file)
			$test |= @file_exists($path . DIRECTORY_SEPARATOR . $file);

		return $test;
	}

	/**
	 * the important one, transfer the content from the source forum to our
	 * destination system
	 *
	 * @global type $to_prefix
	 * @global type $global
	 * @return boolean
	 */
	public function doStep1()
	{
		global $to_prefix;

		$this->cookie->set(array($this->path_to, $this->path_from));

		$_GET['substep'] = isset($_GET['substep']) ? (int) @$_GET['substep'] : 0;
		// @TODO: check if this is needed
		//$progress = ($_GET['substep'] ==  0 ? 1 : $_GET['substep']);

		// Skipping steps?
		if (isset($_SESSION['do_steps']))
			$do_steps = $_SESSION['do_steps'];
		else
			$do_steps = array();

		//calculate our overall time and create the progress bar
		if(!isset($_SESSION['import_overall']))
			list ($_SESSION['import_overall'], $_SESSION['import_steps']) = $this->importer->determineProgress();

		if(!isset($_SESSION['import_progress']))
			$_SESSION['import_progress'] = 0;

		$this->importer->doStep1($do_steps);

		$_GET['substep'] = 0;
		$_REQUEST['start'] = 0;

		return $this->doStep2();
	}

	/**
	 * we have imported the old database, let's recalculate the forum statistics.
	 *
	 * @global Database $db
	 * @global type $to_prefix
	 * @return boolean
	 */
	public function doStep2()
	{
		$_GET['step'] = '2';

		$this->template->step2();

		$key = $this->importer->doStep2($_GET['substep']);

		$this->template->status($key + 1, 1, false, true);

		return $this->doStep3();
	}

	/**
	 * we are done :)
	 *
	 * @global Database $db
	 * @global type $boardurl
	 * @return boolean
	 */
	public function doStep3()
	{
		global $boardurl;

		$this->importer->doStep3($_SESSION['import_steps']);

		$writable = (is_writable(BASEDIR) && is_writable(__FILE__));

		$this->use_template = 'step3';
		$this->params_template = array($this->importer->xml->general->name, $this->_boardurl, $writable);

		unset ($_SESSION['import_steps'], $_SESSION['import_progress'], $_SESSION['import_overall']);
		return true;
	}
}

/**
 * Checks if we've passed a time limit..
 *
 * @param int $substep
 * @param int $stop_time
 * @return null
 */
function pastTime($substep = null, $stop_time = 5)
{
	global $import, $time_start;

	if (isset($_GET['substep']) && $_GET['substep'] < $substep)
		$_GET['substep'] = $substep;

	// some details for our progress bar
	if (isset($import->count->$substep) && $import->count->$substep > 0 && isset($_REQUEST['start']) && $_REQUEST['start'] > 0 && isset($substep))
		$bar = round($_REQUEST['start'] / $import->count->$substep * 100, 0);
	else
		$bar = false;

	@set_time_limit(300);
	if (is_callable('apache_reset_timeout'))
		apache_reset_timeout();

	if (time() - $time_start < $stop_time)
		return;

	$import->template->time_limit($bar, $_SESSION['import_progress'], $_SESSION['import_overall']);
	$import->template->footer();
	exit;
}

/**
 * helper function, simple file copy at all
 *
 * @param string $filename
 * @return boolean
 */
function copy_file($source, $destination)
{
	if (is_file($source))
	{
		copy($source, $destination);
		return false;
	}
	return true;
}

/**
 * // Add slashes recursively...
 *
 * @param array $var
 * @return array
 */
function addslashes_recursive($var)
{
	if (!is_array($var))
		return addslashes($var);
	else
	{
		foreach ($var as $k => $v)
			$var[$k] = addslashes_recursive($v);
		return $var;
	}
}

/**
 * Remove slashes recursively...
 *
 * @param array $var
 * @return array
 */
function stripslashes_recursive($var, $level = 0)
{
	if (!is_array($var))
		return stripslashes($var);

	// Reindex the array without slashes, this time.
	$new_var = array();

	// Strip the slashes from every element.
	foreach ($var as $k => $v)
		$new_var[stripslashes($k)] = $level > 25 ? null : stripslashes_recursive($v, $level + 1);

	return $new_var;
}

/**
 * function copy_dir copies a directory
 * @param string $source
 * @param string $dest
 * @return type
 */
function copy_dir($source, $dest)
{
	if (!is_dir($source) || !($dir = opendir($source)))
		return;
		while ($file = readdir($dir))
	{
		if ($file == '.' || $file == '..')
			continue;
			// If we have a directory create it on the destination and copy contents into it!
		if (is_dir($source . DIRECTORY_SEPARATOR. $file))
		{
			if (!is_dir($dest))
				@mkdir($dest, 0777);
			copy_dir($source . DIRECTORY_SEPARATOR . $file, $dest . DIRECTORY_SEPARATOR . $file);
		}
		else
		{
			if (!is_dir($dest))
				@mkdir($dest, 0777);
			copy($source . DIRECTORY_SEPARATOR . $file, $dest . DIRECTORY_SEPARATOR . $file);
		}
	}
	closedir($dir);
}

/**
 * detects, if a string is utf-8 or not
 * @param type $string
 * @return boolean
 */
 function is_utf8($string)
{
	return utf8_encode(utf8_decode($string)) == $string;
}

/**
 * Function fix based on ForceUTF8 by Sebastián Grignoli <grignoli@framework2.com.ar>
 * @link http://www.framework2.com.ar/dzone/forceUTF8-es/
 * This function leaves UTF8 characters alone, while converting almost all non-UTF8 to UTF8.
 *
 * It may fail to convert characters to unicode if they fall into one of these scenarios:
 *
 * 1) when any of these characters:   ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖ×ØÙÚÛÜÝÞß
 *    are followed by any of these:  ("group B")
 *                                    ¡¢£¤¥¦§¨©ª«¬­®¯°±²³´µ¶•¸¹º»¼½¾¿
 * For example:   %ABREPR%C9SENT%C9%BB. «REPRÉSENTÉ»
 * The "«" (%AB) character will be converted, but the "É" followed by "»" (%C9%BB)
 * is also a valid unicode character, and will be left unchanged.
 *
 * 2) when any of these: àáâãäåæçèéêëìíîï  are followed by TWO chars from group B,
 * 3) when any of these: ðñòó  are followed by THREE chars from group B.
 *
 * @name fix
 * @param string $text  Any string.
 * @return string  The same string, UTF8 encoded
 */
function fix_charset($text)
{
	if (is_array($text))
	{
		foreach ($text as $k => $v)
			$text[$k] = fix_charset($v);
		return $text;
	}
	// numeric? There's nothing to do, we simply return our input.
	if (is_numeric($text))
		return $text;

	$max = strlen($text);
	$buf = '';

	for ($i = 0; $i < $max; $i++)
	{
		$c1 = $text{$i};
		if ($c1 >= "\xc0")
		{
			// Should be converted to UTF8, if it's not UTF8 already
			$c2 = $i+1 >= $max? "\x00" : $text{$i+1};
			$c3 = $i+2 >= $max? "\x00" : $text{$i+2};
			$c4 = $i+3 >= $max? "\x00" : $text{$i+3};
			if ($c1 >= "\xc0" & $c1 <= "\xdf")
			{
				// looks like 2 bytes UTF8
				if ($c2 >= "\x80" && $c2 <= "\xbf")
				{
					// yeah, almost sure it's UTF8 already
					$buf .= $c1 . $c2;
					$i++;
				}
				else
				{
					// not valid UTF8. Convert it.
					$cc1 = (chr(ord($c1) / 64) | "\xc0");
					$cc2 = ($c1 & "\x3f") | "\x80";
					$buf .= $cc1 . $cc2;
				}
			}
			elseif ($c1 >= "\xe0" & $c1 <= "\xef")
			{
				// looks like 3 bytes UTF8
				if ($c2 >= "\x80" && $c2 <= "\xbf" && $c3 >= "\x80" && $c3 <= "\xbf")
				{
					// yeah, almost sure it's UTF8 already
					$buf .= $c1 . $c2 . $c3;
					$i = $i + 2;
				}
				else
				{
					// not valid UTF8. Convert it.
					$cc1 = (chr(ord($c1) / 64) | "\xc0");
					$cc2 = ($c1 & "\x3f") | "\x80";
					$buf .= $cc1 . $cc2;
				}
			}
			elseif ($c1 >= "\xf0" & $c1 <= "\xf7")
			{
				// Looks like 4-byte UTF8
				if ($c2 >= "\x80" && $c2 <= "\xbf" && $c3 >= "\x80" && $c3 <= "\xbf" && $c4 >= "\x80" && $c4 <= "\xbf")
				{
					// Yeah, almost sure it's UTF8 already
					$buf .= $c1 . $c2 . $c3;
					$i = $i + 2;
				}
				else
				{
					// Not valid UTF8. Convert it.
					$cc1 = (chr(ord($c1) / 64) | "\xc0");
					$cc2 = ($c1 & "\x3f") | "\x80";
					$buf .= $cc1 . $cc2;
				}
			}
			else
			{
				// Doesn't look like UTF8, but should be converted
				$cc1 = (chr(ord($c1) / 64) | "\xc0");
				$cc2 = (($c1 & "\x3f") | "\x80");
				$buf .= $cc1 . $cc2;
			}
		}
		elseif (($c1 & "\xc0") == "\x80")
		{
			// Needs conversion
			$cc1 = (chr(ord($c1) / 64) | "\xc0");
			$cc2 = (($c1 & "\x3f") | "\x80");
			$buf .= $cc1 . $cc2;
		}
		else
			// Doesn't need conversion
			$buf .= $c1;
	}
	if (function_exists('mb_decode_numericentity'))
		$buf = mb_decode_numericentity($buf, array(0x80, 0x2ffff, 0, 0xffff), 'UTF-8');
	else
	{
		// Take care of html entities..
		$entity_replace = create_function('$num', '
			return $num < 0x20 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF) ? \'\' :
				  ($num < 0x80 ? \'&#\' . $num . \';\' : ($num < 0x800 ? chr(192 | $num >> 6) . chr(128 | $num & 63) :
				  ($num < 0x10000 ? chr(224 | $num >> 12) . chr(128 | $num >> 6 & 63) . chr(128 | $num & 63) :
				  chr(240 | $num >> 18) . chr(128 | $num >> 12 & 63) . chr(128 | $num >> 6 & 63) . chr(128 | $num & 63))));');

		// @todo use preg_replace_callback
		$buf = preg_replace('~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~e', '$entity_replace(\\2)', $buf);
		$buf = preg_replace('~(&#x(\d{1,7}|x[0-9a-fA-F]{1,6});)~e', '$entity_replace(0x\\2)', $buf);
	}

	// surprise, surprise... the string
	return $buf;
}

/**
 * helper function for storing vars that need to be global
 *
 * @param string $variable
 * @param string $value
 */
function store_global($variable, $value)
{
	$_SESSION['store_globals'][$variable] = $value;
}

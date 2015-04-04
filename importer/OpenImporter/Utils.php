<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 Alpha
 *
 * This file contains code based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:	BSD, See included LICENSE.TXT for terms and conditions.
 */

/**
 * Checks if we've passed a time limit..
 *
 * @param int|null $substep
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

	Throw new PasttimeException($import->template, $bar, $_SESSION['import_progress'], $_SESSION['import_overall']);
}

/**
 * helper function, simple file copy at all
 * @todo consider using:
 *    http://symfony.com/components/Filesystem
 *    http://symfony.com/components/Finder
 *
 * @param string $source
 * @param string $destination
 * @return boolean
 */
function copy_file($source, $destination)
{
	create_folders_recursive(dirname($destination));

	if (is_file($source))
	{
		copy($source, $destination);
		return false;
	}
	return true;
}

function copy_dir_recursive($source, $destination)
{
	$source = rtrim($source, '\\/') . DIRECTORY_SEPARATOR;
	$destination = rtrim($destination, '\\/') . DIRECTORY_SEPARATOR;
	if (!file_exists($source))
		return;
	$dir = opendir($source);
	create_folders_recursive($destination);
	while ($file = readdir($dir))
	{
		if ($file == '.' || $file == '..')
			continue;

		if (is_dir($source . $file))
			copy_dir_recursive($source . $file, $destination . $file);
		else
			copy($source . $file, $destination . $file);
	}
}

function get_files_recursive($base)
{
	$files = array();
	$base = rtrim($base, '\\/') . DIRECTORY_SEPARATOR;

	if (!file_exists($base))
		return false;

	$dir = opendir($base);

	while ($file = readdir($dir))
	{
		if ($file == '.' || $file == '..')
			continue;

		if (is_dir($base . $file))
			$files = array_merge($files, get_files_recursive($base . $file));
		else
			$files[] = $base . $file;
	}

	return $files;
}

function create_folders_recursive($path)
{
	$parent = dirname($path);

	if (!file_exists($parent))
		create_folders_recursive($parent);

	if (!file_exists($path))
		@mkdir($path, 0755);
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
				@mkdir($dest, 0755);
			copy_dir($source . DIRECTORY_SEPARATOR . $file, $dest . DIRECTORY_SEPARATOR . $file);
		}
		else
		{
			if (!is_dir($dest))
				@mkdir($dest, 0755);
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
 * @param string|string[] $text  Any string.
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
		preg_replace_callback('~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'replaceEntities__callback', $buf);
	}

	// surprise, surprise... the string
	return $buf;
}

/**
 * Decode numeric html entities to their UTF8 equivalent character.
 *
 * What it does:
 * - Callback function for preg_replace_callback in subs-members
 * - Uses capture group 2 in the supplied array
 * - Does basic scan to ensure characters are inside a valid range
 *
 * @param mixed[] $matches matches from a preg_match_all
 * @return string $string
 */
function replaceEntities__callback($matches)
{
	if (!isset($matches[2]))
		return '';

	$num = $matches[2][0] === 'x' ? hexdec(substr($matches[2], 1)) : (int) $matches[2];

	// remove left to right / right to left overrides
	if ($num === 0x202D || $num === 0x202E)
		return '';

	// Quote, Ampersand, Apostrophe, Less/Greater Than get html replaced
	if (in_array($num, array(0x22, 0x26, 0x27, 0x3C, 0x3E)))
		return '&#' . $num . ';';

	// <0x20 are control characters, 0x20 is a space, > 0x10FFFF is past the end of the utf8 character set
	// 0xD800 >= $num <= 0xDFFF are surrogate markers (not valid for utf8 text)
	if ($num < 0x20 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF))
		return '';
	// <0x80 (or less than 128) are standard ascii characters a-z A-Z 0-9 and puncuation
	elseif ($num < 0x80)
		return chr($num);
	// <0x800 (2048)
	elseif ($num < 0x800)
		return chr(($num >> 6) + 192) . chr(($num & 63) + 128);
	// < 0x10000 (65536)
	elseif ($num < 0x10000)
		return chr(($num >> 12) + 224) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
	// <= 0x10FFFF (1114111)
	else
		return chr(($num >> 18) + 240) . chr((($num >> 12) & 63) + 128) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
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

function print_dbg($val)
{
	echo '<pre>';
	print_r($val);
	echo '</pre>';
}
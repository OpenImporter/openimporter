<?php

/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *  
 * @version 1.0 Alpha
 *  
 * This software contains functions based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:	BSD, See included LICENSE.TXT for terms and conditions.
 */

/**
 * @name is_utf8
 * @param type $string
 * @return type 
 * simple function to detect whether a string is utf-8 or not
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
* @name fix_charset
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

/**
* helper function for old attachments
*
* @param string $filename
* @param int $attachment_id
* @return string
*/
function getLegacyAttachmentFilename($filename, $attachment_id)
{
	// Remove special accented characters - ie. sí (because they won't write to the filesystem well.)
	$clean_name = strtr($filename, 'ŠŽšžŸÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÑÒÓÔÕÖØÙÚÛÜÝàáâãäåçèéêëìíîïñòóôõöøùúûüýÿ', 'SZszYAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy');
	$clean_name = strtr($clean_name, array('Þ' => 'TH', 'þ' => 'th', 'Ð' => 'DH', 'ð' => 'dh', 'ß' => 'ss', 'Œ' => 'OE', 'œ' => 'oe', 'Æ' => 'AE', 'æ' => 'ae', 'µ' => 'u'));

	// Get rid of dots, spaces, and other weird characters.
	$clean_name = preg_replace(array('/\s/', '/[^\w_\.\-]/'), array('_', ''), $clean_name);

	return $attachment_id . '_' . strtr($clean_name, '.', '_') . md5($clean_name);
}

/**
* helper function to create an encrypted attachment name
*
* @param string $filename
* @return string
*/
function createAttachmentFilehash($filename)
{
	return sha1(md5($filename . time()) . mt_rand());
}

/**
* helper function, simple file copy at all
*
* @param string $filename
* @return bol
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
* Add slashes recursively...
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
 * @name copy_smileys
 * @param type $source
 * @param type $dest
 * @return type 
 * 
 * helper function, copies smileys from source to destination directory
 */
function copy_smileys($source, $dest)
{
	if (!is_dir($source) || !($dir = opendir($source)))
		return;

	while ($file = readdir($dir))
	{
		if ($file == '.' || $file == '..')
			continue;

		// If we have a directory create it on the destination and copy contents into it!
		if (is_dir($source . '/' . $file))
		{
			if (!is_dir($dest))
				@mkdir($dest . '/' . $file, 0777);
			copy_dir($source . '/' . $file, $dest . '/' . $file);
		}
		else
		{
			if (!is_dir($dest))
				@mkdir($dest . '/' . $file, 0777);
			copy($source . '/' . $file, $dest . '/' . $file);
		}
	}
	closedir($dir);
}
/**
 * @name copy_dir
 * @param type $source
 * @param type $dest
 * @return type 
 * 
 * just copy a directory, use by copy_smileys
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
		if (is_dir($source . '/'. $file))
		{
			if (!is_dir($dest))
				@mkdir($dest, 0777);
			copy_dir($source . '/' . $file, $dest . '/' . $file);
		}
		else
		{
			if (!is_dir($dest))
				@mkdir($dest, 0777);
			copy($source . '/' . $file, $dest . '/' . $file);
		}
	}
	closedir($dir);
}

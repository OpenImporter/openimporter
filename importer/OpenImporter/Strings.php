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

namespace OpenImporter\Core;

class Strings
{
	/**
	 * // Add slashes recursively...
	 *
	 * @param array $var
	 * @return array
	 */
	public static function addslashes_recursive($var)
	{
		if (!is_array($var))
			return addslashes($var);
		else
		{
			foreach ($var as $k => $v)
				$var[$k] = Strings::addslashes_recursive($v);
			return $var;
		}
	}

	/**
	 * Remove slashes recursively...
	 *
	 * @param array $var
	 * @return array
	 */
	public static function stripslashes_recursive($var, $level = 0)
	{
		if (!is_array($var))
			return stripslashes($var);

		// Reindex the array without slashes, this time.
		$new_var = array();

		// Strip the slashes from every element.
		foreach ($var as $k => $v)
			$new_var[stripslashes($k)] = $level > 25 ? null : Strings::stripslashes_recursive($v, $level + 1);

		return $new_var;
	}

	/**
	 * @todo apparently unused
	 *
	 * detects, if a string is utf-8 or not
	 * @param type $string
	 * @return boolean
	 */
	public static function is_utf8($string)
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
	public static function fix_charset($text)
	{
		if (is_array($text))
		{
			foreach ($text as $k => $v)
				$text[$k] = Strings::fix_charset($v);
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
			preg_replace_callback('~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~', 'Strings::replaceEntities__callback', $buf);
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
	public static function replaceEntities__callback($matches)
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
}
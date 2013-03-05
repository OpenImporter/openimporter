<?php

/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *  
 * @version 1.0 Alpha
 */

class charset
{
	// simple function to detect whether a string is utf-8 or not
	private static function is_utf8($string)
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
	public static function fix($text)
	{
		if (is_array($text))
		{
			foreach ($text as $k => $v)
				$text[$k] = self::fix($v);
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
}

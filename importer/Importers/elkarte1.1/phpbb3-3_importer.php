<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD https://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 */

/**
 * Class phpBB33
 */
class phpBB33 extends Importers\AbstractSourceImporter
{
	protected $setting_file = '/config.php';

	public function getName()
	{
		return 'phpBB3_3';
	}

	public function getVersion()
	{
		return 'ElkArte 1.1';
	}

	public function setDefines()
	{
		if (!defined('IN_PHPBB'))
		{
			define('IN_PHPBB', 1);
		}
	}

	public function getPrefix()
	{
		$table_prefix = $this->fetchSetting('table_prefix');

		return '`' . $this->getDbName() . '`.' . $table_prefix;
	}

	public function getDbName()
	{
		return $this->fetchSetting('dbname');
	}

	public function getTableTest()
	{
		return 'users';
	}

	public function phpbb_copy_files($dir, $row, $id_attach, $destination_path, $thumb = false)
	{
		// Use the Utils function to get extra details
		list($ext, $basename, $mime_type) = attachment_type($row['filename']);

		// Prep for the copy
		$file = (($thumb) ? 'thumb_' : '') . $row['physical_filename'];
		$source = $dir . '/' . $file;
		$file_hash = createAttachmentFilehash($file);
		$destination = $destination_path . '/' . $id_attach . '_' . $file_hash . '.elk';
		$type = 0;

		// Copy it over
		copy_file($source, $destination);

		// An image must have a legit width/height
		$width = 0;
		$height = 0;
		if (!empty($ext))
		{
			list ($width, $height) = getimagesize($destination);
			if (!empty($width))
			{
				$type = ($thumb) ? 3 : 0;
			}
		}

		// Prepare our insert
		return array(
			'id_attach' => $id_attach,
			'id_thumb' => !$thumb && !empty($row['thumbnail']) ? ++$id_attach : 0,
			'size' => file_exists($destination) ? filesize($destination) : 0,
			'filename' => $basename . '.' . ($thumb ? $ext . '_thumb' : $ext),
			'file_hash' => $file_hash,
			'file_ext' => $ext,
			'mime_type' => $mime_type,
			'attachment_type' => $type,
			'id_msg' => $row['id_msg'],
			'downloads' => $row['downloads'],
			'width' => $width,
			'height' => $height
		);
	}
}

// Utility functions specific to phpbb

/**
 * @param int $percent
 *
 * @return int
 */
function percent_to_px($percent)
{
	return (int) (11 * ((int) $percent / 100.0));
}

/**
 * Normalize BBC
 *
 * @param string $message
 *
 * @return mixed|string
 */
function phpbb_replace_bbc($message)
{
	$message = preg_replace(
		array(
			'~\[quote=&quot;(.+?)&quot;\:(.+?)\]~is',
			'~\[quote\:(.+?)\]~is',
			'~\[/quote\:(.+?)\]~is',
			'~\[b\:(.+?)\]~is',
			'~\[/b\:(.+?)\]~is',
			'~\[i\:(.+?)\]~is',
			'~\[/i\:(.+?)\]~is',
			'~\[u\:(.+?)\]~is',
			'~\[/u\:(.+?)\]~is',
			'~\[url\:(.+?)\]~is',
			'~\[/url\:(.+?)\]~is',
			'~\[url=(.+?)\:(.+?)\]~is',
			'~\[/url\:(.+?)\]~is',
			'~\<a(.+?) href="(.+?)">(.+?)</a>~is',
			'~\[img\:(.+?)\]~is',
			'~\[/img\:(.+?)\]~is',
			'~\[size=(.+?)\:(.+?)\]~is',
			'~\[/size\:(.+?)?\]~is',
			'~\[color=(.+?)\:(.+?)\]~is',
			'~\[/color\:(.+?)\]~is',
			'~\[code=(.+?)\:(.+?)\]~is',
			'~\[code\:(.+?)\]~is',
			'~\[/code\:(.+?)\]~is',
			'~\[list=(.+?)\:(.+?)\]~is',
			'~\[list\:(.+?)\]~is',
			'~\[/list\:(.+?)\]~is',
			'~\[\*\:(.+?)\]~is',
			'~\[/\*\:(.+?)\]~is',
			'~\<img src=\"{SMILIES_PATH}/(.+?)\" alt=\"(.+?)\" title=\"(.+?)\" /\>~is',
		),
		array(
			'[quote author="$1"]',
			'[quote]',
			'[/quote]',
			'[b]',
			'[/b]',
			'[i]',
			'[/i]',
			'[u]',
			'[/u]',
			'[url]',
			'[/url]',
			'[url=$1]',
			'[/url]',
			'[url=$2]$3[/url]',
			'[img]',
			'[/img]',
			'[size=' . percent_to_px("\1") . 'px]',
			'[/size]',
			'[color=$1]',
			'[/color]',
			'[code=$1]',
			'[code]',
			'[/code]',
			'[list type=$1]',
			'[list]',
			'[/list]',
			'[li]',
			'[/li]',
			'$2',
		), $message);

	$message = preg_replace('~\[size=(.+?)px\]~is', "[size=" . ('\1' > '99' ? 99 : '"\1"') . "px]", $message);

	$message = strtr($message, array(
		'[list type=1]' => '[list type=decimal]',
		'[list type=a]' => '[list type=lower-alpha]',
	));

	return stripslashes($message);
}

function userDataDefine(&$row, $db, $config)
{
	static $board_timezone = null, $avatar_dir, $attachment_type, $phpbb_avatar_upload_path, $phpbb_avatar_salt;

	// Try to only do this collection once
	if (!isset($board_timezone))
	{
		$request2 = $db->query("
			SELECT 
			    config_value
			FROM {$config->from_prefix}config
			WHERE config_name = 'board_timezone'
			LIMIT 1"
		);
		list ($board_timezone) = $db->fetch_row($request2);
		$db->free_result($request2);

		// Find out where uploaded avatars go
		$request2 = $db->query("
			SELECT 
				value
			FROM {$config->to_prefix}settings
			WHERE variable = 'custom_avatar_dir'
			LIMIT 1"
		);
		list ($avatar_dir) = $db->fetch_row($request2);
		$attachment_type = '1';
		$db->free_result($request2);

		// Not custom so ...
		if (empty($avatar_dir))
		{
			$request2 = $db->query("
				SELECT 
					value
				FROM {$config->to_prefix}settings
				WHERE variable = 'attachmentUploadDir'
				LIMIT 1");
			list ($avatar_dir) = $db->fetch_row($request2);
			$attachment_type = '0';
			$db->free_result($request2);
		}

		$request2 = $db->query("
			SELECT 
				config_value
			FROM {$config->from_prefix}config
			WHERE config_name = 'avatar_path'
			LIMIT 1");
		$temp = $db->fetch_assoc($request2);
		$phpbb_avatar_upload_path = $_POST['path_from'] . '/' . $temp['config_value'];
		$db->free_result($request2);

		$request2 = $db->query("
			SELECT 
				config_value
			FROM {$config->from_prefix}config
			WHERE config_name = 'avatar_salt'
			LIMIT 1");
		$temp = $db->fetch_assoc($request2);
		$phpbb_avatar_salt = $temp['config_value'];
		$db->free_result($request2);
	}

	// Fix signatures
	$row['signature'] = phpbb_replace_bbc(unParse($row['signature'], $row['user_sig_bbcode_uid']));
	unset($row['user_sig_bbcode_uid']);

	// Convert time zones to time offset in hours
	$dt = new \DateTime('now', new \DateTimeZone($board_timezone));
	$offset_from = $dt->getOffset();
	$timestamp = $dt->getTimestamp();
	$offset_to = $dt->setTimezone(new \DateTimezone($row['time_offset'] ?? $board_timezone))->setTimestamp($timestamp)->getOffset();
	$row['time_offset'] = $offset_to / 3600 - $offset_from / 3600;

	// Determine Avatars
	//	AVATAR_UPLOAD	=> 'avatar.driver.upload', or 1
	//	AVATAR_REMOTE	=> 'avatar.driver.remote', or 2
	//	AVATAR_GALLERY	=> 'avatar.driver.local', or 3
	if (empty($row['user_avatar_type']))
	{
		$row['avatar'] = '';
	}
	elseif (($row['user_avatar_type'] === 'avatar.driver.upload' || $row['user_avatar_type'] === '1')
		&& !empty($row['avatar']))
	{
		// If the avatar type is uploaded, copy avatar with the correct name.
		$phpbb_avatar_ext = substr(strchr($row['avatar'], '.'), 1);
		$elk_avatar_filename = 'avatar_' . $row['id_member'] . strrchr($row['avatar'], '.');

		if (file_exists($phpbb_avatar_upload_path . '/' . $phpbb_avatar_salt . '_' . $row['id_member'] . '.' . $phpbb_avatar_ext))
		{
			@copy($phpbb_avatar_upload_path . '/' . $phpbb_avatar_salt . '_' . $row['id_member'] . '.' . $phpbb_avatar_ext, $avatar_dir . '/' . $elk_avatar_filename);
		}
		else
		{
			@copy($phpbb_avatar_upload_path . '/' . $row['avatar'], $avatar_dir . '/' . $elk_avatar_filename);
		}

		$id = $row['id_member'];
		$db_filename = substr(addslashes($elk_avatar_filename), 0, 255);
		$db->query("INSERT INTO {$config->to_prefix}attachments
			(id_msg, id_member, filename, attachment_type)
			VALUES(0, '$id', '$db_filename', '$attachment_type')"
		);

		$row['avatar'] = '';
	}
	elseif ($row['user_avatar_type'] === 'avatar.driver.local' || $row['user_avatar_type'] === '3')
	{
		$row['avatar'] = substr('gallery/' . $row['avatar'], 0, 255);
	}

	unset($row['user_avatar_type']);
}

function unParse($message, $bbcode_uid = null)
{
	// @todo how should we handle phpbb3 bbcode which uses s9e\TextFormatter
	// a simple "[b]My[/b] Signature" will be saved in the db as
	// "<r><B><s>[b]</s>My<e>[/b]</e></B> Signature</r>"
	// Is it better to leave it alone and use a separate utility to convert the db after ??
	if (preg_match('#^<[rt][ >]#', $message))
	{
		$message = html_entity_decode(strip_tags($message), ENT_QUOTES, 'UTF-8');
		$message = htmlspecialchars($message, ENT_COMPAT);

		$message = str_replace("\r\n", '<br />', $message);
	}
	else
	{
		if ($bbcode_uid)
		{
			$match = array("[/*:m:$bbcode_uid]", ":u:$bbcode_uid", ":o:$bbcode_uid", ":$bbcode_uid");
			$replace = array('', '', '', '');
		}
		else
		{
			$match = array("\n", "\r\n");
			$replace = array('<br />', '<br />');
		}
		$message = str_replace($match, $replace, $message);

		$match = array(
			'#<!\-\- e \-\-><a href="mailto:(.*?)">.*?</a><!\-\- e \-\->#',
			'#<!\-\- l \-\-><a (?:class="[\w-]+" )?href="(.*?)(?:(&amp;|\?)sid=[0-9a-f]{32})?">.*?</a><!\-\- l \-\->#',
			'#<!\-\- ([mw]) \-\-><a (?:class="[\w-]+" )?href="http://(.*?)">\2</a><!\-\- \1 \-\->#',
			'#<!\-\- ([mw]) \-\-><a (?:class="[\w-]+" )?href="(.*?)">.*?</a><!\-\- \1 \-\->#',
			'#<!\-\- s(.*?) \-\-><img src="\{SMILIES_PATH\}\/.*? \/><!\-\- s\1 \-\->#',
			'#<!\-\- .*? \-\->#s',
			'#<.*?>#s',
		);
		$replace = array('\1', '\1', '\2', '\2', '\1', '', '');
		$message = preg_replace($match, $replace, $message);
	}

	return $message;
}

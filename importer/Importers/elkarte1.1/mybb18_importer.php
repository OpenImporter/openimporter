<?php
/**
 * @name      OpenImporter
 * @copyright OpenImporter contributors
 * @license   BSD https://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 */

/**
 * Class mybb18
 * Settings for the MyBB 1.8 system.
 */
class mybb18 extends Importers\AbstractSourceImporter
{
	protected $setting_file = '/inc/config.php';

	public function getName()
	{
		return 'MyBB 1.8';
	}

	public function getVersion()
	{
		return 'ElkArte 1.1';
	}

	public function getPrefix()
	{
		global $config;

		return '`' . $this->getDbName() . '`.' . $config['database']['table_prefix'];
	}

	public function getDbName()
	{
		global $config;

		return $config['database']['database'];
	}

	public function getTableTest()
	{
		return 'users';
	}

	/**
	 * Copy attachment files
	 *
	 * @param string $dir
	 * @param array $row
	 * @param int $id_attach
	 * @param string $destination_path
	 * @param bool $thumb
	 *
	 * @return array
	 */
	public function mybb_copy_files($dir, $row, $id_attach, $destination_path, $thumb = false)
	{
		// Some extra details
		list($ext, $basename, $mime_type) = attachment_type($row['filename']);

		// Prepare for the copy
		$file = $thumb ? $row['thumbnail'] : $row['attachname'];
		$source = $dir . '/' . $file;
		$file_hash = createAttachmentFilehash($file);
		$destination = $destination_path . '/' . $id_attach . '_' . $file_hash . '.elk';
		$width = 0;
		$height = 0;
		$type = 0;

		// Copy it over
		copy_file($source, $destination);

		// If an image, then make sure it has legit width/height
		if (!empty($ext))
		{
			list ($width, $height) = getimagesize($destination);
			if (empty($width))
			{
				$width = 0;
				$height = 0;
			}
			else
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
			'height' => $height,
		);
	}
}

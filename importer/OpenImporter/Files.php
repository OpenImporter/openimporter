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

class Files
{
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
	public static function copy_file($source, $destination)
	{
		Files::create_folders_recursive(dirname($destination));

		if (is_file($source))
		{
			copy($source, $destination);
			return false;
		}
		return true;
	}

	/**
	 * @todo Apparently unused
	 */
	public static function copy_dir_recursive($source, $destination)
	{
		$source = rtrim($source, '\\/') . DIRECTORY_SEPARATOR;
		$destination = rtrim($destination, '\\/') . DIRECTORY_SEPARATOR;
		if (!file_exists($source))
			return;
		$dir = opendir($source);
		Files::create_folders_recursive($destination);
		while ($file = readdir($dir))
		{
			if ($file == '.' || $file == '..')
				continue;

			if (is_dir($source . $file))
				Files::copy_dir_recursive($source . $file, $destination . $file);
			else
				copy($source . $file, $destination . $file);
		}
	}

	public static function get_files_recursive($base)
	{
		$files = array();
		$base = rtrim($base, '\\/') . DIRECTORY_SEPARATOR;

		if (!file_exists($base))
			return $files;

		$dir = opendir($base);

		while ($file = readdir($dir))
		{
			if ($file == '.' || $file == '..')
				continue;

			if (is_dir($base . $file))
				$files = array_merge($files, Files::get_files_recursive($base . $file));
			else
				$files[] = $base . $file;
		}

		return $files;
	}

	public static function create_folders_recursive($path)
	{
		$parent = dirname($path);

		if (!file_exists($parent))
			Files::create_folders_recursive($parent);

		if (!file_exists($path))
			@mkdir($path, 0755);
	}

	/**
	 * @todo Apparently unused
	 *
	 * function copy_dir copies a directory
	 * @param string $source
	 * @param string $dest
	 * @return type
	 */
	public static function copy_dir($source, $dest)
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
				Files::copy_dir($source . DIRECTORY_SEPARATOR . $file, $dest . DIRECTORY_SEPARATOR . $file);
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
}
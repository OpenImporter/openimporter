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

/**
 * Class with a bunch of static methods to deal with copying files and all that.
 * @todo consider using:
 *    http://symfony.com/components/Filesystem
 *    http://symfony.com/components/Finder
 */
class Files
{
	/**
	 * Helper function, simple copies a file from a source to a destination.
	 * If necessary creates the whole destination directory structure.
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
			return copy($source, $destination);
		}
		return false;
	}

	/**
	 * Reads all the files in a directory recursively.
	 *
	 * @param string $base The directory to start from
	 * @return string[] Full path of all the files.
	 */
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

	/**
	 * Creates a directory. If parents do not exist try to create them as well.
	 *
	 * @param string $path The full directory path
	 */
	public static function create_folders_recursive($path)
	{
		$parent = dirname($path);

		if (!file_exists($parent))
			Files::create_folders_recursive($parent);

		if (!file_exists($path))
			@mkdir($path, 0755);
	}
}
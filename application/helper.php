<?php
/**
 * UCOZ to Joomla data converter
 *
 * @copyright  Copyright (C) 2019 Libra.ms. All rights reserved.
 * @license    GNU General Public License version 2 or later
 * @url        http://киноархив.com
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;

/**
 * Helper class.
 *
 * @since  0.1
 */
class ConverterHelper
{
	/**
	 * Load and clean backup file.
	 *
	 * @param   string   $file    File.
	 * @param   boolean  $extra   Extra param. Use while processing news, loads, publications and blog. Replace Ucoz new
	 *                            line(backslash + \n) by tag <newline> for avoid bugs and to replace later for
	 *                            normal \n sybmol.
	 * @param   boolean  $raw     Return just file content.
	 *
	 * @return  mixed  False if file inaccessible, array with rows or file content.
	 * @since   0.1
	 */
	public static function loadBackupFile($file, $extra = false, $raw = false)
	{
		clearstatcache();
		$file = Path::clean($file);

		if (is_file($file))
		{
			$content = file_get_contents($file);

			if ($extra)
			{
				$content = str_replace("\\\n", "<newline>", $content);
			}

			if (!$raw)
			{
				$content = explode("\n", $content);

				// Remove empty lines and reindex array.
				$content = array_values(array_filter($content));
			}

			return $content;
		}

		return false;
	}

	/**
	 * Get assoc array of old data from Ucoz and new for Joomla.
	 *
	 * @param   string  $file   File.
	 *
	 * @return  array
	 * @since   0.1
	 */
	public static function getAssocData($file)
	{
		$list = array();
		$file = Path::clean($file);

		if (is_file($file))
		{
			$content = file_get_contents($file);

			if ($content !== false)
			{
				$list = json_decode($content, true);
			}
		}
		else
		{
			$content = '';

			if (File::write($file, $content) === false)
			{
				Log::add('Could not save file ' . $file, Log::ERROR, 'converter');

				echo 'Could not save file ' . $file . "\n";
				jexit();
			}
		}

		return $list;
	}

	/**
	 * Save assoc array of data to JSON.
	 *
	 * @param   string  $file   File.
	 * @param   array   $data   Array with data.
	 *
	 * @return  boolean
	 * @since   0.1
	 */
	public static function saveAssocData($file, $data)
	{
		$file = Path::clean($file);

		if (file_put_contents($file, json_encode($data)))
		{
			return true;
		}

		return false;
	}

	/**
	 * Load converter configuration.
	 *
	 * @return  object
	 * @since   0.1
	 */
	public static function loadConfig()
	{
		$registry = new Registry;
		$config   = FOFUtilsIniParser::parse_ini_file(dirname(__DIR__) . '/config.ini', false);
		$config   = $registry->loadArray($config);

		return $config;
	}
}

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
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;
use Joomla\String\StringHelper;

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

	/**
	 * Strip introtext from fulltext to avoid text duplicates while view a full article.
	 *
	 * @param   string  $intro    Introtext.
	 * @param   string  $full     Fulltext.
	 *
	 * @return  string  Return fulltext w/o intotext.
	 * @since   0.1
	 */
	public static function removeIntro($intro, $full)
	{
		return StringHelper::str_ireplace($intro, '', $full);
	}

	/**
	 * Generate UID.
	 *
	 * @param   integer  $lenght   Length.
	 *
	 * @return  string
	 * @since   0.1
	 * @throws  Exception
	 */
	public static function generateUID($lenght = 12)
	{
		if (function_exists('random_bytes'))
		{
			$bytes = random_bytes(ceil($lenght / 2));
		}
		elseif (function_exists('openssl_random_pseudo_bytes'))
		{
			$bytes = openssl_random_pseudo_bytes(ceil($lenght / 2));
		}
		else
		{
			throw new Exception('No cryptographically secure random function available');
		}

		$uid = substr(bin2hex($bytes), 0, $lenght);

		return $uid;
	}

	/**
	 * Get list of folders with attached images.
	 *
	 * @param   string  $path   Image path.
	 *
	 * @return  array|boolean   Boolean false if folder inaccessible.
	 * @since   0.1
	 */
	public static function listFolders($path)
	{
		$path = Path::clean($path);

		if (is_dir($path))
		{
			$paths = Folder::folders($path, '.', false, true);
		}
		else
		{
			$paths = false;
		}

		return $paths;
	}

	/**
	 * Replace spoiler.
	 *
	 * @param   string  $text      Spoiler text.
	 * @param   string  $pattern   Pattern.
	 *
	 * @return  string
	 * @since   0.1
	 */
	public static function replaceSpoiler($text, $pattern)
	{
		return preg_replace_callback(
			$pattern,
			function ($matches) {
				$accordionId    = 'ac_' . md5(microtime() . uniqid(mt_rand(), true));
				$spoilerId      = 'sp_' . md5(microtime() . uniqid(mt_rand(), true));
				$accordionGroup = '<div class="accordion" id="' . $accordionId . '">
					<div class="accordion-group">
						<div class="accordion-heading">
							<a href="#' . $spoilerId . '" class="accordion-toggle" data-toggle="collapse"
							   data-parent="#' . $accordionId . '">' . $matches[4] . '</a>
						</div>
						<div id="' . $spoilerId . '" class="accordion-body collapse">
							<div class="accordion-inner">' . $matches[2] . '</div>
						</div>
					</div>
				</div>' . "\n";

				return $accordionGroup;
			},
			$text
		);
	}

	/**
	 * Get a new category ID from Joomla by old category ID from Ucoz.
	 *
	 * @param   integer  $category   Ucoz category ID.
	 * @param   integer  $default    Default category ID from Joomla.
	 *
	 * @return  integer
	 * @since   0.1
	 */
	public static function getCategory($category, $default)
	{
		// Get the default Joomla category ID for uncategorised articles.
		if ($category <= 0)
		{
			// Try to get default category ID from file.
			$defaultCategory = $default;

			if (!empty($defaultCategory))
			{
				$category = $defaultCategory;
			}
			else
			{
				$db    = Factory::getDbo();
				$query = $db->getQuery(true)
					->select($db->quoteName('id'))
					->from($db->quoteName('#__categories'))
					->where($db->quoteName('extension') . " = 'com_content' AND " . $db->quoteName('path') . " = 'uncategorised'");

				$db->setQuery($query);

				try
				{
					$category = $db->loadResult();
				}
				catch (RuntimeException $e)
				{
					echo $e->getMessage();
				}
			}
		}

		return (int) $category;
	}
}

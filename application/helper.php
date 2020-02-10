<?php
/**
 * UCOZ to Joomla data converter
 *
 * @copyright  Copyright (C) 2019 Libra.ms. All rights reserved.
 * @license    GNU General Public License version 2 or later
 * @url        http://киноархив.com
 */

defined('_JEXEC') or die;

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

		try
		{
			$file = Path::clean($file);
		}
		catch (UnexpectedValueException $e)
		{
			echo $e->getMessage() . "\n";

			return false;
		}

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
				jexit("Could not save file $file\n");
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
	 * @return  Registry
	 * @since   0.1
	 */
	public static function loadConfig()
	{
		require_once dirname(__DIR__) . '/config.php';

		$registry = new Registry;
		$config   = new ConverterConfig;

		// Process variables from repeatable fields
		if (isset($config->userfields) && !empty($config->userfields))
		{
			$config->userfields = json_decode($config->userfields);
			$config->userfields = array_combine($config->userfields->userfields_pos, $config->userfields->userfields_id);
		}

		if (isset($config->usergroups) && !empty($config->usergroups))
		{
			$config->usergroups = json_decode($config->usergroups);
			$config->usergroups = array_combine($config->usergroups->usergroups_ucoz, $config->usergroups->usergroups_joomla);
		}

		return $registry->loadObject($config);
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

		return substr(bin2hex($bytes), 0, $lenght);
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
		try
		{
			$path = Path::clean($path);
		}
		catch (UnexpectedValueException $e)
		{
			echo $e->getMessage() . "\n";

			return false;
		}

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
	 * Load categories from source.
	 *
	 * @param   string    $type     Content type. Can be 'blog', 'publ', 'loads', 'news'.
	 * @param   Registry  $config   Converter config object.
	 *
	 * @return  array
	 * @since   0.1
	 */
	public static function loadCategories($type, $config)
	{
		$categories = array();

		if ((int) $config->get('fromCategoryImports') === 0)
		{
			$_categories = $config->get('categoriesAssoc');

			if (!empty($_categories))
			{
				$_categories = json_decode($_categories);
				$categories  = array_combine($_categories->categoriesUcoz, $_categories->categoriesJoomla);
			}
		}
		elseif ((int) $config->get('fromCategoryImports') === 1)
		{
			if (is_file(Path::clean(JPATH_ROOT . '/cli/ucoz_converter/imports/categories_import.json')))
			{
				$categories = self::getAssocData(JPATH_ROOT . '/cli/ucoz_converter/imports/categories_import.json');

				if (array_key_exists($type, $categories))
				{
					$categories = $categories[$type];
				}
			}
		}

		return $categories;
	}

	/**
	 * Get Joomla category ID by Ucoz category ID.
	 *
	 * @param   integer   $id       Ucoz category ID.
	 * @param   string    $type     Content type. Can be 'blog', 'publ', 'loads', 'news'.
	 * @param   Registry  $config   Converter config object.
	 *
	 * @return  integer
	 * @since   0.1
	 */
	public static function getCategory($id, $type, $config)
	{
		$categories = self::loadCategories($type, $config);

		// Default category ID for Uncategorised items in com_content. Hardcoded in Joomla installation package.
		$category = 2;

		if (array_key_exists($id, $categories))
		{
			$category = $categories[$id];
		}
		elseif ($config->get('blogDefaultCategoryId') > 0)
		{
			$category = $config->get('blogDefaultCategoryId');
		}

		return (int) $category;
	}

	/**
	 * Generate alias.
	 *
	 * @param   array  $data   Item data.
	 *
	 * @return  string
	 * @since   0.1
	 */
	public static function generateAlias($data)
	{
		if (JFactory::getConfig()->get('unicodeslugs') == 1)
		{
			$data['alias'] = JFilterOutput::stringURLUnicodeSlug($data['title']);
		}
		else
		{
			$data['alias'] = JFilterOutput::stringURLSafe($data['title']);
		}

		$table = JTable::getInstance('Content', 'JTable');

		if ($table->load(array('alias' => $data['alias'], 'catid' => $data['catid'])))
		{
			$msg = JText::_('COM_CONTENT_SAVE_WARNING') . "\n";
		}

		/** @noinspection  PhpUnusedLocalVariableInspection */
		list($title, $alias) = self::generateNewTitle($data['catid'], $data['alias'], $data['title']);
		$data['alias'] = $alias;

		if (isset($msg))
		{
			echo $msg;
		}

		return $alias;
	}

	/**
	 * Method to change the title & alias.
	 *
	 * @param   integer  $categoryID   The id of the category.
	 * @param   string   $alias        Alias.
	 * @param   string   $title        Title.
	 *
	 * @return	array  Contains the modified title and alias.
	 *
	 * @since	0.1
	 */
	public static function generateNewTitle($categoryID, $alias, $title)
	{
		// Alter the title & alias
		$table = JTable::getInstance('Content', 'JTable');

		while ($table->load(array('alias' => $alias, 'catid' => $categoryID)))
		{
			$title = StringHelper::increment($title);
			$alias = StringHelper::increment($alias, 'dash');
		}

		return array($title, $alias);
	}

	/**
	 * Replace smiles.
	 *
	 * @param   string    $text     Content where to replace.
	 * @param   Registry  $config   Converter config object.
	 *
	 * @return  string
	 * @since   0.1
	 */
	public static function replaceSmiles($text, $config)
	{
		$imgUrlSmiles = $config->get('siteURL') . $config->get('imgPathSmiles');
		$pattern  = '#<img[^>]+src="(.+?)\/sm\/(.+?)\/(.+?).gif"[^>]+>#';
		$replace  = '<img src="' . $imgUrlSmiles . '$3.gif" alt="$3" align="absmiddle" border="0">';

		return preg_replace($pattern, $replace, $text);
	}

	/**
	 * Replace Ucoz spolier by Bootstrap accordion(v. 2.3.2) which included in Joomla 3.x.
	 * Each spolier will be replaced by new accordion group.
	 *
	 * @param   string  $text      Spoiler text.
	 * @param   string  $pattern   Pattern.
	 *
	 * @return  string
	 * @since   0.1
	 */
	public static function replaceSpoiler($text, $pattern = '')
	{
		if (empty($pattern))
		{
			$pattern = '@<!--uSpoiler-->(.+?)<!--ust-->(.*?)<!--\/ust-->(.*?)<!--usn\(=(.+?)\)-->(.+?)<!--\/uSpoiler-->@u';
		}

		return preg_replace_callback(
			$pattern,
			function ($matches) {
				$accordionId = 'ac_' . md5(microtime() . uniqid(mt_rand(), true));
				$spoilerId   = 'sp_' . md5(microtime() . uniqid(mt_rand(), true));

				return '<div class="accordion" id="' . $accordionId . '">
					<div class="accordion-group">
						<div class="accordion-heading">
							<a href="#' . $spoilerId . '" class="accordion-toggle" data-toggle="collapse"
							   data-parent="#' . $accordionId . '">' . $matches[4] . '</a>
						</div>
						<div id="' . $spoilerId . '" class="accordion-body collapse">
							<div class="accordion-inner">' . $matches[2] . '</div>
						</div>
					</div>
				</div><br />';
			},
			$text
		);
	}

	/**
	 * Replace site URL. Require to replace non-https URL in images or links.
	 * Replace more URLs. Sometimes images or other content can be placed at root directory. So if we want to move all
	 * these folders to, e.g. images we need to replace URLs to new location.
	 *
	 * @param   string  $text   Content where to replace.
	 * @param   string  $urls   JSON object with URLs.
	 *
	 * @return  string
	 * @since   0.1
	 */
	public static function replaceUrls($text, $urls)
	{
		if (!empty($urls))
		{
			$urls = json_decode($urls);

			if (property_exists($urls, 'oldUrl') && property_exists($urls, 'newUrl'))
			{
				$text = str_replace($urls->oldUrl, $urls->newUrl, $text);
			}
		}

		return $text;
	}
}

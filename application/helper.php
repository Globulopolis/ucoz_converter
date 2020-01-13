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
	 * Save converter configuration.
	 *
	 * @param   Registry  $config  A Registry object containing config data.
	 *
	 * @return  boolean
	 * @since   0.1
	 */
	public static function saveConfig(Registry $config)
	{
		$file = dirname(__DIR__) . '/config.php';
		$configuration = $config->toString('PHP', array('class' => 'JConfig', 'closingtag' => false));

		if (!File::write($file, $configuration))
		{
			throw new RuntimeException(JText::_('COM_CONFIG_ERROR_WRITE_FAILED'));
		}

		return true;
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
	 * Load categories from source.
	 *
	 * @param   string    $type    Content type. Can be 'blog', 'publ', 'loads', 'news'.
	 * @param   Registry  $config  Converter config object.
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
				$categories  = array_combine($_categories->categories_ucoz, $_categories->categories_joomla);
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
	 * @param   integer   $id           Ucoz category ID.
	 * @param   string    $type         Content type. Can be 'blog', 'publ', 'loads', 'news'.
	 * @param   Registry  $config       Converter config object.
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
	 * @param   integer  $categoryID    The id of the category.
	 * @param   string   $alias         The alias.
	 * @param   string   $title         The title.
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
	 * Replace site URL. Require to replace non-https URL in images or links.
	 * Replace more URLs. Sometimes images or other content can be placed at root directory. So if we want to move all
	 * these folders to, e.g. images we need to replace URLs to new location.
	 *
	 * @param   string  $text     Content where to replace.
	 * @param   object  $config   Converter config object.
	 *
	 * @return  string
	 * @since   0.1
	 */
	public static function replaceUrls($text, $config)
	{
		if ($config['replaceOldUrls'] == 1)
		{
			$text = str_ireplace(explode(',', $config['oldSiteURL']), $config['siteURL'], $text);
		}

		if ($config['replaceUrlsExtra'] == 1)
		{
			$search  = explode(',', $config['replaceUrlsExtraList']);
			$replace = explode(',', $config['replaceUrlsExtraListBy']);

			if (!empty($search) && !empty($replace))
			{
				$text = str_replace($search, $replace, $text);
			}
		}

		return $text;
	}
}

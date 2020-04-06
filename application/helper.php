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
		if (file_put_contents(Path::clean($file), json_encode($data)))
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
		// Default category ID for Uncategorised items in com_content. Hardcoded in Joomla installation package.
		$category = 2;

		if ($config->get('fromCategoryImports') == 0)
		{
			$_categories = $config->get('categoriesAssoc');

			if (!empty($_categories))
			{
				$_categories = json_decode($_categories);
				$categories  = array_combine($_categories->categoriesUcoz, $_categories->categoriesJoomla);

				if (array_key_exists($id, $categories))
				{
					$category = $categories[$id];
				}
			}
		}
		elseif ($config->get('fromCategoryImports') == 1)
		{
			if (is_file(Path::clean(JPATH_ROOT . '/ucoz_converter/imports/categories_import.json')))
			{
				$categories = self::getAssocData(JPATH_ROOT . '/ucoz_converter/imports/categories_import.json');

				if (array_key_exists($type, $categories))
				{
					if (array_key_exists($id, $categories[$type]))
					{
						$category = $categories[$type][$id];
					}
				}
			}
		}
		elseif ($config->get('fromCategoryImports') == 2)
		{
			$category = $config->get($type . 'DefaultCategoryId');
		}

		return (int) $category;
	}

	/**
	 * Method to change the title & alias.
	 *
	 * @param   integer  $categoryID   The id of the category.
	 * @param   string   $alias        Alias.
	 * @param   string   $title        Title.
	 *
	 * @return	array   Contains the modified title and alias.
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
	 * Replace Ucoz <!--IMG--> tag with link by lightbox.
	 *
	 * @param   string  $text      Content where to replace.
	 * @param   string  $type      Content type. Can be blog, news, load, publ.
	 * @param   string  $url       URL to images.
	 * @param   string  $siteUrl   Old site URL.
	 *
	 * @return  string
	 * @since   0.1
	 */
	public static function replaceImageTagByLightbox($text, $type, $url, $siteUrl)
	{
		if ($type == 'blog')
		{
			$urlPart = '_bl';
		}
		elseif ($type == 'news')
		{
			$urlPart = '_nw';
		}
		elseif ($type == 'publ')
		{
			$urlPart = '_pu';
		}
		elseif ($type == 'load')
		{
			$urlPart = '_ld';
		}
		else
		{
			return $text;
		}

		$pattern = '#<!--IMG[^>]+><a[^>]+href="(.+?)\/' . $urlPart . '\/(.+?)\/(.+?)"[^>]*><img[^>]+style="(.+?)"\s+src="(.+?)\/'
			. $urlPart . '\/(.+?)\/(.+?)"[^>]+><\/a><!--IMG[^>]+>#u';

		// Perform match and replace URL only for current site, because articles can have a links to other Ucoz blogs.
		preg_match_all($pattern, $text, $matches);

		foreach ($matches[1] as $href)
		{
			if (stripos($href, $siteUrl) !== false)
			{
				$replace = '<a href="' . $url . '/' . $urlPart . '/$2/$3" class="lightbox"><img src="' . $url . '/'
					. $urlPart . '/$6/$7" alt="" style="$4" /></a>';
			}
			else
			{
				$replace = '<a href="$1/' . $urlPart . '/$2/$3" class="lightbox"><img src="$5/' . $urlPart . '/$6/$7" alt="" style="$4" /></a>';
			}

			$text = preg_replace($pattern, $replace, $text, 1);
		}

		return $text;
	}

	/**
	 * Replace Ucoz <!--IMG--> tag by native HTML <img/>.
	 *
	 * @param   string  $text      Content where to replace.
	 * @param   string  $type      Content type. Can be blog, news, load, publ.
	 * @param   string  $url       URL to images.
	 * @param   string  $siteUrl   Old site URL.
	 *
	 * @return  string
	 * @since   0.1
	 */
	public static function replaceImageTagByHtmlImage($text, $type, $url, $siteUrl)
	{
		if ($type == 'blog')
		{
			$urlPart = '_bl';
		}
		elseif ($type == 'news')
		{
			$urlPart = '_nw';
		}
		elseif ($type == 'publ')
		{
			$urlPart = '_pu';
		}
		elseif ($type == 'load')
		{
			$urlPart = '_ld';
		}
		else
		{
			return $text;
		}

		$pattern = '#<!--IMG[^>]+><img[^>]+style="(.+?)"\s+src="(.+?)\/' . $urlPart . '\/(.+?)\/(.+?)"[^>]+><!--IMG[^>]+>#u';

		// Perform match and replace URL only for current site, because articles can have a links to other Ucoz blogs.
		preg_match_all($pattern, $text, $matches);

		foreach ($matches[1] as $href)
		{
			if (stripos($href, $siteUrl) !== false)
			{
				$replace = '<img src="' . $url . '/' . $urlPart . '/$3/$4" style="$1" /></a>';
			}
			else
			{
				$replace = '<img src="$2/' . $urlPart . '/$3/$4" style="$1" />';
			}

			$text = preg_replace($pattern, $replace, $text, 1);
		}

		return $text;
	}

	/**
	 * Replace smiles.
	 *
	 * @param   string  $text   Content where to replace.
	 * @param   string  $url    Url to smiles.
	 *
	 * @return  string
	 * @since   0.1
	 */
	public static function replaceSmiles($text, $url)
	{
		$pattern = '#<img[^>]+src="(.+?)\/sm\/(.+?)\/(.+?).gif"[^>]+>#';
		$replace = '<img src="' . $url . '/$3.gif" alt="$3" align="absmiddle" border="0">';

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
				foreach ($urls->oldUrl as $i => $url)
				{
					//$text = preg_replace('#' . preg_quote($url) . '#', $urls->newUrl[$i], $text, 1);
				}
			}
		}

		return $text;
	}
}

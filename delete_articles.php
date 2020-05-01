<?php
/**
 * UCOZ to Joomla data converter
 *
 * @copyright  Copyright (C) 2020 Libra.ms. All rights reserved.
 * @license    GNU General Public License version 2 or later
 * @url        http://киноархив.com
 */

/**
 * This is a script to delete all imported articles in Joomla which should be called from the
 * command-line, not the web.
 * Example: /path/to/php /path/to/site/ucoz_converter/delete_articles.php --blog
 *
 * Required parameters:
 * --blog - convert blog.
 * --news - convert news.
 * --publ - convert publications.
 * --loads - convert loads.
 */

const _JEXEC = 1;

error_reporting(E_ALL | E_NOTICE);
ini_set('display_errors', 1);

if (PHP_SAPI !== 'cli')
{
	die('This script can run only in CLI mode!');
}

// Load system defines
if (file_exists(dirname(__DIR__) . '/defines.php'))
{
	require_once dirname(__DIR__) . '/defines.php';
}

if (!defined('_JDEFINES'))
{
	define('JPATH_BASE', dirname(__DIR__));
	require_once JPATH_BASE . '/includes/defines.php';
}

require_once JPATH_BASE . '/includes/framework.php';
require_once __DIR__ . '/application/helper.php';

use Joomla\CMS\Cache\Cache;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

// This will prevent 'Failed to start application' error.
try
{
	$app = Factory::getApplication('site');
}
catch (Exception $e)
{
	jexit($e->getMessage());
}

JLoader::register('ContentModelArticle', JPATH_ADMINISTRATOR . '/components/com_content/models/article.php');

/**
 * Class for articles management.
 *
 * @since  0.1
 * @noinspection PhpUnused
 */
Class ConverterDeleteArticles extends JApplicationCli
{
	/**
	 * The context used for the associations table
	 *
	 * @var    string
	 * @since  3.4.4
	 */
	protected $associationsContext = 'com_content.item';

	/**
	 * Delete all previously imported articles.
	 *
	 * @return  void
	 * @since   0.1
	 * @throws  Exception
	 */
	public function doExecute()
	{
		$lang = Factory::getLanguage();
		$lang->load('lib_joomla');
		$lang->load('com_content');

		// Get cmd options
		$opts = getopt("",
			array(
				'blog'   => 'blog::',
				'news'   => 'news::',
				'publ'   => 'publ::',
				'loads'  => 'loads::'
			)
		);

		if (array_key_exists('blog', $opts))
		{
			$type = 'blog';
		}
		elseif (array_key_exists('news', $opts))
		{
			$type = 'news';
		}
		elseif (array_key_exists('publ', $opts))
		{
			$type = 'publ';
		}
		elseif (array_key_exists('loads', $opts))
		{
			$type = 'loads';
		}
		else
		{
			$msg = 'Wrong content type! Available types: --news, --blog, --publ, --loads';
			die($msg . "\n\nExample: /usr/bin/php /path/to/site/ucoz_converter/delete_articles.php --blog\n");
		}

		$articlesIDs = ConverterHelper::getAssocData(__DIR__ . '/imports/' . $type . '_ids.json');

		// Load IDs file.
		if (empty($articlesIDs) || count($articlesIDs) < 1)
		{
			jexit('Could not load file at ' . $type . '_ids.json. Maybe file is empty.' . "\n");
		}

		$model        = new ContentModelArticle;
		$table        = $model->getTable();
		$db           = Factory::getDbo();
		$totalRows    = count($articlesIDs);
		$totalDeleted = 0;
		$totalErrors  = 0;
		$featured     = array();
		$outputLog    = "======= " . date('Y-m-d H:i:s', time()) . " =======\n";

		echo $outputLog;

		foreach ($articlesIDs as $ucozID => $joomlaID)
		{
			if ($table->load($joomlaID))
			{
				// Multilanguage: if associated, delete the item in the _associations table
				if ($this->associationsContext && JLanguageAssociations::isEnabled())
				{
					$query = $db->getQuery(true)
						->select('COUNT(*) as count, ' . $db->quoteName('as1.key'))
						->from($db->quoteName('#__associations') . ' AS as1')
						->join('LEFT', $db->quoteName('#__associations') . ' AS as2 ON ' . $db->quoteName('as1.key') . ' =  ' . $db->quoteName('as2.key'))
						->where($db->quoteName('as1.context') . ' = ' . $db->quote($this->associationsContext))
						->where($db->quoteName('as1.id') . ' = ' . (int) $joomlaID)
						->group($db->quoteName('as1.key'));

					$db->setQuery($query);
					$row = $db->loadAssoc();

					if (!empty($row['count']))
					{
						$query = $db->getQuery(true)
							->delete($db->quoteName('#__associations'))
							->where($db->quoteName('context') . ' = ' . $db->quote($this->associationsContext))
							->where($db->quoteName('key') . ' = ' . $db->quote($row['key']));

						if ($row['count'] > 2)
						{
							$query->where($db->quoteName('id') . ' = ' . (int) $joomlaID);
						}

						$db->setQuery($query);
						$db->execute();
					}
				}

				if (!$table->delete($joomlaID))
				{
					$totalErrors++;
					$msg = 'Article ID: ' . $joomlaID . '. ' . Text::_('JERROR_AN_ERROR_HAS_OCCURRED') . ' ' . $table->getError();
				}
				else
				{
					$featured[] = $joomlaID;
					$totalDeleted++;
					$msg = 'Article with ID: ' . $joomlaID . ' - deleted.' . "\n";
					unset($articlesIDs[$ucozID]);
				}
			}
			else
			{
				$totalErrors++;
				$msg = 'Article ID: ' . $joomlaID . '. ' . Text::_('JERROR_AN_ERROR_HAS_OCCURRED') . ' ' . $table->getError();
			}

			$outputLog .= $msg;

			echo $msg;
		}

		// Remove featured items
		$query = $db->getQuery(true)
			->delete($db->quoteName('#__content_frontpage'))
			->where('content_id IN (' . implode(',', array_values($featured)) . ')');
		$db->setQuery($query);
		$db->execute();

		// Clear the component's cache
		try
		{
			$this->cleanCache('com_content');
			$this->cleanCache('mod_articles_archive');
			$this->cleanCache('mod_articles_categories');
			$this->cleanCache('mod_articles_category');
			$this->cleanCache('mod_articles_latest');
			$this->cleanCache('mod_articles_news');
			$this->cleanCache('mod_articles_popular');
		}
		catch (Exception $e)
		{
			echo $e->getMessage() . "\n";
		}

		ConverterHelper::saveAssocData(__DIR__ . '/imports/' . $type . '_ids.json', $articlesIDs);

		$succMsg = "\n" . 'Total articles: ' . $totalRows . '.' .
			  "\n" . 'Articles deleted: ' . $totalDeleted . '.' .
			  "\n" . 'Errors found: ' . $totalErrors . "\n";
		$outputLog .= $succMsg;

		file_put_contents(__DIR__ . '/imports/' . $type . '_delete.log', $outputLog . "\n\n", FILE_APPEND);

		echo $succMsg;
	}

	/**
	 * Clean the cache
	 *
	 * @param   string   $group     The cache group
	 * @param   integer  $clientID  The ID of the client
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 * @since   0.1
	 */
	private function cleanCache($group = null, $clientID = 0)
	{
		$config = Factory::getConfig();
		$option = 'com_content';

		$options = array(
			'defaultgroup' => $group ?: (isset($option) ? $option : Factory::getApplication()->input->get('option')),
			'cachebase' => $clientID ? JPATH_ADMINISTRATOR . '/cache' : $config->get('cache_path', JPATH_SITE . '/cache'),
			'result' => true,
		);

		/** @var JCacheControllerCallback $cache */
		$cache = Cache::getInstance('callback', $options);
		$cache->clean();

		// Trigger the onContentCleanCache event.
		JEventDispatcher::getInstance()->trigger('onContentCleanCache', $options);
	}
}

JApplicationCli::getInstance('ConverterDeleteArticles')->execute();

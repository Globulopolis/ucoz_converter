<?php
/**
 * UCOZ to Joomla data converter
 *
 * @copyright  Copyright (C) 2019 Libra.ms. All rights reserved.
 * @license    GNU General Public License version 2 or later
 * @url        http://киноархив.com
 */

/**
 * This is a script to convert categories from Ucoz to Joomla which should be called from the command-line, not the web.
 * Example: /usr/bin/php /path/to/site/cli/converter/categories.php
 */

const _JEXEC = 1;

error_reporting(E_ALL | E_NOTICE);
ini_set('display_errors', 1);

if (PHP_SAPI !== 'cli')
{
	die('This script can run only in CLI mode!');
}

// Load system defines
if (file_exists(dirname(dirname(__DIR__)) . '/defines.php'))
{
	require_once dirname(dirname(__DIR__)) . '/defines.php';
}

if (!defined('_JDEFINES'))
{
	define('JPATH_BASE', dirname(dirname(__DIR__)));
	require_once JPATH_BASE . '/includes/defines.php';
}

require_once JPATH_BASE . '/includes/framework.php';
require_once __DIR__ . '/application/helper.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Filesystem\Path;
use Joomla\Filter\InputFilter;

// This line will prevent 'Failed to start application' error.
$app = Factory::getApplication('site');

/**
 * Class for categories.
 *
 * @since  0.1
 */
Class ConverterCategories extends JApplicationCli
{
	/**
	 * Convert categories and save in Joomla database.
	 *
	 * @return  void
	 * @since   0.1
	 * @throws  Exception
	 */
	public function doExecute()
	{
		$execTime  = -microtime(true);

		Log::addLogger(
			array(
				'text_file' => 'categories_import.php'
			),
			Log::ALL, 'converter'
		);

		JLoader::register('CategoriesHelper', JPATH_ADMINISTRATOR . '/components/com_categories/helpers/categories.php');

		$filter = new InputFilter;
		$lang   = Factory::getLanguage();
		$lang->load('lib_joomla');

		$config     = ConverterHelper::loadConfig();
		$backupPath = Path::clean($config->get('backupPath') . '/_s1');
		$loads      = ConverterHelper::loadBackupFile($backupPath . '/ld_ld.txt');
		$news       = ConverterHelper::loadBackupFile($backupPath . '/nw_nw.txt');
		$publ       = ConverterHelper::loadBackupFile($backupPath . '/pu_pu.txt');
		$categories = array();

		if ($loads !== false)
		{
			foreach ($loads as $rows)
			{
				$cols = explode('|', $rows);
				$categories['loads'][$cols[0]] = $cols[5];
			}
		}
		else
		{
			$msg = "Could not load backup file $backupPath/ld_ld.txt";
			Log::add($msg, Log::CRITICAL, 'converter');
			echo "\n$msg\n";
		}

		if ($news !== false)
		{
			foreach ($news as $rows)
			{
				$cols = explode('|', $rows);
				$categories['news'][$cols[0]] = $cols[3];
			}
		}
		else
		{
			$msg = "Could not load backup file $backupPath/nw_nw.txt";
			Log::add($msg, Log::CRITICAL, 'converter');
			echo "\n$msg\n";
		}

		if ($publ !== false)
		{
			foreach ($publ as $rows)
			{
				$cols = explode('|', $rows);
				$categories['publ'][$cols[0]] = $cols[5];
			}
		}
		else
		{
			$msg = "Could not load backup file $backupPath/pu_pu.txt";
			Log::add($msg, Log::CRITICAL, 'converter');
			echo "\n$msg\n";
		}

		$totalRows     = array_sum(array_map('count', $categories));
		$totalImported = 0;
		$totalErrors   = 0;
		$ids           = ConverterHelper::getIDs(__DIR__ . '/imports/categories_import.json');

		foreach ($categories as $type => $titles)
		{
			$totalTitles = count($titles);
			$i = 1;
			echo "===============================\n"
				. "$type\n"
				. "===============================\n";

			foreach ($titles as $key => $category)
			{
				$table                    = array();
				$table['title']           = $filter->clean($category, 'string');
				$table['parent_id']       = 1;
				$table['extension']       = 'com_content';
				$table['language']        = $config->get('categoriesLang');
				$table['published']       = 1;
				$table['params']          = json_encode(array('category_layout' => '', 'image' => ''));
				$table['metadata']        = json_encode(array('author' => '', 'robots' => ''));
				$table['created_user_id'] = (int) $config->get('defaultUserId');

				if (!empty($ids) && @array_key_exists($key, $ids[$type]))
				{
					$table['id'] = $ids[$type][$key];
					$isNew = 0;
				}
				else
				{
					$isNew = 1;
				}

				$catid = CategoriesHelper::createCategory($table);

				if (!empty($catid))
				{
					$ids[$type][$key] = $catid;
					$txt = $isNew ? ' - imported.' : ' - updated.';
					echo $i . ' of ' . $totalTitles . '. Category: "' . $category . $txt . "\n";
					$totalImported++;
				}
				else
				{
					$msg = '. Category: "' . $category . '" - error(possible duplicate title).' . "\n";
					Log::add($msg, Log::CRITICAL, 'converter');
					echo $i . ' of ' . $totalTitles . $msg;
					$totalErrors++;
				}

				$i++;
			}
		}

		// Store two dimensional array with type, Joomla ID, Ucoz ID as JSON.
		// E.g.: array('news' => array('joomla_id' => 'ucoz_id', ...), ...)
		ConverterHelper::saveIDs(__DIR__ . '/imports/categories_import.json', $ids);

		$execTime += microtime(true);
		$execTime = sprintf('%f', $execTime);
		list($sec, $usec) = explode('.', $execTime);

		echo  "\n" . 'Total categories: ' . $totalRows . '.' .
			  "\n" . 'Categories imported: ' . $totalImported . '.' .
			  "\n" . 'Errors found: ' . $totalErrors . '. See logfile at ' .
			  Path::clean(Factory::getConfig()->get('log_path') . '/categories_import.php') .
			  "\n" . 'Took: ' . number_format($sec / 60, 2) . 'min';
	}
}

JApplicationCli::getInstance('ConverterCategories')->execute();

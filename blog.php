<?php
/**
 * UCOZ to Joomla data converter
 *
 * @copyright  Copyright (C) 2020 Libra.ms. All rights reserved.
 * @license    GNU General Public License version 2 or later
 * @url        http://киноархив.com
 */

/**
 * This is a script to convert blogs from Ucoz to Joomla which should be called from the
 * command-line, not the web.
 * Example: /path/to/php /path/to/site/ucoz_converter/blog.php
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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;
use Joomla\Filter\InputFilter;
use Joomla\Image\Image;

// This will prevent 'Failed to start application' error.
try
{
	$app = Factory::getApplication('site');
}
catch (Exception $e)
{
	jexit($e->getMessage());
}

/**
 * Class for blog.
 *
 * @since  0.1
 * @noinspection PhpUnused
 */
Class ConverterBlog extends JApplicationCli
{
	/**
	 * Convert news and import in Joomla database.
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

		$config     = ConverterHelper::loadConfig();
		$backupPath = Path::clean($config->get('backupPath') . '/_s1/blog.txt');
		$content    = ConverterHelper::loadBackupFile($backupPath, true);

		// Load backup file for blog.
		if ($content === false)
		{
			jexit("Could not load backup file at $backupPath\n");
		}

		JLoader::register('ContentModelArticle', JPATH_ADMINISTRATOR . '/components/com_content/models/article.php');
		JLoader::register('ContentTableFeatured', JPATH_ADMINISTRATOR . '/components/com_content/tables/featured.php');

		$filter        = new InputFilter;
		$totalRows     = count($content);
		$totalImported = 0;
		$totalErrors   = 0;
		$articlesIDs   = ConverterHelper::getAssocData(__DIR__ . '/imports/blog_ids.json');
		$outputLog     = "======= " . date('Y-m-d H:i:s', time()) . " =======\n";

		foreach ($content as $i => $line)
		{
			if ($i > 0)
			{
				//break;
			}

			// Replace Ucoz attachments separator by tag
			$line = str_replace('\|', '<attachment_sep>', $line);

			$data     = array();
			$model    = JModelLegacy::getInstance('Article', 'ContentModel');
			$column   = explode('|', $line);
			$msgLine  = ($i + 1) . ' of ' . $totalRows . '. Article: ';
			$images   = (object) array(
				'image_intro'    => '', 'float_intro' => '',    'image_intro_alt' => '',    'image_intro_caption' => '',
				'image_fulltext' => '', 'float_fulltext' => '', 'image_fulltext_alt' => '', 'image_fulltext_caption' => ''
			);
			$urls     = (object) array(
				'urla' => false, 'urlatext' => '', 'targeta' => '',
				'urlb' => false, 'urlbtext' => '', 'targetb' => '',
				'urlc' => false, 'urlctext' => '', 'targetc' => ''
			);
			$attribs  = (object) array(
				'article_layout'            => '', 'show_title' => '',             'link_titles' => '',
				'show_tags'                 => '', 'show_intro' => '',             'info_block_position' => '',
				'info_block_show_title'     => '', 'show_category' => '',          'link_category' => '',
				'show_parent_category'      => '', 'link_parent_category' => '',   'show_associations' => '',
				'show_author'               => '', 'link_author' => '',            'show_create_date' => '',
				'show_modify_date'          => '', 'show_publish_date' => '',      'show_item_navigation' => '',
				'show_icons'                => '', 'show_print_icon' => '',        'show_email_icon' => '',
				'show_vote'                 => '', 'show_hits' => '',              'show_noauth' => '',
				'urls_position'             => '', 'alternative_readmore' => '',   'article_page_title' => '',
				'show_publishing_options'   => '1', 'show_article_options' => '1', 'show_urls_images_backend' => '',
				'show_urls_images_frontend' => ''
			);
			$metadata = (object) array(
				'robots' => 'index, follow', 'author' => '', 'rights' => '', 'xreference' => ''
			);

			$data['title']   = $filter->clean($column[11]);
			$data['catid']   = ConverterHelper::getCategory($column[1], 'blog', $config);
			$date            = sprintf('%04d-%02d-%02d', $column[2], $column[3], $column[4]);
			$data['created'] = $date . ' ' . gmdate("H:i:s", $filter->clean(($column[8] + date('Z')), 'int'));
			$introtext       = $column[12];
			$fulltext        = str_replace('<newline>', "\n", $column[13]);

			// Sometimes introtext can be empty. So check it and if empty - try to create from fulltext.
			if (!empty($introtext))
			{
				$introtext = str_replace('<newline>', "\n", $introtext);
			}
			else
			{
				if ($config->get('introFromFulltext') == 1)
				{
					// Make introtext from fulltext. This preserve html-tags.
					$introtext = JHtml::_('string.truncateComplex', $fulltext, $config->get('blogIntroLimit'));

					// Hide introtext in full article view
					$attribs->show_intro = 0;
				}
			}

			// Process attachments
			if ((int) $config->get('imgAttachCopy') === 1 && !empty($column[15]))
			{
				$files = explode('<attachment_sep>', $column[15]);

				// Process each file
				foreach ($files as $fi => $file)
				{
					$fileInfo = explode('`', $file);

					/**
					 * List of attachment info must contain at least filename and extension. Max 7 values.
					 * 0 - filename; 1 - extension.
					 * 2, 3 - image width, height for fulltext. Optional
					 * 4, 5 - image width, height for introtext. Optional
					 * 6 - description(?). Optional
					 */
					if (count($fileInfo) >= 2)
					{
						$folderId  = substr($column[0], 0, -2);
						$folderId  = $folderId == '' ? 0 : $folderId;
						$srcFolder = $config->get('backupPath') . '/_bl/' . $folderId . '/';
						$dstFolder = $config->get('imgAttachPathBlogDst') . '/' . $folderId . '/';
						$filename  = $fileInfo[0] . '.' . $fileInfo[1];
						$thumbFilename = 's' . $fileInfo[0] . '.' . $fileInfo[1];

						if (is_file($srcFolder . $filename))
						{
							if (!is_dir($dstFolder))
							{
								Folder::create($dstFolder);
							}

							try
							{
								// Copy big image
								File::copy(
									$srcFolder . '/' . $filename,
									$dstFolder . '/' . $filename
								);

								// Copy thumbnail
								if (is_file($srcFolder . $thumbFilename))
								{
									// Copy thumbnail image
									File::copy(
										$srcFolder . '/' . $thumbFilename,
										$dstFolder . '/' . $thumbFilename
									);
								}
								// Create thumbnail image if not exists
								else
								{
									$imgSrcProps    = Image::getImageFileProperties($srcFolder . $filename);
									$imgThumbWidth  = (int) $config->get('imgThumbWidth');
									$imgThumbHeight = (int) ($imgThumbWidth * $fileInfo[3]) / $fileInfo[2];

									// Create thumbnail only if original image width is bigger than set in converter settings.
									if ($imgSrcProps->width >= $imgThumbWidth)
									{
										$image = new Image;
										$image->loadFile($srcFolder . $filename);
										$image->resize($imgThumbWidth, $imgThumbHeight, false, 1);
										$image->toFile($dstFolder . '/s' . $filename, $imgSrcProps->type);
									}
									else
									{
										File::copy($srcFolder . $filename, $dstFolder . '/s' . $filename);
									}
								}
							}
							catch (Exception $e)
							{
								$_fErrorMsg = 'Ucoz article ID: ' . $column[0] . ' - ' . $e->getMessage() . "\n";
								$outputLog  .= $_fErrorMsg;
								echo $_fErrorMsg;
							}
						}
					}
				}
			}

			if (!empty($introtext))
			{
				$introtext = ConverterHelper::replaceImageTagByLightbox($introtext, 'blog', $config->get('imgPathBlog'), $config->get('siteURL'));
				$introtext = ConverterHelper::replaceImageTagByHtmlImage($introtext, 'blog', $config->get('imgPathBlog'), $config->get('siteURL'));
				$introtext = ConverterHelper::replaceSmiles($introtext, $config->get('imgPathSmiles'));
				$introtext = ConverterHelper::replaceSpoiler($introtext);
				$introtext = ConverterHelper::replaceUrls($introtext, $config->get('replaceUrls'));
			}

			// Replace images, smiles, spoilers in fulltext
			if (!empty($fulltext))
			{
				$fulltext = ConverterHelper::replaceImageTagByLightbox($fulltext, 'blog', $config->get('imgPathBlog'), $config->get('siteURL'));
				$fulltext = ConverterHelper::replaceImageTagByHtmlImage($fulltext, 'blog', $config->get('imgPathBlog'), $config->get('siteURL'));
				$fulltext = ConverterHelper::replaceSmiles($fulltext, $config->get('imgPathSmiles'));
				$fulltext = ConverterHelper::replaceSpoiler($fulltext);
				$fulltext = ConverterHelper::replaceUrls($fulltext, $config->get('replaceUrls'));
			}

			// Final filter text if needed. May strip html.
			if ($config->get('filterText') === 1)
			{
				$data['fulltext']  = ComponentHelper::filterText($fulltext);
				$data['introtext'] = ComponentHelper::filterText($introtext);
			}
			else
			{
				$data['fulltext']  = $fulltext;
				$data['introtext'] = $introtext;
			}

			// Get a new article ID, so we can update instead of save.
			if (array_key_exists($column[0], $articlesIDs))
			{
				$data['id'] = (int) $articlesIDs[$column[0]];
				$isNew = 0;

				// TODO Make a new article title if title is allready exists in DB.
				/** @noinspection  PhpUnusedLocalVariableInspection */
				// list($title, $alias) = ConverterHelper::generateNewTitle($data['catid'], $data['alias'], $data['title']);
				// $data['title'] = $title;
			}
			else
			{
				$isNew = 1;
			}

			$data['images']      = json_encode($images);
			$data['urls']        = json_encode($urls);
			$data['attribs']     = json_encode($attribs);
			$data['hits']        = $filter->clean($column[16], 'int');
			$data['metadata']    = json_encode($metadata);
			$data['language']    = (string) $config->get('articlesLang');
			$data['access']      = 1;
			$data['state']       = (int) $config->get('blogState');
			$data['created_by']  = (int) $config->get('blogDefaultUserId');
			$data['modified']    = HtmlHelper::date($filter->clean($column[29], 'int'), 'Y-m-d H:i:s');
			$data['modified_by'] = (int) $config->get('blogDefaultUserId');
			$data['publish_up']  = $data['created'];
			$data['featured']    = (int) $config->get('blogFeatured');
			$data['rules']       = array('core.edit.delete' => array(), 'core.edit.edit' => array(), 'core.edit.state' => array());

			if (!$model->save($data))
			{
				$totalErrors++;
				$msg = $msgLine . 'ID: ' . $column[0] . '. ' . Text::_('JERROR_AN_ERROR_HAS_OCCURRED') . ' ' . $model->getError();

				echo $msg . "\n";
			}
			else
			{
				$totalImported++;

				if ($isNew == 1)
				{
					$insertId = $model->getItem()->id;
					$txt = ' - Imported.';
				}
				else
				{
					$insertId = $articlesIDs[$column[0]];
					$model->featured($insertId, $data['featured']);
					$txt = ' - Updated.';
				}

				if (empty($insertId))
				{
					$totalErrors++;
					$msg = $msgLine . 'ID: ' . $column[0] . '. ' . Text::_('JERROR_AN_ERROR_HAS_OCCURRED') . ' ' . $model->getError();

					echo $msg . "\n";

					continue;
				}

				// Save old ID and new ID in array to store in json file.
				$articlesIDs[$column[0]] = $insertId;

				echo $msgLine . 'ID ' . $insertId . $txt . "\n";
			}
		}

		ConverterHelper::saveAssocData(__DIR__ . '/imports/blog_ids.json', $articlesIDs);

		$succMsg = "\n" . 'Total articles: ' . $totalRows . '.' .
			  "\n" . 'Articles imported: ' . $totalImported . '.' .
			  "\n" . 'Errors found: ' . $totalErrors . "\n";
		$outputLog .= $succMsg;

		file_put_contents(__DIR__ . '/imports/blog_import.log', $outputLog . "\n\n", FILE_APPEND);

		echo $succMsg;
	}
}

JApplicationCli::getInstance('ConverterBlog')->execute();

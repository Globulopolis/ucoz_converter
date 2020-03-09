<?php
/**
 * UCOZ to Joomla data converter
 *
 * @copyright  Copyright (C) 2020 Libra.ms. All rights reserved.
 * @license    GNU General Public License version 2 or later
 * @url        http://киноархив.com
 */

defined('_JEXEC') or die;

/*
 * Joomla system checks.
 */

const JDEBUG = false;
@ini_set('magic_quotes_runtime', 0);

/*
 * Joomla system startup.
 */

// Import the Joomla Platform.
/** @noinspection PhpIncludeInspection */
require_once JPATH_LIBRARIES . '/import.legacy.php';

// Bootstrap the CMS libraries.
/** @noinspection PhpIncludeInspection */
require_once JPATH_LIBRARIES . '/cms.php';

// Pre-Load configuration. Don't remove the Output Buffering due to BOM issues, see JCode 26026
ob_start();
/** @noinspection PhpIncludeInspection */
require_once JPATH_CONVERTER . '/config.php';
ob_end_clean();

// Import filesystem and utilities classes since they aren't autoloaded
jimport('joomla.filesystem.file');
jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.path');
jimport('joomla.utilities.arrayhelper');

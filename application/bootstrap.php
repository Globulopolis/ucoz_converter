<?php
/**
 * UCOZ to Joomla data converter
 *
 * @copyright  Copyright (C) 2020 Libra.ms. All rights reserved.
 * @license    GNU General Public License version 2 or later
 * @url        http://киноархив.com
 */

defined('_JEXEC') or die;

// Define the base path and require the other defines
define('JPATH_BASE', dirname(__DIR__));
require_once __DIR__ . '/defines.php';

// Launch the application
require_once __DIR__ . '/framework.php';

// Register the Installation application
JLoader::registerPrefix('Installation', JPATH_INSTALLATION);

// Register the application's router due to non-standard include
JLoader::register('JRouterInstallation', __DIR__ . '/router.php');

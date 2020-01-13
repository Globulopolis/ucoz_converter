<?php
/**
 * UCOZ to Joomla data converter
 *
 * @copyright  Copyright (C) 2019 Libra.ms. All rights reserved.
 * @license    GNU General Public License version 2 or later
 * @url        http://киноархив.com
 */

/**
 * This is a script to convert users parameters from Ucoz to Joomla which should be called from the command-line, not the web.
 * Example: /usr/bin/php /path/to/site/cli/converter/ugen.php
 *
 * NOTE! Run this file only after users.php.
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

use Joomla\CMS\Factory;
use Joomla\Filesystem\Path;
use Joomla\Filter\InputFilter;
use Joomla\Utilities\ArrayHelper;

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
 * Class for users.
 *
 * @since  0.1
 */
Class ConverterUgen extends JApplicationCli
{
	/**
	 * @var    object   Configuration.
	 * @since  0.1
	 */
	protected $config;

	/**
	 * @var    array   Group IDs from Joomla.
	 * @since  0.1
	 */
	protected $groups = null;

	/**
	 * Update user information such as user group and ban.
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   0.1
	 */
	public function doExecute()
	{
		$execTime = -microtime(true);
		$db       = Factory::getDbo();
		$lang     = Factory::getLanguage();
		$lang->load('lib_joomla');

		$config       = ConverterHelper::loadConfig();
		$backupPath   = Path::clean($config->get('backupPath') . '/_s1/ugen.txt');
		$this->config = $config;
		$usersParams  = ConverterHelper::loadBackupFile($backupPath);

		if ($usersParams === false)
		{
			jexit("Could not load backup file at $backupPath\n");
		}

		// Load user groups association.
		$this->groups = ArrayHelper::fromObject($config->get('usergroups'));

		$filter             = new InputFilter;
		$totalUsers         = count($usersParams);
		$totalUsersError    = 0;
		$totalUsersBlocked  = 0;
		$ids                = ConverterHelper::getAssocData(__DIR__ . '/imports/users_import.json');
		$blocked            = array();

		if (empty($ids) || is_object($ids))
		{
			$msg = 'Something went wrong with file ' . Path::clean(__DIR__ . '/imports/users_import.json') . '.' . "\n"
				. 'Try to run \'users.php\' again.';
			jexit($msg . "\n");
		}

		$outputLog = "======= " . date('Y-m-d H:i:s', time()) . " =======\n";

		// Process users
		foreach ($usersParams as $i => $line)
		{
			$columnParam = explode('|', $line);
			$username    = $filter->clean($columnParam[1], 'username');
			$ucozGID     = $filter->clean($columnParam[2], 'int');
			$msgLine     = ($i + 1) . ' of ' . $totalUsers . '. User: ';
			$block       = 0;

			$uid = array_search($username, $ids);

			if ($uid === false)
			{
				jexit('Error! User not registered!');
			}

			if ((int) $columnParam[4] > 0)
			{
				$block = 1;
				$blocked[] = $username;
				$totalUsersBlocked++;
			}

			$query = $db->getQuery(true)
				->update($db->quoteName('#__users'))
				->set($db->quoteName('block') . " = '" . $block . "'")
				->where($db->quoteName('id') . ' = ' . (int) $uid);

			$db->setQuery($query);

			try
			{
				$db->execute();
				$msg = $msgLine . $username . ' - Updated.';
			}
			catch (RuntimeException $e)
			{
				$totalUsersError++;
				$msg = $msgLine . $username . ' - ' . $e->getMessage();
			}

			// Get and set user group.
			$joomlaGID = $this->getJoomlaGroupID($ucozGID);
			$query     = $db->getQuery(true)
				->select($db->quoteName('group_id'))
				->from($db->quoteName('#__user_usergroup_map'))
				->where($db->quoteName('user_id') . ' = ' . (int) $uid);

			$db->setQuery($query);
			$result = $db->loadColumn();

			if ($result > 0)
			{
				// Add new group to user only if user not in this group.
				if (!in_array($joomlaGID, $result))
				{
					$query->insert($db->quoteName('#__user_usergroup_map'))
						->columns($db->quoteName(array('user_id', 'group_id')))
						->values("'" . implode("', '", array((int) $uid, $joomlaGID)) . "'");

					$db->setQuery($query);

					try
					{
						$db->execute();
						$msg .= ' Added to a new group.';
					}
					catch (RuntimeException $e)
					{
						$msg = $msgLine . $username . ' - ' . $e->getMessage();
					}
				}
			}

			$outputLog .= $msg . "\n";
			echo $msg . "\n";
		}

		ConverterHelper::saveAssocData(__DIR__ . '/imports/users_blocked.json', $blocked);

		$execTime += microtime(true);
		$execTime = sprintf('%f', $execTime);
		list($sec, $usec) = explode('.', $execTime);

		$succMsg = "\n" . 'Total users: ' . $totalUsers . '.' .
			  "\n" . 'Banned users: ' . $totalUsersBlocked . '.' .
			  "\n" . 'Errors found: ' . $totalUsersError .
			  "\n" . 'Took: ' . number_format($sec / 60, 2) . 'min';
		$outputLog .= $succMsg;

		file_put_contents(__DIR__ . '/imports/ugen_import.log', $outputLog . "\n\n", FILE_APPEND);

		echo $succMsg;
	}

	/**
	 * Get Joomla group ID by Ucoz group ID.
	 *
	 * @param   integer   $gid  Ucoz group ID.
	 *
	 * @return  integer
	 * @since   0.1
	 */
	protected function getJoomlaGroupID($gid)
	{
		if (!empty($this->groups) && array_key_exists($gid, $this->groups))
		{
			$gid = $this->groups[$gid];
		}
		else
		{
			$gid = $this->config->get('defaultUserGroupId');

			if (empty($gid))
			{
				$params = JComponentHelper::getParams('com_users');
				$gid    = $params->get('new_usertype', $params->get('guest_usergroup', 1));
			}
		}

		return (int) $gid;
	}
}

JApplicationCli::getInstance('ConverterUgen')->execute();

<?php
/**
 * UCOZ to Joomla data converter
 *
 * @copyright  Copyright (C) 2019 Libra.ms. All rights reserved.
 * @license    GNU General Public License version 2 or later
 * @url        http://киноархив.com
 */

/**
 * This is a script to convert users from Ucoz to Joomla which should be called from the command-line, not the web.
 * Example: /usr/bin/php /path/to/site/cli/converter/users.php
 *
 * NOTE! All imported users should restore their passwords, because Ucoz and Joomla hashes have different generation algorithms.
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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\String\PunycodeHelper;
use Joomla\CMS\User\User;
use Joomla\Filesystem\Path;
use Joomla\Filter\InputFilter;

// This line will prevent 'Failed to start application' error.
$app = Factory::getApplication('site');

/**
 * Class for users.
 *
 * @since  0.1
 */
Class ConverterUsers extends JApplicationCli
{
	/**
	 * @var    array   Configuration.
	 * @since  0.1
	 */
	protected $config;

	/**
	 * @var    array   Group IDs from Joomla.
	 * @since  0.1
	 */
	protected $groups = null;

	/**
	 * @var    array   Additional fields data.
	 * @since  0.1
	 */
	protected $fields = null;

	/**
	 * @var    mixed   Users file content as array of rows or just content.
	 * @since  0.1
	 */
	protected $users = '';

	/**
	 * @var    mixed   Users params file content as array of rows or just content.
	 * @since  0.1
	 */
	protected $usersParams = '';

	/**
	 * @var    object   Database object.
	 * @since  0.1
	 */
	protected $db;

	/**
	 * Class constructor.
	 *
	 * @since   0.1
	 */
	public function __construct()
	{
		parent::__construct();

		Log::addLogger(
			array(
				'text_file' => 'users_import.php'
			),
			Log::ALL, 'converter'
		);

		$config       = ConverterHelper::loadConfig();
		$backupPath   = Path::clean($config->get('backupPath') . '/_s1');
		$this->config = $config;
		$users        = ConverterHelper::loadBackupFile($backupPath . '/users.txt', false, true);
		$usersParams  = ConverterHelper::loadBackupFile($backupPath . '/ugen.txt');

		// Load backup files for users and their params.
		if ($users !== false)
		{
			$this->users = $users;

			if ($usersParams !== false)
			{
				$this->usersParams = $usersParams;
			}
			else
			{
				$msg = "Could not load backup file $backupPath/ugen.txt";
				Log::add($msg, Log::CRITICAL, 'converter');
				jexit($msg . "\n");
			}
		}
		else
		{
			$msg = "Could not load backup file $backupPath/users.txt";
			Log::add($msg, Log::CRITICAL, 'converter');
			jexit($msg . "\n");
		}

		// Load file with usergroups association. Format: {ucoz_usergroup_id: joomla_usergroup_id, ...}
		$this->groups = ConverterHelper::getAssocData(__DIR__ . '/usergroups.json');

		// Load file with user fields association. Format: {key: field_id, ...} - where: key - array key with data
		// from users.txt file; field_id - field ID from joomla.
		$this->fields = ConverterHelper::getAssocData(__DIR__ . '/userfields.json');
	}

	/**
	 * Convert users and register in Joomla database.
	 * BEWARE! Cli script cannot register users into Super Users group because it's run not from Super User context.
	 *         To avoid this, set Joomla usergroup to 7(default Administrator) for Ucoz usergroup 4 in usergroups.json
	 *         and change it after.
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   0.1
	 */
	public function doExecute()
	{
		$execTime = -microtime(true);
		$this->db = Factory::getDbo();
		$lang     = Factory::getLanguage();
		$lang->load('com_users');
		$lang->load('lib_joomla');

		// Get all usernames from database to check if user allready registered.
		$query = $this->db->getQuery(true)
			->select($this->db->quoteName('username'))
			->from($this->db->quoteName('#__users'));

		$this->db->setQuery($query);
		$dbUsers = $this->db->loadColumn();

		$filter             = new InputFilter;
		$totalUsers         = count($this->usersParams);
		$totalUsersImported = 0;
		$totalUsersError    = 0;
		$totalUsersBlocked  = 0;
		$ids                = ConverterHelper::getAssocData(__DIR__ . '/imports/users_import.json');

		// Process users
		foreach ($this->usersParams as $i => $line)
		{
			if ($i > 0)
			{
				break;
			}

			$columnUserParam = explode('|', $line);

			// Search user params by username from users.txt file
			preg_match('#^' . preg_quote($columnUserParam[1]) . '(.*)\n#mu', $this->users, $matches);

			$columnUser = explode('|', $matches[0]);
			$ucozUserId = $filter->clean($columnUserParam[0], 'int');
			$username   = $filter->clean($columnUserParam[1], 'username');
			$groupid    = $filter->clean($columnUserParam[2], 'int');
			$msgLine    = ($i + 1) . ' of ' . $totalUsers . '. User: ';
			$user       = new User;
			$userData   = new stdClass;

			// Check if user is allready exists in Joomla database and do update.
			if (in_array($username, $dbUsers))
			{
				$userData->id = array_search($ucozUserId, $ids);
			}

			// Sometimes array may differ
			if (count($columnUser) > 27)
			{
				$userData->lastvisitDate = gmdate("Y-m-d H:i:s", $filter->clean(($columnUser[27] + date('Z')), 'int'));
			}
			else
			{
				$userData->lastvisitDate = gmdate("Y-m-d H:i:s", $filter->clean(($columnUser[25] + date('Z')), 'int'));
			}

			$userData->registerDate = gmdate("Y-m-d H:i:s", $filter->clean(($columnUser[15] + date('Z')), 'int'));
			$userData->name         = $this->db->escape($columnUser[5]);
			$userData->requireReset = (int) $this->config['requirePassReset'];

			// Do not use a 'cmd' filter because it'll break username with non-latin chars
			$userData->username = $username;

			$userData->password1 = $columnUser[2];
			$userData->email     = PunycodeHelper::emailToPunycode($columnUser[7]);

			if ((int) $columnUserParam[4] > 0)
			{
				$userData->block = 1;
				$totalUsersBlocked++;
			}
			else
			{
				$userData->block = 0;
			}

			// Do not update usergroup for allready registered user
			if (empty($userData->id))
			{
				$userData->groups   = array();
				$userData->groups[] = $this->getGroup($groupid);
			}

			$userData->avatar = $columnUser[3];

			PluginHelper::importPlugin('user');
			$dispatcher = JEventDispatcher::getInstance();
			$results = $dispatcher->trigger('onContentPrepareData', array('com_users.registration', $userData));

			$data             = (array) $userData;
			$data['password'] = $data['password1'];

			if (!$user->bind($data))
			{
				$totalUsersError++;
				$msg = $msgLine . $userData->username . ' - ' . Text::sprintf('COM_USERS_REGISTRATION_BIND_FAILED', $user->getError());
				Log::add($msg, JLog::ERROR, 'converter');

				echo $msg . "\n";
			}
			else
			{
				if (!$user->save())
				{
					$totalUsersError++;
					$msg = $msgLine . $userData->username . ' - ' . Text::sprintf('COM_USERS_REGISTRATION_SAVE_FAILED', $user->getError());
					Log::add($msg, JLog::ERROR, 'converter');

					echo $msg . "\n";
				}
				else
				{
					$totalUsersImported++;

					if (!empty($userData->id))
					{
						$this->saveExtraFields($columnUser, $userData->id, false);
						$regTxt = 'Updated.';
					}
					else
					{
						$this->saveExtraFields($columnUser, $user->id, true);
						$ids[$user->id] = $ucozUserId;
						$regTxt = 'Registered.';
					}

					echo $msgLine . $userData->username . ' - ' . $regTxt . "\n";
				}
			}
		}

		// Store two dimensional array with type, Joomla ID, Ucoz ID as JSON.
		// E.g.: array('joomla_id' => 'ucoz_id', ...)
		ConverterHelper::saveAssocData(__DIR__ . '/imports/users_import.json', $ids);

		$execTime += microtime(true);
		$execTime = sprintf('%f', $execTime);
		list($sec, $usec) = explode('.', $execTime);

		echo  "\n" . 'Total users: ' . $totalUsers . '.' .
			  "\n" . 'Users imported: ' . $totalUsersImported . '.' .
			  "\n" . 'Banned users: ' . $totalUsersBlocked . '.' .
			  "\n" . 'Errors found: ' . $totalUsersError . '. See logfile at ' .
			  Path::clean(Factory::getConfig()->get('log_path') . '/users_import.php') .
			  "\n" . 'Took: ' . number_format($sec / 60, 2) . 'min';
	}

	/**
	 * Get a group ID from Joomla by old group ID from Ucoz.
	 *
	 * @param   integer  $id   Ucoz group ID.
	 *
	 * @return  integer
	 * @since   0.1
	 */
	protected function getGroup($id)
	{
		$userParams = ComponentHelper::getParams('com_users');
		$group = $userParams->get('new_usertype', $userParams->get('guest_usergroup', 1));

		if (!is_null($this->groups) && array_key_exists($id, $this->groups))
		{
			$group = $this->groups[$id];
		}

		return (int) $group;
	}

	/**
	 * Save additional user info. This will save data only if field created in admin panel and userfields.json file is
	 * filled with valid information.
	 *
	 * @param   array     $data    Field data.
	 * @param   integer   $uid     User ID.
	 * @param   boolean   $isNew   Insert or update rows.
	 *
	 * @return  boolean
	 * @since   0.1
	 */
	protected function saveExtraFields(&$data, $uid, $isNew)
	{
		if ($this->config->get('doExtraFields') != 1)
		{
			return true;
		}

		if (empty($this->fields))
		{
			return false;
		}

		if ($isNew)
		{
			$query = $this->db->getQuery(true)
				->insert($this->db->quoteName('#__fields_values'))
				->columns($this->db->quoteName(array('field_id', 'item_id', 'value')));

			foreach ($this->fields as $key => $fieldId)
			{
				$query->values("'" . (int) $fieldId . "', '" . $uid . "', '" . $this->db->escape($data[$key]) . "'");
			}

			$this->db->setQuery($query);

			try
			{
				$this->db->execute();
			}
			catch (RuntimeException $e)
			{
				echo __LINE__ . " - " . $e->getMessage() . "\n";
			}
		}
		else
		{
			foreach ($this->fields as $key => $fieldId)
			{
				$query = $this->db->getQuery(true)
					->update($this->db->quoteName('#__fields_values'))
					->set($this->db->quoteName('value') . " = '" . $this->db->escape($data[$key]) . "'")
					->where($this->db->quoteName('field_id') . ' = ' . (int) $fieldId);

				$this->db->setQuery($query);

				try
				{
					$this->db->execute();
				}
				catch (RuntimeException $e)
				{
					echo __LINE__ . " - " . $e->getMessage() . "\n";
				}
			}
		}

		return true;
	}
}

JApplicationCli::getInstance('ConverterUsers')->execute();

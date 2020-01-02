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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\String\PunycodeHelper;
use Joomla\CMS\User\User;
use Joomla\Filesystem\Path;
use Joomla\Filter\InputFilter;
use Joomla\Utilities\ArrayHelper;

// This will prevent 'Failed to start application' error.
try
{
	$app = JFactory::getApplication('site');
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
Class ConverterUsers extends JApplicationCli
{
	/**
	 * @var    object   Configuration.
	 * @since  0.1
	 */
	protected $config;

	/**
	 * @var    array   Additional fields data.
	 * @since  0.1
	 */
	protected $fields = null;

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
	}

	/**
	 * Convert users and register in Joomla database.
	 *
	 * BEWARE! Cli script cannot register users into Super Users group because it's run not from Super User context.
	 *         To fix this, run ugen.php script.
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   0.1
	 */
	public function doExecute()
	{
		$execTime = -microtime(true);
		$this->db = JFactory::getDbo();
		$lang     = JFactory::getLanguage();
		$lang->load('com_users');
		$lang->load('lib_joomla');

		$config       = ConverterHelper::loadConfig();
		$backupPath   = Path::clean($config->get('backupPath') . '/_s1');
		$this->config = $config;
		$users        = ConverterHelper::loadBackupFile($backupPath . '/users.txt');

		if ($users === false)
		{
			$msg = "Could not load backup file $backupPath/users.txt";
			Log::add($msg, Log::CRITICAL, 'converter');
			jexit($msg . "\n");
		}

		// Load user fields association.
		$this->fields = ArrayHelper::fromObject($config->get('userfields'));

		// Get all usernames from database to check if user allready registered.
		$query = $this->db->getQuery(true)
			->select($this->db->quoteName('username'))
			->from($this->db->quoteName('#__users'));

		$this->db->setQuery($query);
		$dbUsers = $this->db->loadColumn();

		$filter             = new InputFilter;
		$totalUsers         = count($users);
		$totalUsersImported = 0;
		$totalUsersError    = 0;
		$ids                = ConverterHelper::getAssocData(__DIR__ . '/imports/users_import.json');
		$regTxt             = '';

		// Process users
		foreach ($users as $i => $line)
		{
			$columnUser = explode('|', $line);
			$username   = $filter->clean($columnUser[0], 'username');
			$msgLine    = ($i + 1) . ' of ' . $totalUsers . '. User: ';
			$user       = new User;
			$userData   = new stdClass;

			// Check if user is allready exists in Joomla database and set user ID.
			if (in_array($username, $dbUsers))
			{
				$_uid = array_search($username, $ids);

				if ($_uid !== false)
				{
					$userData->id = (int) $_uid;
				}
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
			$userData->username     = $username;
			$userData->password1    = $columnUser[2];
			$userData->email        = PunycodeHelper::emailToPunycode($columnUser[7]);

			// Do not update usergroup for allready registered user
			if (empty($userData->id))
			{
				$userData->groups   = array();
				$userData->groups[] = $this->getDefaultUserGroup();
			}

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
						$regTxt = 'Updated';
					}
					else
					{
						$this->saveExtraFields($columnUser, $user->id, true);
						$ids[$user->id] = $userData->username;
						$regTxt = 'Registered';
					}

					echo $msgLine . $userData->username . ' - ' . $regTxt . ".\n";
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
			  "\n" . 'Users ' . strtolower($regTxt) . ': ' . $totalUsersImported . '.' .
			  "\n" . 'Errors found: ' . $totalUsersError . '. See logfile at ' .
			  Path::clean(JFactory::getConfig()->get('log_path') . '/users_import.php') .
			  "\n" . 'Took: ' . number_format($sec / 60, 2) . 'min';
	}

	/**
	 * Get default user group for all users.
	 *
	 * @return  integer
	 * @since   0.1
	 */
	protected function getDefaultUserGroup()
	{
		if (!empty($this->config->get('defaultUserGroupId')))
		{
			$group = $this->config->get('defaultUserGroupId');
		}
		else
		{
			$userParams = ComponentHelper::getParams('com_users');
			$group = $userParams->get('new_usertype', $userParams->get('guest_usergroup', 1));
		}

		return (int) $group;
	}

	/**
	 * Save additional user info. This will save data only if field created in admin panel.
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

		// Insert new fields only when insert new user.
		if ($isNew)
		{
			$this->insertField($this->fields, $data, $uid);
		}
		else
		{
			$query = $this->db->getQuery(true)
				->select($this->db->quoteName('field_id'))
				->from($this->db->quoteName('#__fields_values'))
				->where($this->db->quoteName('field_id') . ' IN (' . implode(',', array_values($this->fields)) . ')')
				->where($this->db->quoteName('item_id') . ' = ' . (int) $uid);
			$this->db->setQuery($query);
			$fieldIds = $this->db->loadColumn();

			// Test if we're trying to insert new fields into database.
			$diffs = array_diff($this->fields, $fieldIds);

			if (count($diffs) > 0)
			{
				$this->insertField($diffs, $data, $uid);
			}
			else
			{
				foreach ($this->fields as $key => $fieldId)
				{
					$query = $this->db->getQuery(true)
						->update($this->db->quoteName('#__fields_values'))
						->set($this->db->quoteName('value') . " = '" . $this->db->escape($data[$key]) . "'")
						->where($this->db->quoteName('field_id') . ' = ' . (int) $fieldId)
						->where($this->db->quoteName('item_id') . ' = ' . (int) $uid);

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
		}

		return true;
	}

	/**
	 * Get a group ID from Joomla by old group ID from Ucoz.
	 *
	 * @param   array    $fields   Fields assoc array.
	 * @param   mixed    $data     Field data.
	 * @param   integer  $uid      User ID.
	 *
	 * @return  void
	 * @since   0.1
	 */
	protected function insertField($fields, &$data, $uid)
	{
		$query = $this->db->getQuery(true)
			->insert($this->db->quoteName('#__fields_values'))
			->columns($this->db->quoteName(array('field_id', 'item_id', 'value')));

		foreach ($fields as $key => $fieldId)
		{
			$value = is_array($data) ? $data[$key] : $data;
			$query->values("'" . (int) $fieldId . "', '" . $uid . "', '" . $this->db->escape($value) . "'");
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
}

JApplicationCli::getInstance('ConverterUsers')->execute();

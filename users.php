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

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\String\PunycodeHelper;
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
	 * Convert users and register in Joomla database.
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
		$lang->load('lib_joomla');

		$config       = ConverterHelper::loadConfig();
		$backupPath   = Path::clean($config->get('backupPath') . '/_s1/users.txt');
		$this->config = $config;
		$users        = ConverterHelper::loadBackupFile($backupPath);

		if ($users === false)
		{
			jexit("Could not load backup file at $backupPath\n");
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
		$outputLog          = "======= " . date('Y-m-d H:i:s', time()) . " =======\n";

		// Process users
		foreach ($users as $i => $line)
		{
			$columnUser               = explode('|', $line);
			$username                 = $filter->clean($columnUser[0], 'username');
			$msgLine                  = ($i + 1) . ' of ' . $totalUsers . '. User: ';

			$userData                 = array();
			$userData['id']           = '';
			$userData['name']         = $this->db->escape($columnUser[5]);
			$userData['username']     = $username;
			$userData['email']        = PunycodeHelper::emailToPunycode($columnUser[7]);
			$userData['password']     = $columnUser[2];
			$userData['block']        = 0;
			$userData['sendEmail']    = 0;
			$userData['registerDate'] = gmdate("Y-m-d H:i:s", $filter->clean(($columnUser[15] + date('Z')), 'int'));

			// Sometimes array may differ
			if (count($columnUser) > 27)
			{
				$userData['lastvisitDate'] = gmdate("Y-m-d H:i:s", $filter->clean(($columnUser[27] + date('Z')), 'int'));
			}
			else
			{
				$userData['lastvisitDate'] = gmdate("Y-m-d H:i:s", $filter->clean(($columnUser[25] + date('Z')), 'int'));
			}

			$userData['activation']    = 0;
			$userData['params']        = '';
			$userData['lastResetTime'] = $this->db->getNullDate();
			$userData['resetCount']    = 0;
			$userData['otpKey']        = '';
			$userData['otep']          = '';
			$userData['requireReset']  = (int) $this->config['requirePassReset'];

			// Check if user is allready exists in Joomla database and set user ID.
			if (in_array($username, $dbUsers))
			{
				$_uid = array_search($username, $ids);

				if ($_uid !== false)
				{
					$userData['id'] = (int) $_uid;
				}
			}

			PluginHelper::importPlugin('user');
			$dispatcher = JEventDispatcher::getInstance();
			$results = $dispatcher->trigger('onContentPrepareData', array('com_users.registration', $userData));

			$query = $this->db->getQuery(true);

			if (empty($userData['id']))
			{
				$query->insert($this->db->quoteName('#__users'))
					->columns(
						$this->db->quoteName(
							array('id', 'name', 'username', 'email', 'password', 'block', 'sendEmail', 'registerDate',
							'lastvisitDate', 'activation', 'params', 'lastResetTime', 'resetCount', 'otpKey', 'otep',
							'requireReset')
						)
					)
					->values("'" . implode("', '", $userData) . "'");
			}
			else
			{
				$query->update($this->db->quoteName('#__users'))
					->set($this->db->quoteName('name') . " = '" . $userData['name'] . "'")
					->set($this->db->quoteName('username') . " = '" . $userData['username'] . "'")
					->set($this->db->quoteName('email') . " = '" . $userData['email'] . "'")
					->set($this->db->quoteName('password') . " = '" . $userData['password'] . "'")
					->where($this->db->quoteName('id') . ' = ' . (int) $userData['id']);
			}

			$this->db->setQuery($query);

			try
			{
				$this->db->execute();
				$totalUsersImported++;

				if (!empty($userData['id']))
				{
					$this->saveExtraFields($columnUser, $userData['id'], false);
					$regTxt = 'Updated';
				}
				else
				{
					$insertId = $this->db->insertid();
					$this->saveExtraFields($columnUser, $insertId, true);
					$ids[$insertId] = $userData['username'];
					$regTxt = 'Registered';
				}

				$msg = $msgLine . $userData['username'] . ' - ' . $regTxt . ".\n";
				$outputLog .= $msg;

				echo $msg;
			}
			catch (RuntimeException $e)
			{
				$totalUsersError++;
				$msg = $msgLine . $userData['username'] . ' - ' . $e->getMessage();
				$outputLog .= $msg;

				echo $msg . "\n";
			}
		}

		// Store in array Joomla ID, Ucoz username as JSON.
		// E.g.: array('joomla_id' => 'ucoz_username', ...)
		ConverterHelper::saveAssocData(__DIR__ . '/imports/users_import.json', $ids);

		$execTime += microtime(true);
		$execTime = sprintf('%f', $execTime);
		list($sec, $usec) = explode('.', $execTime);

		$succMsg = "\n" . 'Total users: ' . $totalUsers . '.' .
			  "\n" . 'Users ' . strtolower($regTxt) . ': ' . $totalUsersImported . '.' .
			  "\n" . 'Errors found: ' . $totalUsersError .
			  "\n" . 'Took: ' . number_format($sec / 60, 2) . 'min';
		$outputLog .= $succMsg;

		file_put_contents(__DIR__ . '/imports/users_import.log', $outputLog . "\n\n", FILE_APPEND);

		echo $succMsg;
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
	 * Insert new field infromation.
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

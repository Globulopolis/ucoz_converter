<?php
/**
 * UCOZ to Joomla data converter
 *
 * @copyright  Copyright (C) 2020 Libra.ms. All rights reserved.
 * @license    GNU General Public License version 2 or later
 * @url        http://киноархив.com
 */

use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

defined('_JEXEC') or die;

/**
 * Setup model.
 *
 * @since  3.1
 */
class InstallationModelSetup extends JModelBase
{
	/**
	 * Get the current setup options from the session.
	 *
	 * @return  array  An array of options from the session.
	 *
	 * @since   3.1
	 */
	public function getOptions()
	{
		// Get the config data.
		$config = new ConverterConfig;
		$data   = ArrayHelper::fromObject($config);
		$temp   = JFactory::getSession()->get('setup.options', array());

		// Merge only language session data.
		if (!empty($temp))
		{
			$data['language'] = $temp['language'];
		}

		$data['blogExcludeID']  = !empty($data['blogExcludeID']) ? implode(',', json_decode($data['blogExcludeID'])) : '';
		$data['newsExcludeID']  = !empty($data['newsExcludeID']) ? implode(',', json_decode($data['newsExcludeID'])) : '';
		$data['loadsExcludeID'] = !empty($data['loadsExcludeID']) ? implode(',', json_decode($data['loadsExcludeID'])) : '';
		$data['publExcludeID']  = !empty($data['publExcludeID']) ? implode(',', json_decode($data['publExcludeID'])) : '';

		return $data;
	}

	/**
	 * Store the current setup options in the session.
	 *
	 * @param   array  $options  The installation options.
	 *
	 * @return  array  An array of options from the session.
	 *
	 * @since   3.1
	 */
	public function storeOptions($options)
	{
		// Get the current setup options from the session.
		$old = (array) $this->getOptions();

		// Ensure that we have language
		if (!isset($options['language']) || empty($options['language']))
		{
			$options['language'] = JFactory::getLanguage()->getTag();
		}

		// Get the session
		$session = JFactory::getSession();

		// Merge the new setup options into the current ones and store in the session.
		$options = array_merge($old, (array) $options);
		$session->set('setup.options', $options);

		return $options;
	}

	/**
	 * Method to get the form.
	 *
	 * @param   string  $view  The view being processed.
	 *
	 * @return  JForm|boolean  JForm object on success, false on failure.
	 *
	 * @since   3.1
	 * @throws  Exception
	 */
	public function getForm($view = null)
	{
		if (!$view)
		{
			$view = JFactory::getApplication()->input->getWord('view', 'site');
		}

		// Get the form.
		JForm::addFormPath(JPATH_COMPONENT . '/model/forms');

		try
		{
			$form = JForm::getInstance('jform', $view, array('control' => 'jform'));
		}
		catch (Exception $e)
		{
			JFactory::getApplication()->enqueueMessage($e->getMessage(), 'error');

			return false;
		}

		// Check the session for previously entered form data.
		$data = (array) $this->getOptions();

		// Bind the form data if present.
		if (!empty($data))
		{
			$form->bind($data);
		}

		return $form;
	}

	/**
	 * Method to write the configuration to a file.
	 *
	 * @param   array  $config  A Registry object containing all global config data.
	 *
	 * @return  boolean  True on success, false on failure.
	 *
	 * @throws  Exception|RuntimeException
	 * @since   0.1
	 */
	public function writeConfigFile($config)
	{
		jimport('joomla.filesystem.path');
		jimport('joomla.filesystem.file');

		// Set the configuration file path.
		$file = JPATH_CONVERTER . '/config.php';

		// Get the new FTP credentials.
		$ftp = JClientHelper::getCredentials('ftp', true);

		$app = JFactory::getApplication();

		// Attempt to make the file writeable if using FTP.
		if (!$ftp['enabled'] && JPath::isOwner($file) && !JPath::setPermissions($file, '0644'))
		{
			$app->enqueueMessage(JText::_('COM_CONFIG_ERROR_CONFIGURATION_PHP_NOTWRITABLE'), 'notice');
		}

		$config = new Registry($config);

		// Attempt to write the configuration file as a PHP class named ConverterConfig.
		$configuration = $config->toString('PHP', array('class' => 'ConverterConfig', 'closingtag' => false));

		if (!JFile::write($file, $configuration))
		{
			throw new RuntimeException(JText::_('COM_CONFIG_ERROR_WRITE_FAILED'));
		}

		// Invalidates the cached configuration file
		if (function_exists('opcache_invalidate'))
		{
			opcache_invalidate($file);
		}

		// Attempt to make the file unwriteable if using FTP.
		if (!$ftp['enabled'] && JPath::isOwner($file) && !JPath::setPermissions($file, '0444'))
		{
			$app->enqueueMessage(JText::_('COM_CONFIG_ERROR_CONFIGURATION_PHP_NOTUNWRITABLE'), 'notice');
		}

		return true;
	}

	/**
	 * Checks the availability of the parse_ini_file and parse_ini_string functions.
	 *
	 * @return  boolean  True if the method exists.
	 *
	 * @since   3.1
	 */
	public function getIniParserAvailability()
	{
		$disabledFunctions = ini_get('disable_functions');

		if (!empty($disabledFunctions))
		{
			// Attempt to detect them in the disable_functions blacklist.
			$disabledFunctions = explode(',', trim($disabledFunctions));
			$numberOfDisabledFunctions = count($disabledFunctions);

			for ($i = 0; $i < $numberOfDisabledFunctions; $i++)
			{
				$disabledFunctions[$i] = trim($disabledFunctions[$i]);
			}

			$result = !in_array('parse_ini_string', $disabledFunctions);
		}
		else
		{
			// Attempt to detect their existence; even pure PHP implementation of them will trigger a positive response, though.
			$result = function_exists('parse_ini_string');
		}

		return $result;
	}

	/**
	 * Gets PHP options.
	 *
	 * @return  array  Array of PHP config options
	 *
	 * @since   3.1
	 */
	public function getPhpOptions()
	{
		$options = array();

		// Check the PHP Version.
		$option = new stdClass;
		$option->label  = JText::sprintf('INSTL_PHP_VERSION_NEWER', JOOMLA_MINIMUM_PHP);
		$option->state  = version_compare(PHP_VERSION, JOOMLA_MINIMUM_PHP, '>=');
		$option->notice = null;
		$options[] = $option;

		// Check for magic quotes gpc.
		$option = new stdClass;
		$option->label  = JText::_('INSTL_MAGIC_QUOTES_GPC');
		$option->state  = (ini_get('magic_quotes_gpc') == false);
		$option->notice = null;
		$options[] = $option;

		// Check for register globals.
		$option = new stdClass;
		$option->label  = JText::_('INSTL_REGISTER_GLOBALS');
		$option->state  = (ini_get('register_globals') == false);
		$option->notice = null;
		$options[] = $option;

		// Check for zlib support.
		$option = new stdClass;
		$option->label  = JText::_('INSTL_ZLIB_COMPRESSION_SUPPORT');
		$option->state  = extension_loaded('zlib');
		$option->notice = null;
		$options[] = $option;

		// Check for XML support.
		$option = new stdClass;
		$option->label  = JText::_('INSTL_XML_SUPPORT');
		$option->state  = extension_loaded('xml');
		$option->notice = null;
		$options[] = $option;

		// Check for database support.
		// We are satisfied if there is at least one database driver available.
		$available = JDatabaseDriver::getConnectors();
		$option = new stdClass;
		$option->label  = JText::_('INSTL_DATABASE_SUPPORT');
		$option->label .= '<br />(' . implode(', ', $available) . ')';
		$option->state  = count($available);
		$option->notice = null;
		$options[] = $option;

		// Check for mbstring options.
		if (extension_loaded('mbstring'))
		{
			// Check for default MB language.
			$option = new stdClass;
			$option->label  = JText::_('INSTL_MB_LANGUAGE_IS_DEFAULT');
			$option->state  = strtolower(ini_get('mbstring.language')) === 'neutral';
			$option->notice = $option->state ? null : JText::_('INSTL_NOTICEMBLANGNOTDEFAULT');
			$options[] = $option;

			// Check for MB function overload.
			$option = new stdClass;
			$option->label  = JText::_('INSTL_MB_STRING_OVERLOAD_OFF');
			$option->state  = ini_get('mbstring.func_overload') == 0;
			$option->notice = $option->state ? null : JText::_('INSTL_NOTICEMBSTRINGOVERLOAD');
			$options[] = $option;
		}

		// Check for a missing native parse_ini_file implementation.
		$option = new stdClass;
		$option->label  = JText::_('INSTL_PARSE_INI_FILE_AVAILABLE');
		$option->state  = $this->getIniParserAvailability();
		$option->notice = null;
		$options[] = $option;

		// Check for missing native json_encode / json_decode support.
		$option = new stdClass;
		$option->label  = JText::_('INSTL_JSON_SUPPORT_AVAILABLE');
		$option->state  = function_exists('json_encode') && function_exists('json_decode');
		$option->notice = null;
		$options[] = $option;

		// Check for configuration file writable.
		$writable = (is_writable(JPATH_CONVERTER . '/config.php')
			|| (!is_file(JPATH_CONVERTER . '/config.php') && is_writable(JPATH_CONVERTER)));

		$option = new stdClass;
		$option->label  = JText::sprintf('INSTL_WRITABLE', 'config.php');
		$option->state  = $writable;
		$option->notice = $option->state ? null : JText::_('INSTL_NOTICEYOUCANSTILLINSTALL');
		$options[] = $option;

		return $options;
	}

	/**
	 * Checks if all of the mandatory PHP options are met.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   3.1
	 */
	public function getPhpOptionsSufficient()
	{
		$result  = true;
		$options = $this->getPhpOptions();

		foreach ($options as $option)
		{
			if ($option->notice === null)
			{
				$result = ($result && $option->state);
			}
		}

		return $result;
	}

	/**
	 * Gets PHP Settings.
	 *
	 * @return  array
	 *
	 * @since   3.1
	 */
	public function getPhpSettings()
	{
		$settings = array();

		// Check for safe mode.
		$setting = new stdClass;
		$setting->label = JText::_('INSTL_SAFE_MODE');
		$setting->state = (bool) ini_get('safe_mode');
		$setting->recommended = false;
		$settings[] = $setting;

		// Check for display errors.
		$setting = new stdClass;
		$setting->label = JText::_('INSTL_DISPLAY_ERRORS');
		$setting->state = (bool) ini_get('display_errors');
		$setting->recommended = false;
		$settings[] = $setting;

		// Check for file uploads.
		$setting = new stdClass;
		$setting->label = JText::_('INSTL_FILE_UPLOADS');
		$setting->state = (bool) ini_get('file_uploads');
		$setting->recommended = true;
		$settings[] = $setting;

		// Check for magic quotes runtimes.
		$setting = new stdClass;
		$setting->label = JText::_('INSTL_MAGIC_QUOTES_RUNTIME');
		$setting->state = (bool) ini_get('magic_quotes_runtime');
		$setting->recommended = false;
		$settings[] = $setting;

		// Check for output buffering.
		$setting = new stdClass;
		$setting->label = JText::_('INSTL_OUTPUT_BUFFERING');
		$setting->state = (int) ini_get('output_buffering') !== 0;
		$setting->recommended = false;
		$settings[] = $setting;

		// Check for session auto-start.
		$setting = new stdClass;
		$setting->label = JText::_('INSTL_SESSION_AUTO_START');
		$setting->state = (bool) ini_get('session.auto_start');
		$setting->recommended = false;
		$settings[] = $setting;

		// Check for native ZIP support.
		$setting = new stdClass;
		$setting->label = JText::_('INSTL_ZIP_SUPPORT_AVAILABLE');
		$setting->state = function_exists('zip_open') && function_exists('zip_read');
		$setting->recommended = true;
		$settings[] = $setting;

		return $settings;
	}

	/**
	 * Method to validate the form data.
	 *
	 * @param   array   $data  The form data.
	 * @param   string  $view  The view.
	 *
	 * @return  array|boolean  Array of filtered data if valid, false otherwise.
	 *
	 * @since   3.1
	 * @throws  Exception
	 */
	public function validate($data, $view = null)
	{
		// Get the form.
		$form = $this->getForm($view);

		// Check for an error.
		if ($form === false)
		{
			return false;
		}

		// Filter and validate the form data.
		$data   = $form->filter($data);
		$return = $form->validate($data);

		// Check for an error.
		if ($return instanceof Exception)
		{
			JFactory::getApplication()->enqueueMessage($return->getMessage(), 'warning');

			return false;
		}

		// Check the validation results.
		if ($return === false)
		{
			// Get the validation messages from the form.
			$messages = array_reverse($form->getErrors());

			foreach ($messages as $message)
			{
				if ($message instanceof Exception)
				{
					JFactory::getApplication()->enqueueMessage($message->getMessage(), 'warning');
				}
				else
				{
					JFactory::getApplication()->enqueueMessage($message, 'warning');
				}
			}

			return false;
		}

		return $data;
	}
}

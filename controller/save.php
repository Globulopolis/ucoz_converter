<?php
/**
 * UCOZ to Joomla data converter
 *
 * @copyright  Copyright (C) 2020 Libra.ms. All rights reserved.
 * @license    GNU General Public License version 2 or later
 * @url        http://киноархив.com
 */

defined('_JEXEC') or die;

/**
 * Controller class to set the site data for the Joomla Installer.
 *
 * @since  3.1
 */
class InstallationControllerSave extends JControllerBase
{
	/**
	 * Execute the controller.
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 * @since   3.1
	 */
	public function execute()
	{
		// Get the application
		/** @var InstallationApplicationWeb $app */
		$app = $this->getApplication();

		// Check for request forgeries.
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN_NOTICE'));

		// Get the setup model.
		$model = new InstallationModelSetup;

		// Check the form
		$model  = new InstallationModelSetup;
		$data   = $app->input->post->get('jform', array(), 'array');
		$form   = $model->getForm();

		if (!$form)
		{
			$app->enqueueMessage(JText::_('JGLOBAL_VALIDATION_FORM_FAILED'), 'error');

			return;
		}

		$validData = $model->validate($data, 'site');

		// Check for validation errors.
		if ($validData !== false)
		{
			// Fix bad chars at the end of paths
			$charlist = ' \t\n\r\0\x0B\/';
			$data['backupPath'] = rtrim($data['backupPath'], $charlist);
			$data['siteURL'] = rtrim($data['siteURL'], $charlist);
			$data['imgPathSmiles'] = rtrim($data['imgPathSmiles'], $charlist);
			$data['imgPathBlog'] = rtrim($data['imgPathBlog'], $charlist);
			$data['imgAttachPathBlogDst'] = rtrim($data['imgAttachPathBlogDst'], $charlist);
			$data['imgPathNews'] = rtrim($data['imgPathNews'], $charlist);
			$data['imgAttachPathNewsDst'] = rtrim($data['imgAttachPathNewsDst'], $charlist);
			$data['imgPathLoads'] = rtrim($data['imgPathLoads'], $charlist);
			$data['imgAttachPathLoadsDst'] = rtrim($data['imgAttachPathLoadsDst'], $charlist);
			$data['imgPathPubl'] = rtrim($data['imgPathPubl'], $charlist);
			$data['imgAttachPathPublDst'] = rtrim($data['imgAttachPathPublDst'], $charlist);

			$model->writeConfigFile($data);
			$app->enqueueMessage(JText::_('INSTL_CONFIG_SAVE_SUCCESS'));
		}

		$app->redirect('index.php');
	}
}

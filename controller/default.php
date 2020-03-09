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
 * Default controller class for the Joomla Installer.
 *
 * @since  3.1
 */
class InstallationControllerDefault extends JControllerBase
{
	/**
	 * Execute the controller.
	 *
	 * @return  string  The rendered view.
	 *
	 * @throws  Exception
	 * @since   3.1
	 */
	public function execute()
	{
		// Get the application
		/** @var InstallationApplicationWeb $app */
		$app = $this->getApplication();

		// Get the document object.
		$document = $app->getDocument();

		// Set the default view name and format from the request.
		$defaultView = 'site';

		$vName   = $this->input->getWord('view', $defaultView);
		$vFormat = $document->getType();
		$lName   = $this->input->getWord('layout', 'default');

		if (strcmp($vName, $defaultView) === 0)
		{
			$this->input->set('view', $defaultView);
		}

		switch ($vName)
		{
			case 'preinstall':
				$model        = new InstallationModelSetup;
				$sufficient   = $model->getPhpOptionsSufficient();
				$checkOptions = false;
				$options      = $model->getOptions();

				if ($sufficient)
				{
					$app->redirect('index.php');
				}

				break;

			default:
				$model        = new InstallationModelSetup;
				$sufficient   = $model->getPhpOptionsSufficient();
				$checkOptions = true;
				$options      = $model->getOptions();

				if (!$sufficient)
				{
					$app->redirect('index.php?view=preinstall');
				}

				break;
		}

		if ($vName !== $defaultView && $checkOptions && empty($options))
		{
			$app->redirect('index.php');
		}

		// Register the layout paths for the view
		$paths = new SplPriorityQueue;
		$paths->insert(JPATH_CONVERTER . '/view/' . $vName . '/tmpl', 'normal');

		$vClass = 'InstallationView' . ucfirst($vName) . ucfirst($vFormat);

		if (!class_exists($vClass))
		{
			$vClass = 'InstallationViewDefault';
		}

		/** @var JViewHtml $view */
		$view = new $vClass($model, $paths);
		$view->setLayout($lName);

		// Render our view and return it to the application.
		return $view->render();
	}
}

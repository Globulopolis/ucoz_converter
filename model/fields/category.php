<?php
/**
 * UCOZ to Joomla data converter
 *
 * @copyright  Copyright (C) 2020 Libra.ms. All rights reserved.
 * @license    GNU General Public License version 2 or later
 * @url        http://киноархив.com
 */

defined('JPATH_BASE') or die;

JFormHelper::loadFieldClass('list');

/**
 * Installation Category field.
 *
 * @since  1.6
 */
class InstallationFormFieldCategory extends JFormFieldList
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  1.6
	 */
	protected $type = 'Category';

	/**
	 * Method to get the field options.
	 *
	 * @return  array  The field option objects.
	 *
	 * @since   1.6
	 */
	protected function getOptions()
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);

		$query->select($db->quoteName('id', 'value'))->select($db->quoteName('title', 'text'))
			->from($db->quoteName('#__categories'))
			->where($db->quoteName('extension') . " = 'com_content'")
			->where($db->quoteName('published') . " IN (0,1)");
		$db->setQuery($query);

		try
		{
			$options = $db->loadAssocList();
		}
		catch (RuntimeException $e)
		{
			$options = array();
		}

		// Merge any additional options in the XML definition.
		return array_merge(parent::getOptions(), $options);
	}
}

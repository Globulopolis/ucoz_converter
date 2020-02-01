<?php
/**
 * UCOZ to Joomla data converter
 *
 * @copyright  Copyright (C) 2020 Libra.ms. All rights reserved.
 * @license    GNU General Public License version 2 or later
 * @url        http://киноархив.com
 */

defined('JPATH_BASE') or die;
define('CBLIB', true);

JFormHelper::loadFieldClass('list');

/**
 * List of fields.
 *
 * @since  0.1
 */
class InstallationFormFieldFields extends JFormFieldList
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  0.1
	 */
	protected $type = 'Fields';

	/**
	 * Method to get the field options.
	 *
	 * @return  array  The field option objects.
	 *
	 * @since   0.1
	 */
	protected function getOptions()
	{
		$db        = JFactory::getDbo();
		$cbOptions = array();
		$options   = array();

		// Check if Community Builder is installed.
		$query = $db->getQuery(true)
			->select('COUNT(extension_id)')
			->from('#__extensions')
			->where("element = 'com_comprofiler'");
		$db->setQuery($query);

		$isComprofiler = (int) $db->loadResult();

		if ($isComprofiler > 0)
		{
			// Select all fields for Community Builder only(for #__comprofiler table).
			$query = $db->getQuery(true)
				->select('DISTINCT tablecolumns AS value, title AS text')
				->from($db->quoteName('#__comprofiler_fields'))
				->where($db->quoteName('table') . " = '#__comprofiler'")
				->where($db->quoteName('tablecolumns') . " != ''")
				->order('ordering ASC');
			$db->setQuery($query);

			try
			{
				$_cbOptions = $db->loadAssocList();

				if (!empty($_cbOptions))
				{
					$temp = JFactory::getSession()->get('setup.options', array());

					if (!empty($temp))
					{
						$lang = $temp['language'];
					}
					else
					{
						$lang = JFactory::getLanguage()->getTag();
					}

					// Load Community Builder language file and translate field name.
					require_once JPATH_LIBRARIES . '/CBLib/CBLib/Language/CBTxt.php';
					$cbLang = new \CBLib\Language\CBTxt;
					$cbLang::import(JPATH_ROOT . '/components/com_comprofiler/plugin/language', $lang, 'language.php');

					foreach ($_cbOptions as $cbOpts)
					{
						$value = explode(',', $cbOpts['value']);
						$cbOptions[] = array(
							// Add cb: to field name so we can know that this is CB field not the Joomla field ID.
							'value' => 'cb:' . $value[0],
							'text'  => 'CB: ' . $cbLang::T($cbOpts['text'])
						);
					}
				}
			}
			catch (RuntimeException $e)
			{
				echo $e->getMessage();
			}

		}

		$query = $db->getQuery(true)
			->select('DISTINCT a.id AS value, a.title AS text')
			->from($db->quoteName('#__fields', 'a'))
			->join('LEFT', '#__fields_groups AS g ON g.id = a.group_id')
			->where("a.context = '" . $db->escape($this->element['context']) . "'")
			->where('a.state IN (0, 1)')
			->where('(a.group_id = 0 OR g.state IN (0, 1))')
			->order('a.ordering ASC');
		$db->setQuery($query);

		try
		{
			$options = $db->loadAssocList();
		}
		catch (RuntimeException $e)
		{
			echo $e->getMessage();
		}

		// Merge any additional options in the XML definition.
		return array_merge(parent::getOptions(), $options, $cbOptions);
	}

	/**
	 * Method to get the field input markup for a generic list.
	 * Use the multiple attribute to enable multiselect.
	 *
	 * @return  string  The field input markup.
	 *
	 * @since   0.1
	 */
	protected function getInput()
	{
		$html  = array();
		$class = array();
		$attr  = '';

		// Initialize some field attributes.
		$class[] = !empty($this->class) ? $this->class : '';

		$customGroupText = JText::_('INSTL_EXTRA_FIELDS_CUSTOM_ID');

		$class[] = 'chzn-custom-value';
		$attr .= ' data-custom_group_text="' . $customGroupText . '" '
			. 'data-no_results_text="' . JText::_('INSTL_EXTRA_FIELDS_ADD_CUSTOM_ID') . '" '
			. 'data-placeholder="' . JText::_('INSTL_EXTRA_FIELDS_TYPE_OR_SELECT_OPTION') . '" ';

		if ($class)
		{
			$attr .= 'class="' . implode(' ', $class) . '"';
		}

		$attr .= !empty($this->size) ? ' size="' . $this->size . '"' : '';
		$attr .= $this->multiple ? ' multiple' : '';
		$attr .= $this->required ? ' required aria-required="true"' : '';
		$attr .= $this->autofocus ? ' autofocus' : '';

		// To avoid user's confusion, readonly="true" should imply disabled="true".
		if ((string) $this->readonly == '1'
			|| (string) $this->readonly == 'true'
			|| (string) $this->disabled == '1'
			|| (string) $this->disabled == 'true')
		{
			$attr .= ' disabled="disabled"';
		}

		// Initialize JavaScript field attributes.
		$attr .= $this->onchange ? ' onchange="' . $this->onchange . '"' : '';

		// Get the field options.
		$options = (array) $this->getOptions();

		// Create a read-only list (no name) with hidden input(s) to store the value(s).
		if ((string) $this->readonly == '1' || (string) $this->readonly == 'true')
		{
			$html[] = JHtml::_('select.genericlist', $options, '', trim($attr), 'value', 'text', $this->value, $this->id);

			// E.g. form field type tag sends $this->value as array
			if ($this->multiple && is_array($this->value))
			{
				if (!count($this->value))
				{
					$this->value[] = '';
				}

				foreach ($this->value as $value)
				{
					$html[] = '<input type="hidden" name="' . $this->name . '" value="' . htmlspecialchars($value, ENT_COMPAT, 'UTF-8') . '"/>';
				}
			}
			else
			{
				$html[] = '<input type="hidden" name="' . $this->name . '" value="' . htmlspecialchars($this->value, ENT_COMPAT, 'UTF-8') . '"/>';
			}
		}
		else
		{
			// Create a regular list.
			if (count($options) === 0)
			{
				// All fields have been deleted, so we need a new field.
				$options[0]        = new stdClass;
				$options[0]->value = 0;
				$options[0]->text  = 'New ID';
			}

			$html[] = JHtml::_('select.genericlist', $options, $this->name, trim($attr), 'value', 'text', $this->value, $this->id);
		}

		return implode($html);
	}
}

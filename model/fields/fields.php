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
 * List of fields.
 *
 * @since  1.6
 */
class InstallationFormFieldFields extends JFormFieldList
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  1.6
	 */
	protected $type = 'Fields';

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

		$query->select('DISTINCT a.id AS value, a.title AS text');
		$query->from('#__fields AS a');
		$query->join('LEFT', '#__fields_groups AS g ON g.id = a.group_id');
		$query->where("a.context = '" . $db->escape($this->element['context']) . "'");
		$query->where('a.state IN (0, 1)');
		$query->where('(a.group_id = 0 OR g.state IN (0, 1))');
		$query->order('a.ordering ASC');
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

	/**
	 * Method to get the field input markup for a generic list.
	 * Use the multiple attribute to enable multiselect.
	 *
	 * @return  string  The field input markup.
	 *
	 * @since   3.6
	 */
	protected function getInput()
	{
		$html = array();
		$class = array();
		$attr = '';

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
				$options[0]            = new stdClass;
				$options[0]->value     = 0;
				$options[0]->text      = 'New ID';
			}

			$html[] = JHtml::_('select.genericlist', $options, $this->name, trim($attr), 'value', 'text', $this->value, $this->id);
		}

		return implode($html);
	}
}

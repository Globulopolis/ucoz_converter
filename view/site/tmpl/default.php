<?php
/**
 * UCOZ to Joomla data converter
 *
 * @copyright  Copyright (C) 2020 Libra.ms. All rights reserved.
 * @license    GNU General Public License version 2 or later
 * @url        http://киноархив.com
 */

defined('_JEXEC') or die;
?>
<form action="<?php echo JUri::base(); ?>index.php" method="post" id="languageForm" class="form-horizontal">
	<div class="control-group">
		<label for="jform_language" class="control-label"><?php echo JText::_('INSTL_SELECT_LANGUAGE_TITLE'); ?></label>
		<div class="controls">
			<?php echo $this->form->getInput('language'); ?>
		</div>
	</div>
	<input type="hidden" name="task" value="setlanguage" />
	<?php echo JHtml::_('form.token'); ?>
</form>
<form action="<?php echo JUri::base(); ?>index.php" method="post" id="adminForm" class="form-validate form-horizontal" autocomplete="off">
	<div class="btn-toolbar">
		<div class="btn-group pull-right">
			<button type="submit" class="btn btn-primary"><span class="icon-hdd icon-white"></span> <?php echo JText::_('JSAVE'); ?></button>
		</div>
	</div>
	<h3><?php echo JText::_('INSTL_SITE'); ?></h3>
	<hr class="hr-condensed" />

	<?php echo JHtml::_('bootstrap.startTabSet', 'settings', array('active' => 'page0')); ?>
	<?php echo JHtml::_('bootstrap.addTab', 'settings', 'page0', JText::_('INSTL_TAB1')); ?>

	<div class="row-fluid">
		<div class="span12">
		<?php foreach ($this->form->getFieldset('main') as $field):
			if ($field->name != 'jform[language]'):
		?>
			<div class="control-group">
				<div class="control-label span4"><?php echo $field->label; ?></div>
				<div class="controls span8"><?php echo $field->input; ?></div>
			</div>
			<?php endif;
		endforeach; ?>
		</div>
	</div>

	<?php echo JHtml::_('bootstrap.endTab'); ?>
	<?php echo JHtml::_('bootstrap.addTab', 'settings', 'page1', JText::_('INSTL_TAB7')); ?>

	<div class="row-fluid">
		<div class="span12">
			<?php foreach ($this->form->getFieldset('categories') as $field): ?>
				<div class="control-group">
					<div class="control-label span4"><?php echo $field->label; ?></div>
					<div class="controls span8"><?php echo $field->input; ?></div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<?php echo JHtml::_('bootstrap.endTab'); ?>
	<?php echo JHtml::_('bootstrap.addTab', 'settings', 'page2', JText::_('INSTL_TAB2')); ?>

	<div class="row-fluid">
		<div class="span12">
			<?php foreach ($this->form->getFieldset('blog') as $field): ?>
					<div class="control-group">
						<div class="control-label span4"><?php echo $field->label; ?></div>
						<div class="controls span8"><?php echo $field->input; ?></div>
					</div>
			<?php endforeach; ?>
		</div>
	</div>

	<?php echo JHtml::_('bootstrap.endTab'); ?>
	<?php echo JHtml::_('bootstrap.addTab', 'settings', 'page3', JText::_('INSTL_TAB3')); ?>

	<div class="row-fluid">
		<div class="span12">
			<?php foreach ($this->form->getFieldset('news') as $field): ?>
				<div class="control-group">
					<div class="control-label span4"><?php echo $field->label; ?></div>
					<div class="controls span8"><?php echo $field->input; ?></div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<?php echo JHtml::_('bootstrap.endTab'); ?>
	<?php echo JHtml::_('bootstrap.addTab', 'settings', 'page4', JText::_('INSTL_TAB4')); ?>

	<div class="row-fluid">
		<div class="span12">
			<?php foreach ($this->form->getFieldset('loads') as $field): ?>
				<div class="control-group">
					<div class="control-label span4"><?php echo $field->label; ?></div>
					<div class="controls span8"><?php echo $field->input; ?></div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<?php echo JHtml::_('bootstrap.endTab'); ?>
	<?php echo JHtml::_('bootstrap.addTab', 'settings', 'page5', JText::_('INSTL_TAB5')); ?>

	<div class="row-fluid">
		<div class="span12">
			<?php foreach ($this->form->getFieldset('publ') as $field): ?>
				<div class="control-group">
					<div class="control-label span4"><?php echo $field->label; ?></div>
					<div class="controls span8"><?php echo $field->input; ?></div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<?php echo JHtml::_('bootstrap.endTab'); ?>
	<?php echo JHtml::_('bootstrap.addTab', 'settings', 'page6', JText::_('INSTL_TAB6')); ?>

	<div class="row-fluid">
		<div class="span12">
			<?php foreach ($this->form->getFieldset('users') as $field): ?>
				<div class="control-group">
					<div class="control-label span4"><?php echo $field->label; ?></div>
					<div class="controls span8"><?php echo $field->input; ?></div>
				</div>
			<?php endforeach; ?>

			<div class="control-group">

			</div>
		</div>
	</div>

	<?php echo JHtml::_('bootstrap.endTab'); ?>
	<?php echo JHtml::_('bootstrap.endTabSet'); ?>

	<div class="row-fluid">
		<div class="btn-toolbar">
			<div class="btn-group pull-right">
				<button type="submit" class="btn btn-primary"><span class="icon-hdd icon-white"></span> <?php echo JText::_('JSAVE'); ?></button>
			</div>
		</div>
	</div>
	<input type="hidden" name="task" value="save" />
	<?php echo JHtml::_('form.token'); ?>
</form>

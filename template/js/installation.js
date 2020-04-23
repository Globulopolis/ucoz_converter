/*
 * UCOZ to Joomla data converter
 *
 * @copyright  Copyright (C) 2020 Libra.ms. All rights reserved.
 * @license    GNU General Public License version 2 or later
 * @url        http://киноархив.com
 */

var Installation = function (_container, _base) {
	var $, container, busy, baseUrl, view;

	/**
	 * Initializes JavaScript events on each request, required for AJAX
	 */
	var pageInit = function () {
		// Attach the validator
		$('form.form-validate').each(function (index, form) {
			document.formvalidator.attachToForm(form);
		});

		// Create and append the loading layer.
		Joomla.loadingLayer("load");
	};

	/**
	 * Method to set the language for the installation UI via AJAX
	 *
	 * @return {Boolean}
	 */
	var setlanguage = function () {
		var $form = $('#languageForm');

		if (busy) {
			alert(Joomla.JText._('INSTL_PROCESS_BUSY', 'Process is in progress. Please wait...'));
			return false;
		}

		Joomla.loadingLayer('show');
		busy = true;
		Joomla.removeMessages();
		var data = 'format: json&' + $form.serialize();

		$.ajax({
			type : 'POST',
			url : baseUrl,
			data : data,
			dataType : 'json'
		}).done(function (response) {
			Joomla.replaceTokens(response.token);

			if (response.messages) {
				Joomla.renderMessages(response.messages);
			}

			Joomla.loadingLayer('hide');
			busy = false;

			if (typeof response.lang === 'undefined')
			{
				alert('Could not load or parse language xml file! Check InstallationResponseJson for reply.');
			}

			window.location = baseUrl + '?view=' + response.data.view;
		}).fail(function (xhr, status, error) {
			Joomla.loadingLayer("hide");
			busy = false;

			try {
				var response = JSON.parse(xhr.responseText);
				Joomla.replaceTokens(response.token);
				alert(response.message + error);
			}
			catch (e)
			{
				// Response isn't JSON string, so alert as text.
				alert(error + ': ' + xhr.responseText);
			}
		});

		return false;
	};

	var toggle = function (id, el, value) {
		var val = $('input[name="jform[' + el + ']"]:checked').val(), $id = $('#' + id);
		if (val === value.toString()) {
			$id.show();
		} else {
			$id.hide();
		}
	};

	/**
	 * Initializes the Installation class
	 *
	 * @param _container  The name of the container which the view is rendered in
	 * @param _base       The URL of the current page
	 */
	var initialize = function (_container, _base) {
		$ = jQuery.noConflict();
		busy = false;
		container = _container;
		baseUrl = _base;
		view = '';

		pageInit();
	};
	initialize(_container, _base);

	return {
		setlanguage : setlanguage,
		toggle : toggle
	}
};

/**
 * Initializes the elements
 */
function initElements()
{
	(function ($) {
		$('.hasTooltip').tooltip();

		// Chosen select boxes
		$('select').chosen({
			disable_search_threshold : 10,
			allow_single_deselect : true
		});

		// Reinit Chosen on select when modal is opening
		$('input.form-field-repeatable').on('row-add', function(){
			$('select').chosen({
				disable_search_threshold : 0,
				allow_single_deselect : true,
				placeholder_text: Joomla.JText._('JGLOBAL_SELECT_AN_OPTION')
			});
		});

		// Turn radios into btn-group
		$('.radio.btn-group label').addClass('btn');

		$('.btn-group label:not(.active)').click(function () {
			var label = $(this);
			var input = $('#' + label.attr('for'));

			if (!input.prop('checked'))
			{
				label.closest('.btn-group').find('label').removeClass('active btn-success btn-danger btn-primary');

				if (label.closest('.btn-group').hasClass('btn-group-reverse'))
				{
					if (input.val() == '')
					{
						label.addClass('active btn-primary');
					}
					else if (input.val() == 0)
					{
						label.addClass('active btn-danger');
					}
					else
					{
						label.addClass('active btn-success');
					}
				}
				else
				{
					if (input.val() == '')
					{
						label.addClass('active btn-primary');
					}
					else if (input.val() == 0)
					{
						label.addClass('active btn-success');
					}
					else
					{
						label.addClass('active btn-danger');
					}
				}

				if (label.closest('.btn-group').attr('id') === 'jform_skip_banned' && $('#jform_skip_banned').next('.skip_banned_text').length === 0) {
					$('#jform_skip_banned').after('<div class="skip_banned_text alert"><button type="button" class="close" data-dismiss="alert">&times;</button>' + Joomla.Text._('INSTL_SKIP_BANNED_ALERT', '') + '</div>');
				}

				input.prop('checked', true);
			}
		});

		$('.btn-group input[checked="checked"]').each(function () {
			var $self  = $(this);
			var attrId = $self.attr('id');

			if ($self.hasClass('btn-group-reverse'))
			{
				if ($self.val() == '')
				{
					$('label[for="' + attrId + '"]').addClass('active btn-primary');
				}
				else if ($self.val() == 0)
				{
					$('label[for="' + attrId + '"]').addClass('active btn-danger');
				}
				else
				{
					$('label[for="' + attrId + '"]').addClass('active btn-success');
				}
			}
			else
			{
				if ($self.val() == '')
				{
					$('label[for="' + attrId + '"]').addClass('active btn-primary');
				}
				else if ($self.val() == 0)
				{
					$('label[for="' + attrId + '"]').addClass('active btn-success');
				}
				else
				{
					$('label[for="' + attrId + '"]').addClass('active btn-danger');
				}
			}
		});

		if ($('#jform_skip_banned input').prop('checked'))
		{
			$('#jform_skip_banned').after('<div class="skip_banned_text alert"><button type="button" class="close" data-dismiss="alert">&times;</button>' + Joomla.Text._('INSTL_SKIP_BANNED_ALERT', '') + '</div>');
		}

		// Attach close button to repeatable modal
		$('.inst-subform').find('thead th:last div:last').after('<button type="button" class="close novalidate" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>');
	})(jQuery);
}

// Init on dom content loaded event
document.addEventListener('DOMContentLoaded', function () {

	// Init the elements
	initElements();

	// Init installation
	var installOptions  = Joomla.getOptions('system.installation'),
		installurl = installOptions.url ? installOptions.url.replace(/&amp;/g, '&') : 'index.php';

	window.Install = new Installation('container-installation', installurl);
});

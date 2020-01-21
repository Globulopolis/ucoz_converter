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
	 * Method to submit a form from the installer via AJAX
	 *
	 * @return {Boolean}
	 */
	var submitform = function () {
		var $form = $('#adminForm');

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
		}).done(function (r) {
			Joomla.replaceTokens(r.token);

			if (r.messages) {
				Joomla.renderMessages(r.messages);
			}

			var lang = $('html').attr('lang');

			if (r.lang !== null && lang.toLowerCase() === r.lang.toLowerCase()) {
				Install.goToPage(r.data.view, true);
			}
			else
			{
				window.location = baseUrl + '?view=' + r.data.view;
			}
		}).fail(function (xhr) {
			Joomla.loadingLayer("hide");
			busy = false;

			try {
				var r = $.parseJSON(xhr.responseText);
				Joomla.replaceTokens(r.token);
				alert(r.message);
			}
			catch (e)
			{
				alert(e);
			}
		});

		return false;
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

			var lang = $('html').attr('lang');
			if (lang.toLowerCase() === response.lang.toLowerCase()) {
				Install.goToPage(response.data.view, true);
			}
			else
			{
				window.location = baseUrl + '?view=' + response.data.view;
			}
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

	/**
	 * Method to request a different page via AJAX
	 *
	 * @param  page        The name of the view to request
	 * @param  fromSubmit  Unknown use
	 *
	 * @return {Boolean}
	 */
	var goToPage = function (page, fromSubmit) {
		if (!fromSubmit) {
			Joomla.removeMessages();
			Joomla.loadingLayer("show");
		}

		$.ajax({
			type : "GET",
			url : baseUrl + '?tmpl=body&view=' + page,
			dataType : 'html'
		}).done(function (result) {
			$('#' + container).html(result);
			view = page;

			// Attach JS behaviors to the newly loaded HTML
			pageInit();

			Joomla.loadingLayer("hide");
			busy = false;

			initElements();
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
		submitform : submitform,
		setlanguage : setlanguage,
		goToPage : goToPage,
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

		// Attach close button to repeatable modal
		$('#jform_categories_assoc_table, #jform_userfields_table, #jform_usergroups_table').find('thead th:last div:last').after('<button type="button" class="close novalidate" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>');
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

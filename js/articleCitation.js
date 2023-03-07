/**
 * @file plugins/generic/citationStyleLanguage/js/articleCitation.js
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Fetch and display an article citation on the article's page
 *
 * This script is designed to be compatible with any themes that follow these
 * steps. First, you need a target element that will display the citation in
 * the requested format. This should have an id of `citationOutput`:
 *
 * <div id="citationOutput"></div>
 *
 * You can create a link to retrieve a citation and display it in this div by
 * assigning the link a `data-load-citation` attribute:
 *
 * <a href="{url ...}" data-load-citation="true">View citation in ABNT format</a>
 *
 * Downloadable citations should leave the `data-load-citation` attribute out
 * to allow normal browser download handling.
 *
 * To make use of the dropdown display of citation formats, you must include
 * a button to expand the dropdown with the following attributes:
 *
 *	<button aria-controls="cslCitationFormats" aria-expanded="false" data-csl-dropdown="true">
 *		More Citation Formats
 *	</button>
 *
 * And the dropdown should have the id `cslCitationFormats`:
 *
 * <div id="cslCitationFormats" aria-hidden="true">
 *   // additional citation formats
 * </div>
 *
 * This script requires jQuery. The format you specify should match
 * a format provided by a CitationFormat plugin.
 */

(function($) {

	// Require jQuery
	if (typeof $ === 'undefined') {
		return;
	}

	var citationOutput = $('#citationOutput'),
		citationFormatLinks = $('[data-load-citation]'),
		citationFormatBtn = $('[aria-controls="cslCitationFormats"]'),
		citationFormatDropdown = $('#cslCitationFormats');

	// Fetch new citations and update diplayed citation
	if (!citationOutput.length || !citationFormatLinks.length) {
		return;
	}

	citationFormatLinks.click(function(e) {

		if (!$(this).data('json-href')) {
			return true;
		}

		e.preventDefault();

		var url = $(this).data('json-href');

		citationOutput.css('opacity', 0.5);

		$.ajax({url: url, dataType: 'json'})
			.done(function(r) {
				citationOutput.html(r.content)
					.hide()
					.css('opacity', 1)
					.fadeIn();
			})
			.fail(function(r) {
				citationOutput.css('opacity', 1);
			});
	});

	// Display dropdown with more citation formats
	if (!citationFormatBtn.length || !citationFormatDropdown.length) {
		return;
	}

	citationFormatBtn.click(function(e) {
		e.preventDefault();
		e.stopPropagation();

		var state = citationFormatBtn.attr('aria-expanded');

		if (state === "true") {
			citationFormatBtn.attr('aria-expanded', false);
			citationFormatDropdown.attr('aria-hidden', true);
		} else {
			citationFormatBtn.attr('aria-expanded', true);
			citationFormatDropdown.attr('aria-hidden', false);
		}
	});

	$('a, button', citationFormatDropdown).click(function(e) {
		citationFormatBtn.attr('aria-expanded', false);
		citationFormatDropdown.attr('aria-hidden', true);
	});

})(jQuery);

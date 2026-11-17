/**
 * @file plugins/generic/citationStyleLanguage/js/articleCitation.js
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2000-2026 John Willinsky
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

document.addEventListener('DOMContentLoaded', () => {
	// Get the citation elements
	const citationOutput = document.getElementById('citationOutput');
	const citationFormatLinks = document.querySelectorAll('[data-load-citation]');
	const citationFormatBtn = document.querySelector(
		'[aria-controls="cslCitationFormats"]'
	);
	const citationFormatDropdown = document.getElementById('cslCitationFormats');

	function describeSelection(link) {
		const label = citationFormatBtn && citationFormatBtn.dataset.selectedLabel;
		if (!label) {
			return;
		}
		citationFormatBtn.setAttribute(
			'aria-label',
			label.replace('{$format}', link.textContent.trim())
		);
	}

	// Check if the required elements exist
	if (!citationOutput || citationFormatLinks.length === 0) {
		return;
	}

	// Fetch new citations and update diplayed citation
	citationFormatLinks.forEach((link) => {
		link.addEventListener('click', (e) => {
			const jsonHref = link.dataset.jsonHref;

			if (!jsonHref) {
				return true;
			}

			e.preventDefault();
			const previousLabel = citationFormatBtn && citationFormatBtn.getAttribute('aria-label');
			describeSelection(link);
			citationOutput.classList.remove('fade-in');
			citationOutput.style.opacity = '0.5';

			fetch(jsonHref)
				.then((response) => response.json())
				.then((r) => {
					citationOutput.innerHTML = r.content;
					citationOutput.classList.add('fade-in');
					citationOutput.style.opacity = '1';
					citationFormatLinks.forEach((choice) => choice.removeAttribute('aria-current'));
					link.setAttribute('aria-current', 'true');
				})
				.catch(() => {
					citationOutput.style.opacity = '1';
					if (!citationFormatBtn) {
						return;
					}
					if (previousLabel) {
						citationFormatBtn.setAttribute('aria-label', previousLabel);
					} else {
						citationFormatBtn.removeAttribute('aria-label');
					}
				});
		});
	});

	// Check if the required elements for the dropdown exist
	if (!citationFormatBtn || !citationFormatDropdown) {
		return;
	}

	const currentChoice = citationFormatDropdown.querySelector('[data-load-citation][aria-current="true"]');
	if (currentChoice) {
		describeSelection(currentChoice);
	}

	// Function to handle dropdown display for more citation formats
	citationFormatBtn.addEventListener('click', (e) => {
		e.preventDefault();
		e.stopPropagation();

		const state = citationFormatBtn.getAttribute('aria-expanded') === 'true';

		citationFormatBtn.setAttribute('aria-expanded', !state);
		citationFormatDropdown.setAttribute('aria-hidden', state);
	});

	// Close the dropdown when any link or button inside is clicked
	citationFormatDropdown.querySelectorAll('a, button').forEach((elem) => {
		elem.addEventListener('click', () => {
			if (citationFormatDropdown.contains(document.activeElement)) {
				citationFormatBtn.focus();
			}
			citationFormatBtn.setAttribute('aria-expanded', false);
			citationFormatDropdown.setAttribute('aria-hidden', true);
		});
	});
});

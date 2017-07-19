<?php

/**
 * @file plugins/generic/citationStyleLanguage/CitationStyleLanguageHandler.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 *
 * @class CitationStyleLanguageHandler
 * @ingroup plugins_generic_citationstylelanguage
 *
 * @brief Handle router requests for the citation style language plugin
 */

import('classes.handler.Handler');

class CitationStyleLanguageHandler extends Handler {
	/** @var PublishedArticle article being requested */
	public $article = null;

	/** @var array citation style being requested */
	public $citationStyle = '';

	/** @var bool Whether or not to return citation in JSON format */
	public $returnJson = false;

	/**
	 * Get a citation style
	 *
	 * @param $args array
	 * @param $request PKPRequest
	 * @return null|JSONMessage
	 */
	public function get($args, $request) {
		$this->_setupRequest($args, $request);
		$plugin = PluginRegistry::getPlugin('generic', 'citationstylelanguageplugin');
		$citation = $plugin->getCitation($this->article, $this->citationStyle);

		if ($citation === false ) {
			if ($this->returnJson) {
				return new JSONMessage(false);
			}
			exit;
		}

		if ($this->returnJson) {
			return new JSONMessage(true, $citation);
		}

		echo $citation;
		exit;
	}

	/**
	 * Download a citation in a downloadable format
	 *
	 * @param $args array
	 * @param $request PKPRequest
	 */
	public function download($args, $request) {
		$this->_setupRequest($args, $request);
		$plugin = PluginRegistry::getPlugin('generic', 'citationstylelanguageplugin');
		$plugin->downloadCitation($this->article, $this->citationStyle);
		exit;
	}

	/**
	 * Generate a citation based on page parameters
	 *
	 * @param $args array
	 * @param $request PKPRequest
	 */
	public function _setupRequest($args, $request) {
		$userVars = $request->getUserVars();
		$journal = $request->getContext();

		if (!isset($userVars['submissionId']) || !is_array($args) || empty($args) || !$journal) {
			return false;
		}

		$this->citationStyle = $args[0];
		$this->returnJson = isset($userVars['return']) && $userVars['return'] === 'json';

		$publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO');
		$this->article = $publishedArticleDao->getPublishedArticleByBestArticleId($journal->getId(), (int) $userVars['submissionId'], true);

		assert(is_a($this->article, 'PublishedArticle') && !empty($this->citationStyle));
	}
}

?>

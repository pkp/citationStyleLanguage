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

		if (!$this->article) {
			return new JSONMessage(false);
		}

		$plugin = PluginRegistry::getPlugin('generic', 'citationstylelanguageplugin');
		$citation = $plugin->getCitation($request, $this->article, $this->citationStyle);

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

		if (!$this->article) {
			return new JSONMessage(false);
		}

		$plugin = PluginRegistry::getPlugin('generic', 'citationstylelanguageplugin');
		$plugin->downloadCitation($request, $this->article, $this->citationStyle);
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
		$user = $request->getUser();
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : 0;

		assert(isset($userVars['submissionId']) && is_array($args) && !empty($args) && $journal);

		// Load plugin categories which might need to add data to the citation
		PluginRegistry::loadCategory('pubIds', true);
		PluginRegistry::loadCategory('metadata', true);

		$this->citationStyle = $args[0];
		$this->returnJson = isset($userVars['return']) && $userVars['return'] === 'json';

		$publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO');
		$this->article = $publishedArticleDao->getPublishedArticleByBestArticleId($journal->getId(), $userVars['submissionId'], true);

		$issueDao = DAORegistry::getDAO('IssueDAO');
		$issue = $issueDao->getById($this->article->getIssueId(), $contextId);

		assert(is_a($this->article, 'PublishedArticle') && !empty($this->citationStyle));

		// Disallow access to unpublished submissions, unless the user is a
		// journal manager or an assigned subeditor or assistant. This ensures the
		// article preview will work for those who can see it.
		if (!$issue || !$issue->getPublished() || $this->article->getStatus() != STATUS_PUBLISHED) {
			$userCanAccess = false;

			if ($user && $user->hasRole([ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT], $contextId)) {
				$isAssigned = false;
				$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
				$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
				$assignments = $stageAssignmentDao->getBySubmissionAndStageId($this->article->getId());
				foreach ($assignments as $assignment) {
					if ($assignment->getUser()->getId() !== $user->getId()) {
						continue;
					}
					$userGroup = $userGroupDao->getById($assignment->getUserGroupId($contextId));
					if (in_array($userGroup->getRoleId(), [ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT])) {
						$userCanAccess = true;
						break;
					}
				}
			}

			if ($user && $user->hasRole(ROLE_ID_MANAGER, $contextId)) {
				$userCanAccess = true;
			}

			if (!$userCanAccess) {
				$this->article = null;
			}
		}
	}
}

?>

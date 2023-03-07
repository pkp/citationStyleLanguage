<?php

/**
 * @file plugins/generic/citationStyleLanguage/CitationStyleLanguageHandler.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 *
 * @class CitationStyleLanguageHandler
 * @ingroup plugins_generic_citationStyleLanguage
 *
 * @brief Handle router requests for the citation style language plugin
 */

namespace APP\plugins\generic\citationStyleLanguage\pages;

use APP\core\Application;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\issue\Issue;
use APP\monograph\Chapter;
use APP\plugins\generic\citationStyleLanguage\CitationStyleLanguagePlugin;
use APP\publication\Publication;
use APP\submission\Submission;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\plugins\PluginRegistry;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignmentDAO;
use PKP\submission\PKPSubmission;

class CitationStyleLanguageHandler extends Handler
{
    /** @var null|Submission $submission being requested */
    public ?Submission $submission = null;

    /** @var null|Publication $publication being requested */
    public ?Publication $publication = null;

    /** @var null|Chapter $chapter being requested  */
    public ?Chapter $chapter = null;

    /** @var Issue issue of the publication being requested */
    public ?Issue $issue = null;

    /** @var array citation style being requested */
    public $citationStyle = '';

    /** @var bool Whether or not to return citation in JSON format */
    public $returnJson = false;

    /**
     * Constructor
     */
    public function __construct(public CitationStyleLanguagePlugin $plugin) {
        parent::__construct();
    }

    /**
     * Get a citation style
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return null|JSONMessage
     */
    public function get($args, $request)
    {
        $this->_setupRequest($args, $request);

        $plugin = $this->plugin;
        if (null === $plugin) {
            $request->getDispatcher()->handle404();
        }
        $citation = $plugin->getCitation($request, $this->submission, $this->citationStyle, $this->issue, $this->publication, $this->chapter);

        if ($citation === false) {
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
     * @param array $args
     * @param PKPRequest $request
     */
    public function download($args, $request)
    {
        $this->_setupRequest($args, $request);

        $plugin = $this->plugin;
        if (null !== $plugin) {
            $plugin->downloadCitation($request, $this->submission, $this->citationStyle, $this->issue, $this->publication, $this->chapter);
        }
        exit;
    }

    protected function isSubmissionUnpublished($submission, $issue = null) {
        $applicationName = Application::get()->getName();

        if ($applicationName === 'ojs2') {
            return !$issue || !$issue->getPublished() || $submission->getStatus() != PKPSubmission::STATUS_PUBLISHED;
        }

        return $submission->getStatus() != PKPSubmission::STATUS_PUBLISHED;
    }

    /**
     * Generate a citation based on page parameters
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function _setupRequest($args, $request)
    {
        $userVars = $request->getUserVars();
        $user = $request->getUser();
        $context = $request->getContext();

        if (empty($userVars['submissionId']) || !$context || empty($args)) {
            $request->getDispatcher()->handle404();
        }

        // Load plugin categories which might need to add data to the citation
        PluginRegistry::loadCategory('pubIds', true);
        PluginRegistry::loadCategory('metadata', true);

        $this->citationStyle = $args[0];
        $this->returnJson = ($userVars['return'] ?? null) === 'json';
        $this->submission = Repo::submission()->get((int) $userVars['submissionId']);

        if (!$this->submission) {
            $request->getDispatcher()->handle404();
        }

        $this->publication = !empty($userVars['publicationId'])
            ? Repo::publication()->get((int) $userVars['publicationId'])
            : $this->submission->getCurrentPublication();

        if (!empty($userVars['chapterId'])) {
            $chapterDao = DAORegistry::getDAO('ChapterDAO'); /** @var ChapterDAO $chapterDao */
            $this->chapter = $chapterDao->getChapter((int) $userVars['chapterId'], $this->publication->getId());
        }

        if ($this->submission && $this->plugin->application === 'ojs2') {
            $this->issue = $userVars['issueId'] ? Repo::issue()->get((int) $userVars['issueId']) : null;
        }

        // Disallow access to unpublished submissions, unless the user is a
        // journal manager or an assigned subeditor or assistant. This ensures the
        // submission preview will work for those who can see it.
        if (($this->plugin->application !== 'omp' && !$this->issue )
            || $this->isSubmissionUnpublished($this->submission, $this->issue)
            || ($this->plugin->application !== 'omp' && !$this->issue->getPublished())) {
            $userRoles = $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_USER_ROLES);
            if (!$this->canUserAccess($context, $user, $userRoles)) {
                $request->getDispatcher()->handle404();
            }
        }
    }

    protected function canUserAccess($context, $user, $userRoles) {
        if ($user && !empty(array_intersect($userRoles, [Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT]))) {
            /** @var StageAssignmentDAO */
            $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
            $assignments = $stageAssignmentDao->getBySubmissionAndStageId($this->submission->getId());
            foreach ($assignments as $assignment) {
                if ($assignment->getUser()->getId() == $user->getId()) {
                    continue;
                }
                $userGroup = Repo::userGroup()->get($assignment->getUserGroupId($context->getId()));
                if (in_array($userGroup->getRoleId(), [Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT])) {
                    return true;
                }
            }
        }

        if ($user && count(array_intersect([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], $userRoles))) {
            return true;
        }

        return false;
    }
}

<?php
/**
 * @file CitationStyleLanguageSettingsForm.php
 *
 * Copyright (c) 2017-2020 Simon Fraser University
 * Copyright (c) 2017-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CitationStyleLanguageSettingsForm.inc
 *
 * @ingroup plugins_generic_citationStyleLanguage
 *
 * @brief Form for site admins to modify Citation Style Language settings.
 */

namespace APP\plugins\generic\citationStyleLanguage;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;
use PKP\notification\Notification;
use PKP\security\Role;

class CitationStyleLanguageSettingsForm extends Form
{
    public CitationStyleLanguagePlugin $plugin;

    /**
     * Constructor
     *
     * @param CitationStyleLanguagePlugin $plugin object
     */
    public function __construct(CitationStyleLanguagePlugin $plugin)
    {
        parent::__construct($plugin->getTemplateResource('settings.tpl'));
        $this->plugin = $plugin;
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
    * @copydoc Form::init
    */
    public function initData(): void
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $contextId = $context->getId();
        $this->setData('primaryCitationStyle', $this->plugin->getSetting($contextId, 'primaryCitationStyle'));
        $this->setData('enabledCitationStyles', array_keys($this->plugin->getEnabledCitationStyles($contextId)));
        $this->setData('enabledCitationDownloads', $this->plugin->getEnabledCitationDownloads($contextId));
        $this->setData('publisherLocation', $this->plugin->getSetting($contextId, 'publisherLocation'));
        $this->setData('groupAuthor', $this->plugin->getAuthorGroups($contextId));
        $this->setData('groupTranslator', $this->plugin->getTranslatorGroups($contextId));
        if ($this->plugin->application === 'omp') {
            $this->setData('groupEditor', $this->plugin->getEditorGroups($contextId));
            $this->setData('groupChapterAuthor', $this->plugin->getChapterAuthorGroups($contextId));
        }
    }

    /**
     * Assign form data to user-submitted data.
     */
    public function readInputData(): void
    {
        $this->readUserVars([
            'primaryCitationStyle',
            'enabledCitationStyles',
            'enabledCitationDownloads',
            'publisherLocation',
            'groupAuthor',
            'groupTranslator'
        ]);
        if ($this->plugin->application === 'omp') {
            $this->readUserVars(['groupEditor']);
            $this->readUserVars(['groupChapterAuthor']);
        }
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false): ?string
    {
        $context = $request->getContext();
        $contextId = $context->getId();

        $allStyles = [];
        foreach ($this->plugin->getCitationStyles() as $style) {
            $allStyles[$style['id']] = $style['title'];
        }

        $allDownloads = [];
        foreach ($this->plugin->getCitationDownloads() as $style) {
            $allDownloads[$style['id']] = $style['title'];
        }

        $allUserGroups = [];
        $userGroups = Repo::userGroup()->getByRoleIds([Role::ROLE_ID_AUTHOR], $contextId)->all();
        foreach ($userGroups as $userGroup) {
            $allUserGroups[(int) $userGroup->id] = $userGroup->getLocalizedData('name');
        }
        asort($allUserGroups);

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'pluginName' => $this->plugin->getName(),
            'allDownloads' => $allDownloads,
            'allStyles' => $allStyles,
            'primaryCitationStyle' => $this->getData('primaryCitationStyle'),
            'enabledStyles' => $this->plugin->mapCitationIds($this->plugin->getEnabledCitationStyles($contextId)),
            'enabledDownloads' => $this->plugin->mapCitationIds($this->plugin->getEnabledCitationDownloads($contextId)),
            'application' => $this->plugin->application,
            'groupAuthor' => $this->getData('groupAuthor'),
            'groupTranslator' => $this->getData('groupTranslator'),
            'allUserGroups' => $allUserGroups,
        ]);

        if ($this->plugin->application === 'omp') {
            $templateMgr->assign([
                'groupEditor' => $this->getData('groupEditor'),
                'groupChapterAuthor' => $this->getData('groupChapterAuthor'),
            ]);
        }

        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $contextId = $context->getId();
        $this->plugin->updateSetting($contextId, 'primaryCitationStyle', $this->getData('primaryCitationStyle'));
        $enabledCitationStyles = $this->getData('enabledCitationStyles') ?: [];
        $this->plugin->updateSetting($contextId, 'enabledCitationStyles', $enabledCitationStyles);
        $enabledCitationDownloads = $this->getData('enabledCitationDownloads') ?: [];
        $this->plugin->updateSetting($contextId, 'enabledCitationDownloads', $enabledCitationDownloads);
        $this->plugin->updateSetting($contextId, 'publisherLocation', $this->getData('publisherLocation'));
        $this->plugin->updateSetting($contextId, 'groupAuthor', $this->getData('groupAuthor'));
        $this->plugin->updateSetting($contextId, 'groupTranslator', $this->getData('groupTranslator'));
        if ($this->plugin->application === 'omp') {
            $this->plugin->updateSetting($contextId, 'groupEditor', $this->getData('groupEditor'));
            $this->plugin->updateSetting($contextId, 'groupChapterAuthor', $this->getData('groupChapterAuthor'));
        }

        $notificationMgr = new NotificationManager();
        $user = $request->getUser();
        $notificationMgr->createTrivialNotification($user->getId(), Notification::NOTIFICATION_TYPE_SUCCESS, ['contents' => __('common.changesSaved')]);

        return parent::execute(...$functionArgs);
    }
}

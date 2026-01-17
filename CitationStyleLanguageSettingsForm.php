<?php

/**
 * @file CitationStyleLanguageSettingsForm.php
 *
 * Copyright (c) 2017-2026 Simon Fraser University
 * Copyright (c) 2017-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CitationStyleLanguageSettingsForm
 *
 * @ingroup plugins_generic_citationStyleLanguage
 *
 * @brief Form for site admins to modify Citation Style Language settings.
 */

namespace APP\plugins\generic\citationStyleLanguage;

use APP\core\Application;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;
use PKP\notification\Notification;

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
        ]);
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

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'pluginName' => $this->plugin->getName(),
            'allDownloads' => $allDownloads,
            'allStyles' => $allStyles,
            'primaryCitationStyle' => $this->getData('primaryCitationStyle'),
            'enabledStyles' => $this->plugin->mapCitationIds($this->plugin->getEnabledCitationStyles($contextId)),
            'enabledDownloads' => $this->plugin->mapCitationIds($this->plugin->getEnabledCitationDownloads($contextId)),
            'application' => $this->plugin->application,
        ]);

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

        $notificationMgr = new NotificationManager();
        $user = $request->getUser();
        $notificationMgr->createTrivialNotification($user->getId(), Notification::NOTIFICATION_TYPE_SUCCESS, ['contents' => __('common.changesSaved')]);

        return parent::execute(...$functionArgs);
    }
}

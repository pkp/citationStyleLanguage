<?php
/**
 * @file CitationStyleLanguageSettingsForm.inc.inc.php
 *
 * Copyright (c) 2017-2020 Simon Fraser University
 * Copyright (c) 2017-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CitationStyleLanguageSettingsForm.inc
 * @ingroup plugins_generic_citationStyleLanguage
 *
 * @brief Form for site admins to modify Citation Style Language settings.
 */


import('lib.pkp.classes.form.Form');

class CitationStyleLanguageSettingsForm extends Form {

	/** @var $plugin object */
	public $plugin;

	/** @var null|bool ChapterFrontendPagePluginEnabled */
	private ?bool $ChapterFrontendPagePluginEnabled;

	/**
	 * Constructor
	 * @param $plugin object
	 */
	public function __construct($plugin) {
		parent::__construct($plugin->getTemplateResource('settings.tpl'));
		$this->plugin = $plugin;
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
		$this->ChapterFrontendPagePluginEnabled = null;
	}

	/**
	* @copydoc Form::init
	*/
	public function initData() {
		$request = Application::get()->getRequest();
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : 0;
		$this->setData('primaryCitationStyle', $this->plugin->getSetting($contextId, 'primaryCitationStyle'));
		$this->setData('enabledCitationStyles', array_keys($this->plugin->getEnabledCitationStyles($contextId)));
		$this->setData('enabledCitationDownloads', $this->plugin->getEnabledCitationDownloads($contextId));
		$this->setData('publisherLocation', $this->plugin->getSetting($contextId, 'publisherLocation'));
		$this->setData('groupAuthor', $this->plugin->getAuthorGroup($contextId));
		$this->setData('groupTranslator', $this->plugin->getTranslatorGroup($contextId));
		if ($this->plugin->isApplicationOmp()) {
			$this->setData('groupEditor', $this->plugin->getEditorGroup($contextId));
			if ($this->isChapterFrontendPagePluginEnabled()) {
				$this->setData('groupChapterAuthor', $this->plugin->getChapterAuthorGroup($contextId));
			}
		}
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	public function readInputData() {
		$this->readUserVars(array(
			'primaryCitationStyle',
			'enabledCitationStyles',
			'enabledCitationDownloads',
			'publisherLocation',
			'groupAuthor',
			'groupTranslator'
		));
		if ($this->plugin->isApplicationOmp()) {
			$this->readUserVars(['groupEditor']);
			if ($this->isChapterFrontendPagePluginEnabled()) {
				$this->readUserVars(['groupChapterAuthor']);
			}
		}
	}

	/**
	 * @copydoc Form::fetch()
	 */
	public function fetch($request, $template = null, $display = false) {
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : 0;

		$allStyles = [];
		foreach ($this->plugin->getCitationStyles() as $style) {
			$allStyles[$style['id']] = $style['title'];
		}

		$allDownloads = [];
		foreach ($this->plugin->getCitationDownloads() as $style) {
			$allDownloads[$style['id']] = $style['title'];
		}

		$allUserGroups = [];
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
		$userGroups = $userGroupDao->getByRoleId( $contextId, ROLE_ID_AUTHOR );
		while ($userGroup = $userGroups->next()) {
			$allUserGroups[(int) $userGroup->getId()] = $userGroup->getLocalizedName();
		}
		asort($allUserGroups);

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'pluginName' => $this->plugin->getName(),
			'allDownloads' => $allDownloads,
			'allStyles' => $allStyles,
			'primaryCitationStyle' => $this->getData('primaryCitationStyle'),
			'enabledStyles' => $this->plugin->mapCitationIds($this->plugin->getEnabledCitationStyles($contextId)),
			'enabledDownloads' => $this->plugin->mapCitationIds($this->plugin->getEnabledCitationDownloads($contextId)),
			'isApplicationOmp' => $this->plugin->isApplicationOmp(),
			'groupAuthor' => $this->getData('groupAuthor'),
			'groupTranslator' => $this->getData('groupTranslator'),
			'allUserGroups' => $allUserGroups,
		));
		if ($this->plugin->isApplicationOmp()) {
			$templateMgr->assign([
				'groupEditor' => $this->getData('groupEditor'),
				'isChapterFrontendPagePluginEnabled' => $this->isChapterFrontendPagePluginEnabled()
			]);
			if ($this->isChapterFrontendPagePluginEnabled()) {
				$templateMgr->assign(['groupChapterAuthor' => $this->getData('groupChapterAuthor')]);
			}
		}

		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc Form::execute()
	 */
	public function execute(...$functionArgs) {
		$request = Application::get()->getRequest();
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : 0;
		$this->plugin->updateSetting($contextId, 'primaryCitationStyle', $this->getData('primaryCitationStyle'));
		$enabledCitationStyles = $this->getData('enabledCitationStyles') ? $this->getData('enabledCitationStyles') : array();
		$this->plugin->updateSetting($contextId, 'enabledCitationStyles', $enabledCitationStyles);
		$enabledCitationDownloads = $this->getData('enabledCitationDownloads') ? $this->getData('enabledCitationDownloads') : array();
		$this->plugin->updateSetting($contextId, 'enabledCitationDownloads', $enabledCitationDownloads);
		$this->plugin->updateSetting($contextId, 'publisherLocation', $this->getData('publisherLocation'));
		$this->plugin->updateSetting($contextId, 'groupAuthor', $this->getData('groupAuthor'));
		$this->plugin->updateSetting($contextId, 'groupTranslator', $this->getData('groupTranslator'));
		if( $this->plugin->isApplicationOmp()){
			$this->plugin->updateSetting($contextId, 'groupEditor', $this->getData('groupEditor'));
			if( $this->isChapterFrontendPagePluginEnabled() ){
				$this->plugin->updateSetting($contextId, 'groupChapterAuthor', $this->getData('groupChapterAuthor'));
			}
		}

		import('classes.notification.NotificationManager');
		$notificationMgr = new NotificationManager();
		$user = $request->getUser();
		$notificationMgr->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('common.changesSaved')));

		return parent::execute(...$functionArgs);
	}

	/**
	 * @return bool
	 */
	private function isChapterFrontendPagePluginEnabled() : bool {
		if (null === $this->ChapterFrontendPagePluginEnabled) {
			if ($this->plugin->isApplicationOmp()) {
				$request = Application::get()->getRequest();
				$context = $request->getContext();
				$contextId = $context ? $context->getId() : 0;
				$chapterPlugin = PluginRegistry::getPlugin('generic', 'chapterfrontendpageplugin');
				$this->ChapterFrontendPagePluginEnabled = null !== $chapterPlugin && $chapterPlugin->getEnabled($contextId);
			} else {
				$this->ChapterFrontendPagePluginEnabled = false;
			}
		}
		return $this->ChapterFrontendPagePluginEnabled;
	}
}

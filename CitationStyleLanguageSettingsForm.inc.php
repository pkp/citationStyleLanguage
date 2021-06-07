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

	/** @var bool $isChapterFrontendPagePluginEnabled */
	private bool $isChapterFrontendPagePluginEnabled;

	/**
	 * Constructor
	 * @param $plugin object
	 */
	public function __construct($plugin) {
		parent::__construct($plugin->getTemplateResource('settings.tpl'));
		$this->plugin = $plugin;
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
		if ($this->plugin->isApplicationOmp()) {
			$request = Application::get()->getRequest();
			$context = $request->getContext();
			$contextId = $context ? $context->getId() : 0;
			$chapterPlugin = PluginRegistry::getPlugin('generic', 'chapterfrontendpageplugin');
			$this->isChapterFrontendPagePluginEnabled = NULL !== $chapterPlugin && $chapterPlugin->getEnabled($contextId);
		}
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
		$this->setData('groupAuthor', $this->plugin->getSetting($contextId, 'groupAuthor'));
		$this->setData('groupTranslator', $this->plugin->getSetting($contextId, 'groupTranslator'));
		if( $this->plugin->isApplicationOmp()){
			$this->setData('groupEditor', $this->plugin->getSetting($contextId, 'groupEditor'));
			if( $this->isChapterFrontendPagePluginEnabled ){
				$this->setData('groupChapterAuthor', $this->plugin->getSetting($contextId, 'groupChapterAuthor'));
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
		if( $this->plugin->isApplicationOmp()){
			$this->readUserVars(['groupEditor']);
			if( $this->isChapterFrontendPagePluginEnabled ){
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
		$userGroups = $userGroupDao->getByContextId($contextId);
		while ($userGroup = $userGroups->next()) {
			$allUserGroups[(int) $userGroup->getId()] = $userGroup->getLocalizedName();
		}

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'pluginName' => $this->plugin->getName(),
			'allDownloads' => $allDownloads,
			'allStyles' => $allStyles,
			'primaryCitationStyle' => $this->getData('primaryCitationStyle'),
			'enabledStyles' => $this->plugin->mapCitationIds($this->plugin->getEnabledCitationStyles($contextId)),
			'enabledDownloads' => $this->plugin->mapCitationIds($this->plugin->getEnabledCitationDownloads($contextId)),
			'isApplicationOmp' => $this->plugin->isApplicationOmp(),
			'groupAuthor' => $this->getData('groupAuthor') ? (int) $this->getData('groupAuthor') : 0,
			'groupEditor' => $this->getData('groupEditor') ? (int) $this->getData('groupEditor') : 0,
			'groupTranslator' => $this->getData('groupTranslator') ? (int) $this->getData('groupTranslator') : 0,
			'allUserGroups' => $allUserGroups,
		));
		if( $this->plugin->isApplicationOmp()){
			$templateMgr->assign([
				'groupEditor' => $this->getData('groupEditor') ? (int) $this->getData('groupEditor') : 0,
				'isChapterFrontendPagePluginEnabled' => $this->isChapterFrontendPagePluginEnabled
			]);
			if( $this->isChapterFrontendPagePluginEnabled ){
				$templateMgr->assign(['groupChapterAuthor' => $this->getData('groupChapterAuthor') ? (int) $this->getData('groupChapterAuthor') : 0]);
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
			if( $this->isChapterFrontendPagePluginEnabled ){
				$this->plugin->updateSetting($contextId, 'groupChapterAuthor', $this->getData('groupChapterAuthor'));
			}
		}

		import('classes.notification.NotificationManager');
		$notificationMgr = new NotificationManager();
		$user = $request->getUser();
		$notificationMgr->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('common.changesSaved')));

		return parent::execute(...$functionArgs);
	}
}

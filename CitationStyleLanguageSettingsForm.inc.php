<?php
/**
 * @file CitationStyleLanguageSettingsForm.inc.inc.php
 *
 * Copyright (c) 2017-2018 Simon Fraser University
 * Copyright (c) 2017-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
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

	/**
	 * Constructor
	 * @param $plugin object
	 */
	public function __construct($plugin) {
		parent::__construct($plugin->getTemplatePath() . 'settings.tpl');
		$this->plugin = $plugin;
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	* @copydoc Form::init
	*/
	public function initData() {
		$request = Application::getRequest();
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : 0;
		$this->setData('primaryCitationStyle', $this->plugin->getSetting($contextId, 'primaryCitationStyle'));
		$this->setData('enabledCitationStyles', array_keys($this->plugin->getEnabledCitationStyles($contextId)));
		$this->setData('enabledCitationDownloads', $this->plugin->getEnabledCitationDownloads($contextId));
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	public function readInputData() {
		$this->readUserVars(array(
			'primaryCitationStyle',
			'enabledCitationStyles',
			'enabledCitationDownloads',
		));
	}

	/**
	 * @copydoc Form::fetch()
	 */
	public function fetch($request, $template = null, $display = false) {
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : 0;

		import('lib.pkp.controllers.list.SelectListHandler');

		$primaryCitationStyleList = new SelectListHandler(array(
			'title' => 'plugins.generic.citationStyleLanguage.settings.citationFormatsPrimary',
			'notice' => 'plugins.generic.citationStyleLanguage.settings.citationFormatsPrimaryDescription',
			'inputName' => 'primaryCitationStyle',
			'inputType' => 'radio',
			'selected' => array($this->getData('primaryCitationStyle')),
			'items' => $this->plugin->getCitationStyles(),
		));

		$citationStylesList = new SelectListHandler(array(
			'title' => 'plugins.generic.citationStyleLanguage.settings.citationFormats',
			'notice' => 'plugins.generic.citationStyleLanguage.settings.citationFormatsDescription',
			'inputName' => 'enabledCitationStyles[]',
			'selected' => $this->plugin->mapCitationIds($this->plugin->getEnabledCitationStyles($contextId)),
			'items' => $this->plugin->getCitationStyles(),
		));

		$citationDownloadsList = new SelectListHandler(array(
			'title' => 'plugins.generic.citationStyleLanguage.settings.citationDownloads',
			'notice' => 'plugins.generic.citationStyleLanguage.settings.citationDownloadsDescription',
			'inputName' => 'enabledCitationDownloads[]',
			'selected' => $this->plugin->mapCitationIds($this->plugin->getEnabledCitationDownloads($contextId)),
			'items' => $this->plugin->getCitationDownloads(),
		));

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'pluginName' => $this->plugin->getName(),
			'primaryCitationStyleListData' => json_encode($primaryCitationStyleList->getConfig()),
			'citationStylesListData' => json_encode($citationStylesList->getConfig()),
			'citationDownloadsListData' => json_encode($citationDownloadsList->getConfig()),
		));

		return parent::fetch($request, $template, $display);
	}

	/**
	 * Save settings.
	 */
	public function execute() {
		$request = Application::getRequest();
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : 0;
		$this->plugin->updateSetting($contextId, 'primaryCitationStyle', $this->getData('primaryCitationStyle'));
		$enabledCitationStyles = $this->getData('enabledCitationStyles') ? $this->getData('enabledCitationStyles') : array();
		$this->plugin->updateSetting($contextId, 'enabledCitationStyles', $enabledCitationStyles);
		$enabledCitationDownloads = $this->getData('enabledCitationDownloads') ? $this->getData('enabledCitationDownloads') : array();
		$this->plugin->updateSetting($contextId, 'enabledCitationDownloads', $enabledCitationDownloads);

		import('classes.notification.NotificationManager');
		$notificationMgr = new NotificationManager();
		$user = $request->getUser();
		$notificationMgr->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('common.changesSaved')));

		return parent::execute();
	}
}

?>

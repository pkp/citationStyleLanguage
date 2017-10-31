<?php
/**
 * @file CitationStyleLanguageSettingsForm.inc.inc.php
 *
 * Copyright (c) 2017 Simon Fraser University
 * Copyright (c) 2017 John Willinsky
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
	public function initData($request) {
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
	 * Fetch the form.
	 * @copydoc Form::fetch()
	 */
	public function fetch($request) {
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : 0;

		$primaryCitationStyleListData = array(
			'inputName' => 'primaryCitationStyle',
			'inputType' => 'radio',
			'selected' => array($this->getData('primaryCitationStyle')),
			'collection' => array(
				'items' => $this->plugin->getCitationStyles(),
			),
			'i18n' => array(
				'title' => __('plugins.generic.citationStyleLanguage.settings.citationFormatsPrimary'),
				'notice' => __('plugins.generic.citationStyleLanguage.settings.citationFormatsPrimaryDescription'),
			),
		);

		$citationStylesListData = array(
			'inputName' => 'enabledCitationStyles[]',
			'selected' => $this->plugin->mapCitationIds($this->plugin->getEnabledCitationStyles($contextId)),
			'collection' => array(
				'items' => $this->plugin->getCitationStyles(),
			),
			'i18n' => array(
				'title' => __('plugins.generic.citationStyleLanguage.settings.citationFormats'),
				'notice' => __('plugins.generic.citationStyleLanguage.settings.citationFormatsDescription'),
			),
		);

		$citationDownloadsListData = array(
			'inputName' => 'enabledCitationDownloads[]',
			'selected' => $this->plugin->mapCitationIds($this->plugin->getEnabledCitationDownloads($contextId)),
			'collection' => array(
				'items' => $this->plugin->getCitationDownloads(),
			),
			'i18n' => array(
				'title' => __('plugins.generic.citationStyleLanguage.settings.citationDownloads'),
				'notice' => __('plugins.generic.citationStyleLanguage.settings.citationDownloadsDescription'),
			),
		);

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'pluginName' => $this->plugin->getName(),
			'primaryCitationStyleListData' => json_encode($primaryCitationStyleListData),
			'citationStylesListData' => json_encode($citationStylesListData),
			'citationDownloadsListData' => json_encode($citationDownloadsListData),
		));

		return parent::fetch($request);
	}

	/**
	 * Save settings.
	 */
	public function execute($request) {
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

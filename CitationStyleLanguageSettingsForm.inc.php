<?php
/**
 * @file plugins/generic/citationStyleLanguage/CitationStyleLanguageSettingsForm.inc.inc.php
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
	public function __construct(&$plugin) {
		parent::__construct($plugin->getTemplatePath() . 'settings.tpl');
		$this->plugin =& $plugin;
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	* @copydoc Form::init
	*/
	public function initData() {
		$context = Application::getRequest()->getContext();
		$contextId = empty($context) ? 0 : $context->getId();
		$this->setData('primaryCitationStyle', $this->plugin->getSetting($contextId, 'primaryCitationStyle'));
		$this->setData('enabledCitationStyles', array_keys($this->plugin->getEnabledCitationStyles()));
		$this->setData('enabledCitationDownloads', array_keys($this->plugin->getEnabledCitationDownloads()));
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	public function readInputData() {
		$this->readUserVars(array('primaryCitationStyle'));
		$this->readUserVars(array('enabledCitationStyles'));
		$this->readUserVars(array('enabledCitationDownloads'));
	}

	/**
	 * Fetch the form.
	 * @copydoc Form::fetch()
	 */
	public function fetch($request) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'pluginName' => $this->plugin->getName(),
			'citationStyles' => $this->plugin->getCitationStyles(),
			'primaryCitationStyle' => $this->getData('primaryCitationStyle'),
			'enabledCitationStyles' => $this->getData('enabledCitationStyles'),
			'citationDownloads' => $this->plugin->getCitationDownloads(),
			'enabledCitationDownloads' => $this->getData('enabledCitationDownloads'),
		));
		return parent::fetch($request);
	}

	/**
	 * Save settings.
	 */
	public function execute() {
		$context = Application::getRequest()->getContext();
		$contextId = empty($context) ? 0 : $context->getId();
		$this->plugin->updateSetting($contextId, 'primaryCitationStyle', $this->getData('primaryCitationStyle'));
		$this->plugin->updateSetting($contextId, 'enabledCitationStyles', $this->getData('enabledCitationStyles'));
		$this->plugin->updateSetting($contextId, 'enabledCitationDownloads', $this->getData('enabledCitationDownloads'));

		import('classes.notification.NotificationManager');
		$notificationMgr = new NotificationManager();
		$user = Application::getRequest()->getUser();
		$notificationMgr->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('common.changesSaved')));

		return parent::execute($plugin);
	}
}

?>

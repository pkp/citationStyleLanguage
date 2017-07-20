<?php

/**
 * @file plugins/generic/citationStyleLanguage/CitationStyleLanguagePlugin.inc.php
 *
 * Copyright (c) 2017 Simon Fraser University
 * Copyright (c) 2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CitationStyleLanguagePlugin
 * @ingroup plugins_generic_citationStyleLanguage
 *
 * @brief Citation Style Language plugin class.
 */

import('lib.pkp.classes.plugins.GenericPlugin');
require_once(__DIR__ . '/lib/vendor/autoload.php');
use Seboettg\CiteProc\CiteProc;

class CitationStyleLanguagePlugin extends GenericPlugin {
	/** @var array List of citation styles available */
	public $_citationStyles = array();

	/** @var array List of citation download formats available */
	public $_citationDownloads = array();

	/**
	 * @copydoc Plugin::getDisplayName()
	 */
	public function getDisplayName() {
		return __('plugins.generic.citationStyleLanguage.displayName');
	}

	/**
	 * @copydoc Plugin::getDescription()
	 */
	public function getDescription() {
		return __('plugins.generic.citationStyleLanguage.description');
	}

	/**
	 * @copydoc Plugin::register()
	 */
	public function register($category, $path) {
		$success = parent::register($category, $path);
		if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return $success;
		if ($success && $this->getEnabled()) {
			HookRegistry::register('ArticleHandler::view', array($this, 'getArticleTemplateData'));
			HookRegistry::register('LoadHandler', array($this, 'setPageHandler'));
		}
		return $success;
	}

	/**
	 * Get list of citation styles available
	 *
	 * @return array
	 */
	public function getCitationStyles() {

		if (!empty($this->_citationStyles)) {
			return $this->_citationStyles;
		}

		$defaults = array(
			'acm-sig-proceedings' => array(
				'label' => __('plugins.generic.citationStyleLanguage.style.acm-sig-proceedings'),
				'isEnabled' => true,
			),
			'acs-nano' => array(
				'label' => __('plugins.generic.citationStyleLanguage.style.acs-nano'),
				'isEnabled' => true,
			),
			'apa' => array(
				'label' => __('plugins.generic.citationStyleLanguage.style.apa'),
				'isEnabled' => true,
				'isPrimary' => true,
			),
			'associacao-brasileira-de-normas-tecnicas' => array(
				'label' => __('plugins.generic.citationStyleLanguage.style.associacao-brasileira-de-normas-tecnicas'),
				'isEnabled' => true,
			),
			'chicago-author-date' => array(
				'label' => __('plugins.generic.citationStyleLanguage.style.chicago-author-date'),
				'isEnabled' => true,
			),
			'harvard-cite-them-right' => array(
				'label' => __('plugins.generic.citationStyleLanguage.style.harvard-cite-them-right'),
				'isEnabled' => true,
			),
			'ieee' => array(
				'label' => __('plugins.generic.citationStyleLanguage.style.ieee'),
				'isEnabled' => true,
			),
			'modern-language-association' => array(
				'label' => __('plugins.generic.citationStyleLanguage.style.modern-language-association'),
				'isEnabled' => true,
			),
			'turabian-fullnote-bibliography' => array(
				'label' => __('plugins.generic.citationStyleLanguage.style.turabian-fullnote-bibliography'),
				'isEnabled' => true,
			),
			'vancouver' => array(
				'label' => __('plugins.generic.citationStyleLanguage.style.vancouver'),
				'isEnabled' => true,
			),
		);

		$this->_citationStyles = $defaults;

		return $this->_citationStyles;
	}

	/**
	 * Get the primary style name or default to the first available style
	 *
	 * @return string
	 */
	public function getPrimaryStyleName() {
		$styles = $this->getCitationStyles();
		assert(count($styles));
		$primaryStyle = array_filter($styles, function($style) {
			return !empty($style['isPrimary']);
		});
		if (count($primaryStyle)) {
			return array_keys($primaryStyle)[0];
		}
		return array_keys($styles)[0];
	}

	/**
	 * Get enabled citation styles
	 *
	 * @return array
	 */
	public function getEnabledCitationStyles() {
		$request = Application::getRequest();
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : 0;
		$styles = $this->getCitationStyles();
		$enabled = $this->getSetting($contextId, 'enabledCitationStyles');
		if (empty($enabled)) {
			return array_filter($styles, function($style) {
				return !empty($style['isEnabled']);
			});
		} else {
			return array_filter($styles, function($style, $styleId) use ($enabled) {
				return in_array($styleId, $enabled);
			}, ARRAY_FILTER_USE_BOTH);
		}
	}

	/**
	 * Get list of citation download formats available
	 *
	 * @return array
	 */
	public function getCitationDownloads() {

		if (!empty($this->_citationDownloads)) {
			return $this->_citationDownloads;
		}

		$defaults = array(
			'bibtex' => array(
				'label' => __('plugins.generic.citationStyleLanguage.download.bibtex'),
				'isEnabled' => true,
				'fileExtension' => 'bib',
				'contentType' => 'application/x-bibtex',
			),
			'ris' => array(
				'label' => __('plugins.generic.citationStyleLanguage.download.ris'),
				'isEnabled' => true,
				'fileExtension' => 'ris',
				'contentType' => 'application/x-Research-Info-Systems',
			),
		);

		$this->_citationDownloads = $defaults;

		return $this->_citationDownloads;
	}

	/**
	 * Get enabled citation styles
	 *
	 * @return array
	 */
	public function getEnabledCitationDownloads() {
		$request = Application::getRequest();
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : 0;
		$downloads = $this->getCitationDownloads();
		$enabled = $this->getSetting($contextId, 'enabledCitationDownloads');
		if (empty($enabled)) {
			return array_filter($downloads, function($style) {
				return !empty($style['isEnabled']);
			});
		} else {
			return array_filter($downloads, function($style, $styleId) use ($enabled) {
				return in_array($styleId, $enabled);
			}, ARRAY_FILTER_USE_BOTH);
		}
	}

	/**
	 * Retrieve citation information for the article details template. This
	 * method is hooked in before a template displays.
	 *
	 * @see ArticleHandler::view()
	 * @param $hookname string
	 * @param $args array
	 * @return false
	 */
	public function getArticleTemplateData($hookName, $args) {
		$issue = $args[1];
		$article = $args[2];
		$request = Application::getRequest();
		$templateMgr = TemplateManager::getManager();

		$templateMgr->assign(array(
			'citation' => $this->getCitation($article, $this->getPrimaryStyleName(), $issue),
			'citationArgs' => array('submissionId' => $article->getId()),
			'citationStyles' => $this->getEnabledCitationStyles(),
			'citationDownloads' => $this->getEnabledCitationDownloads(),
		));

		$templateMgr->addJavaScript(
			'citationStyleLanguage',
			$request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/articleCitation.js'
		);

		return false;
	}

	/**
	 * Get a specified citation for a given article
	 *
	 * This citation format follows the csl-json schema and takes some direction
	 * from existing CSL mappings documented by Zotero and Mendeley.
	 *
	 * @see CSL-json schema https://github.com/citation-style-language/schema#csl-json-schema
	 * @see Zotero's mappings https://aurimasv.github.io/z2csl/typeMap.xml#map-journalArticle
	 * @see Mendeley's mappings http://support.mendeley.com/customer/portal/articles/364144-csl-type-mapping
	 * @param $article PublishedArticle
	 * @param $citationStyle string Name of the citation style to use.
	 * @param $issue Issue Optional. Will fetch from db if not passed.
	 * @return string
	 */
	public function getCitation($article, $citationStyle = 'apa', $issue = null) {
		$request = Application::getRequest();
		$journal = $request->getContext();

		if (empty($issue)) {
			$issueDao = DAORegistry::getDAO('IssueDAO');
			$issue = $issueDao->getById($article->getIssueId());
		}

		$citationData = new stdClass();
		$citationData->type = 'article-journal';
		$citationData->id = $article->getId();
		$citationData->title = $article->getLocalizedTitle();
		$citationData->{'container-title'} = $journal->getLocalizedName();
		$citationData->{'container-title-short'} = $journal->getLocalizedAcronym();
		$citationData->volume = $issue->getData('volume');
		// Zotero prefers issue and Mendeley uses `number` to store revisions
		$citationData->issue = $issue->getData('number');
		$citationData->section = $article->getSectionTitle();
		$citationData->URL = $request->getDispatcher()->url(
			$request,
			ROUTE_PAGE,
			null,
			'article',
			'view',
			$article->getBestArticleId()
		);
		$citationData->accessed = new stdClass();
		$citationData->accessed->raw = date('Y-m-d');

		$authors = $article->getAuthors();
		if (count($authors)) {
			$citationData->author = array();
			foreach ($authors as $author) {
				$currentAuthor = new stdClass();
				$currentAuthor->family = $author->getLastName();
				$currentAuthor->given = $author->getFirstName();
				$citationData->author[] = $currentAuthor;
			}
		}

		if ($article->getDatePublished()) {
			$citationData->issued = new stdClass();
			$citationData->issued->raw = $article->getDatePublished();
		}

		if ($article->getPages()) {
			$citationData->page = $article->getPages();
		}

		HookRegistry::call('CitationStyleLanguage::citation', array(&$citationData, &$citationStyle, $article, $issue));

		$citation = '';

		$style = $this->loadStyle($citationStyle);
		if ($style) {
			$locale = str_replace('_', '-', AppLocale::getLocale());
			$citeProc = new CiteProc($style, $locale);
			$citation = $citeProc->render(array($citationData), 'bibliography');
		}

		return $citation;
	}

	/**
	 * Load a CSL style and return the contents as a string
	 *
	 * @param $name string CSL file to load
	 */
	public function loadStyle($name) {
		return file_get_contents($this->getPluginPath() . '/citation-styles/' . $name . '.csl');
	}

	/**
	 * Download a citation format
	 *
	 * Downloadable citation formats can be used to import into third-party
	 * software.
	 *
	 * @param $article PublishedArticle
	 * @param $citationStyle string Name of the citation style to use.
	 * @param $issue Issue Optional. Will fetch from db if not passed.
	 * @return string
	 */
	public function downloadCitation($article, $citationStyle = 'harvard-cite-them-right', $issue = null) {
		$request = Application::getRequest();
		$journal = $request->getContext();

		if (empty($issue)) {
			$issueDao = DAORegistry::getDAO('IssueDAO');
			$issue = $issueDao->getById($article->getIssueId());
		}

		$citationDownloads = $this->getCitationDownloads();
		if (!isset($citationDownloads[$citationStyle])) {
			return false;
		}

		$citation = trim(strip_tags($this->getCitation($article, $citationStyle, $issue)));
		// TODO this is likely going to cause an error in a citation some day,
		// but is necessary to get the .ris downloadable format working. The
		// CSL language doesn't seem to offer a way to indicate a line break.
		// See: https://github.com/citation-style-language/styles/issues/2831
		$citation = str_replace('\n', "\n", $citation);

		$filename = substr(preg_replace('/[^a-zA-Z0-9_.-]/', '', str_replace(' ', '-', $article->getLocalizedTitle())), 0, 60);

		header('Content-Disposition: attachment; filename="' . $filename . '.' . $citationDownloads[$citationStyle]['fileExtension'] . '"');
		header('Content-Type: ' . $citationDownloads[$citationStyle]['contentType']);
		echo $citation;
		exit;
	}

	/**
	 * @see Plugin::getActions()
	 */
	public function getActions($request, $actionArgs) {

		$actions = parent::getActions($request, $actionArgs);

		if (!$this->getEnabled()) {
			return $actions;
		}

		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$linkAction = new LinkAction(
			'settings',
			new AjaxModal(
				$router->url(
					$request,
					null,
					null,
					'manage',
					null,
					array(
						'verb' => 'settings',
						'plugin' => $this->getName(),
						'category' => 'generic'
					)
				),
				$this->getDisplayName()
			),
			__('manager.plugins.settings'),
			null
		);

		array_unshift($actions, $linkAction);

		return $actions;
	}

	/**
	 * @see Plugin::manage()
	 */
	public function manage($args, $request) {
		switch ($request->getUserVar('verb')) {
			case 'settings':
				$this->import('CitationStyleLanguageSettingsForm');
				$form = new CitationStyleLanguageSettingsForm($this);

				if ($request->getUserVar('save')) {
					$form->readInputData();
					if ($form->validate()) {
						$form->execute();
						return new JSONMessage(true);
					}
				}

				$form->initData();
				return new JSONMessage(true, $form->fetch($request));
		}
		return parent::manage($args, $request);
	}

	/**
	 * @copydoc Plugin::getTemplatePath($inCore)
	 */
	public function getTemplatePath($inCore = false) {
		return parent::getTemplatePath($inCore) . 'templates/';
	}

	/**
	 * Route requests for the citation styles to custom page handler
	 *
	 * @see PKPPageRouter::route()
	 * @param $hookName string
	 * @param $params array
	 */
	public function setPageHandler($hookName, $params) {
		$page = $params[0];
		if ($this->getEnabled() && $page === 'citationstylelanguage') {
			$this->import('pages/CitationStyleLanguageHandler');
			define('HANDLER_CLASS', 'CitationStyleLanguageHandler');
			return true;
		}
		return false;
	}
}
?>

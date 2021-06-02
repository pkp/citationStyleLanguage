<?php

/**
 * @file plugins/generic/citationStyleLanguage/CitationStyleLanguagePlugin.inc.php
 *
 * Copyright (c) 2017-2020 Simon Fraser University
 * Copyright (c) 2017-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
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

	/** @var bool $applicationOmp */
	private bool $applicationOmp;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
		$applicationName = Application::get()->getName();
		$this->applicationOmp =  stripos($applicationName, 'omp') !== false;
	}

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
		return $this->isApplicationOmp() ? __('plugins.generic.citationStyleLanguage.description.omp')
			: __('plugins.generic.citationStyleLanguage.description');
	}

	/**
	 * @copydoc Plugin::register()
	 */
	public function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return $success;
		if ($success && $this->getEnabled($mainContextId)) {
			HookRegistry::register('CatalogBookHandler::book', array($this, 'getTemplateData'));
			HookRegistry::register('Templates::Catalog::Book::Details', array($this, 'displayCitationMonograph'));
			HookRegistry::register('ArticleHandler::view', array($this, 'getTemplateData'));
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
			array(
				'id' => 'acm-sig-proceedings',
				'title' => __('plugins.generic.citationStyleLanguage.style.acm-sig-proceedings'),
				'isEnabled' => true,
			),
			array(
				'id' => 'acs-nano',
				'title' => __('plugins.generic.citationStyleLanguage.style.acs-nano'),
				'isEnabled' => true,
			),
			array(
				'id' => 'apa',
				'title' => __('plugins.generic.citationStyleLanguage.style.apa'),
				'isEnabled' => true,
				'isPrimary' => true,
			),
			array(
				'id' => 'associacao-brasileira-de-normas-tecnicas',
				'title' => __('plugins.generic.citationStyleLanguage.style.associacao-brasileira-de-normas-tecnicas'),
				'isEnabled' => true,
			),
			array(
				'id' => 'chicago-author-date',
				'title' => __('plugins.generic.citationStyleLanguage.style.chicago-author-date'),
				'isEnabled' => true,
			),
			array(
				'id' => 'harvard-cite-them-right',
				'title' => __('plugins.generic.citationStyleLanguage.style.harvard-cite-them-right'),
				'isEnabled' => true,
			),
			array(
				'id' => 'ieee',
				'title' => __('plugins.generic.citationStyleLanguage.style.ieee'),
				'isEnabled' => true,
			),
			array(
				'id' => 'modern-language-association',
				'title' => __('plugins.generic.citationStyleLanguage.style.modern-language-association'),
				'isEnabled' => true,
			),
			array(
				'id' => 'turabian-fullnote-bibliography',
				'title' => __('plugins.generic.citationStyleLanguage.style.turabian-fullnote-bibliography'),
				'isEnabled' => true,
			),
			array(
				'id' => 'vancouver',
				'title' => __('plugins.generic.citationStyleLanguage.style.vancouver'),
				'isEnabled' => true,
			),
		);

		// If hooking in to add a custom .csl file, add a `useCsl` key to your
		// style definition with the path to the file.
		HookRegistry::call('CitationStyleLanguage::citationStyleDefaults', array(&$defaults, $this));
		$this->_citationStyles = $defaults;

		return $this->_citationStyles;
	}

	/**
	 * Get the primary style name or default to the first available style
	 *
	 * @param $contextId integer Journal ID
	 * @return string
	 */
	public function getPrimaryStyleName($contextId = 0) {

		$primaryStyleName = $this->getSetting($contextId, 'primaryCitationStyle');
		if ($primaryStyleName) {
			return $primaryStyleName;
		}

		$styles = $this->getCitationStyles();
		$primaryStyles = array_filter($styles, function($style) {
			return !empty($style['isPrimary']);
		});

		$primaryStyle = count($primaryStyles) ? array_shift($primaryStyles) : array_shift($styles);

		return $primaryStyle['id'];
	}

	/**
	 * Get enabled citation styles
	 *
	 * @param $contextId integer Journal ID
	 * @return array
	 */
	public function getEnabledCitationStyles($contextId = 0) {
		$styles = $this->getCitationStyles();
		$enabled = $this->getSetting($contextId, 'enabledCitationStyles');
		if (!is_array($enabled)) {
			return array_filter($styles, function($style) {
				return !empty($style['isEnabled']);
			});
		} else {
			return array_filter($styles, function($style) use ($enabled) {
				return in_array($style['id'], $enabled);
			});
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
			array(
				'id' => 'ris',
				'title' => __('plugins.generic.citationStyleLanguage.download.ris'),
				'isEnabled' => true,
				'useTemplate' => $this->getTemplateResource('citation-styles/ris.tpl'),
				'fileExtension' => 'ris',
				'contentType' => 'application/x-Research-Info-Systems',
			),
			array(
				'id' => 'bibtex',
				'title' => __('plugins.generic.citationStyleLanguage.download.bibtex'),
				'isEnabled' => true,
				'fileExtension' => 'bib',
				'contentType' => 'application/x-bibtex',
			),
		);

		// If hooking in to add a custom .csl file, add a `useCsl` key to your
		// style definition with the path to the file.
		HookRegistry::call('CitationStyleLanguage::citationDownloadDefaults', array(&$defaults, $this));
		$this->_citationDownloads = $defaults;

		return $this->_citationDownloads;
	}

	/**
	 * Get enabled citation styles
	 *
	 * @param $contextId integer Journal ID
	 * @return array
	 */
	public function getEnabledCitationDownloads($contextId = 0) {
		$downloads = $this->getCitationDownloads();
		$enabled = $this->getSetting($contextId, 'enabledCitationDownloads');
		if (!is_array($enabled)) {
			return array_filter($downloads, function($style) {
				return !empty($style['isEnabled']);
			});
		} else {
			return array_filter($downloads, function($style) use ($enabled) {
				return in_array($style['id'], $enabled);
			});
		}
	}

	/**
	 * Pluck citation IDs from array of citations
	 *
	 * @param $citations array See getCitationStyles()
	 * @return array
	 */
	public function mapCitationIds($citations) {
		return array_values(array_map(function($citation) { return $citation['id']; }, $citations));
	}

	/**
	 * Get citation config for a citation ID
	 *
	 * @param $styleId string Example: 'apa'
	 * @return array
	 */
	public function getCitationStyleConfig($styleId) {
		$styleConfigs = array_merge($this->getCitationStyles(), $this->getCitationDownloads());
		$styleConfig = array_filter($styleConfigs, function($styleConfig) use ($styleId) {
			return $styleConfig['id'] === $styleId;
		});
		return array_shift($styleConfig);
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
	public function getTemplateData($hookName, $args) {
		$templateMgr = TemplateManager::getManager();
		$request = $args[0];
		if ($this->isApplicationOmp()) {
			$submission =& $args[1];
			$publication = $submission->getCurrentPublication();
			$issue = null;
			if (null === $publication) {
				return false;
			}
			$templateMgr->addStyleSheet(
				'cslPluginStyles',
				$request->getBaseUrl() . '/' . $this->getPluginPath() . '/css/citationStyleLanguagePlugin.css',
				array(
					'priority' => STYLE_SEQUENCE_LAST,
					'contexts' => array('frontend'),
					'inline'   => false,
				)
			);
		} else {
			$issue = $args[1];
			$submission = $args[2];
			$publication = $args[3];
		}

		$context = $request->getContext();
		$contextId = $context ? $context->getId() : 0;

		$citationArgs = array(
			'submissionId' => $submission->getId(),
			'publicationId' => $publication->getId(),
		);
		$citationArgsJson = $citationArgs;
		$citationArgsJson['return'] = 'json';

		$templateMgr->assign(array(
			'citation' => $this->getCitation($request, $submission, $this->getPrimaryStyleName($contextId), $issue, $publication),
			'citationArgs' => $citationArgs,
			'citationArgsJson' => $citationArgsJson,
			'citationStyles' => $this->getEnabledCitationStyles($contextId),
			'citationDownloads' => $this->getEnabledCitationDownloads($contextId),
		));

		$templateMgr->addJavaScript(
			'citationStyleLanguage',
			$request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/articleCitation.js'
		);

		return false;
	}

	/**
	 * Add citation style language to book view page
	 * Hooked to `Templates::Catalog::Book::Main`
	 *
	 * @param $hookName string
	 * @param $params   array array [
	 * @option Smarty
	 * @option string HTML output to return
	 * ]
	 * @return false
	 */
	public function displayCitationMonograph($hookName, $params) {
		$smarty =& $params[1];
		$output =& $params[2];
		$output .= $smarty->fetch($this->getTemplateResource('citationblock.tpl'));

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
	 * @param $request Request
	 * @param $submission Submission
	 * @param $citationStyle string Name of the citation style to use.
	 * @param $issue Issue Optional. Will fetch from db if not passed.
	 * @param $publication Publication Optional. A particular version
	 * @return string
	 */
	public function getCitation($request, Submission $submission, $citationStyle = 'apa', $issue = null, $publication = null) {
		$publication = $publication ?? $submission->getCurrentPublication();
		$context = $request->getContext();

		import('lib.pkp.classes.core.PKPString');

		$citationData = new stdClass();
		if ($this->isApplicationOmp()){
			$citationData->type = 'book';
			$citationData->risType = 'BOOK';
			$citationData->publisher = htmlspecialchars($context->getLocalizedName());
			$citationData->serialNumber = array();
			$publicationFormats = $publication->getData('publicationFormats');
			/** @var PublicationFormat $publicationFormat */
			foreach ($publicationFormats as $publicationFormat) {
				if ($publicationFormat->getIsApproved()) {
					$identificationCodes = $publicationFormat->getIdentificationCodes();
					$identificationCodes = $identificationCodes->toArray();
					foreach ($identificationCodes as $identificationCode) {
						$citationData->serialNumber[] = htmlspecialchars($identificationCode->getValue());
					}
				}
			}

			$seriesId = $publication->getData('seriesId');
			if ($seriesId) {
				/** @var SeriesDAO $seriesDao */
				$seriesDao = DAORegistry::getDAO('SeriesDAO');
				if (null !== $seriesDao) {
					$series = $seriesDao->getById($seriesId);
					if (null !== $series) {
						$citationData->{'collection-title'} = htmlspecialchars(trim($series->getLocalizedFullTitle()));
						$citationData->volume = htmlspecialchars($publication->getData('seriesPosition'));
						$citationData->{'collection-editor'} = htmlspecialchars($series->getEditorsString());
						$onlineISSN = $series->getOnlineISSN();
						if (null !== $onlineISSN && !empty($onlineISSN)) {
							$citationData->serialNumber[] = htmlspecialchars($onlineISSN);
						}
						$printISSN = $series->getPrintISSN();
						if (null !== $printISSN && !empty($printISSN)) {
							$citationData->serialNumber[] = htmlspecialchars($printISSN);
						}
					}
				}
			}
		} else {
			$issueDao = DAORegistry::getDAO('IssueDAO'); /* @var $issueDao IssueDAO */
			$issue = $issue ?? $issueDao->getById($publication->getData('issueId'));
			$citationData->type = 'article-journal';
			$citationData->risType = 'JOUR';
			$citationData->{'container-title'} = htmlspecialchars($context->getLocalizedName());
			if ($issue) {
				$citationData->volume = htmlspecialchars($issue->getData('volume'));
				// Zotero prefers issue and Mendeley uses `number` to store revisions
				$citationData->issue = htmlspecialchars($issue->getData('number'));
			}

			$sectionDao = DAORegistry::getDAO('SectionDAO'); /** @var $sectionDao SectionDAO */
			if ($sectionId = $publication->getData('sectionId')) {
				$section = $sectionDao->getById($sectionId);
				if ($section && !$section->getHideTitle()) $citationData->section = htmlspecialchars($section->getTitle($context->getPrimaryLocale()));
			}
		}

		/* @var $submissionKeywordDao SubmissionKeywordDAO */
		$submissionKeywordDao = DAORegistry::getDAO('SubmissionKeywordDAO');
		$keywords = $submissionKeywordDao->getKeywords($publication->getId(), array(AppLocale::getLocale()));
		$citationData->keywords = $keywords[AppLocale::getLocale()];

		/* @var $submissionLanguageDao SubmissionLanguageDAO */
		$submissionLanguageDao = DAORegistry::getDAO('SubmissionLanguageDAO');
		$languages = $submissionLanguageDao->getLanguages($publication->getId(), array(AppLocale::getLocale()));
		if (array_key_exists(AppLocale::getLocale(), $languages)) {
			$citationData->languages = $languages[AppLocale::getLocale()];
		}

		$citationData->id = $submission->getId();
		$citationData->title = htmlspecialchars($publication->getLocalizedFullTitle());
		$citationData->{'publisher-place'} = $this->getSetting($context->getId(), 'publisherLocation');
		$citationData->abstract = htmlspecialchars(strip_tags($publication->getLocalizedData('abstract')));

		$abbreviation = $context->getData('abbreviation', $context->getPrimaryLocale()) ?? $context->getData('acronym', $context->getPrimaryLocale());
		if ($abbreviation) $citationData->{'container-title-short'} = htmlspecialchars($abbreviation);

		$citationData->URL = $request->getDispatcher()->url(
			$request,
			ROUTE_PAGE,
			null,
			$this->isApplicationOmp() ? 'catalog' : 'article',
			$this->isApplicationOmp() ? 'book' : 'view',
			(string) $submission->getId()
		);
		$citationData->accessed = new stdClass();
		$citationData->accessed->raw = date('Y-m-d');

		$authors = $publication->getData('authors');
		if (count($authors)) {
			$citationData->author = array();
			foreach ($authors as $author) {
				$currentAuthor = new stdClass();
				if (empty($author->getLocalizedFamilyName())) {
					$currentAuthor->family = htmlspecialchars($author->getLocalizedGivenName());
				} else {
					$currentAuthor->family = htmlspecialchars($author->getLocalizedFamilyName());
					$currentAuthor->given = htmlspecialchars($author->getLocalizedGivenName());
				}
				$userGroup = $author->getUserGroup();
				if (null !== $userGroup) {
					switch ($userGroup->getId()) {
						case $this->getEditorGroup($context->getId()):
							if (!isset($citationData->editor)) {
								$citationData->editor = array();
							}
							$citationData->editor[] = $currentAuthor;
							break;
						case $this->getTranslatorGroup($context->getId()):
							if (!isset($citationData->translator)) {
								$citationData->translator = array();
							}
							$citationData->translator[] = $currentAuthor;
							break;
						case $this->getAuthorGroup($context->getId()):
							if (!isset($citationData->author)) {
								$citationData->author = array();
							}
							$citationData->author[] = $currentAuthor;
							break;
						default:
							break;
					}
				}
			}
		}

		if ($publication->getData('datePublished')) {
			$citationData->issued = new stdClass();
			$citationData->issued->raw = htmlspecialchars($publication->getData('datePublished'));
			$publishedPublications = $submission->getPublishedPublications();
			if (count($publishedPublications) > 1) {
				$originalPublication = array_reduce($publishedPublications, function($a, $b) {
					return $a && $a->getId() < $b->getId() ? $a : $b;
				});
				$originalDate = $originalPublication->getData('datePublished');
				if ($originalDate && $originalDate !== $publication->getData('datePublished')) {
					$citationData->{'original-date'} = new stdClass();
					$citationData->{'original-date'}->raw = htmlspecialchars($originalPublication->getData('datePublished'));
				}
			}
		} elseif ( !$this->isApplicationOmp() && $issue && $issue->getPublished()) {
			$citationData->issued = new stdClass();
			$citationData->issued->raw = htmlspecialchars($issue->getDatePublished());
		}

		if ($publication->getData('pages')) {
			$citationData->page = htmlspecialchars($publication->getData('pages'));
		}

		HookRegistry::call('CitationStyleLanguage::citation', array(&$citationData, &$citationStyle, $submission, $issue, $context, $publication));

		$citation = '';

		// Determine whether to use citeproc-php or a custom template to render
		// the citation
		$styleConfig = $this->getCitationStyleConfig($citationStyle);
		if (!empty($styleConfig)) {
			if (!empty($styleConfig['useTemplate'])) {
				$templateMgr = TemplateManager::getManager($request);
				$templateMgr->assign([
					'citationData' => $citationData,
					'citationStyle' => $citationStyle,
					'publication' => $publication,
				]);
				if ($this->isApplicationOmp()) {
					$templateMgr->assign([
						'book' => $submission,
						'press' => $context,
					]);
				} else {
					$templateMgr->assign([
						'article' => $submission,
						'issue' => $issue,
						'journal' => $context,
					]);
				}
				$citation = $templateMgr->fetch($styleConfig['useTemplate']);
			} else {
				$style = $this->loadStyle($styleConfig);
				if ($style) {
					// Determine what locale to use. Try in order:
					//  - xx_YY
					//  - xx
					// Fall back English if none found.
					$tryLocale = null;
					foreach (array(
						str_replace('_', '-', substr(AppLocale::getLocale(), 0, 5)),
						substr(AppLocale::getLocale(), 0, 2),
						'en-US'
					) as $tryLocale) {
						if (file_exists(dirname(__FILE__) . '/lib/vendor/citation-style-language/locales/locales-' . $tryLocale . '.xml')) break;
					}
					$citeProc = new CiteProc($style, $tryLocale);
					$citation = $citeProc->render(array($citationData), 'bibliography');
				}
			}
		}

		return $citation;
	}

	/**
	 * Load a CSL style and return the contents as a string
	 *
	 * @param $styleConfig array CSL configuration to load
	 */
	public function loadStyle($styleConfig) {
		if (!empty($styleConfig['useCsl'])) {
			return file_get_contents($styleConfig['useCsl']);
		} else {
			return file_get_contents($this->getPluginPath() . '/citation-styles/' . $styleConfig['id'] . '.csl');
		}
	}

	/**
	 * Download a citation format
	 *
	 * Downloadable citation formats can be used to import into third-party
	 * software.
	 *
	 * @param $request Request
	 * @param $submission Submission
	 * @param $citationStyle string Name of the citation style to use.
	 * @param $issue Issue Optional. Will fetch from db if not passed.
	 * @return string
	 */
	public function downloadCitation($request, $submission, $citationStyle = 'ris', $issue = null) {
		if (!$this->isApplicationOmp()) {
			$journal = $request->getContext();

			if (empty($issue)) {
				$issueDao = DAORegistry::getDAO('IssueDAO'); /* @var $issueDao IssueDAO */
				$issue = $issueDao->getById($submission->getCurrentPublication()->getData('issueId'));
			}
		}

		$styleConfig = $this->getCitationStyleConfig($citationStyle);
		if (empty($styleConfig)) {
			return false;
		}

		$publication = $submission->getCurrentPublication();
		$citation = trim(strip_tags($this->getCitation($request, $submission, $citationStyle, $issue)));
		// TODO this is likely going to cause an error in a citation some day,
		// but is necessary to get the .ris downloadable format working. The
		// CSL language doesn't seem to offer a way to indicate a line break.
		// See: https://github.com/citation-style-language/styles/issues/2831
		$citation = str_replace('\n', "\n", $citation);

        $encodedFilename = urlencode(substr(($publication ? $publication->getLocalizedTitle() : ''), 0, 60)) . '.' . $styleConfig['fileExtension'];

		header("Content-Disposition: attachment; filename*=UTF-8''\"$encodedFilename\"");
		header('Content-Type: ' . $styleConfig['contentType']);
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

	/**
	 * @return bool
	 */
	public function isApplicationOmp() : bool {
		return $this->applicationOmp;
	}

	/**
	 * @param int $contextId
	 *
	 * @return int|null
	 */
	public function getEditorGroup(int $contextId = 0) : ?int {
		$editorGroup = $this->getSetting($contextId, 'groupEditor');
		if ($editorGroup) {
			return (int) $editorGroup;
		}
		return null;
	}

	/**
	 * @param int $contextId
	 *
	 * @return int|null
	 */
	public function getTranslatorGroup(int $contextId = 0) : ?int {
		$translatorGroup = $this->getSetting($contextId, 'groupTranslator');
		if ($translatorGroup) {
			return (int) $translatorGroup;
		}
		return null;
	}

	/**
	 * @param int $contextId
	 *
	 * @return int|null
	 */
	public function getAuthorGroup(int $contextId = 0) : ?int {
		$authorGroup = $this->getSetting($contextId, 'groupAuthor');
		if ($authorGroup) {
			return (int) $authorGroup;
		}
		return null;
	}
}

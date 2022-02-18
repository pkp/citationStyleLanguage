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

require_once __DIR__ . '/lib/vendor/autoload.php';

use APP\core\Application;
use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\GenericPlugin;
use PKP\plugins\HookRegistry;
use Seboettg\CiteProc\CiteProc;

class CitationStyleLanguagePlugin extends GenericPlugin
{
    /** @var array List of citation styles available */
    public $_citationStyles = [];

    /** @var array List of citation download formats available */
    public $_citationDownloads = [];

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName()
    {
        return __('plugins.generic.citationStyleLanguage.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription()
    {
        return __('plugins.generic.citationStyleLanguage.description');
    }

    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if (Application::isUnderMaintenance()) {
            return $success;
        }
        if ($success && $this->getEnabled($mainContextId)) {
            HookRegistry::register('ArticleHandler::view', [$this, 'getArticleTemplateData']);
            HookRegistry::register('LoadHandler', [$this, 'setPageHandler']);
        }
        return $success;
    }

    /**
     * Get list of citation styles available
     *
     * @return array
     */
    public function getCitationStyles()
    {
        if (!empty($this->_citationStyles)) {
            return $this->_citationStyles;
        }

        $defaults = [
            [
                'id' => 'acm-sig-proceedings',
                'title' => __('plugins.generic.citationStyleLanguage.style.acm-sig-proceedings'),
                'isEnabled' => true,
            ],
            [
                'id' => 'acs-nano',
                'title' => __('plugins.generic.citationStyleLanguage.style.acs-nano'),
                'isEnabled' => true,
            ],
            [
                'id' => 'apa',
                'title' => __('plugins.generic.citationStyleLanguage.style.apa'),
                'isEnabled' => true,
                'isPrimary' => true,
            ],
            [
                'id' => 'associacao-brasileira-de-normas-tecnicas',
                'title' => __('plugins.generic.citationStyleLanguage.style.associacao-brasileira-de-normas-tecnicas'),
                'isEnabled' => true,
            ],
            [
                'id' => 'chicago-author-date',
                'title' => __('plugins.generic.citationStyleLanguage.style.chicago-author-date'),
                'isEnabled' => true,
            ],
            [
                'id' => 'harvard-cite-them-right',
                'title' => __('plugins.generic.citationStyleLanguage.style.harvard-cite-them-right'),
                'isEnabled' => true,
            ],
            [
                'id' => 'ieee',
                'title' => __('plugins.generic.citationStyleLanguage.style.ieee'),
                'isEnabled' => true,
            ],
            [
                'id' => 'modern-language-association',
                'title' => __('plugins.generic.citationStyleLanguage.style.modern-language-association'),
                'isEnabled' => true,
            ],
            [
                'id' => 'turabian-fullnote-bibliography',
                'title' => __('plugins.generic.citationStyleLanguage.style.turabian-fullnote-bibliography'),
                'isEnabled' => true,
            ],
            [
                'id' => 'vancouver',
                'title' => __('plugins.generic.citationStyleLanguage.style.vancouver'),
                'isEnabled' => true,
            ],
            [
                'id' => 'ama',
                'title' => __('plugins.generic.citationStyleLanguage.style.ama'),
                'isEnabled' => true,
            ],
        ];

        // If hooking in to add a custom .csl file, add a `useCsl` key to your
        // style definition with the path to the file.
        HookRegistry::call('CitationStyleLanguage::citationStyleDefaults', [&$defaults, $this]);
        $this->_citationStyles = $defaults;

        return $this->_citationStyles;
    }

    /**
     * Get the primary style name or default to the first available style
     *
     * @param int $contextId Journal ID
     *
     * @return string
     */
    public function getPrimaryStyleName($contextId = 0)
    {
        $primaryStyleName = $this->getSetting($contextId, 'primaryCitationStyle');
        if ($primaryStyleName) {
            return $primaryStyleName;
        }

        $styles = $this->getCitationStyles();
        $primaryStyles = array_filter($styles, function ($style) {
            return !empty($style['isPrimary']);
        });

        $primaryStyle = count($primaryStyles) ? array_shift($primaryStyles) : array_shift($styles);

        return $primaryStyle['id'];
    }

    /**
     * Get enabled citation styles
     *
     * @param int $contextId Journal ID
     *
     * @return array
     */
    public function getEnabledCitationStyles($contextId = 0)
    {
        $styles = $this->getCitationStyles();
        $enabled = $this->getSetting($contextId, 'enabledCitationStyles');
        if (!is_array($enabled)) {
            return array_filter($styles, function ($style) {
                return !empty($style['isEnabled']);
            });
        }
        return array_filter($styles, function ($style) use ($enabled) {
            return in_array($style['id'], $enabled);
        });
    }

    /**
     * Get list of citation download formats available
     *
     * @return array
     */
    public function getCitationDownloads()
    {
        if (!empty($this->_citationDownloads)) {
            return $this->_citationDownloads;
        }

        $defaults = [
            [
                'id' => 'ris',
                'title' => __('plugins.generic.citationStyleLanguage.download.ris'),
                'isEnabled' => true,
                'useTemplate' => $this->getTemplateResource('citation-styles/ris.tpl'),
                'fileExtension' => 'ris',
                'contentType' => 'application/x-Research-Info-Systems',
            ],
            [
                'id' => 'bibtex',
                'title' => __('plugins.generic.citationStyleLanguage.download.bibtex'),
                'isEnabled' => true,
                'fileExtension' => 'bib',
                'contentType' => 'application/x-bibtex',
            ],
        ];

        // If hooking in to add a custom .csl file, add a `useCsl` key to your
        // style definition with the path to the file.
        HookRegistry::call('CitationStyleLanguage::citationDownloadDefaults', [&$defaults, $this]);
        $this->_citationDownloads = $defaults;

        return $this->_citationDownloads;
    }

    /**
     * Get enabled citation styles
     *
     * @param int $contextId Journal ID
     *
     * @return array
     */
    public function getEnabledCitationDownloads($contextId = 0)
    {
        $downloads = $this->getCitationDownloads();
        $enabled = $this->getSetting($contextId, 'enabledCitationDownloads');
        if (!is_array($enabled)) {
            return array_filter($downloads, function ($style) {
                return !empty($style['isEnabled']);
            });
        }
        return array_filter($downloads, function ($style) use ($enabled) {
            return in_array($style['id'], $enabled);
        });
    }

    /**
     * Pluck citation IDs from array of citations
     *
     * @param array $citations See getCitationStyles()
     *
     * @return array
     */
    public function mapCitationIds($citations)
    {
        return array_values(array_map(function ($citation) {
            return $citation['id'];
        }, $citations));
    }

    /**
     * Get citation config for a citation ID
     *
     * @param string $styleId Example: 'apa'
     *
     * @return array
     */
    public function getCitationStyleConfig($styleId)
    {
        $styleConfigs = array_merge($this->getCitationStyles(), $this->getCitationDownloads());
        $styleConfig = array_filter($styleConfigs, function ($styleConfig) use ($styleId) {
            return $styleConfig['id'] === $styleId;
        });
        return array_shift($styleConfig);
    }

    /**
     * Retrieve citation information for the article details template. This
     * method is hooked in before a template displays.
     *
     * @see ArticleHandler::view()
     *
     * @param array $args
     *
     * @return false
     */
    public function getArticleTemplateData($hookName, $args)
    {
        $request = $args[0];
        $issue = $args[1];
        $article = $args[2];
        $publication = $args[3];
        $context = $request->getContext();
        $contextId = $context ? $context->getId() : 0;
        $templateMgr = TemplateManager::getManager();

        $citationArgs = [
            'submissionId' => $article->getId(),
            'publicationId' => $publication->getId(),
        ];
        $citationArgsJson = $citationArgs;
        $citationArgsJson['return'] = 'json';

        $templateMgr->assign([
            'citation' => $this->getCitation($request, $article, $this->getPrimaryStyleName($contextId), $issue, $publication),
            'citationArgs' => $citationArgs,
            'citationArgsJson' => $citationArgsJson,
            'citationStyles' => $this->getEnabledCitationStyles($contextId),
            'citationDownloads' => $this->getEnabledCitationDownloads($contextId),
        ]);

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
     *
     * @param Request $request
     * @param Submission $article
     * @param string $citationStyle Name of the citation style to use.
     * @param Issue $issue Optional. Will fetch from db if not passed.
     * @param Publication $publication Optional. A particular version
     *
     * @return string
     */
    public function getCitation($request, $article, $citationStyle = 'apa', $issue = null, $publication = null)
    {
        $publication ??= $article->getCurrentPublication();
        $issue ??= Repo::issue()->get($publication->getData('issueId'));
        $context = $request->getContext();

        import('lib.pkp.classes.core.PKPString');

        $citationData = new stdClass();
        $citationData->type = 'article-journal';
        $citationData->id = $article->getId();
        $citationData->title = $publication->getLocalizedFullTitle();
        $citationData->{'container-title'} = $context->getLocalizedName();
        $citationData->{'publisher-place'} = $this->getSetting($context->getId(), 'publisherLocation');
        $citationData->abstract = htmlspecialchars($publication->getLocalizedData('abstract'));

        $abbreviation = $context->getData('abbreviation', $context->getPrimaryLocale()) ?? $context->getData('acronym', $context->getPrimaryLocale());
        if ($abbreviation) {
            $citationData->{'container-title-short'} = htmlspecialchars($abbreviation);
        }

        if ($issue) {
            $citationData->volume = htmlspecialchars($issue->getData('volume'));
            // Zotero prefers issue and Mendeley uses `number` to store revisions
            $citationData->issue = htmlspecialchars($issue->getData('number'));
        }

        $sectionDao = DAORegistry::getDAO('SectionDAO'); /** @var SectionDAO $sectionDao */
        if ($sectionId = $publication->getData('sectionId')) {
            $section = $sectionDao->getById($sectionId);
            if ($section && !$section->getHideTitle()) {
                $citationData->section = htmlspecialchars($section->getTitle($context->getPrimaryLocale()));
            }
        }

        $citationData->URL = $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            null,
            'article',
            'view',
            $article->getBestId()
        );
        $citationData->accessed = new stdClass();
        $citationData->accessed->raw = date('Y-m-d');

        $authors = $publication->getData('authors');
        if (count($authors)) {
            $citationData->author = [];
            foreach ($authors as $author) {
                $currentAuthor = new stdClass();
                if (empty($author->getLocalizedFamilyName())) {
                    $currentAuthor->family = htmlspecialchars($author->getLocalizedGivenName());
                } else {
                    $currentAuthor->family = htmlspecialchars($author->getLocalizedFamilyName());
                    $currentAuthor->given = htmlspecialchars($author->getLocalizedGivenName());
                }
                $citationData->author[] = $currentAuthor;
            }
        }

        if ($publication->getData('datePublished')) {
            $citationData->issued = new stdClass();
            $citationData->issued->raw = htmlspecialchars($publication->getData('datePublished'));
            $publishedPublications = $article->getPublishedPublications();
            if (count($publishedPublications) > 1) {
                $originalPublication = array_reduce($publishedPublications, function ($a, $b) {
                    return $a && $a->getId() < $b->getId() ? $a : $b;
                });
                $originalDate = $originalPublication->getData('datePublished');
                if ($originalDate && $originalDate !== $publication->getData('datePublished')) {
                    $citationData->{'original-date'} = new stdClass();
                    $citationData->{'original-date'}->raw = htmlspecialchars($originalPublication->getData('datePublished'));
                }
            }
        } elseif ($issue && $issue->getPublished()) {
            $citationData->issued = new stdClass();
            $citationData->issued->raw = htmlspecialchars($issue->getDatePublished());
        }

        if ($publication->getData('pages')) {
            $citationData->page = htmlspecialchars($publication->getData('pages'));
        }

        // DOI
        if ($issue && $issue->getPublished()) {
            $citationData->DOI = $article->getStoredPubId('doi');
        }

        HookRegistry::call('CitationStyleLanguage::citation', [&$citationData, &$citationStyle, $article, $issue, $context, $publication]);

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
                    'article' => $article,
                    'publication' => $publication,
                    'issue' => $issue,
                    'journal' => $context,
                ]);
                $citation = $templateMgr->fetch($styleConfig['useTemplate']);
            } else {
                $style = $this->loadStyle($styleConfig);
                if ($style) {
                    // Determine what locale to use. Try in order:
                    //  - xx_YY
                    //  - xx
                    // Fall back English if none found.
                    $tryLocale = null;
                    foreach ([
                        str_replace('_', '-', substr(Locale::getLocale(), 0, 5)),
                        substr(Locale::getLocale(), 0, 2),
                        'en-US'
                    ] as $tryLocale) {
                        if (file_exists(dirname(__FILE__) . '/lib/vendor/citation-style-language/locales/locales-' . $tryLocale . '.xml')) {
                            break;
                        }
                    }
                    $citeProc = new CiteProc($style, $tryLocale);
                    $citation = $citeProc->render([$citationData], 'bibliography');
                }
            }
        }

        return $citation;
    }

    /**
     * Load a CSL style and return the contents as a string
     *
     * @param array $styleConfig CSL configuration to load
     */
    public function loadStyle($styleConfig)
    {
        $path = empty($styleConfig['useCsl'])
            ? $this->getPluginPath() . '/citation-styles/' . $styleConfig['id'] . '.csl'
            : $styleConfig['useCsl'];
        return file_get_contents($path);
    }

    /**
     * Download a citation format
     *
     * Downloadable citation formats can be used to import into third-party
     * software.
     *
     * @param Request $request
     * @param Submission $article
     * @param string $citationStyle Name of the citation style to use.
     * @param Issue $issue Optional. Will fetch from db if not passed.
     *
     * @return string
     */
    public function downloadCitation($request, $article, $citationStyle = 'ris', $issue = null)
    {
        $journal = $request->getContext();

        if (empty($issue)) {
            $issue = Repo::issue()->get($article->getCurrentPublication()->getData('issueId'));
        }

        $styleConfig = $this->getCitationStyleConfig($citationStyle);
        if (empty($styleConfig)) {
            return false;
        }

        $citation = trim(strip_tags($this->getCitation($request, $article, $citationStyle, $issue)));
        // TODO this is likely going to cause an error in a citation some day,
        // but is necessary to get the .ris downloadable format working. The
        // CSL language doesn't seem to offer a way to indicate a line break.
        // See: https://github.com/citation-style-language/styles/issues/2831
        $citation = str_replace('\n', "\n", $citation);

        $encodedFilename = urlencode(substr($article->getLocalizedTitle(), 0, 60)) . '.' . $styleConfig['fileExtension'];

        header("Content-Disposition: attachment; filename*=UTF-8''\"${encodedFilename}\"");
        header('Content-Type: ' . $styleConfig['contentType']);
        echo $citation;
        exit;
    }

    /**
     * @see Plugin::getActions()
     */
    public function getActions($request, $actionArgs)
    {
        $actions = parent::getActions($request, $actionArgs);

        if (!$this->getEnabled()) {
            return $actions;
        }

        $router = $request->getRouter();
        $linkAction = new LinkAction(
            'settings',
            new AjaxModal(
                $router->url(
                    $request,
                    null,
                    null,
                    'manage',
                    null,
                    [
                        'verb' => 'settings',
                        'plugin' => $this->getName(),
                        'category' => 'generic'
                    ]
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
    public function manage($args, $request)
    {
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
     *
     * @param string $hookName
     * @param array $params
     */
    public function setPageHandler($hookName, $params)
    {
        $page = $params[0];
        if ($this->getEnabled() && $page === 'citationstylelanguage') {
            $this->import('pages/CitationStyleLanguageHandler');
            define('HANDLER_CLASS', 'CitationStyleLanguageHandler');
            return true;
        }
        return false;
    }
}

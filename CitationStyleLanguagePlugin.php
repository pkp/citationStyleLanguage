<?php

/**
 * @file plugins/generic/citationStyleLanguage/CitationStyleLanguagePlugin.php
 *
 * Copyright (c) 2017-2020 Simon Fraser University
 * Copyright (c) 2017-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CitationStyleLanguagePlugin
 *
 * @ingroup plugins_generic_citationStyleLanguage
 *
 * @brief Citation Style Language plugin class.
 */

namespace APP\plugins\generic\citationStyleLanguage;

require_once(__DIR__ . '/lib/vendor/autoload.php');

use APP\author\Author;
use APP\core\Application;
use APP\facades\Repo;
use APP\issue\Issue;
use APP\monograph\Chapter;
use APP\monograph\ChapterDAO;
use APP\plugins\generic\citationStyleLanguage\pages\CitationStyleLanguageHandler;
use APP\publication\Publication;
use APP\publicationFormat\PublicationFormat;
use APP\submission\Submission;
use APP\template\TemplateManager;
use Exception;
use PKP\controlledVocab\ControlledVocab;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\core\PKPString;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use Seboettg\CiteProc\CiteProc;
use stdClass;

class CitationStyleLanguagePlugin extends GenericPlugin
{
    /** @var array List of citation styles available */
    public array $_citationStyles = [];

    /** @var array List of citation download formats available */
    public array $_citationDownloads = [];

    /** @var string Name of the application */
    public string $application;

    protected bool $isBook = false;
    protected bool $isChapter = false;
    protected bool $isArticle = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->application = Application::get()->getName();
    }

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
            Hook::add('PreprintHandler::view', $this->getTemplateData(...));
            Hook::add('CatalogBookHandler::book', $this->getTemplateData(...));
            Hook::add('ArticleHandler::view', $this->getTemplateData(...));
            Hook::add('Templates::Article::Details', $this->addCitationMarkup(...));
            Hook::add('Templates::Catalog::Book::Details', $this->addCitationMarkup(...));
            Hook::add('Templates::Catalog::Chapter::Details', $this->addCitationMarkup(...));
            Hook::add('LoadHandler', $this->setPageHandler(...));
        }
        
        return $success;
    }

    /**
     * Get list of citation styles available
     *
     * @hook CitationStyleLanguage::citationStyleDefaults [[&$defaults, $this]]
     */
    public function getCitationStyles(): array
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
                'id' => 'national-library-of-medicine',
                'title' => __('plugins.generic.citationStyleLanguage.style.national-library-of-medicine'),
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
        Hook::call('CitationStyleLanguage::citationStyleDefaults', [&$defaults, $this]);
        $this->_citationStyles = $defaults;

        return $this->_citationStyles;
    }

    /**
     * Get the primary style name or default to the first available style
     */
    public function getPrimaryStyleName(int $contextId): string
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
     */
    public function getEnabledCitationStyles(int $contextId): array
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
     * @hook CitationStyleLanguage::citationDownloadDefaults [[&$defaults, $this]]
     */
    public function getCitationDownloads(): array
    {
        if (!empty($this->_citationDownloads)) {
            return $this->_citationDownloads;
        }

        $defaults = [
            [
                'id' => 'ris',
                'title' => __('plugins.generic.citationStyleLanguage.download.ris'),
                'isEnabled' => true,
                'useTemplate' => $this->getTemplateResource('citation-styles.ris'),
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
        Hook::call('CitationStyleLanguage::citationDownloadDefaults', [&$defaults, $this]);
        $this->_citationDownloads = $defaults;

        return $this->_citationDownloads;
    }

    /**
     * Get enabled citation styles
     */
    public function getEnabledCitationDownloads(int $contextId): array
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
     */
    public function mapCitationIds(array $citations): array
    {
        return array_values(array_map(function ($citation) {
            return $citation['id'];
        }, $citations));
    }

    /**
     * Get citation config for a citation ID (example: 'apa')
     */
    public function getCitationStyleConfig(string $styleId): array
    {
        $styleConfigs = array_merge($this->getCitationStyles(), $this->getCitationDownloads());
        $styleConfig = array_filter($styleConfigs, function ($styleConfig) use ($styleId) {
            return $styleConfig['id'] === $styleId;
        });
        return array_shift($styleConfig);
    }

    protected function getPublicationTypeUrlPath(): string
    {
        return match ($this->application) {
            'ojs2' => 'article',
            'ops' => 'preprint',
            'omp' => 'catalog',
            default => ''
        };
    }

    /**
     * Retrieve citation information for the article details template. This
     * method is hooked in before a template displays.
     *
     * @throws Exception
     */
    public function getTemplateData(string $hookName, array $args): bool
    {
        $request = $args[0];
        $templateMgr = TemplateManager::getManager($request);

        $context = $request->getContext();
        $contextId = $context->getId();

        switch ($this->application) {
            case 'ops':
                $submission = $args[1];
                $publication = $args[2];
                $citation = $this->getCitation($request, $submission, $this->getPrimaryStyleName($contextId), null, $publication);
                break;
            case 'ojs2':
                $issue = $args[1];
                $submission = $args[2];
                $publication = $args[3];
                $chapter = null;
                $citation = $this->getCitation($request, $submission, $this->getPrimaryStyleName($contextId), $issue, $publication);
                break;
            case 'omp':
                /** @var Submission $submission */
                $submission = &$args[1];
                $publication = &$args[2];
                $chapter = &$args[3];
                $issue = null;
                $citation = $this->getCitation($request, $submission, $this->getPrimaryStyleName($contextId), $issue, $publication);
                break;
            default:
                throw new Exception('Unknown application!');
        }

        $citationArgs = [
            'submissionId' => $submission->getId(),
            'publicationId' => $publication->getId(),
        ];
        if ($issue) {
            $citationArgs['issueId'] = $issue->getId();
        }
        if ($chapter) {
            $citationArgs['chapterId'] = $chapter->getId();
        }
        $citationArgsJson = $citationArgs;
        $citationArgsJson['return'] = 'json';

        $templateMgr->assign([
            'citation' => $citation,
            'citationArgs' => $citationArgs,
            'citationArgsJson' => $citationArgsJson,
            'citationStyles' => $this->getEnabledCitationStyles($contextId),
            'citationDownloads' => $this->getEnabledCitationDownloads($contextId),
        ]);

        $templateMgr->addStyleSheet(
            'cslPluginStyles',
            $request->getBaseUrl() . '/' . $this->getPluginPath() . '/css/citationStyleLanguagePlugin.css',
            [
                'priority' => TemplateManager::STYLE_SEQUENCE_LAST,
                'contexts' => ['frontend'],
                'inline' => false,
            ]
        );

        $templateMgr->addJavaScript(
            'citationStyleLanguage',
            $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/articleCitation.js'
        );

        return false;
    }

    public function addCitationMarkup(string $hookName, array $args): bool
    {
        $output = &$args[2];

        // Get partial blade view
        $output .= TemplateManager::getManager()->fetch($this->getTemplateResource('citation-block'));

        return false;
    }

    /**
     * Get a specified citation for a given article, book or chapter
     *
     * This citation format follows the csl-json schema and takes some direction
     * from existing CSL mappings documented by Zotero and Mendeley.
     *
     * @see CSL-json schema https://github.com/citation-style-language/schema#csl-json-schema
     * @see Zotero's mappings https://aurimasv.github.io/z2csl/typeMap.xml#map-journalArticle
     * @see Mendeley's mappings http://support.mendeley.com/customer/portal/articles/364144-csl-type-mapping
     *
     * @param string $citationStyle Name of the citation style to use.
     * @param ?Issue $issue Optional. Will fetch from db if not passed.
     * @param ?Publication $publication Optional. A particular version
     * @param ?Chapter $chapter Optional. OMP chapter pages only.
     *
     * @throws Exception
     *
     * @hook CitationStyleLanguage::citation [[&$citationData, &$citationStyle, $submission, $issue, $context, $publication]]
     */
    public function getCitation(PKPRequest $request, Submission $submission, string $citationStyle = 'apa', ?Issue $issue = null, ?Publication $publication = null, ?Chapter $chapter = null): string
    {
        $publication ??= $submission->getCurrentPublication();
        $context = $request->getContext();
        if (!$chapter) {
            $chapter = $this->application === 'omp' ? $this->getChapter($request, $publication) : null;
        }
        $this->setDocumentType($chapter);

        $keywords = collect($publication->getData('keywords'))
                            ->map(
                                fn(array $items): array => collect($items)
                                    ->pluck("name")
                                ->all()
                                )
                            ->all();

        $citationData = new stdClass();

        if ($this->isArticle) {
            $citationData->type = ($this->application === 'ojs2' ? 'article-journal' : 'article');
            $citationData->risType = 'JOUR';
            $citationData->id = $submission->getId();
            $citationData->title = $publication->getLocalizedFullTitle();
            $citationData->{'container-title'} = $context->getLocalizedName();
            $issueId = $publication->getData('issueId');
            $issue ??= $issueId ? Repo::issue()->get($issueId) : null;
            if ($issue) {
                $citationData->volume = $issue->getData('volume');
                // Zotero prefers issue and Mendeley uses `number` to store revisions
                $citationData->issue = $issue->getData('number');
            }

            if ($sectionId = $publication->getData('sectionId')) {
                $section = Repo::section()->get($sectionId);
                if ($section && !$section->getHideTitle()) {
                    $citationData->section = htmlspecialchars($section->getTitle($context->getPrimaryLocale()));
                }
            }
            $citationData->keywords = $keywords[Locale::getLocale()] ?? [];
            if ($publication->getData('pages')) {
                $citationData->page = htmlspecialchars($publication->getData('pages'));
            }
            $citationData->abstract = PKPString::html2text($publication->getLocalizedData('abstract'));
            $citationData = $this->setArticleAuthors($citationData, $publication, $context);
            $citationData->URL = $request->getDispatcher()->url($request, PKPApplication::ROUTE_PAGE, null, $this->getPublicationTypeUrlPath(), 'view', [$publication->getData('urlPath') ?? $submission->getId()], urlLocaleForPage: '');
            if ($publication->getDoi()) {
                $citationData->DOI = $publication->getDoi();
            }
        } elseif ($this->isBook) {
            $citationData->type = 'book';
            $citationData->risType = 'BOOK';
            $citationData->id = $submission->getId();
            $citationData->title = $publication->getLocalizedFullTitle();
            $citationData = $this->addSeriesInformation($citationData, $publication);
            $citationData->publisher = $context->getLocalizedName();
            $citationData->keywords = $keywords[Locale::getLocale()] ?? [];
            if ($publication->getData('pages')) {
                $citationData->page = htmlspecialchars($publication->getData('pages'));
            }
            $citationData->abstract = PKPString::html2text($publication->getLocalizedData('abstract'));
            $citationData->serialNumber = $this->getSerialNumber($publication);
            $citationData = $this->setBookAuthors($citationData, $publication, $context);
            $citationData->URL = $request->getDispatcher()->url($request, PKPApplication::ROUTE_PAGE, null, $this->getPublicationTypeUrlPath(), 'book', [$publication->getData('urlPath') ?? $submission->getId()], urlLocaleForPage: '');
            if ($publication->getDoi()) {
                $citationData->DOI = $publication->getDoi();
            }
        } elseif ($this->isChapter) {
            $citationData->type = 'chapter';
            $citationData->risType = 'CHAP';
            $citationData->id = $chapter->getSourceChapterId();
            $citationData->title = $chapter->getLocalizedFullTitle();
            $citationData->{'container-title'} = $publication->getLocalizedFullTitle();
            $citationData = $this->addSeriesInformation($citationData, $publication);
            $citationData->publisher = $context->getLocalizedName();
            if ($chapter->getPages()) {
                $citationData->page = htmlspecialchars($chapter->getPages());
            }
            $citationData->abstract = htmlspecialchars(strip_tags($chapter->getLocalizedData('abstract')));
            $citationData->serialNumber = $this->getSerialNumber($publication);
            $citationData = $this->setBookChapterAuthors($citationData, $publication, $context, $chapter);
            $citationData->URL = $request->getDispatcher()->url($request, PKPApplication::ROUTE_PAGE, null, $this->getPublicationTypeUrlPath(), 'book', [$publication->getData('urlPath') ?? $submission->getId(), 'chapter', $chapter->getSourceChapterId()], urlLocaleForPage: '');

            if ($chapter->getDoi()) {
                $citationData->DOI = $chapter->getDoi();
            }
        } else {
            throw new Exception('Unknown submission content type!');
        }

        $citationData->languages = collect($publication->getData('galleys') ?? [])
            ->map(fn ($g) => $g->getData('locale'))
            ->push($submission->getData('locale'))
            ->filter()->unique()->sort()->values()->toArray();

        $citationData->{'publisher-place'} = $this->getSetting($context->getId(), 'publisherLocation');
        $abbreviation = $context->getData('abbreviation', $context->getPrimaryLocale()) ?? $context->getData('acronym', $context->getPrimaryLocale());
        if ($abbreviation) {
            $citationData->{'container-title-short'} = $abbreviation;
        }

        $citationData->accessed = new stdClass();
        $citationData->accessed->raw = date('Y-m-d');

        if ($publication->getData('datePublished')) {
            $citationData->issued = new stdClass();
            $citationData->issued->raw = htmlspecialchars($publication->getData('datePublished'));
            $publishedPublications = $submission->getPublishedPublications();
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
        } elseif ($this->isArticle && $issue?->getPublished()) {
            $citationData->issued = new stdClass();
            $citationData->issued->raw = htmlspecialchars($issue->getDatePublished());
        }

        Hook::call('CitationStyleLanguage::citation', [&$citationData, &$citationStyle, $submission, $issue, $context, $publication]);

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
                switch ($this->application) {
                    case 'ops':
                    case 'ojs2':
                        $templateMgr->assign([
                            'article' => $submission,
                            'issue' => $issue,
                            'journal' => $context,
                        ]);
                        break;
                    case 'omp':
                        $templateMgr->assign([
                            'book' => $submission,
                            'press' => $context,
                        ]);
                        break;
                    default: throw new Exception('Unknown application!');
                }

                $citation = $templateMgr->fetch($styleConfig['useTemplate']);
            } else {
                $style = $this->loadStyle($styleConfig);
                if ($style) {
                    // Determine what locale to use. Fall back English if none found.
                    $tryLocale = $this->getCSLLocale(Locale::getLocale(), 'en-US');

                    // Clickable URL and DOI including affixes
                    $additionalMarkup = [
                        'DOI' => [
                            'function' => function ($item, $renderedValue) {
                                $doiWithUrl = 'https://doi.org/' . $item->DOI;
                                if (str_contains($renderedValue, $doiWithUrl)) {
                                    $doiLink = '<a href="' . $doiWithUrl . '">' . $doiWithUrl . '</a>';
                                    return str_replace($doiWithUrl, $doiLink, $renderedValue);
                                } else {
                                    $doiLink = '<a href="' . $doiWithUrl . '">' . $item->DOI . '</a>';
                                    return str_replace($item->DOI, $doiLink, $renderedValue);
                                }
                            },
                            'affixes' => true
                        ],
                        'URL' => [
                            'function' => function ($item, $renderedValue) {
                                return '<a href="' . $item->URL . '">' . $renderedValue . '</a>';
                            },
                            'affixes' => false
                        ],
                    ];

                    $citeProc = new CiteProc($style, $tryLocale, $additionalMarkup);
                    $citation = $citeProc->render([$citationData], 'bibliography');
                }
            }
        }

        return $citation;
    }

    /**
     * Load a CSL style and return the contents as a string
     */
    public function loadStyle(array $styleConfig): false|string
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
     * @param string $citationStyle Name of the citation style to use.
     * @param Issue $issue Optional. Will fetch from db if not passed.
     * @param $publication Publication Optional.
     * @param $chapter Chapter Optional. OMP chapter pages only.
     */
    public function downloadCitation(PKPRequest $request, Submission $submission, string $citationStyle = 'ris', ?Issue $issue = null, ?Publication $publication = null, ?Chapter $chapter = null)
    {
        if ($this->isArticle) {
            $issueId = $submission->getCurrentPublication()->getData('issueId');
            $issue ??= $issueId ? Repo::issue()->get($issueId) : null;
        }

        $styleConfig = $this->getCitationStyleConfig($citationStyle);
        if (empty($styleConfig)) {
            return false;
        }

        $citation = trim(PKPString::html2text($this->getCitation($request, $submission, $citationStyle, $issue, $publication, $chapter)));

        // TODO this is likely going to cause an error in a citation some day,
        // but is necessary to get the .ris downloadable format working. The
        // CSL language doesn't seem to offer a way to indicate a line break.
        // See: https://github.com/citation-style-language/styles/issues/2831
        $citation = str_replace('\n', "\n", $citation);

        $encodedFilename = urlencode(substr(($publication ? $publication->getLocalizedTitle() : ''), 0, 60)) . '.' . $styleConfig['fileExtension'];

        header("Content-Disposition: attachment; filename*=UTF-8''{$encodedFilename}");
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
                        'category' => 'generic',
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
     */
    public function setPageHandler(string $hookName, array $params): bool
    {
        $page = &$params[0];
        $handler = &$params[3];
        if ($this->getEnabled() && $page === 'citationstylelanguage') {
            $handler = new CitationStyleLanguageHandler($this);
            return true;
        }
        return false;
    }

    public function getEditorGroups(int $contextId): array
    {
        return $this->getSetting($contextId, 'groupEditor') ?? [];
    }

    public function getTranslatorGroups(int $contextId): array
    {
        return $this->getSetting($contextId, 'groupTranslator') ?? [];
    }

    public function getAuthorGroups(int $contextId): array
    {
        return $this->getSetting($contextId, 'groupAuthor') ?? [];
    }

    public function getChapterAuthorGroups(int $contextId): array
    {
        return $this->getSetting($contextId, 'groupChapterAuthor') ?? [];
    }

    public function getSerialNumber(Publication $publication): array
    {
        $serialNumber = [];
        $publicationFormats = $publication->getData('publicationFormats');
        /** @var PublicationFormat $publicationFormat */
        foreach ($publicationFormats as $publicationFormat) {
            if ($publicationFormat->getIsApproved()) {
                $identificationCodes = $publicationFormat->getIdentificationCodes();
                $identificationCodes = $identificationCodes->toArray();
                foreach ($identificationCodes as $identificationCode) {
                    $serialNumber[] = htmlspecialchars($identificationCode->getValue());
                }
            }
        }
        return $serialNumber;
    }

    protected function addSeriesInformation(stdClass $citationData, Publication $publication): stdClass
    {
        $seriesId = $publication->getData('seriesId');
        $series = $seriesId ? Repo::section()->get($seriesId) : null;
        if (!$series) {
            return $citationData;
        }
        $citationData->{'collection-title'} = trim($series->getLocalizedFullTitle());
        $citationData->volume = $publication->getData('seriesPosition');
        $citationData->{'collection-editor'} = htmlspecialchars($series->getEditorsString());
        $onlineISSN = $series->getOnlineISSN();
        if (!empty($onlineISSN)) {
            $citationData->serialNumber[] = htmlspecialchars($onlineISSN);
        }
        $printISSN = $series->getPrintISSN();
        if (!empty($printISSN)) {
            $citationData->serialNumber[] = htmlspecialchars($printISSN);
        }
        return $citationData;
    }

    protected function getChapter(PKPRequest $request, Publication $publication): ?Chapter
    {
        $args = $request->getRequestedArgs();

        if (isset($args[1], $args[2]) && $args[1] === 'chapter') {
            $key = 2;
        } elseif (isset($args[1], $args[3], $args[4]) && $args[1] === 'version' && $args[3] === 'chapter') {
            $key = 4;
        } else {
            return null;
        }

        $chapterId = (int) $args[$key];
        if ($chapterId > 0) {
            /** @var ChapterDAO */
            $chapterDao = DAORegistry::getDAO('ChapterDAO');
            return $chapterDao->getBySourceChapterAndPublication($chapterId, $publication->getId());
        }
        return null;
    }

    /**
     * @throws Exception
     */
    protected function setDocumentType($chapter): void
    {
        switch ($this->application) {
            case 'ops':
            case 'ojs2':
                $this->isArticle = true;
                break;
            case 'omp':
                if ($chapter) {
                    $this->isChapter = true;
                } else {
                    $this->isBook = true;
                }
                break;
            default: throw new Exception('Unknown application!');
        }
    }

    protected function setArticleAuthors($citationData, $publication, $context): stdClass
    {
        $authors = $publication->getData('authors');
        $authorsGroups = $this->getAuthorGroups($context->getId());
        $translatorsGroups = $this->getTranslatorGroups($context->getId());
        if (count($authors)) {
            /** @var Author $author */
            foreach ($authors as $author) {
                $currentAuthor = new stdClass();
                if (empty($author->getLocalizedFamilyName())) {
                    $currentAuthor->family = htmlspecialchars($author->getLocalizedGivenName());
                } else {
                    $currentAuthor->family = htmlspecialchars($author->getLocalizedFamilyName());
                    $currentAuthor->given = htmlspecialchars($author->getLocalizedGivenName());
                }

                $userGroupId = $author->getUserGroupId();
                switch (true) {
                    case in_array($userGroupId, $translatorsGroups):
                        if (!isset($citationData->translator)) {
                            $citationData->translator = [];
                        }
                        $citationData->translator[] = $currentAuthor;
                        break;
                    case in_array($userGroupId, $authorsGroups):
                        if (!isset($citationData->author)) {
                            $citationData->author = [];
                        }
                        $citationData->author[] = $currentAuthor;
                        break;
                    default:
                        if (!isset($citationData->author)) {
                            $citationData->author = [];
                        }
                        break;
                }
            }
        }

        return $citationData;
    }

    protected function setBookAuthors($citationData, $publication, $context): stdClass
    {
        $authors = $publication->getData('authors');
        $authorsGroups = $this->getAuthorGroups($context->getId());
        $editorsGroups = $this->getEditorGroups($context->getId());
        $translatorsGroups = $this->getTranslatorGroups($context->getId());
        if (count($authors)) {
            /** @var Author $author */
            foreach ($authors as $author) {
                $currentAuthor = new stdClass();
                if (empty($author->getLocalizedFamilyName())) {
                    $currentAuthor->family = htmlspecialchars($author->getLocalizedGivenName());
                } else {
                    $currentAuthor->family = htmlspecialchars($author->getLocalizedFamilyName());
                    $currentAuthor->given = htmlspecialchars($author->getLocalizedGivenName());
                }

                $userGroupId = $author->getUserGroupId();
                switch (true) {
                    case in_array($userGroupId, $editorsGroups):
                        if (!isset($citationData->editor)) {
                            $citationData->editor = [];
                        }
                        $citationData->editor[] = $currentAuthor;
                        break;
                    case in_array($userGroupId, $translatorsGroups):
                        if (!isset($citationData->translator)) {
                            $citationData->translator = [];
                        }
                        $citationData->translator[] = $currentAuthor;
                        break;
                    case in_array($userGroupId, $authorsGroups):
                        if (!isset($citationData->author)) {
                            $citationData->author = [];
                        }
                        $citationData->author[] = $currentAuthor;
                        break;
                    default:
                        if (!isset($citationData->author)) {
                            $citationData->author = [];
                        }
                        break;
                }
            }
        }

        return $citationData;
    }

    protected function setBookChapterAuthors($citationData, $publication, $context, $chapter): stdClass
    {
        $chapterAuthorGroups = $this->getChapterAuthorGroups($context->getId());
        $chapterAuthors = $chapter->getAuthors();

        /** @var Author $chapterAuthor */
        foreach ($chapterAuthors as $chapterAuthor) {
            $currentAuthor = new stdClass();
            if (empty($chapterAuthor->getLocalizedFamilyName())) {
                $currentAuthor->family = htmlspecialchars($chapterAuthor->getLocalizedGivenName());
            } else {
                $currentAuthor->family = htmlspecialchars($chapterAuthor->getLocalizedFamilyName());
                $currentAuthor->given = htmlspecialchars($chapterAuthor->getLocalizedGivenName());
            }

            $userGroupId = $chapterAuthor->getUserGroupId();
            if (in_array($userGroupId, $chapterAuthorGroups)) {
                $citationData->author[] = $currentAuthor;
            }
        }

        $bookAuthors = $publication->getData('authors');
        $authorsGroups = $this->getAuthorGroups($context->getId());
        $editorsGroups = $this->getEditorGroups($context->getId());
        $translatorsGroups = $this->getTranslatorGroups($context->getId());
        if (count($bookAuthors)) {
            /** @var Author $bookAuthor */
            foreach ($bookAuthors as $bookAuthor) {
                $currentAuthor = new stdClass();
                if (empty($bookAuthor->getLocalizedFamilyName())) {
                    $currentAuthor->family = htmlspecialchars($bookAuthor->getLocalizedGivenName());
                } else {
                    $currentAuthor->family = htmlspecialchars($bookAuthor->getLocalizedFamilyName());
                    $currentAuthor->given = htmlspecialchars($bookAuthor->getLocalizedGivenName());
                }

                $userGroupId = $bookAuthor->getUserGroupId();
                switch (true) {
                    case in_array($userGroupId, $editorsGroups):
                        if (!isset($citationData->editor)) {
                            $citationData->editor = [];
                        }
                        $citationData->editor[] = $currentAuthor;
                        break;
                    case in_array($userGroupId, $translatorsGroups):
                        if (!isset($citationData->translator)) {
                            $citationData->translator = [];
                        }
                        $citationData->translator[] = $currentAuthor;
                        break;
                    case in_array($userGroupId, $authorsGroups):
                        if (!isset($citationData->{'container-author'})) {
                            $citationData->{'container-author'} = [];
                        }
                        $citationData->{'container-author'}[] = $currentAuthor;
                        break;
                    default:
                        if (!isset($citationData->author)) {
                            $citationData->author = [];
                        }
                        break;
                }
            }
        }

        if (isset($citationData->{'container-author'})) {
            $diffChapterAuthorsAuthors = array_udiff($citationData->{'container-author'}, $citationData->author, $this->compareAuthors(...));
            if (count($diffChapterAuthorsAuthors) === 0) {
                $citationData->{'container-author'} = [];
            }
        }

        return $citationData;
    }

    protected function compareAuthors($a, $b): int
    {
        return 0 === strcmp($a->family, $b->family) && 0 === strcmp($a->given, $b->given) ? 0 : 1;
    }

    /**
     * Find the best match for a CSL locale.
     *
     * @param $locale Weblate locale.
     * @param $defaultLocale A locale code to use as default. This should already be sanitized.
     *
     * @return string A language code that's available in the CSL library.
     */
    public function getCSLLocale(string $locale, string $defaultLocale = 'en-US'): string
    {
        $prefix = $this->getPluginPath() . '/lib/vendor/citation-style-language/locales/locales-';
        $suffix = '.xml';
        $preferences = [
            'de' => 'de-DE',
            'en' => 'en-US',
            'es' => 'es-ES',
            'fr' => 'fr-FR',
            'pt' => 'pt-PT',
        ];
        // Determine the language and region we're looking for from $locale
        $language = \Locale::getPrimaryLanguage($locale);
        $region = \Locale::getRegion($locale) ?? null;
        $localeAndRegion = $language . ($region ? "-{$region}" : '');
        // Get a list of available options from the filesystem.
        $availableLocaleFiles = glob("{$prefix}*{$suffix}");
        // 1. Look for an exact match and return it.
        if (in_array("{$prefix}{$locale}{$suffix}", $availableLocaleFiles)) {
            return $locale;
        }
        // 2. Look in the preference list for a preferred fallback.
        if ($preference = $preferences[$localeAndRegion] ?? false) {
            return $preference;
        }
        // 3. Find the first match by language.
        foreach ($availableLocaleFiles as $filename) {
            if (strpos($filename, "{$prefix}{$language}-") === 0) {
                return substr($filename, strlen($prefix), -strlen($suffix));
            }
        }
        // 4. Use the supplied default.
        return $defaultLocale;
    }
}

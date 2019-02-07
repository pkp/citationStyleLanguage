{**
 * plugins/generic/citationStyleLanguage/templates/citation-styles/ris.tpl
 *
 * Copyright (c) 2017-2018 Simon Fraser University
 * Copyright (c) 2017-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief Citation output for the .ris downloadable format
 *
 * @uses $citationData stdClass Compiled citation data
 * @uses $citationStyle string Name of the citation style being compiled.
 * @uses $article Article
 * @uses $issue Issue
 * @uses $journal Journal
 *}
{assign var="containerTitle" value="container-title"}
{assign var="containerTitleShort" value="container-title-short"}
TY  - JOUR
{foreach from=$citationData->author item="author"}
AU  - {$author->given} {$author->family}
{/foreach}
PY  - {$citationData->issued->raw|date_format:"%Y/%m/%d"}
Y2  - {$citationData->accessed->raw|date_format:"%Y/%m/%d"}
TI  - {$citationData->title}
JF  - {$citationData->$containerTitle}
JA  - {$citationData->$containerTitleShort}
VL  - {$citationData->volume}
IS  - {$citationData->issue}
SE  - {$citationData->section}
DO  - {$citationData->DOI}
UR  - {$citationData->URL}
AB  - {$article->getLocalizedAbstract()|replace:"\r\n":""|replace:"\n":""}
ER  -

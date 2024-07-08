{**
 * plugins/generic/citationStyleLanguage/templates/citation-styles/ris.tpl
 *
 * Copyright (c) 2017-2020 Simon Fraser University
 * Copyright (c) 2017-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Citation output for the .ris downloadable format
 *
 * @uses $citationData stdClass Compiled citation data
 * @uses $citationStyle string Name of the citation style being compiled.
 * @uses $article Article
 * @uses $publication Publication
 * @uses $issue Issue
 * @uses $journal Journal
 *}
{assign var="containerTitle" value="container-title"}
{assign var="containerTitleShort" value="container-title-short"}
{assign var="collectionTitle" value="collection-title"}
{assign var="collectionNumber" value="collection-number"}
{assign var="collectionEditor" value="collection-editor"}
{assign var="publisherPlace" value="publisher-place"}

TY  - {$citationData->risType}
{foreach from=$citationData->author item="author"}
AU  - {$author->family}, {$author->given}
{/foreach}
{if $citationData->risType === 'BOOK'}{** OMP book only **}
{if $citationData->$collectionEditor}
A2  - {$citationData->$collectionEditor}
{/if}
{foreach from=$citationData->editor item="editor"}
A3  - {$editor->family}, {$editor->given}
{/foreach}
{elseif $citationData->risType === 'CHAP'}{** OMP chapter only **}
{foreach from=$citationData->editor item="editor"}
A2  - {$editor->family}, {$editor->given}
{/foreach}
{if $citationData->$collectionEditor}
A3  - {$citationData->$collectionEditor}
{/if}
{/if}
{foreach from=$citationData->translator item="translator"}
A4  - {$translator->family}, {$translator->given}
{/foreach}
{if $citationData->title}
TI  - {$citationData->title}
{/if}
{if $citationData->risType === 'JOUR'}{** OJS only **}
{if $citationData->issued}
PY  - {$citationData->issued->raw|date_format:"%Y/%m/%d"}
{/if}
{if $citationData->accessed}
Y2  - {$citationData->accessed->raw|date_format:"%Y/%m/%d"}
{/if}
{if $citationData->$containerTitle}
JF  - {$citationData->$containerTitle}
{/if}
{if $citationData->$containerTitleShort}
JA  - {$citationData->$containerTitleShort}
{/if}
{if $citationData->volume}
VL  - {$citationData->volume}
{/if}
{if $citationData->issue}
IS  - {$citationData->issue}
{/if}
{if $citationData->section}
SE  - {$citationData->section}
{/if}
{else}{** OMP only **}
{if $citationData->$containerTitle}
T2  - {trim($citationData->$containerTitle)}
{/if}
{if $citationData->$collectionTitle}
T3  - {trim($citationData->$collectionTitle)}
{/if}
{if $citationData->$collectionNumber}
M1  - {$citationData->$collectionNumber}
{/if}
{if $citationData->$publisherPlace}
PP  - {$citationData->$publisherPlace}
{/if}
{if $citationData->$publisher}
PB  - {$citationData->publisher}
{/if}
{if $citationData->issued}
PY  - {$citationData->issued->raw|date_format:"%Y"}
{/if}
{/if}{** all **}
{foreach from=$citationData->languages item="language"}
LA  - {$language}
{/foreach}
{foreach from=$citationData->serialNumber item="serialNumber"}
SN  - {$serialNumber}
{/foreach}
{foreach from=$citationData->keywords item="keyword"}
KW  - {$keyword}
{/foreach}
{if $citationData->DOI}
DO  - {$citationData->DOI}
UR  - https://doi.org/{$citationData->DOI}
{else}
UR  - {$citationData->URL}
{/if}
{if $citationData->page}
SP  - {$citationData->page}
{/if}
{if $citationData->abstract}
AB  - {$citationData->abstract|replace:"\r\n":""|replace:"\n":""}
{/if}
ER  -

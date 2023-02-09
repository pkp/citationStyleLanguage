<?php
/**
 * @defgroup plugins_generic_citationStyleLanguage
 */
/**
 * @file plugins/generic/citationStyleLanguage/index.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup plugins_generic_citationStyleLanguage
 * @brief Wrapper for Citation Style Language plugin.
 *
 */
require_once('CitationStyleLanguagePlugin.php');
return new APP\plugins\generic\citationStyleLanguage\CitationStyleLanguagePlugin();

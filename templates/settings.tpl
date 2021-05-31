{**
 * plugins/generic/citationStyleLanguage/templates/settings.tpl
 *
 * Copyright (c) 2017-2020 Simon Fraser University
 * Copyright (c) 2017-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Form to enable/disable CSL citation styles and define a primary citation style.
 *
 *}
<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#citationStyleLanguageSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="citationStyleLanguageSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}

	{fbvFormArea id="citationStyleLanguagePluginSettings"}
		{fbvFormSection list=true title="plugins.generic.citationStyleLanguage.settings.citationFormatsPrimary"}
			<p>
				{if $isApplicationOmp}
					{translate key="plugins.generic.citationStyleLanguage.settings.citationFormatsPrimaryDescription.omp"}
				{else}
					{translate key="plugins.generic.citationStyleLanguage.settings.citationFormatsPrimaryDescription"}
				{/if}
			</p>
			{foreach from=$allStyles item="style" key="id"}
				{fbvElement type="radio" name="primaryCitationStyle" id="primaryCitationStyle"|concat:$id value=$id checked=($id === $primaryCitationStyle) label=$style translate=false}
			{/foreach}
		{/fbvFormSection}
		{fbvFormSection list=true title="plugins.generic.citationStyleLanguage.settings.citationFormats"}
			<p>{translate key="plugins.generic.citationStyleLanguage.settings.citationFormatsDescription"}</p>
			{foreach from=$allStyles item="style" key="id"}
				{fbvElement type="checkbox" id="enabledCitationStyles[]" value=$id checked=in_array($id, $enabledStyles) label=$style translate=false}
			{/foreach}
		{/fbvFormSection}
		{fbvFormSection list=true title="plugins.generic.citationStyleLanguage.settings.citationDownloads"}
			<p>{translate key="plugins.generic.citationStyleLanguage.settings.citationDownloadsDescription"}</p>
			{foreach from=$allDownloads item="style" key="id"}
				{fbvElement type="checkbox" id="enabledCitationDownloads[]" value=$id checked=in_array($id, $enabledDownloads) label=$style translate=false}
			{/foreach}
		{/fbvFormSection}
		{fbvFormSection}
			<div id="description">{translate key="plugins.generic.citationStyleLanguage.settings.publisherLocation.description"}</div>
			{fbvElement type="text" id="publisherLocation" value=$publisherLocation label="plugins.generic.citationStyleLanguage.settings.publisherLocation"}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons}
</form>

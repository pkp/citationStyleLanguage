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
		{fbvFormSection}
			<list-panel
				v-bind="components.primaryCitationStyles"
				@set="set"
			/>
		{/fbvFormSection}
		{fbvFormSection}
			<list-panel
				v-bind="components.citationStyles"
				@set="set"
			/>
		{/fbvFormSection}
		{fbvFormSection}
			<list-panel
				v-bind="components.citationDownloads"
				@set="set"
			/>
		{/fbvFormSection}
		{fbvFormSection}
			<div id="description">{translate key="plugins.generic.citationStyleLanguage.settings.publisherLocation.description"}</div>
			{fbvElement type="text" id="publisherLocation" value=$publisherLocation label="plugins.generic.citationStyleLanguage.settings.publisherLocation"}
		{/fbvFormSection}
	{/fbvFormArea}
	<script type="text/javascript">
		pkp.registry.init('citationStyleLanguagePluginSettings', 'Container', {$settingsData|json_encode});
	</script>

	{fbvFormButtons}
</form>

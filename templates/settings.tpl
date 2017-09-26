{**
 * plugins/generic/citationStyleLanguage/templates/settings.tpl
 *
 * Copyright (c) 2017 Simon Fraser University
 * Copyright (c) 2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
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

	{fbvFormArea}
		{fbvFormSection}
			{assign var="uuid" value=""|uniqid|escape}
			<div id="primary-citation-styles-{$uuid}">
				<script type="text/javascript">
					pkp.registry.init('primary-citation-styles-{$uuid}', 'SelectListPanel', {$primaryCitationStyleListData});
				</script>
			</div>
		{/fbvFormSection}
		{fbvFormSection}
			{assign var="uuid" value=""|uniqid|escape}
			<div id="citation-styles-{$uuid}">
				<script type="text/javascript">
					pkp.registry.init('citation-styles-{$uuid}', 'SelectListPanel', {$citationStylesListData});
				</script>
			</div>
		{/fbvFormSection}
		{fbvFormSection}
			{assign var="uuid" value=""|uniqid|escape}
			<div id="citation-downloads-{$uuid}">
				<script type="text/javascript">
					pkp.registry.init('citation-downloads-{$uuid}', 'SelectListPanel', {$citationDownloadsListData});
				</script>
			</div>
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons}
</form>

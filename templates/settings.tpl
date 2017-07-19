{**
 * plugins/generic/citationStyleLanguage/settings.tpl
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
		{fbvFormSection title="plugins.generic.citationStyleLanguage.settings.citationFormats" description="plugins.generic.citationStyleLanguage.settings.citationFormatsDescription"}
			<table class="pkp_csl_styles">
				<tr>
					<th class="pkp_csl_style_label">
						{translate key="plugins.generic.citationStyleLanguage.settings.format"}
					</th>
					<th class="pkp_csl_style_enabled">
						{translate key="common.enabled"}
					</th>
					<th class="pkp_csl_style_primary">
						{translate key="plugins.generic.citationStyleLanguage.settings.primary"}
					</th>
				</tr>
				{foreach from=$citationStyles key="citationStyleId" item="citationStyle"}
					<tr class="pkp_csl_style pkp_csl_style_header">
						<td class="pkp_csl_style_label">
							{$citationStyle.label}
						</td>
						<td class="pkp_csl_style_enabled">
							<label for="csl-enabled-{$citationStyleId|escape}" class="pkp_screen_reader">
								{translate key="common.enabled"}
							</label>
							<input type="checkbox" id="csl-enabled-{$citationStyleId|escape}" name="enabledCitationStyles[]" value="{$citationStyleId|escape}"{if in_array($citationStyleId, $enabledCitationStyles)} checked{/if}>
						</td>
						<td class="pkp_csl_style_primary">
							<label for="csl-primary-{$citationStyleId|escape}" class="pkp_screen_reader">
								{translate key="plugins.generic.citationStyleLanguage.settings.primary"}
							</label>
							<input type="radio" id="csl-primary-{$citationStyleId|escape}" name="primaryCitationStyle" value="{$citationStyleId|escape}"{if $citationStyleId == $primaryCitationStyle} checked{/if}>
						</td>
					</tr>
				{/foreach}
			</table>
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormArea}
		{fbvFormSection title="plugins.generic.citationStyleLanguage.settings.citationDownloads" description="plugins.generic.citationStyleLanguage.settings.citationDownloadsDescription"}
			<table class="pkp_csl_styles">
				<tr>
					<th class="pkp_csl_style_label">
						{translate key="plugins.generic.citationStyleLanguage.settings.format"}
					</th>
					<th class="pkp_csl_style_enabled">
						{translate key="common.enabled"}
					</th>
				</tr>
				{foreach from=$citationDownloads key="citationDownloadId" item="citationDownload"}
					<tr class="pkp_csl_style pkp_csl_style_header">
						<td class="pkp_csl_style_label">
							{$citationDownload.label}
						</td>
						<td class="pkp_csl_style_enabled">
							<label for="csl-enabled-{$citationDownloadId|escape}" class="pkp_screen_reader">
								{translate key="common.enabled"}
							</label>
							<input type="checkbox" id="csl-enabled-{$citationDownloadId|escape}" name="enabledCitationDownloads[]" value="{$citationDownloadId|escape}"{if in_array($citationDownloadId, $enabledCitationDownloads)} checked{/if}>
						</td>
					</tr>
				{/foreach}
			</table>
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons}
</form>

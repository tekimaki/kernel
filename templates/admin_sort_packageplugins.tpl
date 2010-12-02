{strip}
{assign var=pageName value=kernel_`$page`}

{form class=$pageName|replace:'packages':'pkg'}
	<input type="hidden" name="page" value="{$page}" />
	{legend legend="Sort Plugin Display Order"}
		<p>{tr}Set the order in which you would like plugins to be displayed (mostly this is for plugin edit include fieldsets.{/tr}</p>

		{foreach from=$gBitSystem->getPackagePluginsConfig() key=plugin_guid item=plugin}
			<div class="row">
				{formlabel label=$plugin.name for=plugin_$plugin_guid}
				{forminput}
					<input type="text" size=4 value="{$plugin.pos}" name="package_plugins[{$plugin_guid}]" id="plugin_{$plugin_guid}" />
					{formhelp note=$plugin.description}
				{/forminput}
			</div>
		{/foreach}
	{/legend}

	<div class="buttonHolder row submit">
		<input class="button" type="submit" name="update_plugins" value="{tr}Save{/tr}"/>
	</div>
{/form}
{/strip}

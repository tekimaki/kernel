{strip}
{* checks if installer path is available *}
{assign var=installfile value="`$smarty.const.INSTALL_PKG_PATH`install.php"|is_file}
{assign var=installread value="`$smarty.const.INSTALL_PKG_PATH`install.php"|is_readable}
{if $installfile neq 1 and $installread neq 1}
	{capture assign=install_unavailable}
		<p>{tr}You might have to rename your <strong>install/install.done</strong> file back to <strong>install/install.php</strong>.{/tr}</p>
	{/capture}
{/if}

{assign var=pageName value=kernel_`$page`}

{form class=$pageName|replace:'packages':'pkg'}
	<input type="hidden" name="page" value="{$page}" />
	{jstabs}
		{if $upgradable}
			{jstab title="Updates"}
				{if $upgradable}
				{legend legend="Upgradable plugins"}
					<p class="warning">
						{biticon iname="large/dialog-warning" iexplain="Warning"} {tr}You seem to have at least one package plugin that can be upgraded.{/tr} <a href="{$smarty.const.INSTALL_PKG_URL}install.php?step=4">{tr}We recommend you visit the installer now{/tr}</a>.
					</p>

					{foreach from=$gBitSystem->mPackagesSchemas key=package item=item}
						{if !empty($item.plugins)}
							{assign var=hasPluginUpgrade value=0}
							{if $item.plugins}
								{foreach from=$item.plugins key=plugin_guid item=plugin}
									{if $upgradable.$plugin_guid}
										{assign var=hasPluginUpgrade value=1}
									{/if}
								{/foreach}
							{/if}
							{if $hasPluginUpgrade}
							<div class="row">
								<div class="formlabel">
									<label for="{$package}">
										{biticon ipackage=$package iname="pkg_$package" iexplain=`$package`}
									</label>
								</div>
								{forminput}
									<div><strong>{$item.name} Package Plugins</strong></div>
									<ul>
									{foreach from=$item.plugins key=plugin_guid item=plugin}
										{if $upgradable.$plugin_guid}
											<li>
												<label>
												{if $plugin.required}
													{biticon iname=dialog-ok iexplain="Required"}&nbsp;
												{/if}
												<strong>{$plugin.name|default:$plugin_guid|capitalize}</strong></label>
												<div class="formhelp" >
												<strong>Current Version</strong>: {$upgradable.$plugin_guid.info.version}<br />
												<strong>Upgrade Version</strong>: {$upgradable.$plugin_guid.info.upgrade}<br />
											</li>
										{/if}
									{/foreach}
									</ul>
								{/forminput}
							</div>
							{/if}
						{/if}
					{/foreach}
				{/legend}
				{/if}
			{/jstab}
		{/if}

		{jstab title="Installed"}
			{legend legend="Packages installed on your system"}
				<p>{tr}You can control the display order of plugin template includes by sorting them. <a href="{$smarty.const.KERNEL_PKG_URL}admin/index.php?page=sort_packageplugins">{tr}Sort package plugins{/tr}</a>{/tr}</p>
				<p>
					{tr}Packages with checkmarks are currently enabled, packages without are disabled.  To enable or disable a package, check or uncheck it, and click the 'Modify Activation' button.{/tr} <a href='{$smarty.const.INSTALL_PKG_URL}install.php?step=3'>{tr}To uninstall or reinstall a package, visit the installer.{/tr}</a>
				</p>

				{$install_unavailable}

				{foreach from=$gBitSystem->mPackagesSchemas key=package item=item}
					{if !empty($item.plugins) && $gBitSystem->isPackageInstalled($package)}
						{assign var=hasPluginInstalled value=0}
						{if $item.plugins}
							{foreach from=$item.plugins key=plugin item=plugin}
								{if $gBitSystem->isPluginInstalled($plugin)}
									{assign var=hasPluginInstalled value=1}
								{/if}
							{/foreach}
						{/if}
						{if $hasPluginInstalled}
						<div class="row">
							<div class="formlabel">
								<label for="{$package}">
									{biticon ipackage=$package iname="pkg_$package" iexplain=`$package`}
								</label>
							</div>
							{forminput}
								<div><strong>{$item.name} Package Plugins</strong></div>
								<ul>
								{foreach from=$item.plugins key=plugin_guid item=plugin}
									{if $gBitSystem->isPluginInstalled($plugin_guid)}
										<li>
											<label>
											{if $plugin.required}
												{biticon iname=dialog-ok iexplain="Required"}
												<input type="hidden" value="y" name="package_plugins[{$plugin_guid}]" id="plugin_{$plugin_guid}" />
											{else}
												<input type="checkbox" value="y" name="package_plugins[{$plugin_guid}]" id="plugin_{$plugin_guid}" {if $gBitSystem->isPackagePluginActive($plugin_guid)}checked="checked"{/if} />
											{/if}
											&nbsp;
											<strong>{$plugin.name|default:$plugin_guid|capitalize}</strong></label>
											{formhelp note=$plugin.description}
										</li>
									{/if}
								{/foreach}
								</ul>
							{/forminput}
						</div>
						{/if}
					{/if}
				{/foreach}
			{/legend}

			<div class="buttonHolder row submit">
				<input class="button" type="submit" name="update_plugins" value="{tr}Modify Activation{/tr}"/>
			</div>
		{/jstab}


		{jstab title="Not Installed"}
			{legend legend="bitweaver plugins available for installation"}

				<div class="row">
					<div class="formlabel">
						{biticon ipackage=install iname="pkg_install" iexplain="install" iforce=icon}
					</div>
					{forminput}
						<p><strong><a class="warning" href='{$smarty.const.INSTALL_PKG_URL}install.php?step=3'>{tr}Click here to install more Plugins{/tr}&nbsp;&hellip;</a></strong></p>

						{$install_unavailable}
					{/forminput}
				</div>

				<hr style="clear:both" />

				{foreach from=$gBitSystem->mPackagesSchemas key=package item=item}
					{if !empty($item.plugins)}
						{assign var=hasPluginNotInstalled value=0}
						{if $item.plugins}
							{foreach from=$item.plugins key=plugin item=plugin}
								{if !$gBitSystem->isPluginInstalled($plugin)}
									{assign var=hasPluginNotInstalled value=1}
								{/if}
							{/foreach}
						{/if}
						{if $hasPluginNotInstalled}
						<div class="row">
							<div class="formlabel">
								<label for="{$package}">
									{biticon ipackage=$package iname="pkg_$package" iexplain=`$package`}
								</label>
							</div>
							{forminput}
								<div><strong>{$item.name} Package Plugins</strong></div>
								<ul>
								{foreach from=$item.plugins key=plugin_guid item=plugin}
									{if !$gBitSystem->isPluginInstalled($plugin_guid)}
										<li>
											<label>
											{if $plugin.required}
												{biticon iname=dialog-ok iexplain="Required"}&nbsp;
											{/if}
											<strong>{$plugin.name|default:$plugin_guid|capitalize}</strong></label>
											{formhelp note=$plugin.description}
										</li>
									{/if}
								{/foreach}
								</ul>
							{/forminput}
						</div>
						{/if}
					{/if}
				{/foreach}
			{/legend}
		{/jstab}
	{/jstabs}
{/form}

{/strip}

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
		{if $upgradable || $newrequired}
			{jstab title="Updates"}
				{if $newrequired}
				{legend legend="Required packages"}
					<p class="warning">
						{biticon iname="large/dialog-warning" iexplain="Warning"} {tr}You seem to have at least one required package that is not installed.{/tr} <a href="{$smarty.const.INSTALL_PKG_URL}install.php?step=3">{tr}We recommend you visit the installer now{/tr}</a>.
					</p>
					{foreach key=guid item=package from=$newrequired}
						<div class="row">
							<div class="formlabel">
								{biticon ipackage=$guid iname="pkg_`$guid`" iexplain=$package.name iforce=icon}
							</div>
							{forminput}
								<strong>{$package.name|capitalize}</strong>
								{include file="bitpackage:kernel/package_help_inc.tpl" package=$gBitSystem->mPackagesSchemas.$guid}
							{/forminput}
						</div>
					{/foreach}
				{/legend}
				{/if}
				{if $upgradable}
				{legend legend="Upgradable packages"}
					<p class="warning">
						{biticon iname="large/dialog-warning" iexplain="Warning"} {tr}You seem to have at least one package that can be upgraded.{/tr} <a href="{$smarty.const.INSTALL_PKG_URL}install.php?step=4">{tr}We recommend you visit the installer now{/tr}</a>.
					</p>

					{foreach from=$upgradable item=package key=guid}
						<div class="row">
							<div class="formlabel">
								<label for="package_{$guid}">{biticon ipackage=$guid iname="pkg_`$guid`" iexplain="$package.name" iforce=icon}</label>
							</div>
							{forminput}
								<label>
									<strong>{$package.name}</strong>
								</label>
								{include file="bitpackage:kernel/package_help_inc.tpl" package=$gBitSystem->mPackagesSchemas.$guid}
							{/forminput}
						</div>
					{/foreach}
				{/legend}
				{/if}
			{/jstab}
		{/if}

		{jstab title="Installed"}
			{legend legend="Packages installed on your system"}
				<p>
					{tr}Packages with checkmarks are currently enabled, packages without are disabled.  To enable or disable a package, check or uncheck it, and click the 'Modify Activation' button.{/tr} <a href='{$smarty.const.INSTALL_PKG_URL}install.php?step=3'>{tr}To uninstall or reinstall a package, visit the installer.{/tr}</a>
				</p>

				{$install_unavailable}

				{foreach key=guid item=package from=$installed}
					{if !$gBitSystem->mPackagesSchemas.$guid.required}
						<div class="row">
							<div class="formlabel">
								<label for="package_{$guid}">{biticon ipackage=$guid iname="pkg_`$guid`" iexplain="$package.name" iforce=icon}</label>
							</div>
							{forminput}
								<label>
									{assign var=is_requirement value=''}
									{foreach from=$gBitSystem->mPackagesSchemas key=pkgguid2 item=package2}
										{if $gBitSystem->isPackageActive($pkgguid2) && $package2.requirements.$guid}
											{assign var=is_requirement value='true'}
										{/if}
									{/foreach}
									{if $is_requirement}
										{biticon iname=dialog-ok iexplain="Required"}
										<input type="hidden" value="y" name="fPackage[{$guid}]" id="package_{$guid}" />
									{else}
										<input type="checkbox" value="y" name="fPackage[{$guid}]" id="package_{$guid}" {if $package.active eq 'y' }checked="checked"{/if} />
									{/if}
									&nbsp; <strong>{$package.name|capitalize}</strong>
									{assign var=first_loop value=1}
									{foreach from=$gBitSystem->mRequirements key=required_by item=reqs}
										{if $reqs.$guid}
											{if $first_loop}<br />{biticon iname=dialog-warning iexplain="Requirement"} Required by {else}, {/if}{$required_by}
											{assign var=first_loop value=0}
										{/if}
									{/foreach}
								</label>
								{include file="bitpackage:kernel/package_help_inc.tpl" package=$gBitSystem->mPackagesSchemas.$guid}
							{/forminput}
						</div>
					{/if}
				{/foreach}
			{/legend}

			<div class="buttonHolder row submit">
				<input class="button" type="submit" name="features" value="{tr}Modify Activation{/tr}"/>
			</div>
		{/jstab}


		{jstab title="Required"}
			{legend legend="Required packages installed on your system"}
				{foreach key=guid item=package from=$gBitSystem->mPackagesSchemas}
					{if $package.required && $gBitSystem->isPackageInstalled($guid)}
						<div class="row">
							<div class="formlabel">
								{biticon ipackage=$guid iname="pkg_`$guid`" iexplain=$package.name iforce=icon}
							</div>
							{forminput}
								<strong>{$package.name|capitalize}</strong>
								{include file="bitpackage:kernel/package_help_inc.tpl" package=$gBitSystem->mPackagesSchemas.$guid}
							{/forminput}
						</div>
					{/if}
				{/foreach}
			{/legend}
			{legend legend="Required services installed on your system"}
				{foreach key=name item=package from=$gBitSystem->mPackagesConfig}
					{if $package.installed && $package.service && $package.required}
						<div class="row">
							<div class="formlabel">
								{biticon ipackage=$name iname="pkg_`$name`" iexplain="$name" iforce=icon}
							</div>
							{forminput}
								<label>
									<strong>{$name|capitalize}</strong>
								</label>
								{include file="bitpackage:kernel/package_help_inc.tpl" package=$gBitSystem->mPackagesSchemas.$name}
							{/forminput}
						</div>
					{/if}
				{/foreach}
			{/legend}
		{/jstab}


		{if $requirementsMap || $requirements}
			{jstab title="Dependencies"}
				{legend legend="Requirements"}
					{if $requirementsMap}
						<p class="help">{tr}Below you will find an illustration of how packages depend on each other.{/tr}</p>
						<div style="text-align:center; overflow:auto;">
							<img alt="A graphical representation of package requirements" title="Requirements graph" src="{$smarty.const.KERNEL_PKG_URL}requirements_graph.php?install_version=1&amp;format={$smarty.request.format}&amp;command={$smarty.request.command}" usemap="#Requirements" />
							{$requirementsMap}
						</div>
					{/if}

					{if $requirements}
						<p class="help">{tr}Below you will find a detailed table with package requirements. If not all package requirements are met, consider trying to meet all package requirements. If you don't meet them, you may continue at your own peril.{/tr}</p>
						<table id="requirements">
							<caption>Package Requirements</caption>
							<tr>
								<th style="width:16%;">Requirement</th>
								<th style="width:16%;">Min Version</th>
								<th style="width:16%;">Max Version</th>
								<th style="width:16%;">Available</th>
								<th style="width:36%;">Result</th>
							</tr>
							{foreach from=$requirements item=dep}
								{if $pkg != $dep.package}
									<tr><th colspan="5">{$dep.package|ucfirst} requirements</th></tr>
									{assign var=pkg value=$dep.package}
								{/if}

								{if $dep.result == 'ok'}
									{assign var=class value=success}
								{elseif $dep.result == 'missing'}
									{assign var=class value=error}
								{elseif $dep.result == 'min_dep'}
									{assign var=class value=error}
								{elseif $dep.result == 'max_dep'}
									{assign var=class value=warning}
								{/if}

								<tr class="{$class}">
									<td>{$dep.requires|ucfirst}</td>
									<td>{$dep.required_version.min}</td>
									<td>{$dep.required_version.max}</td>
									<td>{$dep.required_version.available}</td>
									<td>
										{if $dep.result == 'ok'}
											OK
										{elseif $dep.result == 'missing'}
											Package not installed or not activated
											{assign var=missing value=true}
										{elseif $dep.result == 'min_dep'}
											Minimum version not met
											{assign var=min_dep value=true}
										{elseif $dep.result == 'max_dep'}
											Maximum version exceeded
											{assign var=max_dep value=true}
										{/if}
									</td>
								</tr>
							{/foreach}
						</table>

						{if $missing}
							{formfeedback warning="At least one required package is missing. Please activate or install the missing package." link="install/install.php?step=3/Install Package"}
						{/if}

						{if $min_dep}
							{formfeedback warning="At least one package did not meet the minimum version requirement. If possible, please upgrade to a newer version."}
						{/if}

						{if $max_dep}
							{formfeedback warning="At least one package recommend a version lower to the one you have installed. This might cause problems."}
						{/if}

						{if !$min_dep && !$max_dep && !$missing}
							{formfeedback success="All package requirements have been met."}
						{/if}
					{/if}

					<ul>
						<li>{smartlink ititle="Install Packages" ipackage=install ifile=install.php step=3}</li>
						<li>{smartlink ititle="Upgrade Packages" ipackage=install ifile=install.php step=4}</li>
					</ul>
				{/legend}
			{/jstab}
		{/if}


		{jstab title="Not Installed"}
			{legend legend="bitweaver packages available for installation"}

				<div class="row">
					<div class="formlabel">
						{biticon ipackage=install iname="pkg_install" iexplain="install" iforce=icon}
					</div>
					{forminput}
						<p><strong><a class="warning" href='{$smarty.const.INSTALL_PKG_URL}install.php?step=3'>{tr}Click here to install more Packages{/tr}&nbsp;&hellip;</a></strong></p>

						{$install_unavailable}
					{/forminput}
				</div>

				<hr style="clear:both" />

				{foreach key=guid item=package from=$gBitSystem->mPackagesSchemas}
					{if !$package.required && !$gBitSystem->mPackagesConfig.$guid }
						<div class="row">
							<div class="formlabel">
								{biticon ipackage=$guid iname="pkg_`$guid`" iexplain=$package.name iforce=icon}
							</div>
							{forminput}
								<strong>{$package.name|capitalize}</strong>
								{include file="bitpackage:kernel/package_help_inc.tpl" package=$gBitSystem->mPackagesSchemas.$guid}
							{/forminput}
						</div>
					{/if}
				{/foreach}
			{/legend}
		{/jstab}
	{/jstabs}
{/form}

{/strip}

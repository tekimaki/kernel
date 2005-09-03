{* $Header: /cvsroot/bitweaver/_bit_kernel/templates/admin_packages.tpl,v 1.4 2005/09/03 10:19:29 squareing Exp $ *}

{strip}

{form}
	{jstabs}
		{jstab title="Activate Packages"}
			{legend legend="bitweaver Packages that are ready for activation"}
				<input type="hidden" name="page" value="{$page}" />
				{foreach key=name item=package from=$gBitSystem->mPackages}
					{if $package.installed and !$package.required and $package.activatable}
						<div class="row">
							<div class="formlabel">
								<label for="package_{$name}">{biticon ipackage=$name iname="pkg_`$name`" iexplain="$name" iforce=icon}</label>
							</div>
							{forminput}
								<label>
									<input type="checkbox" value="y" name="fPackage[{$name}]" id="package_{$name}" {if $package.active_switch eq 'y' }checked="checked"{/if}/>
									&nbsp;{$name|capitalize}
								</label>
								{formhelp note=`$package.info` package=$name}
							{/forminput}
						</div>
					{elseif $package.tables && !$package.required && !$package.installed}
						{assign var=show_install_tab value=TRUE}
					{/if}
				{/foreach}

				<div class="row submit">
					<input type="submit" name="features" value="{tr}Activate bitweaver Packages{/tr}"/>
				</div>
			{/legend}
		{/jstab}

		{if $show_install_tab}
			{jstab title="Install Packages"}
				{legend legend="bitweaver Packages available for installation"}
					{foreach key=name item=package from=$gBitSystem->mPackages}
						{if $package.tables && !$package.required && !$package.installed}
							<div class="row">
								<div class="formlabel">
									{biticon ipackage=$name iname="pkg_`$name`" iexplain="$name" iforce=icon}
								</div>
								{forminput}
									{$name|capitalize}
									{formhelp note=`$package.info` package=$name}
								{/forminput}
							</div>
						{/if}
					{/foreach}
				{/legend}

				<br />

				{box title="How to install bitweaver Packages"}
					{tr}To install more packages, please run the <a href='{$smarty.const.INSTALL_PKG_URL}install.php?step=3'>installer</a> to choose your desired packages.{/tr}
					<br />
					<small><strong>{tr}Note{/tr}</strong> : {tr}you might have to rename your 'install/install.done' file back to 'install/install.php' to be able to install more packages{/tr}</small>
				{/box}
			{/jstab}
		{/if}

		{jstab title="Required Packages"}
			{legend legend="bitweaver Packages that are required on your system"}
				{foreach key=name item=package from=$gBitSystem->mPackages}
					{if ( $package.required and $package.installed ) or ( !$package.activatable and $package.info )}
						<div class="row">
							<div class="formlabel">
								{biticon ipackage=$name iname="pkg_`$name`" iexplain="$name" iforce=icon}
							</div>
							{forminput}
								<label>
									{*
									<input type="checkbox" value="y" name="fPackage[{$name}]" id="package_{$name}" {if $package.active_switch eq 'y' }checked="checked"{/if}/>
									*}
									{$name|capitalize}
								</label>
								{formhelp note=`$package.info` package=$name}
							{/forminput}
						</div>
					{/if}
				{/foreach}
			{/legend}
		{/jstab}
	{/jstabs}
{/form}

{/strip}

{strip}
{if $package}
<div class="formhelp" >
	{if $package.requirements}
		<strong>Requirements</strong>: &nbsp;
		{foreach from=$package.requirements item=req key=regname}
			{* @TODO add code urls to pkg schema *}
			<a class="external" href="http://www.bitweaver.org/wiki/{$regname|ucfirst}Package">{$regname|ucfirst}</a> ({$req.min}), &nbsp;
		{/foreach}
		<br />
	{/if}
	<strong>Description</strong>: {$package.description}<br /> {*description may contain html - dont escape *}
	{if $package.license}
	<strong>License</strong>: &nbsp;
	<a href="{$package.license.url}">{$package.license.name}</a><br />
	{/if}
	<strong>Version</strong>: {$package.version|default:'0.0.0'}<br />
	{* add maintenance urls to pkg schema
	<strong>Online help</strong>: &nbsp;
	<a class='external' href='http://doc.bitweaver.org/wiki/index.php?page=AccountsPackage'>AccountsPackage</a><br />
	*}
</div>
{/if}
{/strip}

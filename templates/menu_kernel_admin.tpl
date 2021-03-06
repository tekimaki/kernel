{strip}
<ul>
	<li><a class="item" href="{$smarty.const.KERNEL_PKG_URL}admin/index.php?page=features">{tr}Kernel Settings{/tr}</a></li>
	<li><a class="item" href="{$smarty.const.KERNEL_PKG_URL}admin/index.php?page=packages">{tr}Packages{/tr}</a></li>
	<li><a class="item" href="{$smarty.const.KERNEL_PKG_URL}admin/index.php?page=packageplugins">{tr}Package Plugins{/tr}</a></li>
	<li><a class="item" href="{$smarty.const.KERNEL_PKG_URL}admin/index.php?page=installer">{tr}Installer{/tr}</a></li>
	<li><a class="item" href="{$smarty.const.KERNEL_PKG_URL}admin/index.php?page=server">{tr}Server Settings{/tr}</a></li>
	<li><a class="item" href="{$smarty.const.KERNEL_PKG_URL}admin/admin_system.php">{tr}System Cache{/tr}</a></li>
	<li><a class="item" href="{$smarty.const.KERNEL_PKG_URL}admin/admin_notifications.php">{tr}Notification{/tr}</a></li>
	{if $smarty.const.DB_PERFORMANCE_STATS eq 'TRUE'}
		<li><a class="item" href="{$smarty.const.KERNEL_PKG_URL}admin/db_performance.php">{tr}Database Performance{/tr}</a></li>
	{/if}
	<li><a class="item" href="{$smarty.const.KERNEL_PKG_URL}admin/phpinfo.php">{tr}PHPinfo{/tr}</a></li>
	<li><a class="item" href="{$smarty.const.KERNEL_PKG_URL}admin/index.php?version_check=1">{tr}Check Version{/tr}</a></li>
</ul>
{/strip}

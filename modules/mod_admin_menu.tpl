{strip}
{bitmodule title="$moduleTitle" name="admin_menu"}
	{foreach key=key item=menu from=$adminMenu}
		<div class="admenu {$key}menu">
			{if $gBitSystem->isFeatureActive( 'feature_menusfolderstyle' )}
				<a class="menuhead" href="javascript:flipIcon('{$key}admenu');">{biticon ipackage=liberty iname="collapsed" id="`$key`menuimg" iexplain="folder"}
			{else}
				<a class="menuhead" href="javascript:toggle('{$key}admenu');">
			{/if}
			{tr}{$key|capitalize}{/tr}</a>
			{if $gBitSystem->isFeatureActive( 'feature_menusfolderstyle' )}
				<script type="text/javascript">
					flipIcon('{$key}admenu');
				</script>
			{/if}
			<div id="{$key}admenu">
				{include file=`$menu.tpl`}
			</div>
			<script type="text/javascript">
				$({$key}admenu).style.display = '{$menu.display}';
			</script>
		</div>
	{/foreach}
{/bitmodule}
{/strip}

{strip}
{if $cant_pages gt 1}
	<div class="pagination">
		{if $prev_offset >= 0}
			<a href="{$pgnUrl}?find={$find|default:$smarty.request.find}&amp;sort_mode={$sort_mode}&amp;offset={$prev_offset}{$pgnVars}">&laquo;</a>
		{/if}

		&nbsp;{tr}Page {$actual_page} of {$cant_pages}{/tr}&nbsp;

		{if $next_offset >= 0}
			<a href="{$pgnUrl}?find={$find|default:$smarty.request.find}&amp;sort_mode={$sort_mode}&amp;offset={$next_offset}{$pgnVars}">&raquo;</a>
		{/if}

		<br />

		{if $gBitSystem->isFeatureActive( 'site_direct_pagination' )}
			{section loop=$cant_pages name=foo}
				{assign var=selector_offset value=$smarty.section.foo.index|times:"$gBitSystem->getConfig('max_records')"}
				<a href="{$pgnUrl}?find={$find|default:$smarty.request.find}&amp;sort_mode={$sort_mode}&amp;offset={$selector_offset}">{$smarty.section.foo.index_next}</a>
			{/section}
		{else}
			{form action="$pgnUrl" id="fPageSelect"}
				<input type="hidden" name="find" value="{$find|default:$smarty.request.find}" />
				<input type="hidden" name="sort_mode" value="{$sort_mode}" />
				{foreach from=$pgnHidden key=name item=value}
					<input type="hidden" name="{$name}" value="{$value}" />
				{/foreach}
				{tr}Go to page{/tr} <input class="gotopage" type="text" size="3" maxlength="4" name="page" />
			{/form}
		{/if}
	</div> <!-- end .pagination -->

{elseif $listInfo && $listInfo.total_pages > 1}

	{* Build up URL variable string *}
	{capture name=string}
		{foreach from=$listInfo.parameters key=param item=value}
			{if $value|is_array}
				{foreach from=$value item=v}{if $value ne ''}&amp;{$param}[]={$v}{/if}{/foreach}
			{else}
				{if $value ne ''}&amp;{$param}={$value}{/if}
			{/if}
		{/foreach}
		{foreach from=$listInfo.ihash key=param item=value}
			{if $value|is_array}
				{foreach from=$value item=v}{if $value ne ''}&amp;{$param}[]={$v}{/if}{/foreach}
			{else}
				{if $value ne ''}&amp;{$param}={$value}{/if}
			{/if}
		{/foreach}
		{foreach from=$pgnHidden key=param item=value}
			{if $value|is_array}
				{foreach from=$value item=v}{if $value ne ''}&amp;{$param}[]={$v}{/if}{/foreach}
			{else}
				{if $value ne ''}&amp;{$param}={$value}{/if}
			{/if}
		{/foreach}
		{if $listInfo.sort_mode}
			{if is_array($listInfo.sort_mode)}
				{foreach from=$listInfo.sort_mode item=sort}
					&amp;sort_mode[]={$sort}
				{/foreach}
			{else}
				&amp;sort_mode={$listInfo.sort_mode}
			{/if}
		{/if}
		{if isset($listInfo.find) && $listInfo.find ne ''}
			&amp;find={$listInfo.find}
		{/if}
	{/capture}

	<div class="pagination">
		{assign var=pageUrlVar value=$smarty.capture.string|regex_replace:"/^\&amp;/":""}
		{assign var=pageUrl value="`$pgnUrl`?`$pageUrlVar`"}
		{math equation="offset + 1 * max" offset=$listInfo.offset|default:0 max=$listInfo.max_records|default:$gBitSystem->getConfig('max_records',20) assign=to}
{*
		<br />
		{tr}Items <strong>{$listInfo.offset+1}</strong> to <strong>{if $to > $listInfo.total_records}{$listInfo.total_records}{else}{$to}{/if}</strong> (of <strong>{$listInfo.total_records}</strong>){/tr}
*}
		{if !$forceAjaxPagination && $gBitSystem->isFeatureActive( 'site_direct_pagination' )}
			<div class="pager">
				<span class="left" style="float:left; width:48%; text-align:right;">
					{foreach from=$listInfo.block.prev key=list_page item=prev}
						&nbsp;<a href="{$pageUrl}&amp;list_page={$list_page}">{$prev}</a>&nbsp;
					{foreachelse}
						&nbsp;
					{/foreach}

					{if $listInfo.current_page > 1}
						&nbsp;<a href="{$pageUrl}&amp;list_page={$listInfo.current_page-1}">&laquo;</a>&nbsp;
					{/if}
					{tr}Page <strong>{$listInfo.current_page}</strong> of <strong>{$listInfo.total_pages}</strong>{/tr}
				</span>

				<span class="right" style="float:right; width:48%; text-align:left;">
					{if $listInfo.current_page < $listInfo.total_pages}
						&nbsp;<a href="{$pageUrl}&amp;list_page={$listInfo.current_page+1}">&raquo;</a>&nbsp;
					{/if}

					{foreach from=$listInfo.block.next key=list_page item=next}
						&nbsp;<a href="{$pageUrl}&amp;list_page={$list_page}">{$next}</a>&nbsp;
					{foreachelse}
						&nbsp;
					{/foreach}
				</span>
			</div>
		{else}
			{if $listInfo.current_page > 1}
				{if $forceAjaxPagination || $gBitThemes->isAjaxRequest()}
					&nbsp;<a href="javascript:void(0);" onclick="{$ajaxHandler}( {if $ajaxParams}{$ajaxParams},{/if} '{$pageUrlVar}&amp;list_page={$listInfo.current_page-1}' );">&laquo;</a>
				{else}
					&nbsp;<a href="{$pageUrl}&amp;list_page={$listInfo.current_page-1}">&laquo;</a>&nbsp;
				{/if}
			{/if}
			{tr}Page <strong>{$listInfo.current_page}</strong> of <strong>{$listInfo.total_pages}</strong>{/tr}
			{if $listInfo.current_page < $listInfo.total_pages}
				{if $forceAjaxPagination || $gBitThemes->isAjaxRequest()}
					&nbsp;<a href="javascript:void(0);" onclick="{$ajaxHandler}( {if $ajaxParams}{$ajaxParams},{/if} '{$pageUrlVar}&amp;list_page={$listInfo.current_page+1}' );">&raquo;</a>
				{else}
					&nbsp;<a href="{$pageUrl}&amp;list_page={$listInfo.current_page+1}">&raquo;</a>&nbsp;
				{/if}
			{/if}
			{* disabled - right now this form is getting nested in another form in map
			   overlay editing forms - this is a problem.
			   also the go to page part is not handle by ajax situaations - so exclude it
			   for now until we want to deal with really solving this, if we want to 
			   bother supporting it at all -wjames5
			{form action="$pageUrl"}
				<input type="hidden" name="find" value="{$find|default:$smarty.request.find}" />
				<input type="hidden" name="sort_mode" value="{$sort_mode}" />
				{foreach from=$pgnHidden key=name item=value}
					<input type="hidden" name="{$name}" value="{$value}" />
				{/foreach}
				{tr}Go to page{/tr} <input class="gotopage" type="text" size="3" maxlength="4" name="list_page" /> {tr}of{/tr} <strong>{$listInfo.total_pages}</strong>
			{/form}
			*}
		{/if}
	</div> <!-- end .pagination -->
{/if}
{/strip}

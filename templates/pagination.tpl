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
		{if isset($listInfo.max_records) && $listInfo.max_records ne ''}
			&amp;max_records={$listInfo.max_records}
		{/if}
	{/capture}

	<div class="pagination">
		{assign var=pageUrlVar value=$smarty.capture.string|regex_replace:"/^\&amp;/":""|regex_replace:'/"/':'%22':''}
		{assign var=pageUrl value="`$pgnUrl`?`$pageUrlVar`"}
		{math equation="offset + 1 * max" offset=$listInfo.offset|default:0 max=$listInfo.max_records|default:$gBitSystem->getConfig('max_records',20) assign=to}

		{* legacy small format pagination with input box and ajax support *}
		{if $pformat eq 'small' || $ajaxId} {* if ajax use legacy pagination style *}
			{if $gBitSystem->isFeatureActive( 'site_direct_pagination' )}
				<div class="pager">
					<span class="left floatleft width48p alignright">
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

					<span class="right floatright width48p alignleft">
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
					{if $ajaxId}
						&nbsp;<a href="javascript:void(0);" onclick="BitAjax.ajaxUpdater( '{$ajaxId}', '{$smarty.const.LIBERTY_PKG_URL}ajax_attachment_browser.php', 'list_page={$listInfo.current_page-1}' );">&raquo;</a>
					{else}
						&nbsp;<a href="{$pageUrl}&amp;list_page={$listInfo.current_page-1}">&laquo;</a>&nbsp;
					{/if}
				{/if}
				{tr}Page <strong>{$listInfo.current_page}</strong> of <strong>{$listInfo.total_pages}</strong>{/tr}
				{if $listInfo.current_page < $listInfo.total_pages}
					{if $ajaxId}
						&nbsp;<a href="javascript:void(0);" onclick="BitAjax.ajaxUpdater( '{$ajaxId}', '{$smarty.const.LIBERTY_PKG_URL}ajax_attachment_browser.php', 'list_page={$listInfo.current_page+1}' );">&raquo;</a>
					{else}
						&nbsp;<a href="{$pageUrl}&amp;list_page={$listInfo.current_page+1}">&raquo;</a>&nbsp;
					{/if}
				{/if}
				{form action="$pageUrl"}
					<input type="hidden" name="find" value="{$find|default:$smarty.request.find}" />
					<input type="hidden" name="sort_mode" value="{$sort_mode}" />
					{foreach from=$pgnHidden key=name item=value}
						<input type="hidden" name="{$name}" value="{$value}" />
					{/foreach}
					{tr}Go to page{/tr} <input class="gotopage" type="text" size="3" maxlength="6" name="list_page" /> {tr}of{/tr} <strong>{$listInfo.total_pages}</strong>
				{/form}
			{/if}
		{* large format pagination default *} 
		{else}
			<div class="numbered_pagination">
				{assign var="adjacents" value='3'}
				{*Setup page vars for display.*}
				{assign var=currpage value=$listInfo.current_page}
				{assign var=prevpage value=$listInfo.current_page-1}	{*previous page is page - 1*}
				{assign var=nextpage value=$listInfo.current_page+1}	{*next page is page + 1*}
				{assign var=lastpage value=$listInfo.total_pages}	{*lastpage is = total pages*}
				{assign var=lpm1 value=$lastpage-1} {*last page minus 1*}
				{assign var=previous value="&laquo; Previous"}
				{assign var=next value="Next &raquo;"}	
				{* 
					Now we apply our rules and draw the pagination object. 
					We're actually saving the code to a variable in case we want to draw it more than once.
				*}
				{if $lastpage > 0}	
					{*previous button*}
					{if $currpage > 1}
						<div class="page_num"><a href="{$pageUrl}&amp;list_page={$prevpage}">{$previous}</a></div>
					{else}
						<div class="disabled page">{$previous}</div>	
					{/if}
					{assign var="counts" value=0}
					{*pages*}
					
					{math equation="x + (y * z)" x=7 y=$adjacents z=2 assign="break_up"}
					{math equation="x + (y * z)" x=5 y=$adjacents z=2 assign="hide"}
					{if $lastpage < $break_up}	{*not enough pages to bother breaking it up*}
						{section name=counter start=1 loop=$lastpage+1 step=1}
							{assign var="counts" value=$smarty.section.counter.index}
							{if $counts == $currpage}
								<div class="page_num_current">{$counts}</div>
							{else}
								{*normal pagination*}
								<div class="page_num"><a href="{$pageUrl}&amp;list_page={$counts}">{$counts}</a></div>	
							{/if}
						{/section}
					{else if $lastpage > $hide}	{*enough pages to hide some*}
						{math equation="x + (y * z)" x=1 y=$adjacents z=2 assign="hide_back"}
						{math equation="x - (y * z)" x=$lastpage y=$adjacents z=2 assign="hide_front_back"}
						{math equation="x * y" x=$adjacents y=2 assign="shide_front_back"}
						{*close to beginning; only hide later pages*}
						{if $currpage < $hide_back }
							{math equation="x + (y * z)" x=4 y=$adjacents z=2 assign="loop_calc"}
							{section name=counter start=1 loop=$loop_calc step=1}
								{assign var="counts" value=$smarty.section.counter.index}
								{if $counts == $currpage}
									<div class="page_num_current">{$counts}</div>
								{else}
									{*normal pagination*}
									<div class="page_num"><a href="{$pageUrl}&amp;list_page={$counts}">{$counts}</a></div>	
								{/if}
							{/section}
							<div class="page_text">...</div>
							<div class="page_num"><a href="{$pageUrl}&amp;list_page={$lpm1}">{$lpm1}</a></div>
							<div class="page_num"><a href="{$pageUrl}&amp;list_page={$lastpage}">{$lastpage}</a></div>	
						
						{* in middle; hide some front and some back *}
						{elseif $hide_front_back > $currpage && $currpage > $shide_front_back }
							<div class="page_num"><a href="{$pageUrl}&amp;list_page=1">1</a></div>
							<div class="page_num"><a href="{$pageUrl}&amp;list_page=2">2</a></div>
							<div class="page_text">...</div>
							{math equation="x - y" x=$currpage y=$adjacents z=2 assign="start_calc"}
							{math equation="x + y + z" x=$currpage y=$adjacents z=1 assign="loop_calc"}
							{section name=counter start=$start_calc loop=$loop_calc step=1}
								{assign var="counts" value=$smarty.section.counter.index}
								{if $counts == $currpage}
									<div class="page_num_current">{$counts}</div>
								{else}
									{*normal pagination*}
									<div class="page_num"><a href="{$pageUrl}&amp;list_page={$counts}">{$counts}</a></div>	
								{/if}
							{/section}
							<div class="page_text">...</div>
							<div class="page_num"><a href="{$pageUrl}&amp;list_page={$lpm1}">{$lpm1}</a></div>
							<div class="page_num"><a href="{$pageUrl}&amp;list_page={$lastpage}">{$lastpage}</a></div>	
						{* close to end; only hide early pages *}
						{else}
							<div href="{$pageUrl}&amp;list_page=1" class="page_num" ><a href="{$pageUrl}&amp;list_page=1">1</a></div>
							<div href="{$pageUrl}&amp;list_page=2" class="page_num" ><a href="{$pageUrl}&amp;list_page=2">2</a></div>
							<div class="page_text">...</div>
							{math equation="x - ( y + ( z * a) )" x=$lastpage y=2 z=$adjacents a=2 assign="start_calc"}
							{math equation="x + y" x=1 y=$lastpage assign="loop_calc"}
							{section name=counter start=$start_calc loop=$loop_calc step=1}
								{assign var="counts" value=$smarty.section.counter.index}
								{if $counts == $currpage}
									<div class="page_num_current">{$counts}</div>
								{else}
									{*normal pagination*}
									<div class="page_num"><a href="{$pageUrl}&amp;list_page={$counts}">{$counts}</a></div>	
								{/if}
								{assign var="counts" value=$counter}
							{/section}
						{/if}
					{/if}
					{*next button*}
					{if $page < $lastpage} 
						<div class="page_num" ><a href="{$pageUrl}&amp;list_page={$nextpage}">{$next}</a></div>
					{else}
						<div class="disabled page">{$next}</div>	
					{/if}
				{/if}
			</div>
		{/if}
	</div> <!-- end .pagination -->
{/if}
{/strip}

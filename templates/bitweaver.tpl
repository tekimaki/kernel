{include file="bitpackage:kernel/header.tpl"}
{strip}
{if $print_page ne "y"}
	{if $gBitSystem->isFeatureActive( 'bidirectional_text' )}<div dir="rtl">{/if}

	<div id="outerwrapper" class="blocks{
		if $gBitSystem->isFeatureActive( 'site_left_column' ) && $l_modules && !$gHideModules and $gBitSystem->isFeatureActive( 'site_right_column' ) && $r_modules && !$gHideModules
			}3{
		elseif $gBitSystem->isFeatureActive( 'site_left_column' ) && $l_modules && !$gHideModules
			}2n{
		elseif $gBitSystem->isFeatureActive( 'site_right_column' ) && $r_modules && !$gHideModules
			}2e{
		else
			}1{
		/if
	}">

		<div id="header">
			{if $gBitSystem->isFeatureActive( 'site_top_column' ) && $t_modules && !$gHideModules}
				<a href="#content" class="skip" style="position:absolute;left:-999em;top:-999em;height:1px;width:1px">{tr}Skip to content{/tr}</a>
				{section name=homeix loop=$t_modules}
					<div class="{$t_modules[homeix].module_name}">
						<div class="container">
							{$t_modules[homeix].data}
						</div>
					</div>
				{/section}
			{/if}
		</div><!-- end #header -->{* needed by output filters. *}

		<div id="container">
    		<div id="wrapper">
    			<div id="content">
    				<div id="main">
    					{* TODO: This should probably be in a nav registration in libertystructure instead. *}
    					{if $gBitSystem->isPackageActive('libertystructure')}
    						{include file="bitpackage:libertystructure/display_structure.tpl"}
    					{/if}
    					{if $pageError}<div class="error">{$pageError}</div>{/if}
    					{include file=$mid}
    				</div><!-- end #main -->
    			</div><!-- end #content -->{* needed by output filters. *}
    		</div><!-- end #wrapper -->
    
    		{if $gBitSystem->isFeatureActive( 'site_left_column' ) && $l_modules && !$gHideModules}
    			<div id="navigation">
    				{include file="bitpackage:kernel/bit_left.tpl"}
    			</div><!-- end #navigation -->{* needed by output filters. *}
    		{/if}
    
    		{if $gBitSystem->isFeatureActive( 'site_right_column' ) && $r_modules && !$gHideModules}
    			<div id="extra">
    				{include file="bitpackage:kernel/bit_right.tpl"}
    			</div><!-- end #extra -->{* needed by output filters. *}
    		{/if}
        </div>
	</div><!-- end #container -->
	<div id="footer">
		{if $gBitSystem->isFeatureActive( 'site_bottom_column' ) && $b_modules && !$gHideModules}
			{section name=homeix loop=$b_modules}
				<div class="{$b_modules[homeix].module_name}">
					<div class="container">
						{$b_modules[homeix].data}
					</div>
				</div>
			{/section}
		{/if}
	</div><!-- end #footer -->{* needed by output filters. *}

	{if $gBitSystem->isFeatureActive( 'bidirectional_text' )}</div>{/if}
{/if}
{/strip}
{*haha made you look. xoxo - spiderr. 01-APR-2009*}
{include file="bitpackage:kernel/footer.tpl"}

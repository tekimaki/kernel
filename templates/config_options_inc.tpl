{legend legend=$legend}
{foreach from=$options key=item item=output}
	<div class="row">
		{formlabel label=`$output.label` for=$item}
		{forminput}
			{if $output.type == 'numeric'}
				<input size="5" type='text' name="{$item}" id="{$item}" value="{$gBitSystem->getConfig($item,$output.default)}" />
			{elseif $output.type == 'input'}
				<input type='text' name="{$item}" id="{$item}" value="{$gBitSystem->getConfig($item,$output.default)}" />
			{elseif $output.type=="hexcolor"}
				<input size="6" type="text" name="{$item}" id="" class="color" value="{$gBitSystem->getConfig($item,$output.default)}" />
			{else}
				{html_checkboxes name="$item" values="y" checked=$gBitSystem->getConfig($item,$output.default) labels=false id=$item}
			{/if}
			{formhelp note=`$output.note` page=`$output.page`}
		{/forminput}
	</div>
{/foreach}
{/legend}

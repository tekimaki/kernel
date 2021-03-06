{strip}
<div class="display confirm">
	<div class="header">
		<h1>{tr}Confirmation{/tr}</h1>
	</div>

	<div class="body">
		{form}
			{box title=Confirm}
				{foreach from=$hiddenFields item=value key=name}
					<input type="hidden" name="{$name}" value="{$value}" />
				{/foreach}
				<div class="row">
					<h1>{$msgFields.label}</h1>
					<p class="highlight aligncenter">{$msgFields.confirm_item}</p>
					{if $inputFields}
						<ul>
							{section name=ix loop=$inputFields}
								<li class="note">{$inputFields[ix]}</li>
							{/section}
						</ul>
					{/if}
					{formfeedback warning=$msgFields.warning}
					{formfeedback success=$msgFields.success}
					{formfeedback error=$msgFields.error}
				</div>

				<div class="buttonHolder row submit">
					<input class="button" type="button" name="cancel" {$backJavascript} value="{tr}Cancel{/tr}" /> &nbsp;
					<input class="button" type="submit" name="confirm" value="{tr}{$msgFields.submit_label|default:"Yes"}{/tr}" />
				</div>
			{/box}
		{/form}
	</div><!-- end .body -->
</div><!-- end .confirm -->
{/strip}

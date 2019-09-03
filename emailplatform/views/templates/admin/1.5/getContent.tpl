<!--
 *
 * 2007-2019 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 *
-->
{if $errors.apiInfo}
<div class="bootstrap emailplatform">
	<div class="alert alert-danger">
		<h4>{l s="There"} {if $errors.apiInfo|sizeof > 1} {l s="are"} {else} {l s="is"} {/if} {$errors.apiInfo|sizeof} {l s="errors"}</h4>
		<ul class="list-unstyled">
			{foreach from=$errors.apiInfo item=error}
			<li>{$error}</li>
			{/foreach}
		</ul>
	</div>
</div>
{/if}

{if $errors.listOptions}
<div class="bootstrap emailplatform">
	<div class="alert alert-danger">
		<h4>{l s="There"} {if $errors.apiInfo|sizeof > 1} {l s="are"} {else} {l s="is"} {/if} {$errors.listOptions|sizeof} {l s="error"}</h4>
		<ul class="list-unstyled">
			{foreach from=$errors.listOptions item=error}
			<li>{$error}</li>
			{/foreach}
		</ul>
	</div>
</div>
{/if}

<form id="module_form" action="" method="post" class="emailplatformForm defaultForm form-horizontal">
	<div class="panel">

		<div class="panel-heading">{l s="eMailPlatform API credentials"}</div>

		<div class="form-wrapper">
			<div class="form-group">
				<label for="emp_api_username" class="control-label col-lg-3">{l s="eMailPlatform API username"}</label>
				<div class="col-lg-9">
					<input id="emp_api_username" type="text" value="{$api_username}" name="emp_api_username">
					<p class="help-block">{l s="Example:"} emailplatform_dk</p>
				</div>
			</div>

			<div class="form-group">
				<label for="emp_api_token" class="control-label col-lg-3">{l s="eMailPlatform API token"}</label>
				<div class="col-lg-9">
					<input id="emp_api_token" type="text" value="{$api_token}" name="emp_api_token">
					<p class="help-block">{l s="Example:"} Q2OiFItB0CYBEbYFKWSN</p>
				</div>
			</div>

		</div>

		<div class="panel-footer">
			<button id="module_form_submit_btn" class="btn btn-default pull-right" value="{l s='Save Settings'}" name="saveSettings">
				<i class="process-icon-save"></i> {l s='Save Settings'}
			</button>
		</div>

	</div>
</form>

<form id="module_form" action="" method="post" class="emailplatformForm defaultForm form-horizontal">
	<div class="panel">

		<div class="panel-heading">{l s="eMailPlatform List Options"}</div>

		<div class="form-wrapper">

			<div class="form-group">
				<label class="control-label col-lg-3" for="list">{l s="eMailPlatform List"}</label>
				<div class="col-lg-9">
					<select id="list" name="list" class="form-control fixed-width-xl">
						<option></option>
						{if $lists != ''}
							{foreach from=$lists item=listItem}
								{if $selected_list == $listItem->listid}
									{assign var='selected' value=' selected="selected"'}
								{else}
									{assign var='selected' value=''}
								{/if}
								<option{$selected} value="{$listItem->listid}">{$listItem->name}</option>
							{/foreach}
						{/if}
					</select>
					<p class="help-block">{l s="The destination list"}</p>
				</div>
			</div>


			<div class="form-group">
				<label class="control-label col-lg-3" for="list">{l s="custom fields"}</label>
				<div class="col-lg-9">
					<select name="custom_fields[]" size="5" multiple="multiple" class="form-control fixed-width-xl">
						{foreach from=$customfieldsDefault key=value item=field}
							{assign var='selected' value=''}
							{foreach from=$customfields item=objField}
								{if isset($objField->fieldname)}
									{if $value == $objField->fieldname}
										{assign var='selected' value=' selected="selected"'}
									{/if}
								{/if}
							{/foreach}
							<option{$selected} value="{$value}">{l s=$field.fieldname}</option>
						{/foreach}
					</select>
					<p class="help-block">{l s="Custom fields export (press ctrl/cmd to multiselect)"}</p>
				</div>
			</div>

		</div>

		<div class="panel-footer">
			<button id="module_form_submit_btn" class="btn btn-default pull-right" value="{l s='save settings'}" name="saveOptions">
				<i class="process-icon-save"></i> {l s='Save Options'}
			</button>
		</div>

	</div>
</form>

<form action="" method="post" class="defaultForm form-horizontal">
	<div class="panel">
		<div class="panel-heading">{l s="Manual Export"}</div>

		<div class="errors"></div>

		<div class="form-wrapper">
			<div class="form-group">
				<label class="control-label col-lg-4">{l s="Manually export to eMailPlatform"}</label>
				<div class="col-lg-6">
					<input class="btn btn-default" type="submit" value="{l s='Export Users'}" name="exportToEMP" />
				</div>
			</div>

		</div>
	</div>
</form><div class="clear"></div>

<form action="" method="post" class="defaultForm form-horizontal">
	<div class="panel">
		<div class="panel-heading">{l s="Synchronise Data"}</div>

		<div class="errors"></div>

		<div class="form-wrapper">
			<div class="form-group">
				<label class="control-label col-lg-4">
					{l s="Synchronise to Prestashop in case a customer unsubscribed via eMailPlatform"}
				</label>
				<div class="col-lg-6">
					<input class="btn btn-default" type="submit" value="{l s='Synchronise'}" name="syncData" />
					<div class="clear"></div>
				</div><div class="clear"></div><br />

				<p class="help-block">
					{l s="Setup the cronjob to unsubscribe customers in Prestashop, if they have unsubscribed from eMailPlatform (the job will unsubscribe todays unsubscribes from eMailPlatform)"}<br /><br />
					{$cronUrl}
				</p>

			</div>
		</div>
	</div>
</form>
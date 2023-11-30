{$coinsnap_confirmation}

<div class="coinsnap-header">

<h2 class="page-title"><a href="https://coinsnap.io"><img src="{$module_dir}logo.png" alt="{l s='Coinsnap' mod='coinsnap'}" class="coinsnap-logo" /></a></h2>
</div>

<form action="{$coinsnap_form|escape:'htmlall':'UTF-8'}" id="module_form" class="defaultForm form-horizontal" method="post">
<div class="panel" id="fieldset_0">    
<div class="panel-heading">
<i class="icon-cogs"></i>{l s='Settings' mod='coinsnap'}
</div>    

<div class="form-wrapper">

<div class="form-group">            
<label  class="control-label col-lg-3" for="coinsnap-store-id">{l s='Store ID:' mod='coinsnap'}</label>
<div class="col-lg-3">
<div class="input-group">
<span class="input-group-addon"><i class="icon icon-tag"></i></span>
<input type="text" class="text" name="coinsnap_store_id" id="coinsnap-store-id" value="{$coinsnap_store_id|escape:'htmlall':'UTF-8'}" />
</div>
</div>
</div>
	
<div class="form-group">          
<label class="control-label col-lg-3" for="coinsnap-api-key">{l s='API Key:' mod='coinsnap'}</label>
<div class="col-lg-3">
<div class="input-group">
<span class="input-group-addon"><i class="icon icon-tag"></i></span>
<input type="text" class="text" name="coinsnap_api_key" id="coinsnap-api-key" value="{$coinsnap_api_key|escape:'htmlall':'UTF-8'}" />
</div>
</div>
</div>

</div>
</div>

<div class="panel" id="fieldset_1">    
<div class="panel-heading">
<i class="icon-shopping-cart"></i>{l s='Order Status' mod='coinsnap'}
</div>    

<div class="form-wrapper">
<div class="form-group">                    
<label class="control-label col-lg-3" for="coinsnap_status_new">{l s='New:' mod='coinsnap'}</label>                                
<div class="col-lg-3">
    <select name="coinsnap_status_new" id="input-transaction-method" class="form-control">
        {foreach from=$orderstates key='ordid' item='ordname'}                  
            <option value="{$ordid}" {if $ordid == $coinsnap_status_new} selected="selected"{/if}>{$ordname}</option>
        {/foreach}
    </select>             
</div>
</div>

<div class="form-group">                    
<label class="control-label col-lg-3" for="coinsnap_status_expired">{l s='Expired:' mod='coinsnap'}</label>                                
<div class="col-lg-3">
    <select name="coinsnap_status_expired" id="input-transaction-method" class="form-control">
        {foreach from=$orderstates key='ordid' item='ordname'}                  
            <option value="{$ordid}" {if $ordid == $coinsnap_status_expired} selected="selected"{/if}>{$ordname}</option>
        {/foreach}
    </select>             
</div>
</div>

<div class="form-group">                    
<label class="control-label col-lg-3" for="coinsnap_status_settled">{l s='Settled:' mod='coinsnap'}</label>                                
<div class="col-lg-3">
    <select name="coinsnap_status_settled" id="input-transaction-method" class="form-control">
        {foreach from=$orderstates key='ordid' item='ordname'}                  
            <option value="{$ordid}" {if $ordid == $coinsnap_status_settled} selected="selected"{/if}>{$ordname}</option>
        {/foreach}
    </select>             
</div>
</div>

<div class="form-group">                    
<label class="control-label col-lg-3" for="coinsnap_status_processing">{l s='Processing:' mod='coinsnap'}</label>                                
<div class="col-lg-3">
    <select name="coinsnap_status_processing" id="input-transaction-method" class="form-control">
        {foreach from=$orderstates key='ordid' item='ordname'}                  
            <option value="{$ordid}" {if $ordid == $coinsnap_status_processing} selected="selected"{/if}>{$ordname}</option>
        {/foreach}
    </select>             
</div>
</div>


</div>
<div class="panel-footer">
<button type="submit" value="1" id="module_form_submit_btn" name="submitcoinsnap" class="btn btn-default pull-right">
<i class="process-icon-save"></i> Save
</button>
</div>        
</div>
</form>



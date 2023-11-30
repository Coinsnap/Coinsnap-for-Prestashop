<form id="stc-payment-form" action="{$action_url}" method="post">    
<section>    
    <p>
    <img src="{$module_dir}front-logo.png" alt="{l s='Bitcoin + Lightning' mod='coinsnap'}" class="coinsnap-logo" />
    {l s='Bitcoin + Lightning' mod='coinsnap'}</p>
</section>
{if isset($errmsg)}
<div class="alert alert-warning">{$errmsg|escape:'html':'UTF-8'}</div>

{/if}
</form>	
{**
 * Copyright since 2023 Coinsnap
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@coinsnap.io so we can send you a copy immediately.
 *
 * @author    Coinsnap <dev@coinsnap.io>
 * @copyright Since 2023 Coinsnap
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *}

<form id="stc-payment-form" action="{if isset($action_url)}{$action_url|escape:'html':'UTF-8'}{/if}" method="post">    

    <p>
    <img src="{$module_dir|escape:'html':'UTF-8'}/views/img/front-logo.png" alt="{l s='Bitcoin + Lightning' mod='coinsnap'}" class="coinsnap-logo" />
    {l s='Bitcoin + Lightning' mod='coinsnap'}
    </p>

{if isset($errmsg)}
<div class="alert alert-warning">{$errmsg|escape:'html':'UTF-8'}</div>
{/if}
</form>	
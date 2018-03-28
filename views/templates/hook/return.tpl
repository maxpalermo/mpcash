{*
* 2007-2017 PrestaShop
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
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2017 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}
{if (isset($status) == true) && ($status == 'ok')}
    <h3><strong style='color: #333; text-transform: uppercase; text-shadow: 2px 2px 2px #d0d0d0;'>{l s='Your order on %s is complete.' sprintf=$shop_name mod='mpCash'}</strong></h3>
    <div style='border-radius: 3px; border: 1px solid #d0d0d0; background-color: #f0f0ee; color: #333; padding: 10px; font-size: 1.3em;'>
        <p><i class='icon icon-angle-right'></i>  {l s='Amount' mod='mpCash'} : <span class="price" style='font-size: 1.4em;'><strong style='color: #333;'>{displayPrice price=$total_paid|escape:'htmlall':'UTF-8'}</strong></span></p>
        <p><i class='icon icon-angle-right'></i>  {l s='Reference' mod='mpCash'} : <span class="reference"><strong>{$reference|escape:'html':'UTF-8'}</strong></span></p>
        {if (!empty($transaction_id))}
        <p><i class='icon icon-angle-right'></i>  {l s='Transaction id' mod='mpCash'} : <span class="transaction_id"><strong>{$transaction_id|escape:'html':'UTF-8'}</strong></span></p>
        {/if}
        <br/>
        <p style='font-style: italic;'>{l s='An email has been sent with this information.' mod='mpCash'}</p>
        <p style='font-style: italic;'>{l s='If you have questions, comments or concerns, please contact our' mod='mpCash'} <a href="{$link->getPageLink('contact', true)|escape:'html':'UTF-8'}">{l s='expert customer support team.' mod='mpCash'}</a></p>
    </div>
{else}
<div class='alert'>
    <h3>{l s='Your order on %s has not been accepted.' sprintf=$shop_name mod='mpCash'}</h3>
    <div style='border: 1px solid grey; background-color: #faf55e; color: #333; padding: 10px;'>
        <p>
            <br />- {l s='Reference' mod='mpCash'} <span class="reference"> <strong>{$reference|escape:'html':'UTF-8'}</strong></span>
            <br /><br />{l s='Please, try to order again.' mod='mpCash'}
            <br /><br />{l s='If you have questions, comments or concerns, please contact our' mod='mpCash'} <a href="{$link->getPageLink('contact', true)|escape:'html':'UTF-8'}">{l s='expert customer support team.' mod='mpCash'}</a>
        </p>
    </div>
</div>
{/if}
<hr />
{if isset($google_script) && !empty($google_script)}
    {$google_script}
{/if}
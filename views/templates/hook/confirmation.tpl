{*
* 2017 mpSOFT
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
*  @author    mpSOFT <info@mpsoft.it>
*  @copyright 2017 mpSOFT Massimiliano Palermo
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of mpSOFT
*}

{capture name=path}
    {l s='Cash payment' mod='mpCash'}
{/capture}

<form class='defaultForm form-horizontal' action='{$validationLink|escape:'html'}' method='POST'>
    <div class="panel panel-default">
        <div class='panel-heading'>
            <i class="icon-dollar"></i>
            {l s='Payment method:' mod='mpCash'} {l s='cash' mod='mpCash'}
        </div>  
        
        <div class="panel-body panel-info">
            {l s='You have chosen to pay with' mod='mpCash'}
            &nbsp;
            <strong>{l s='CASH' mod='mpCash'}</strong>
        </div>
        
        <div class='panel'>
            <div class='panel-body panel-info'>
                {l s='TOTAL CART:' mod='mpCash'}
                &nbsp;
                <strong>{displayPrice price=$total_cart}</strong>
            </div>
            <div class='panel-body panel-info'>
                {if $fees<0}
                    {l s='TOTAL DISCOUNTS:' mod='mpCash'}
                {else}
                    {l s='TOTAL FEES:' mod='mpCash'}
                {/if}
                &nbsp;
                <strong>{displayPrice price=$fees}</strong>
            </div>
            <div class='panel-body panel-info'>
                {l s='TOTAL TO PAY:' mod='mpCash'}
                &nbsp;
                <strong>{displayPrice price=$total_to_pay}</strong>
            </div>
        </div>
    </div>
    <br>
    <p class="cart_navigation clearfix" id="cart_navigation">
        <a
            class="button-exclusive btn btn-default"
            href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html':'UTF-8'}">
            <i class="icon-chevron-left"></i>{l s='Other payment methods' mod='mpCash'}
        </a>
        <button
            class="button btn btn-default button-medium"
            type="submit">
            <span>{l s='I confirm my order' mod='mpCash'}<i class="icon-chevron-right right"></i></span>
        </button>
	</p>
</form>

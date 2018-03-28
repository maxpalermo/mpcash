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
 
<div class="row">
    <div class="col-xs-12">
        <div class="payment_block_module">
            <a href="{$confirmationLink|escape:'html'}" class="mpCash">
                <div class="panel-body">
                    <div style='position:absolute; top: 10px; left: 10px;'>
                        <img src="modules/mpCash/logo.png" width=64>
                        &nbsp;
                        <strong>{l s='CASH PAYMENT' mod='mpCash'}</strong>
                    </div>
                    <br>
                    <br>
                    <div>
                        <div style='display: inline-block; margin-right: 10px; padding-right: 10px; border-right: 1px solid #aaaaaa; font-weight: normal;'>
                            {l s='TOTAL CART' mod='mpCash'} : 
                            {displayPrice price=$total_cart}
                        </div>
                        <div style='display: inline-block; margin-right: 10px; padding-right: 10px; border-right: 1px solid #aaaaaa; font-weight: normal;'>
                            {if $fees<0}
                                <span style='text-align: left;'>{l s='DISCOUNTS' mod='mpCash'}</span>
                                : {displayPrice price=($fees*-1)}
                            {else}
                                <span style='text-align: left;'>{l s='FEES' mod='mpCash'}</span>
                                : {displayPrice price=$fees}
                            {/if}
                        </div>
                        <div style='display: inline-block; font-weight: normal;'>
                            {l s='TOTAL TO PAY' mod='mpCash'} : 
                            <strong>{displayPrice price=$total_to_pay}</strong>
                        </div>
                    </div>
                </div> 
            </a>
        </div>
    </div>
</div>
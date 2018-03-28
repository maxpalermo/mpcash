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
*  @author    Massimiliano Palermo mpSoft <maxx.palermo@gmail.com>
*  @copyright 2017 Massimiliano Palermo mpSoft
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of Massimiliano Palermo mpSoft
*}

{assign var=diff value=$total_document-$total_cart}

<style>
    #div-table
    {
        border-collapse: collapse;
        border: 1px solid #555;
    }
    #div-table thead tr th
    {
        background: #0088cc;
        color: #FFFFFF;
        text-align: center;
        text-transform: uppercase;
    }
    #div-table tbody tr td
    {
        background: #E1EEf4;
        color: #555555;
    }
    #div-table tbody tr:nth-child(odd) td
    {
        background: #dcdcdc;
        color: #555555;
    }
    #div-table tbody tr.total td
    {
        background: #55AAFF;
        color: #FFFFFF;
        text-align: right;
    }
    #div-table .right
    {
        text-align: right;
    }
</style>
<div>
    <h3>{l s='Cash email notification' mod='mpCash'}</h3>
    <div>
        <p>{l s='Customer' mod='mpCash'}: <b>{$firstname} {$lastname}</b></p>
        <p>{l s='Email' mod='mpCash'}: <a href="mailto:{$email}">{$email}</a></p>
        <p>{l s='Order reference' mod='mpCash'}: {$order_reference}</p>
        <p>{l s='Made on' mod='mpCash'}: {$order_date}</p>
        <p>{l s='Payment method' mod='mpCash'}: {l s='Cash' mod='mpCash'}</p>
        <p>{l s='Carrier' mod='mpCash'} : <b>{$carrier->name}</b></p>
        {if !empty($message)}
            <p>{l s='Message' mod='mpCash'} : <b>{$message}</b></p>
        {/if}
    </div>
</div>
<div>
    <h3>{l s='Order details' mod='mpCash'}</h3>
    <table style='border-collapse: collapse; border: 1px solid #a0a0a0;'>
        <thead>
            <tr style='line-height: 1.4em; border-bottom: 1px solid #a0a0a0;'>
                <th style='background: #909090; color: #fcfcfc; text-align: center;'>{l s='reference' mod='mpCash'}</th>
                <th style='background: #909090; color: #fcfcfc; text-align: center;'>{l s='name' mod='mpCash'}</th>
                <th style='background: #909090; color: #fcfcfc; text-align: center;'>{l s='qty' mod='mpCash'}</th>
                <th style='background: #909090; color: #fcfcfc; text-align: center;'>{l s='price' mod='mpCash'}</th>
                <th style='background: #909090; color: #fcfcfc; text-align: center;'>{l s='reduction' mod='mpCash'}</th>
                <th style='background: #909090; color: #fcfcfc; text-align: center;'>{l s='tax rate' mod='mpCash'}</th>
                <th style='background: #909090; color: #fcfcfc; text-align: center;'>{l s='total' mod='mpCash'}</th>
            </tr>
        </thead>
        <tbody>
        {foreach $order_details as $order_detail}
            <tr style='line-height: 1.4em; border-bottom: 1px solid #a0a0a0;'>
                <td style='background: #fcfcfc; color: #555555; text-align: left; padding-left: 5px;'>{$order_detail['reference']}</td>
                <td style='background: #fcfcfc; color: #555555; text-align: left; padding-left: 5px;'>{$order_detail['name']}</td>
                <td style='background: #fcfcfc; color: #555555; text-align: left; padding-left: 5px;'>{$order_detail['qty']}</td>
                <td style='background: #fcfcfc; color: #555555; text-align: right; padding-right: 5px;'>{displayPrice price=$order_detail['price']}</td>
                <td style='background: #fcfcfc; color: #555555; text-align: right; padding-right: 5px;'>{$order_detail['reduction_percent']}%</td>
                <td style='background: #fcfcfc; color: #555555; text-align: right; padding-right: 5px;'>{$order_detail['tax_rate']}%</td>
                <td style='background: #fcfcfc; color: #555555; text-align: right; padding-right: 5px;'><strong>{displayPrice price=$order_detail['total']}</strong></td>
            </tr>
        {/foreach}
            <tr style='line-height: 1.4em; border-bottom: 1px solid #a0a0a0;'>
                <td colspan="6" style='background: #909090; color: #fcfcfc; text-align: right; padding-right: 5px;'>{l s='TOTAL PRODUCTS' mod='mpCash'}</td>
                <td style='background: #909090; color: #fcfcfc; text-align: right; padding-right: 5px;'>{displayPrice price=$total_products}</td>
            </tr>
            {foreach $vouchers as $voucher}
                <tr style='line-height: 1.4em; border-bottom: 1px solid #a0a0a0;'>
                    <td colspan="5" style='background: #909090; color: #fcfcfc; text-align: right; padding-right: 5px;'>{l s='VOUCHER' mod='mpCash'}</td>
                    <td style='background: #909090; color: #fcfcfc; text-align: right; padding-right: 5px;'>{$voucher['name']}</td>
                    <td style='background: #909090; color: #fcfcfc; text-align: right; padding-right: 5px;'>{displayPrice price=-$voucher['value']}</td>
                </tr>
            {/foreach}
            <tr style='line-height: 1.4em; border-bottom: 1px solid #a0a0a0;'>
                <td colspan="6" style='background: #909090; color: #fcfcfc; text-align: right; padding-right: 5px;'>{l s='SHIPPING' mod='mpCash'}</td>
                <td style='background: #909090; color: #fcfcfc; text-align: right; padding-right: 5px;'>{displayPrice price=$total_shipping}</td>
            </tr>
            <tr style='line-height: 1.4em; border-bottom: 1px solid #a0a0a0;'>
                <td colspan="6" style='background: #909090; color: #fcfcfc; text-align: right; padding-right: 5px;'>{l s='TOTAL ORDER' mod='mpCash'}</td>
                <td style='background: #909090; color: #fcfcfc; text-align: right; padding-right: 5px;'>{displayPrice price=$total_cart}</td>
            </tr>
            <tr style='line-height: 1.4em; border-bottom: 1px solid #a0a0a0;'>
                <td colspan="6" style='background: #909090; color: #fcfcfc; text-align: right; padding-right: 5px;'>
                    {if $diff<0}
                        {l s='DISCOUNT' mod='mpCash'}
                    {else}
                        {l s='FEE' mod='mpCash'}
                    {/if}
                </td>
                <td style='background: #909090; color: #fcfcfc; text-align: right; padding-right: 5px;'>{displayPrice price=$diff}</td>
            </tr>
            <tr style='line-height: 1.4em; border-bottom: 1px solid #a0a0a0;'>
                <td colspan="6" style='background: #909090; color: #fcfcfc; text-align: right; padding-right: 5px;'>{l s='TOTAL PAID' mod='mpCash'}</td>
                <td style='background: #909090; color: #fcfcfc; text-align: right; padding-right: 5px;'><strong>{displayPrice price=$total_document}</strong></td>
            </tr>
        </tbody>
        <tfoot>

        </tfoot>
    </table>
</div>
<br>
<div style='overflow: hidden; margin-bottom: 20px;'>
    <div style='padding: 12px; border: 1px solid #a0a0a0;'>
        <h3>{l s='Delivery address' mod='mpCash'}</h3>
        <br>
        {if !empty($delivery_address->company)}
            <p><b>{$delivery_address->company}</b></p>
        {else}
            <p><b>{$delivery_address->firstname} {$delivery_address->lastname}</b></p>
        {/if}
        <p>{$delivery_address->address1}</p>
        <p>{$delivery_address->address2}</p>
        <p>{$delivery_address->postcode} {$delivery_address->city}</p>
        <p><b>{$delivery_address->country}</b></p>
        {if !empty($delivery_address->phone)}
            <p>{l s='Phone number' mod='mpCash'}: {$delivery_address->phone}</p>
        {/if}
        {if !empty($delivery_address->phone_mobile)}
            <p>{l s='Mobile number' mod='mpCash'}: {$delivery_address->phone_mobile}</p>
        {/if}
        {if !empty($delivery_address->dni)}
            <p>{l s='Dni' mod='mpCash'}: <b>{$delivery_address->dni}</b></p>
        {/if}
    </div>
    <br>
    <div style='padding: 12px; border: 1px solid #a0a0a0;'>
        <h3>{l s='Invoice address' mod='mpCash'}</h3>
        <br>
        {if !empty($invoice_address->company)}
            <p><b>{$invoice_address->company}</b></p>
        {else}
            <p><b>{$invoice_address->firstname} {$invoice_address->lastname}</b></p>
        {/if}
        <p>{$invoice_address->address1}</p>
        <p>{$invoice_address->address2}</p>
        <p>{$invoice_address->postcode} {$invoice_address->city}</p>
        <p><b>{$invoice_address->country}</b></p>
        {if !empty($invoice_address->phone)}
            <p>{l s='Phone number' mod='mpCash'}: {$invoice_address->phone}</p>
        {/if}
        {if !empty($invoice_address->phone_mobile)}
            <p>{l s='Mobile number' mod='mpCash'}: {$invoice_address->phone_mobile}</p>
        {/if}
        {if !empty($invoice_address->dni)}
            <p>{l s='Dni' mod='mpCash'}: <b>{$invoice_address->dni}</b></p>
        {/if}
        {if !empty($invoice_address->vat_number)}
            <p>{l s='Vat number' mod='mpCash'}: <b>{$invoice_address->vat_number}</b></p>
        {/if}
    </div>
</div>
<br style='clear: both;'>
<br>
<div style='height: 20px; background: #909090; color: #f0f0f0; width: 100%; text-align: center;'></div>
<br>
<br>

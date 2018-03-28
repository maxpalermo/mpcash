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
        border-color: grey;
        background-color: white;
    }
    #div-table thead tr th, #div-table tbody tr.total td
    {
        background: #e6e4e4;
        color: #333;
        text-align: right;
    }
    #div-table tbody tr td
    {
        background: #fdfdfd;
        color: #333;
    }
    #div-table tbody tr:nth-child(odd) td
    {
        background: #f0f0f0;
        color: #333;
    }
    #div-table .right
    {
        text-align: right;
    }
</style>
<div style='text-align: center;'>
    <img src="{$logo}" style='margin: 10px auto; box-shadow: 0 5px 25px 1px #bfafaf; margin-bottom: 20px;'>
    <h3 style='text-align: center;'>
        {l s='Hello' mod='mpCash'} {$firstname} {$lastname}
        <br>
        {l s='Thank you for purchasing on' mod='mpCash'} {$shop} 
    </h3>
</div>
<div style='background-color: #e6e4e4; border: 1px solid grey; padding: 5px; color: #333;'>
    <h3><strong>{l s='Order details' mod='mpCash'}</strong></h3>
    <div>
        <p>{l s='Order reference' mod='mpCash'}: {$order_reference}</p>
        <p>{l s='Made on' mod='mpCash'}: {$order_date}</p>
        <p>{l s='Payment method' mod='mpCash'}: {l s='Cash' mod='mpCash'}</p>
        <p>{l s='Carrier' mod='mpCash'} : <strong>{$carrier->name}</strong></p>
        {if !empty($message)}
            <p>{l s='Message' mod='mpCash'} : <strong>{$message}</strong></p>
        {/if}
        {if !empty($transaction_id)}
            <p>{l s='Transaction id' mod='mpCash'}: <strong>{$transaction_id}</strong></p>
        {/if}
    </div>
</div>
<div style='text-align: center;'>
    <h3>{l s='Order details' mod='mpCash'}</h3>
    <table style='width: 95%; border-collapse: collapse; border: 1px solid grey; margin: 5px auto;'>
        <thead>
            <tr style='line-height: 1.4em; border-bottom: 1px solid #a0a0a0;'>
                <th style='border: 1px solid grey; background: #e6e4e4; color: #333; text-align: center;'>{l s='reference' mod='mpCash'}</th>
                <th style='border: 1px solid grey; background: #e6e4e4; color: #333; text-align: center;'>{l s='name' mod='mpCash'}</th>
                <th style='border: 1px solid grey; background: #e6e4e4; color: #333; text-align: center;'>{l s='qty' mod='mpCash'}</th>
                <th style='border: 1px solid grey; background: #e6e4e4; color: #333; text-align: center;'>{l s='price' mod='mpCash'}</th>
                <th style='border: 1px solid grey; background: #e6e4e4; color: #333; text-align: center;'>{l s='reduction' mod='mpCash'}</th>
                <th style='border: 1px solid grey; background: #e6e4e4; color: #333; text-align: center;'>{l s='tax rate' mod='mpCash'}</th>
                <th style='border: 1px solid grey; background: #e6e4e4; color: #333; text-align: center;'>{l s='total' mod='mpCash'}</th>
            </tr>
        </thead>
        <tbody>
        {foreach $order_details as $order_detail}
            <tr style='line-height: 1.4em; border-bottom: 1px solid #a0a0a0;'>
                <td style='border: 1px solid grey; background: #fdfdfd; color: #333; text-align: left; padding-left: 5px;'>{$order_detail['reference']}</td>
                <td style='border: 1px solid grey; background: #fdfdfd; color: #333; text-align: left; padding-left: 5px;'>{$order_detail['name']}</td>
                <td style='border: 1px solid grey; background: #fdfdfd; color: #333; text-align: left; padding-left: 5px;'>{$order_detail['qty']}</td>
                <td style='border: 1px solid grey; background: #fdfdfd; color: #333; text-align: right; padding-right: 5px;'>{displayPrice price=$order_detail['price']}</td>
                <td style='border: 1px solid grey; background: #fdfdfd; color: #333; text-align: right; padding-right: 5px;'>{$order_detail['reduction_percent']}%</td>
                <td style='border: 1px solid grey; background: #fdfdfd; color: #333; text-align: right; padding-right: 5px;'>{$order_detail['tax_rate']}%</td>
                <td style='border: 1px solid grey; background: #fdfdfd; color: #333; text-align: right; padding-right: 5px;'><strong>{displayPrice price=$order_detail['total']}</strong></td>
            </tr>
        {/foreach}
            <tr style='line-height: 1.4em; border-bottom: 1px solid #a0a0a0;'>
                <td colspan="6" style='background: #e6e4e4; color: #333; text-align: right; padding-right: 5px;'>{l s='TOTAL PRODUCTS' mod='mpCash'}</td>
                <td style='border: 1px solid grey; background: #e6e4e4; color: #333; text-align: right; padding-right: 5px;'>{displayPrice price=$total_products}</td>
            </tr>
            {foreach $vouchers as $voucher}
                <tr style='line-height: 1.4em; border-bottom: 1px solid #a0a0a0;'>
                    <td colspan="5" style='background: #e6e4e4; color: #333; text-align: right; padding-right: 5px;'>{l s='VOUCHER' mod='mpCash'}</td>
                    <td style='border: 1px solid grey; background: #e6e4e4; color: #333; text-align: right; padding-right: 5px;'>{$voucher['name']}</td>
                    <td style='border: 1px solid grey; background: #e6e4e4; color: #333; text-align: right; padding-right: 5px;'>{displayPrice price=-$voucher['value']}</td>
                </tr>
            {/foreach}
            <tr style='line-height: 1.4em; border-bottom: 1px solid #a0a0a0;'>
                <td colspan="6" style='border: 1px solid grey; background: #e6e4e4; color: #333; text-align: right; padding-right: 5px;'>{l s='SHIPPING' mod='mpCash'}</td>
                <td style='border: 1px solid grey; background: #e6e4e4; color: #333; text-align: right; padding-right: 5px;'>{displayPrice price=$total_shipping}</td>
            </tr>
            <tr style='line-height: 1.4em; border-bottom: 1px solid #a0a0a0;'>
                <td colspan="6" style='border: 1px solid grey; background: #e6e4e4; color: #333; text-align: right; padding-right: 5px;'>{l s='TOTAL ORDER' mod='mpCash'}</td>
                <td style='border: 1px solid grey; background: #e6e4e4; color: #333; text-align: right; padding-right: 5px;'>{displayPrice price=$total_cart}</td>
            </tr>
            <tr style='line-height: 1.4em; border-bottom: 1px solid #a0a0a0;'>
                <td colspan="6" style='border: 1px solid grey; background: #e6e4e4; color: #333; text-align: right; padding-right: 5px;'>
                    {if $diff<0}
                        {l s='DISCOUNT' mod='mpCash'}
                    {else}
                        {l s='FEE' mod='mpCash'}
                    {/if}
                </td>
                <td style='border: 1px solid grey; background: #e6e4e4; color: #333; text-align: right; padding-right: 5px;'>{displayPrice price=$diff}</td>
            </tr>
            <tr style='line-height: 1.4em; border-bottom: 1px solid #a0a0a0;'>
                <td colspan="6" style='border: 1px solid grey; background: #e6e4e4; color: #333; text-align: right; padding-right: 5px;'>{l s='TOTAL PAID' mod='mpCash'}</td>
                <td style='border: 1px solid grey; background: #e6e4e4; color: #333; text-align: right; padding-right: 5px;'><strong>{displayPrice price=$total_document}</strong></td>
            </tr>
        </tbody>
        <tfoot>

        </tfoot>
    </table>
</div>
<br>
<div style='overflow: hidden; margin-bottom: 20px;'>
    <div style='padding: 12px; border: 1px solid grey; background-color: #e6e4e4;'>
        <h3>{l s='Delivery address' mod='mpCash'}</h3>
        <br>
        {if !empty($delivery_address->company)}
            <p><strong>{$delivery_address->company}</strong></p>
        {else}
            <p><strong>{$delivery_address->firstname} {$delivery_address->lastname}</strong></p>
        {/if}
        <p>{$delivery_address->address1}</p>
        <p>{$delivery_address->address2}</p>
        <p>{$delivery_address->postcode} {$delivery_address->city}</p>
        <p><strong>{$delivery_address->country}</strong></p>
        {if !empty($delivery_address->phone)}
            <p>{l s='Phone number' mod='mpCash'}: {$delivery_address->phone}</p>
        {/if}
        {if !empty($delivery_address->phone_mobile)}
            <p>{l s='Mobile number' mod='mpCash'}: {$delivery_address->phone_mobile}</p>
        {/if}
        {if !empty($delivery_address->dni)}
            <p>{l s='Dni' mod='mpCash'}: <strong>{$delivery_address->dni}</strong></p>
        {/if}
    </div>
    <br>
    <div style='padding: 12px; border: 1px solid grey; background-color: #e6e4e4;'>
        <h3>{l s='Invoice address' mod='mpCash'}</h3>
        <br>
        {if !empty($invoice_address->company)}
            <p><strong>{$invoice_address->company}</strong></p>
        {else}
            <p><strong>{$invoice_address->firstname} {$invoice_address->lastname}</strong></p>
        {/if}
        <p>{$invoice_address->address1}</p>
        <p>{$invoice_address->address2}</p>
        <p>{$invoice_address->postcode} {$invoice_address->city}</p>
        <p><strong>{$invoice_address->country}</strong></p>
        {if !empty($invoice_address->phone)}
            <p>{l s='Phone number' mod='mpCash'}: {$invoice_address->phone}</p>
        {/if}
        {if !empty($invoice_address->phone_mobile)}
            <p>{l s='Mobile number' mod='mpCash'}: {$invoice_address->phone_mobile}</p>
        {/if}
        {if !empty($invoice_address->dni)}
            <p>{l s='Dni' mod='mpCash'}: <strong>{$invoice_address->dni}</strong></p>
        {/if}
        {if !empty($invoice_address->vat_number)}
            <p>{l s='Vat number' mod='mpCash'}: <strong>{$invoice_address->vat_number}</strong></p>
        {/if}
    </div>
</div>
<br style='clear: both;'>
<br>
<div style='height: 20px; background: #e6e4e4; border: 1px solid grey; width: 100%; text-align: center;'></div>
<br>
<br>

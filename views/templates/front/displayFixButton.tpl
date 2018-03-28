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
*  @author    Massimiliano Palermo <info@mpsoft.it>
*  @copyright 2007-2018 Digital Solutions®
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}
<style>
    .modalDialog {
	position: fixed;
	font-family: Arial, Helvetica, sans-serif;
	top: 0;
	right: 0;
	bottom: 0;
	left: 0;
	background: rgba(200,200,200,0.8);
	z-index: 9999;
	opacity:1;
        display:none;
    }
    
    .modalDialog > div {
        width: 400px;
        position: relative;
        margin: 10% auto;
        padding: 0;
        border-radius: 5px;
        background: #fff;
        background: -moz-linear-gradient(#ffffff, #fcfcfc);
        background: -webkit-linear-gradient(#ffffff, #fcfcfc);
        background: -o-linear-gradient(#ffffff, #fcfcfc);
        opacity: 1;
    }
    
    .modalDialog h2 {
        width: 100%;
        padding: 10px;
        background-color: #3399cc;
        color: #fefefe;
        font-weight: bold;
        text-align: left;
        border: 1px solid #3399cc;
        margin-bottom: 10px;
        margin-top: -5px;
        border-top-left-radius: 5px;
        border-top-right-radius: 5px;
    }
    
    .modalDialog p {
        width: 100%;
        padding: 10px;
        font-size: 1.2em;
    }
    
    .modalDialog_close {
            color: #222 !important;
            font: 14px/100% arial, sans-serif;
            position: absolute;
            right: 5px;
            text-decoration: none;
            text-shadow: 0 1px 0 #fff;
            top: 5px;
    }
    
    .modalDialog_close:hover {
        cursor: pointer;
        color: #fff !important;
        transition-duration: 0.5s;
    }
    
    .close-thick:after {
        content: '✖'; /* UTF-8 symbol */
      }
    
</style>

<div id="openModal" class="modalDialog">
    <div>
        <a id='popup-close' href="#" onclick='javascript:$("#openModal").fadeOut();' title="Close" class="modalDialog_close close-thick"></a>
        <h2>{l s='Fix order' mod='mpCash'}</h2>
        <p id='popup-msg'>
        
        </p>
    </div>
</div>

<a id='fix_order_button' 
   class="btn btn-default _blank" 
   href="#" >
        <i class="icon-gears"></i>
        {l s='Fix this order' mod='mpCash'}
</a>
<script type='text/javascript'>
    $(document).ready(function(){
        var wall = $.find('.well')[0];
        console.log('wall:');
        console.log(wall);
        $('#fix_order_button').detach().appendTo(wall);
        
        $('#fix_order_button').on('click', function(e){
            e.preventDefault();
            if (confirm('{l s='Fix this order?' mod='mpCash'}', '{l s='Request' mod='mpCash'}') === false) {
                return;
            }
            $.ajax({
                url: '{$ajax_fixOrder_url}',
                type: 'POST',
                useDefaultXhrHeader: false,
                dataType: 'json',
                data: 
                {
                    token: '{$token|escape:'htmlall':'UTF-8'}',
                    ajax: true,
                    action: 'fixOrder',
                    id_order: {$id_order}
                }
            })
            .done(function(result)
            {
                jAlert(result.msg);
                location.reload();
            })
            .fail(function()
            {
                jAlert('{l s='Ajax call failed' mod='mpCash'}');
            });
        });
    });
</script>

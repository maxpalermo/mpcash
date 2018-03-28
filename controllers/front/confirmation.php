<?php
/**
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
*/

class MpCashConfirmationModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $this->display_column_left = false;
        $this->display_column_right = false;
        /**
         * INITIALIZE CLASS
         */
        parent::initContent();
    }
    
    public function postProcess()
    {
        $link = new LinkCore();
        $classFee = new ClassCashFee();
        $classFee->getSession();
        
        ContextCore::getContext()->cookie->__set('payment_method', 'cash');
        
        $this->context->smarty->assign(        
                array(
                    'total_cart' => $classFee->getTotalCartTaxIncl(),
                    'fees' => $classFee->getFeeTaxIncl(),
                    'total_to_pay' => $classFee->getTotalDocumentTaxIncl(),
                    'validationLink' => $link->getModuleLink($this->module->name, 'validation')
                    )
        );
        $this->setTemplate('confirmation.tpl');
        //return '<h1>Result: ' . $result . '</h1>';
        //Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart_id.'&id_module='.$module_id.'&id_order='.$order_id.'&key='.$secure_key);
    }
}

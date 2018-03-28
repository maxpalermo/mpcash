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

require_once 'validation.php';
require_once _PS_MODULE_DIR_ . 'mpCash/classes/ClassCashFee.php';

class MpCashFixModuleFrontController extends ModuleFrontController
{
    private $id_order;
    
    public function initContent()
    {
        $this->display_column_left = false;
        $this->display_column_right = false;
        /**
         * INITIALIZE CLASS
         */
        parent::initContent();
        $this->id_order = (int)Tools::getValue('id_order');
    }
    
    public function postProcess()
    {
        
    }
    
    public function displayAjaxFixOrder()
    {
        $id_order = Tools::getValue('id_order');
        if((int)$id_order == 0) {
            print $this->module->l('ERROR: Order id not valid.');
            return false;
        }
        
        $order = new OrderCore($id_order);
        $validation = new MpCashValidationModuleFrontController();
        $fee = new ClassCashFee();
        if ($fee->load($id_order)) {
            if ($order->total_paid==$fee->getTotalDocumentTaxIncl()) {
                print printf($this->module->l('NOTICE: Order %d is already fixed.'), $id_order);
                return false;
            } 
        }
        
        $fee->create($id_order);
        
        print printf($this->module->l(
                'Order total: %f <br>'),
                Tools::displayPrice($order->total_paid)
                );
        print printf($this->module->l(
                'Order fixed total: %f </br>'), 
                Tools::displayPrice($fee->getTotalDocumentTaxIncl())
                );
        
        $fee->insert();
        $fee->saveSession();
        
        //update Order
        $validation->updateOrder($id_order);
        
        //update Invoice
        $validation->updateInvoice($id_order);
        
        //update payment
        $transaction_id = $fee->generateTransactionId();
        $validation->updateOrderPayment($id_order, $transaction_id);
        
        //delete session variables
        $fee->delSession();
        
        print sprintf($this->module->l('Order: %d fixed.'), $id_order);
    }
}

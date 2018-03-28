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

require_once _PS_MODULE_DIR_ . 'mpCash/classes/ClassMpCashPayment.php';

class MpCashValidationModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        if (!defined('MP_CASH_FOLDER')) {
            define('MP_CASH_FOLDER', _PS_MODULE_DIR_ . 'mpCash/');
        }
        
        if (!defined('MP_CASH_TEMPLATES')) {
            define('MP_CASH_TEMPLATES', MP_CASH_FOLDER . 'views/templates/');
        }
        
        if (!defined('MP_CASH_TEMPLATES_FRONT')) {
            define('MP_CASH_TEMPLATES_FRONT', MP_CASH_TEMPLATES . 'front/');
        }
        
        if (!defined('MP_CASH_TEMPLATES_HOOK')) {
            define('MP_CASH_TEMPLATES_HOOK', MP_CASH_TEMPLATES . 'hook/');
        }
        
        if (!defined('MP_CASH_TEMPLATES_ADMIN')) {
            define('MP_CASH_TEMPLATES_ADMIN', MP_CASH_TEMPLATES . 'admin/');
        }
        
        if (!defined('MP_CASH_JS')) {
            define('MP_CASH_JS', MP_CASH_FOLDER . 'views/js/');
        }
        
        if (!defined('MP_CASH_CSS')) {
            define('MP_CASH_CSS', MP_CASH_FOLDER . 'views/css/');
        }
        
        $this->display_column_left = false;
        $this->display_column_right = false;
        /**
         * INITIALIZE CLASS
         */
        parent::initContent();
    }
    
    /**
     * This class should be use by your Instant Payment
     * Notification system to validate the order remotely
     */
    public function postProcess()
    {
        $id_cart = Context::getContext()->cart->id;
        
        if ((int)$id_cart==0) {
            MpCash::addLog('Not a valid id cart.',$id_cart);
            return false;
        }
        
        $db = Db::getInstance();
        $classFee = new ClassCashFee();
        $classFee->getSession();
        $cart = new Cart($id_cart);
        
        /**************
         * VALIDATION *
         **************/
        if (!$this->validateCart($cart)) {
            MpCash::addLog('Not a valid cart.', $id_cart);
            Tools::redirect('index.php?controller=order&step=1');
        }
        
        if (!$this->checkValidPaymentMethod()) {
            MpCash::addLog('Not a valid payment method.', $id_cart);
            Tools::d($this->module->l('This payment method is not available.', 'validation'));
        }
        
        if (!$this->checkCustomer($cart)) {
            MpCash::addLog('Not a valid customer.', $id_cart);
            Tools::redirect('index.php?controller=order&step=1');
        }
        
        /****************
         * CREATE ORDER *
         ****************/
        $sqlOrderState = new DbQueryCore();
        $sqlOrderState->select('id_order_state')
                ->from('mp_advpayment_configuration')
                ->where('payment_method = \'' . pSQL('cash') . '\'');
        $id_order_state = (int)$db->getValue($sqlOrderState);
        
        if($id_order_state==0) {
            $id_order_state = ConfigurationCore::get(_PS_OS_PREPARATION_);
        }
        if((int)$id_order_state == 0) {
            MpCash::addLog('Not a valid id order state.', $id_cart);
            return false;
        }
        
        $customer = new CustomerCore($cart->id_customer);
        
        $sqlOrderCart = new DbQueryCore();
        $sqlOrderCart->select('id_order')
                ->from('orders')
                ->where('id_cart=' . (int)$cart->id);
        $id_order = $db->getValue($sqlOrderCart);
        
        if((int)$id_order==0) {
            PrestaShopLoggerCore::addLog(
                'No order found. Creating a new  one.',
                1,
                $db->getNumberError(),
                'cart',
                $id_cart);
            $id_order = $this->createOrder($cart, $customer->secure_key, $id_order_state);
        }
        
        if ((int)$id_order==0) {
            MpCash::addLog('Not a valid id order.', $id_cart);
            return false;
        }
        
        $classFee->load($id_order);
        
        $params = array(
            'id_cart' => $this->context->cart->id,
            'order' => new OrderCore($id_order),
            'total_paid' => $classFee->getTotalDocumentTaxIncl(),
            'transaction_id' => $classFee->getTransactionId(),
            'status' => 'ok',
        );
        Context::getContext()->smarty->assign(
                array(
                    'hookDisplayPaymentReturn' => Hook::exec('displayPaymentReturn', $params, (int)$this->module->id),
                    'status' => true
                )        
        );
        
        Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$id_order.'&key='.$customer->secure_key);
    }
    
    private function sendNotification($cart, OrderCore $order, CustomerCore $customer, ClassCashFee $classFee)
    {
        //Send email notifications
        $smarty = Context::getContext()->smarty;
        $db = Db::getInstance();
        $emails = html_entity_decode(ConfigurationCore::get('MP_CASH_EMAIL_NOTIFICATION'));
        $arrEmails = explode(PHP_EOL, $emails);
        
        $total_shipping = $cart->getTotalShippingCost();
        $total_products = $cart->getTotalCart($cart->id, false, CartCore::ONLY_PRODUCTS);
        
        $sql = new DbQueryCore();
        $sql->select('*')
                ->from('order_detail')
                ->where('id_order = ' . (int)$order->id);
        $product_details = $db->executeS($sql);
        $order_details = array();
        foreach ($product_details as $product_detail) {
            $tax_rate = TaxCore::getProductTaxRate($product_detail['product_id']);
            
            $order_details[] = array(
                'reference' => $product_detail['product_reference'],
                'name' => $product_detail['product_name'],
                'qty' => $product_detail['product_quantity'],
                'price' => $product_detail['product_price'],
                'reduction_percent' => $product_detail['reduction_percent'],
                'reduction_amount' => $product_detail['reduction_amount'],
                'tax_rate' => $tax_rate,
                'total' => $product_detail['total_price_tax_incl'],
            );
        }
        
        $delivery_address = new AddressCore($order->id_address_delivery);
        $invoice_address = new AddressCore($order->id_address_invoice);
        $fmessage = $order->getFirstMessage();
        $cust_email = $customer->email;
        $carrier = new CarrierCore($order->id_carrier);
        
        $sqlCartRule = new DbQueryCore();
        $sqlCartRule->select('*')
                ->from('order_cart_rule')
                ->where('id_order = ' . (int)$order->id);
        $cart_rules = $db->executeS($sqlCartRule);
        $vouchers = array();
        foreach ($cart_rules as $cart_rule) {
            $voucher = array();
            $voucher['name'] = $cart_rule['name'];
            $voucher['value'] = number_format($cart_rule['value'], 2,'.','');
            $vouchers[] = $voucher;
        }
        
        $smarty->assign(
                array(
                    'firstname' => $customer->firstname,
                    'lastname' => $customer->lastname,
                    'order_reference' => $order->reference,
                    'order_date' => $order->date_add,
                    'order_details' => $order_details,
                    'total_cart' => $classFee->getTotalCartTaxIncl(),
                    'total_shipping' => $total_shipping,
                    'total_products' => $total_products,
                    'total_document' => $classFee->getTotalDocumentTaxIncl(),
                    'delivery_address' => $delivery_address,
                    'invoice_address' => $invoice_address,
                    'message' => $fmessage,
                    'email' => $cust_email,
                    'carrier' => $carrier,
                    'vouchers' => $vouchers,
                )
            );
        $message = $smarty->fetch(MP_CASH_TEMPLATES_FRONT . 'email_notification.tpl');
        
        
        foreach ($arrEmails as $email)
        {
            $to = $email;
            $subject = $this->module->l('Cash payment method notification', 'validation');
            $shop_email = strval(Configuration::get('PS_SHOP_EMAIL'));
            
            $headers = "From: " . $shop_email . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=latin-1\r\n";
            
            
            
            mail($to, $subject, $message, $headers);
        }
    }
    
    private function validateCart($cart)
    {
         /**
         * Validate cart
         */
        if (
                $cart->id_customer == 0
                || $cart->id_address_delivery == 0
                || $cart->id_address_invoice == 0
                || !$this->module->active) {
            return false;
        } else {
            return true;
        }
    }
    
    private function checkValidPaymentMethod()
    {
        /**
         * Check if module is enabled
         * @var bool $authorized 
         */
        $authorized = false;
        $count = count(ModuleCore::getPaymentModules());
        foreach (ModuleCore::getPaymentModules() as $pay_module) {
            if ($pay_module['name'] == $this->module->name) {
                $authorized = true;
                break;
            }
        }
        
        if($count==0) {
            $authorized = true;
        }
        
        return $authorized;
    }
    
    private function checkCustomer($cart)
    {
        /**
         * Validate customer
         */
        $customer = new CustomerCore($cart->id_customer);
        if (!ValidateCore::isLoadedObject($customer)) {
            return false;
        }
        return true;
    }
    
    public function createOrder($cart, $secure_key, $id_order_state)
    {
        mpCash::addLog('cart id: ' . $cart->id);
        mpCash::addLog('secure key: ' . $secure_key);
        mpCash::addLog('id order state: ' . $id_order_state);
        $extra_vars = array();
        
        if ((int)$id_order_state==0) {
            MpCash::addLog('Not a valid id order state.', $cart->id);
            return false;
        }
        
        /**
         * Validate order
         */
        $cashPayment = new ClassMpCashPayment($this->module, 'mpCash');
        $result = $cashPayment->validateOrder(
                $cart->id,
                $id_order_state,
                $cart->getOrderTotal(true, Cart::BOTH),
                $this->module->l('cash', 'validation'),
                null,
                $extra_vars,
                (int)$cart->id_currency,
                false,
                $secure_key,
                ContextCore::getContext()->shop,
                '');
        MpCash::addLog('Validate order returns ' . (int)$result);
        
        if($result) {
            Mpcash::addLog('get order by cart ' . $cart->id);
            return $this->getOrderIdByIdCart($cart->id);
        } else {
            print $this->module->l('Error during Cart validation', 'validation');
            return false;
        }
    }
    
    /**
     * Retrieve order reference from id cart
     * @param int $id_cart cart id
     * @return string product reference
     */
    public function getOrderReferenceByIdCart($id_cart)
    {
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        $sql->select('reference')
                ->from('orders')
                ->where('id_cart = ' . (int)$id_cart);
        return $db->getValue($sql);
    }
    
    /**
     * Retrieve order id from id cart
     * @param int $id_cart
     * @return int order id
     */
    public function getOrderIdByIdCart($id_cart)
    {
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        $sql->select('id_order')
                ->from('orders')
                ->where('id_cart = ' . (int)$id_cart);
        return (int)$db->getValue($sql);
    }
    
    public function updateOrder($id_order)
    {
        if ((int)$id_order == 0) {
            MpCash::addLog('Not a valid id order.');
            return false; 
        }
        
        $classFee = new ClassCashFee();
        $classFee->getSession();
        
        $db=Db::getInstance();
        $result = $db->update(
                'orders',
                array(
                    'total_paid' => number_format((float)$classFee->getTotalDocumentTaxIncl(),6,'.',''),
                    'total_paid_real' => number_format((float)$classFee->getTotalDocumentTaxIncl(),6,'.',''),
                    'total_paid_tax_incl' => number_format((float)$classFee->getTotalDocumentTaxIncl(),6,'.',''),
                    'total_paid_tax_excl' => number_format((float)$classFee->getTotalDocumentTaxExcl(),6,'.',''),
                ),
                'id_order = ' . (int)$id_order);
        return $result;
    }
    
    public function updateInvoice($id_order)
    {
        if ((int)$id_order == 0) {
            MpCash::addLog('Not a valid id order.');
            return false; 
        }
        
        $payment = new ClassCashFee();
        $payment->getSession();
        
        $db=Db::getInstance();
        $result = $db->update(
                'order_invoice',
                array(
                    'total_paid_tax_incl' => number_format((float)$payment->getTotalDocumentTaxIncl(),6,'.',''),
                    'total_paid_tax_excl' => number_format((float)$payment->getTotalDocumentTaxExcl(),6,'.',''),
                ),
                'id_order = ' . (int)$id_order);
        return $result;
    }
    
    public function updateOrderPayment($id_order, $transaction_id = '')
    {
        if ((int)$id_order == 0) {
            MpCash::addLog('Not a valid id order.');
            return false; 
        }
        
        $payment = new ClassCashFee();
        $payment->getSession();
        $id_order_payment = $payment->getIdOrderPayment($id_order);
        
        if ((int)$id_order_payment==0) {
            MpCash::addLog('Not a valid order payment.');
            return false;
        }
        $orderPayment = new OrderPaymentCore($id_order_payment);
        
        $orderPayment->amount = number_format($payment->getTotalDocumentTaxIncl(),2,'.','');
        $orderPayment->payment_method = $this->module->l('cash');
        if (!empty($transaction_id)) {
            $orderPayment->transaction_id = $payment->getTransactionId();
        }
        $result = $orderPayment->update();
        
        return $result;
    }
    
    public function updateOrderPaymentModule($id_order, $payment_method)
    {
        if ((int)$id_order == 0) {
            MpCash::addLog('Not a valid id order.');
            return false; 
        }
        
        if (empty($payment_method)) {
            MpCash::addLog('Not a valid payment method.');
            return false; 
        }
        
        $order = new OrderCore($id_order);
        $order->module = $payment_method;
        $result =  $order->update();
        return $result;
    }
}

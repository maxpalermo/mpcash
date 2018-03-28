<?php
/**
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
 */

class ClassCashFee
{
    const CASH      = 'cash';
    const BANKWIRE  = 'bankwire';
    const PAYPAL    = 'paypal';
    
    const FEE_TYPE_NONE     = '0';
    const FEE_TYPE_FIXED    = '1';
    const FEE_TYPE_PERCENT  = '2';
    const FEE_TYPE_MIXED    = '3';
    const FEE_TYPE_DISCOUNT = '4';
    
    public $payment_method;
    public $id_fee;
    public $id_order;
    public $id_order_payment;
    public $transaction_id;
    public $date_add;
    public $date_upd;
    public $tax_included;
    public $tablename;
    private $fee_tax_incl;
    private $fee_tax_excl;
    private $fee_tax_rate;
    private $total_paid_tax_incl;
    private $total_paid_tax_excl;
    
    public function __construct()
    {
        $this->id_fee = 0;
        $this->fee_tax_incl = 0;
        $this->fee_tax_excl = 0;
        $this->fee_tax_rate = 0;
        $this->payment_method = 'cash';
        $this->tablename = 'mp_advpayment_fee';
        $this->date_upd = '';
        $this->id_order_payment = 0;
    }
    
    public function getTotalCartTaxIncl()
    {
        return (float)$this->total_paid_tax_incl;
    }
    
    public function getTotalCartTaxExcl()
    {
        return (float)$this->total_paid_tax_excl;
    }
    
    public function getTotalDocumentTaxIncl()
    {
        return (float)$this->total_paid_tax_incl + (float)$this->fee_tax_incl;
    }
    
    public function getTotalDocumentTaxExcl()
    {
        return (float)$this->total_paid_tax_excl + (float)$this->fee_tax_excl;
    }
    
    public function getFeeTaxIncl()
    {
        return (float)$this->fee_tax_incl;
    }
    
    public function getFeeTaxExcl()
    {
        return (float)$this->fee_tax_excl;
    }
    
    public function getFeeTaxRate()
    {
        return (float)$this->fee_tax_rate;
    }
    
    /**
     * Get id order invoice from order
     * @param type $id_order id order
     */
    public function getIdOrderInvoice($id_order)
    {
        if ((int)$id_order==0 ) {
            mpBankwire::addLog('No valid id order.');
            return false;
        }
        
        $db = Db::getInstance();
        $sqlOrder = new DbQueryCore();
        $sqlOrder->select('id_order_invoice')
                ->from('order_invoice')
                ->where('id_order = ' . (int)$id_order);
        $id_order_invoice = $db->getValue($sqlOrder);
        
        if(empty($id_order_invoice)) {
            mpCash::addLog('No invoice found.');
            return false;
        }
        
        return $id_order_invoice;
    }
    
    /**
     * Set invoice payment
     * @param type $id_order_invoice
     * @param type $id_order_payment
     * @param type $id_order
     * @return boolean
     */
    public function setInvoicepayment($id_order_invoice,$id_order_payment,$id_order)
    {
        if (!$id_order_invoice || !$id_order_payment || !$id_order) {
            return false;
        }
        $db = Db::getInstance();
        $res = $db->insert(
            'order_invoice_payment', 
            array(
                'id_order_invoice' => (int)$id_order_invoice,
                'id_order_payment' => (int)$id_order_payment,
                'id_order' => (int)$id_order,
            )
        );
        if ($res) {
            return true;
        } else {
            PrestaShopLoggerCore::addLog('Cash insert invoice payment: ' . $db->getMsgError());
            return false;
        }
    }
    
    /**
     * Get id order payment from order reference
     * @param int $id_order order id
     * @return mixed id_order_payment if success, false otherwise
     */
    public function getIdOrderPayment($id_order)
    {
        if ((int)$id_order==0 ) {
            MpCash::addLog('No id order set.');
            return false;
        }
        
        $db = Db::getInstance();
        $sqlOrder = new DbQueryCore();
        $sqlOrder->select('reference')
                ->from('orders')
                ->where('id_order = ' . (int)$id_order);
        $reference = $db->getValue($sqlOrder);
        
        if(empty($reference)) {
            MpCash::addLog('No order found.');
            return false;
        }
        
        $sql = new DbQueryCore();
        $sql->select('id_order_payment')
                ->from('order_payment')
                ->where('order_reference = \'' . pSQL($reference) . '\'');
        $id_order_payment = $db->getValue($sql);
        
        return $id_order_payment;
    }
    
    /**
     *
     * @param string $payment_method
     * @return array sorted array of id products
     */
    public static function getListProductsExclusion($payment_method)
    {
        $products = array();
        $exclusions = self::getExclusions($payment_method);
        self::addProduct(self::getProductsFromCarriers($exclusions['carriers']), $products);
        self::addProduct(self::getProductsFromCategories($exclusions['categories']), $products);
        self::addProduct(self::getProductsFromManufacturers($exclusions['manufacturers']), $products);
        self::addProduct(self::getProductsFromSuppliers($exclusions['suppliers']), $products);
        self::addProduct(self::getProductsVirtual(), $products);
        self::addProduct($exclusions['products'], $products);
        
        //Sort array
        asort($products);
        $product_list = array_values($products);
        
        return $product_list;
    }
    
    /**
     *
     * @param string $payment_method CONST:
     * classMpPayment::CASH
     * classMpPayment::BANKWIRE
     * classMpPayment::PAYPAL
     * @return array result query
     */
    public static function getExclusions($payment_method)
    {
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        
        $sql    ->select("carriers")
                ->select("categories")
                ->select("manufacturers")
                ->select("suppliers")
                ->select("products")
                ->from("mp_advpayment_configuration")
                ->where("payment_method = '$payment_method'");
        return $db->getRow($sql);
    }
    
    /**
     *
     * @param array $array
     * @return array returns indexed array
     */
    public static function purifyArray($array)
    {
        $purified_array = array();
        foreach ($array as $item) {
            foreach ($item as $key => $value) {
                $purified_array[] = $value;
            }
        }
        return $purified_array;
    }
    
    /**
     *
     * @param string $carriers carrier list comma separated
     * @return array id product list
     */
    public static function getProductsFromCarriers($carriers)
    {
        if (empty($carriers)) {
            return array();
        }
        
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        
        $sql    ->select("id_product")
                ->from("product_carrier")
                ->where("id_carrier_reference in ($carriers)");
        return self::purifyArray($db->ExecuteS($sql));
    }
    
    /**
     *
     * @param string $categories categories list comma separated
     * @return array id product list
     */
    public static function getProductsFromCategories($categories)
    {
        if (empty($categories)) {
            return array();
        }
        
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        
        $sql    ->select("id_product")
                ->from("category_product")
                ->where("id_category in ($categories)");
        return self::purifyArray($db->ExecuteS($sql));
    }
    
    /**
     *
     * @param string $manufacturers manufacturers list comma separated
     * @return array id product list
     */
    public static function getProductsFromManufacturers($manufacturers)
    {
        if (empty($manufacturers)) {
            return array();
        }
        
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        
        $sql    ->select("id_product")
                ->from("product")
                ->where("id_manufacturer in ($manufacturers)");
        return self::purifyArray($db->ExecuteS($sql));
    }
    
    /**
     *
     * @param string $suppliers suppliers list comma separated
     * @return array id product list
     */
    public static function getProductsVirtual()
    {
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        
        $sql    ->select("id_product")
                ->from("product")
                ->where("is_virtual = 1");
        return self::purifyArray($db->ExecuteS($sql));
    }
    
    /**
     *
     * @param string $suppliers suppliers list comma separated
     * @return array id product list
     */
    public static function getProductsFromSuppliers($suppliers)
    {
        if (empty($suppliers)) {
            return array();
        }
        
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        
        $sql    ->select("id_product")
                ->from("product_supplier")
                ->where("id_supplier in ($suppliers)");
        return self::purifyArray($db->ExecuteS($sql));
    }
    
    /**
     *
     * @param array $product_list list of id products to add
     * @param array $products product list
     * @return int array size
     */
    public static function addProduct($product_list, &$products)
    {
        if (empty($product_list)) {
            return count($products);
        }
        if (!is_array($product_list)) {
            $product_list = explode(",", $product_list);
        }
        foreach ($product_list as $id_product) {
            if (!in_array($id_product, $products, true)) {
                $products[] = $id_product;
            }
        }
        
        return count($products);
    }
    
    /**
     * Get product list from given cart id
     * @param int $id_cart id
     * @return array cart product list
     */
    public static function getCartProductList($id_cart)
    {
        $cart = new Cart($id_cart);
        $products = $cart->getProducts();
        
        return $products;
    }
    
    /**
     * Create fee amount and recalculate total order
     * @param int $id_order Order id
     */
    public function create($id_order=0)
    {
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        $sql->select('*')
                ->from('mp_advpayment_configuration')
                ->where('payment_method = \'' . pSQL($this->payment_method) . '\'');
        $result = $db->getRow($sql);
        $this->fee_tax_rate = (float)$result['tax_rate'];
        $this->id_carrier = (int)$result['carriers'];
        
        PrestaShopLoggerCore::addLog('Get carrier id: ' . (int)$this->id_carrier);
        
        if ((int)$id_order == 0) { //No order, check cart
            $cart = new Cart(ContextCore::getContext()->cart->id);
            
            if((int)$this->id_carrier!=0 && (int)$this->id_carrier!=$cart->id_carrier)
            {
                $cart->id_carrier = $this->id_carrier;
                ContextCore::getContext()->cart->id_carrier = $this->id_carrier;
                MpCash::addLog('Set carrier to ' . $cart->id_carrier);
            }
            $this->total_paid_tax_incl = $cart->getOrderTotal(true, Cart::BOTH);
            $this->total_paid_tax_excl = $cart->getOrderTotal(false, Cart::BOTH);
        } else {
            $order = new OrderCore($id_order);
            $this->total_paid_tax_incl = $order->total_paid_tax_incl;
            $this->total_paid_tax_excl = $order->total_paid_tax_excl;
        }
        
        $this->calcFee($result);
        
        $this->id_order = $id_order;
        $this->tax_included = (int)$result['tax_included'];
        
        if ($this->tax_included) {
            $this->fee_tax_incl = $this->fee;
            $this->fee_tax_excl = $this->extractTax($this->fee_tax_incl);
        } else {
            $this->fee_tax_excl = $this->fee;
            $this->fee_tax_incl = $this->insertTax($this->fee_tax_excl);
            $this->fee = $this->fee_tax_incl;
        }
    }
    
    /**
     * Calculate fee amount
     * @param associated array $row Associated array of table row
     */
    private function calcFee($row)
    {
        //calc fee
        PrestaShopLoggerCore::addLog('Fee type:' . (int)$row['fee_type']);
        
        switch ((int)$row['fee_type']) {
            case 1: //AMOUNT
                $this->fee = (float)$row['fee_amount'];
                break;
            case 2: //PERCENT
                $this->fee = (float)$this->total_paid_tax_incl * (float)$row['fee_percent'] /100;
                break;
            case 3: //AMOUNT + PERCENT
                $this->fee = (float)$this->total_paid_tax_incl * (float)$row['fee_percent'] /100;
                $this->fee += (float)$row['fee_amount'];
                break;
            case 4: //DISCOUNT
                $this->fee = -((float)$this->total_paid_tax_incl * (float)$row['discount'] /100);
                break;
            default:
                MpCash::addLog('No fee type selected');
                $this->fee=0;
                break;
        }
        
        if ($row['fee_type']!=4) { //not a discount
            if ((float)$row['fee_min'] != 0 && $this->fee<(float)$row['fee_min']) {
                MpCash::addLog('Applied fee min: ' . $row['fee_min']);
                $this->fee = $row['fee_min'];
            } 

            if ((float)$row['fee_max'] != 0 && $this->fee>(float)$row['fee_max']) {
                MpCash::addLog('Applied fee max: ' . $row['fee_max']);
                $this->fee = $row['fee_max'];
            }
        }
        if ((float)$row['order_min'] != 0 && $this->total_paid_tax_incl<(float)$row['order_min']) {
            MpCash::addLog('Applied order min: ' . $row['order_min']);
            $this->fee = 0;
        }

        if ((float)$row['order_max'] != 0 && $this->total_paid_tax_incl>(float)$row['order_max']) {
            MpCash::addLog('Applied order max: ' . $row['order_max']);
            $this->fee = 0;
        }
        
        $this->fee_tax_incl = Tools::ceilf($this->fee,2);
    }
    
    public function insert()
    {
        if((int)$this->id_order==0) {
            MpCash::addLog('Not a valid id order.');
            return false;
        }
        
        $this->date_add = date('Y-m-d');
        $db = Db::getInstance();
        
        try {
            $result = $db->insert(
                    $this->tablename,
                    array(
                        'id_order' => (int)$this->id_order,
                        'total_paid_tax_incl' => (float)$this->total_paid_tax_incl,
                        'total_paid_tax_excl' => (float)$this->total_paid_tax_excl,
                        'fee_tax_incl' => (float)$this->fee_tax_incl,
                        'fee_tax_excl' => (float)$this->fee_tax_excl,
                        'fee_tax_rate' => (float)$this->fee_tax_rate,
                        'transaction_id' => pSQL($this->transaction_id),
                        'payment_method' => pSQL($this->payment_method),
                        'date_add' => pSQL($this->date_add),
                    ),
                    false,
                    false,
                    Db::REPLACE
                    );
            if ($result) {
                MpCash::addLog('Order payment inserted with id ' . $db->Insert_ID());
            } else {
                MpCash::addLog('Database error: ' . $db->getMsgError());
                return false;
            }
        } catch (Exception $exc) {
            MpCash::addLog('Database error during insertion: ' . $exc->getMessage());
        }
        
        return $result;
    }
    
    public function delete()
    {
        if ((int)$this->id_order==0) {
            return false;
        }
        
        $db = Db::getInstance();
        $result = $db->delete(
                $this->tablename,
                'id_order = ' . (int)$this->id_order
                );
        return $result;
    }
    
    public function load($id_order)
    {
        if((int)$id_order==0) {
            return false;
        }
        
        $this->id_order = $id_order;
        
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        $sql->select('*')
                ->from($this->tablename)
                ->where('id_order = ' . $id_order);
        $result = $db->getRow($sql);
        
        if ($result) {
           foreach($result as $key=>$value)
           {
               $this->$key = $value;
           }
        } else {
            return $result;
        }
        
        return true;
    }
    
    private function extractTax($value)
    {
        return number_format($value / ((100 + $this->fee_tax_rate)/100), 2);
    }
    
    private function insertTax($value)
    {
        return number_format(($value * (100 + $this->fee_tax_rate))/100, 2);
    }
    
    public function setTransactionId($transaction_id)
    {
        $this->transaction_id = $transaction_id;
        $db = Db::getInstance();
        $result = $db->update(
                $this->tablename,
                array('transaction_id' => pSQL($this->transaction_id)),
                'id_order = ' . (int)$this->id_order
        );
        return $result;
    }
    
    public function saveSession()
    {
        $cookie = ContextCore::getContext()->cookie;
        $cookie->__set('cash_total_cart_tax_incl', $this->getTotalCartTaxIncl());
        $cookie->__set('cash_total_cart_tax_excl', $this->getTotalCartTaxExcl());
        $cookie->__set('cash_fee_tax_incl', $this->getFeeTaxIncl());
        $cookie->__set('cash_fee_tax_excl', $this->getFeeTaxExcl());
        $cookie->__set('cash_fee_tax_rate', $this->getFeeTaxRate());
        $cookie->__set('cash_transaction_id', $this->getTransactionId());
    }
    
    public function getSession()
    {
        $cookie = ContextCore::getContext()->cookie;
        $this->total_paid_tax_incl = $cookie->__get('cash_total_cart_tax_incl');
        $this->total_paid_tax_excl = $cookie->__get('cash_total_cart_tax_excl');
        $this->fee_tax_incl = $cookie->__get('cash_fee_tax_incl');
        $this->fee_tax_excl = $cookie->__get('cash_fee_tax_excl');
        $this->fee_tax_rate = $cookie->__get('cash_fee_tax_rate');
        $this->transaction_id = $cookie->__get('cash_transaction_id');
    }
    
    public function delSession()
    {
        $cookie = ContextCore::getContext()->cookie;
        $cookie->__unset('cash_total_cart_tax_incl');
        $cookie->__unset('cash_total_cart_tax_excl');
        $cookie->__unset('cash_fee_tax_incl');
        $cookie->__unset('cash_fee_tax_excl');
        $cookie->__unset('cash_fee_tax_rate');
        $cookie->__unset('cash_transaction_id');
    }
    
    public function getTransactionId()
    {
        if(empty($this->transaction_id)) {
            $this->generateTransactionId();
        }
        return $this->transaction_id;
    }
    
    public function generateTransactionId($length = 16)
    {
        
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        
        if ($this->checkTransactionId($randomString) == 0) {
            $this->transaction_id = $randomString;
            return $randomString;
        } else {
            return $this->generateTransactionId();
        }
    }
    
    private function checkTransactionId($transactionId)
    {
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        $sql->select('transaction_id')
                ->from($this->tablename)
                ->where('transaction_id = \'' . pSQL($transactionId) . '\'')
                ->where('payment_method != \'' . pSQL('paypal') . '\'');
        $value = $db->getValue($sql);
        return (int)$value;
    }
}

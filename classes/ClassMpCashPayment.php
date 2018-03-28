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

class ClassMpCashPayment {
    public function __construct($module, $moduleName) {
        MpCash::addLog('Initialize ClassMpCashPayment');
        $this->module = $module;
        $this->name = $moduleName;
        
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        $sql->select('active')
                ->from('module')
                ->where('name = \'' . pSQL($moduleName) . '\'');
        $this->active = (int)$db->getValue($sql);
        
        MpCash::addLog('ClassMpCashPayment initialized');
    }
    
    /**
     * Validate an order in database
     * Function called from a payment module
     *
     * @param int $id_cart
     * @param int $id_order_state
     * @param float   $amount_paid    Amount really paid by customer (in the default currency)
     * @param string  $payment_method Payment method (eg. 'Credit card')
     * @param null    $message        Message to attach to order
     * @param array   $extra_vars
     * @param null    $currency_special
     * @param bool    $dont_touch_amount
     * @param bool    $secure_key
     * @param Shop    $shop
     *
     * @return bool
     * @throws PrestaShopException
     */
    public function validateOrder(
            $id_cart,
            $id_order_state,
            $amount_paid,
            $payment_method = 'Unknown',
            $message = null,
            $extra_vars = array(),
            $currency_special = null,
            $dont_touch_amount = false,
            $secure_key = false,
            Shop $shop = null,
            $transaction_id = '')
    {
        MpCash::addLog('[VALIDATEORDER]- Function called.', $id_cart);

        if (!isset($this->context)) {
            $this->context = Context::getContext();
        }
        //Set Cart
        $this->context->cart = new Cart((int)$id_cart);
        //Set Customer
        $this->context->customer = new Customer((int)$this->context->cart->id_customer);
        // The tax cart is loaded before the customer so re-cache the tax calculation method
        $this->context->cart->setTaxCalculationMethod();
        //Set language
        $this->context->language = new Language((int)$this->context->cart->id_lang);
        //Set Shop
        $this->context->shop = ($shop ? $shop : new Shop((int)$this->context->cart->id_shop));
        
        ShopUrl::resetMainDomainCache();
        //Set currency
        $id_currency = $currency_special ? (int)$currency_special : (int)$this->context->cart->id_currency;
        $this->context->currency = new Currency((int)$id_currency, null, (int)$this->context->shop->id);
        //Set Country
        if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_delivery') {
            $context_country = $this->context->country;
        }
        //Set order State
        $order_status = new OrderState((int)$id_order_state, (int)$this->context->language->id);
        if (!Validate::isLoadedObject($order_status)) {
            MpCash::addLog('[VALIDATEORDER] Order Status cannot be loaded', $id_cart);
            throw new PrestaShopException('Can\'t load Order status');
        }

        if (!$this->active) {
            MpCash::addLog('[VALIDATEORDER] Module is not active', $id_cart);
            die(Tools::displayError());
        }

        // Does order already exists ?
        if (Validate::isLoadedObject($this->context->cart) && $this->context->cart->OrderExists() == false) {
            if ($secure_key !== false && $secure_key != $this->context->cart->secure_key) {
                MpCash::addLog('[VALIDATEORDER] Secure key does not match', $id_cart);
                die(Tools::displayError());
            }

            // For each package, generate an order
            $delivery_option_list = $this->context->cart->getDeliveryOptionList();
            $package_list = $this->context->cart->getPackageList();
            $cart_delivery_option = $this->context->cart->getDeliveryOption();

            // If some delivery options are not defined, or not valid, use the first valid option
            foreach ($delivery_option_list as $id_address => $package) {
                if (!isset($cart_delivery_option[$id_address]) || !array_key_exists($cart_delivery_option[$id_address], $package)) {
                    foreach ($package as $key => $val) {
                        if(!empty($val)) {
                            //nothing to do
                        }
                        $cart_delivery_option[$id_address] = $key;
                        break;
                    }
                }
            }

            $order_list = array();
            $order_detail_list = array();

            do {
                $reference = Order::generateReference();
            } while (Order::getByReference($reference)->count());

            $this->currentOrderReference = $reference;

            $order_creation_failed = false;
            $cart_total_paid = (float)Tools::ps_round((float)$this->context->cart->getOrderTotal(true, Cart::BOTH), 2);

            foreach ($cart_delivery_option as $id_address => $key_carriers) {
                foreach ($delivery_option_list[$id_address][$key_carriers]['carrier_list'] as $id_carrier => $data) {
                    foreach ($data['package_list'] as $id_package) {
                        // Rewrite the id_warehouse
                        $package_list[$id_address][$id_package]['id_warehouse'] = (int)$this->context->cart->getPackageIdWarehouse($package_list[$id_address][$id_package], (int)$id_carrier);
                        $package_list[$id_address][$id_package]['id_carrier'] = $id_carrier;
                    }
                }
            }
            // Make sure CartRule caches are empty
            CartRule::cleanCache();
            $cart_rules = $this->context->cart->getCartRules();
            foreach ($cart_rules as $cart_rule) {
                if (($rule = new CartRule((int)$cart_rule['obj']->id)) && Validate::isLoadedObject($rule)) {
                    if ($error = $rule->checkValidity($this->context, true, true)) {
                        MpCash::addLog('[VALIDATEORDER] remove cartrule ' . $rule->id, $id_cart);
                        $this->context->cart->removeCartRule((int)$rule->id);
                        if (isset($this->context->cookie) && isset($this->context->cookie->id_customer) && $this->context->cookie->id_customer && !empty($rule->code)) {
                            if (Configuration::get('PS_ORDER_PROCESS_TYPE') == 1) {
                                Tools::redirect('index.php?controller=order-opc&submitAddDiscount=1&discount_name='.urlencode($rule->code));
                            }
                            Tools::redirect('index.php?controller=order&submitAddDiscount=1&discount_name='.urlencode($rule->code));
                        } else {
                            $rule_name = isset($rule->name[(int)$this->context->cart->id_lang]) ? $rule->name[(int)$this->context->cart->id_lang] : $rule->code;
                            $error = sprintf(Tools::displayError('CartRule ID %1s (%2s) used in this cart is not valid and has been withdrawn from cart'), (int)$rule->id, $rule_name);
                            MpCash::addLog('[VALIDATEORDER] error: ' . $error, $id_cart);
                        }
                    }
                }
            }

            foreach ($package_list as $id_address => $packageByAddress) {
                foreach ($packageByAddress as $id_package => $package) {
                    /** @var Order $order */
                    $order = new Order();
                    $order->product_list = $package['product_list'];

                    if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_delivery') {
                        $address = new Address((int)$id_address);
                        $this->context->country = new Country((int)$address->id_country, (int)$this->context->cart->id_lang);
                        if (!$this->context->country->active) {
                            throw new PrestaShopException('The delivery address country is not active.');
                        }
                    }

                    $carrier = null;
                    if (!$this->context->cart->isVirtualCart() && isset($package['id_carrier'])) {
                        $carrier = new Carrier((int)$package['id_carrier'], (int)$this->context->cart->id_lang);
                        $order->id_carrier = (int)$carrier->id;
                        $id_carrier = (int)$carrier->id;
                    } else {
                        $order->id_carrier = 0;
                        $id_carrier = 0;
                    }

                    $order->id_customer = (int)$this->context->cart->id_customer;
                    $order->id_address_invoice = (int)$this->context->cart->id_address_invoice;
                    $order->id_address_delivery = (int)$id_address;
                    $order->id_currency = $this->context->currency->id;
                    $order->id_lang = (int)$this->context->cart->id_lang;
                    $order->id_cart = (int)$this->context->cart->id;
                    $order->reference = $reference;
                    $order->id_shop = (int)$this->context->shop->id;
                    $order->id_shop_group = (int)$this->context->shop->id_shop_group;

                    $order->secure_key = ($secure_key ? pSQL($secure_key) : pSQL($this->context->customer->secure_key));
                    $order->payment = $payment_method;
                    if (isset($this->name)) {
                        $order->module = $this->name;
                    }
                    $order->recyclable = $this->context->cart->recyclable;
                    $order->gift = (int)$this->context->cart->gift;
                    $order->gift_message = $this->context->cart->gift_message;
                    $order->mobile_theme = $this->context->cart->mobile_theme;
                    $order->conversion_rate = $this->context->currency->conversion_rate;
                    $amount_paid = !$dont_touch_amount ? Tools::ps_round((float)$amount_paid, 2) : $amount_paid;
                    $order->total_paid_real = 0;

                    $order->total_products = (float)$this->context->cart->getOrderTotal(false, Cart::ONLY_PRODUCTS, $order->product_list, $id_carrier);
                    $order->total_products_wt = (float)$this->context->cart->getOrderTotal(true, Cart::ONLY_PRODUCTS, $order->product_list, $id_carrier);
                    $order->total_discounts_tax_excl = (float)abs($this->context->cart->getOrderTotal(false, Cart::ONLY_DISCOUNTS, $order->product_list, $id_carrier));
                    $order->total_discounts_tax_incl = (float)abs($this->context->cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS, $order->product_list, $id_carrier));
                    $order->total_discounts = $order->total_discounts_tax_incl;

                    $order->total_shipping_tax_excl = (float)$this->context->cart->getPackageShippingCost((int)$id_carrier, false, null, $order->product_list);
                    $order->total_shipping_tax_incl = (float)$this->context->cart->getPackageShippingCost((int)$id_carrier, true, null, $order->product_list);
                    $order->total_shipping = $order->total_shipping_tax_incl;

                    if (!is_null($carrier) && Validate::isLoadedObject($carrier)) {
                        $order->carrier_tax_rate = $carrier->getTaxesRate(new Address((int)$this->context->cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')}));
                    }

                    $order->total_wrapping_tax_excl = (float)abs($this->context->cart->getOrderTotal(false, Cart::ONLY_WRAPPING, $order->product_list, $id_carrier));
                    $order->total_wrapping_tax_incl = (float)abs($this->context->cart->getOrderTotal(true, Cart::ONLY_WRAPPING, $order->product_list, $id_carrier));
                    $order->total_wrapping = $order->total_wrapping_tax_incl;

                    $order->total_paid_tax_excl = (float)Tools::ps_round((float)$this->context->cart->getOrderTotal(false, Cart::BOTH, $order->product_list, $id_carrier), _PS_PRICE_COMPUTE_PRECISION_);
                    $order->total_paid_tax_incl = (float)Tools::ps_round((float)$this->context->cart->getOrderTotal(true, Cart::BOTH, $order->product_list, $id_carrier), _PS_PRICE_COMPUTE_PRECISION_);
                    $order->total_paid = $order->total_paid_tax_incl;
                    $order->round_mode = Configuration::get('PS_PRICE_ROUND_MODE');
                    $order->round_type = Configuration::get('PS_ROUND_TYPE');

                    $order->invoice_date = '0000-00-00 00:00:00';
                    $order->delivery_date = '0000-00-00 00:00:00';

                    MpCash::addLog('[VALIDATEORDER] Order is about to be added', $id_cart);

                    // Creating order
                    $result = $order->add();

                    if (!$result) {
                        MpCash::addLog('[VALIDATEORDER] Order cannot be created', $id_cart);
                        throw new PrestaShopException('Can\'t save Order');
                    }

                    // Amount paid by customer is not the right one -> Status = payment error
                    // We don't use the following condition to avoid the float precision issues : http://www.php.net/manual/en/language.types.float.php
                    // if ($order->total_paid != $order->total_paid_real)
                    // We use number_format in order to compare two string
                    if ($order_status->logable && number_format($cart_total_paid, _PS_PRICE_COMPUTE_PRECISION_) != number_format($amount_paid, _PS_PRICE_COMPUTE_PRECISION_)) {
                        $id_order_state = Configuration::get('PS_OS_ERROR');
                    }

                    $order_list[] = $order;

                    MpCash::addLog('[VALIDATEORDER] OrderDetail is about to be added', $id_cart);

                    // Insert new Order detail list using cart for the current order
                    $order_detail = new OrderDetail(null, null, $this->context);
                    $order_detail->createList($order, $this->context->cart, $id_order_state, $order->product_list, 0, true, $package_list[$id_address][$id_package]['id_warehouse']);
                    $order_detail_list[] = $order_detail;

                    MpCash::addLog('[VALIDATEORDER] OrderCarrier is about to be added', $id_cart);

                    // Adding an entry in order_carrier table
                    if (!is_null($carrier)) {
                        $order_carrier = new OrderCarrier();
                        $order_carrier->id_order = (int)$order->id;
                        $order_carrier->id_carrier = (int)$id_carrier;
                        $order_carrier->weight = (float)$order->getTotalWeight();
                        $order_carrier->shipping_cost_tax_excl = (float)$order->total_shipping_tax_excl;
                        $order_carrier->shipping_cost_tax_incl = (float)$order->total_shipping_tax_incl;
                        $order_carrier->add();
                    }
                }
            }

            // The country can only change if the address used for the calculation is the delivery address, and if multi-shipping is activated
            if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_delivery') {
                $this->context->country = $context_country;
            }

            if (!$this->context->country->active) {
                MpCash::addLog('[VALIDATEORDER] Country is not active', $id_cart);
                throw new PrestaShopException('The order address country is not active.');
            }

            MpCash::addLog('[VALIDATEORDER] Payment is about to be added', $id_cart);

            // Register Payment only if the order status validate the order
            if ($order_status->logable) {
                // $order is the last order loop in the foreach
                // The method addOrderPayment of the class Order make a create a paymentOrder
                // linked to the order reference and not to the order id
                if (!isset($order) || !Validate::isLoadedObject($order) || !$order->addOrderPayment($amount_paid, null, $transaction_id)) {
                    MpCash::addLog('[VALIDATEORDER] Cannot save Order Payment', $id_cart);
                    throw new PrestaShopException('Can\'t save Order Payment');
                }
            }

            // Next !
            $only_one_gift = false;
            $cart_rule_used = array();
            $products = $this->context->cart->getProducts();

            // Make sure CartRule caches are empty
            CartRule::cleanCache();
            foreach ($order_detail_list as $key => $order_detail) {
                /** @var OrderDetail $order_detail */

                $order = $order_list[$key];
                if (!$order_creation_failed && isset($order->id)) {
                    if (!$secure_key) {
                        $message .= '<br />'.Tools::displayError('Warning: the secure key is empty, check your payment account before validation');
                    }
                    // Optional message to attach to this order
                    if (isset($message) & !empty($message)) {
                        $msg = new Message();
                        $message = strip_tags($message, '<br>');
                        if (Validate::isCleanHtml($message)) {
                            MpCash::addLog('[VALIDATEORDER] Message is about to be added', $id_cart);
                            $msg->message = $message;
                            $msg->id_cart = (int)$id_cart;
                            $msg->id_customer = (int)($order->id_customer);
                            $msg->id_order = (int)$order->id;
                            $msg->private = 1;
                            $msg->add();
                        }
                    }

                    // Insert new Order detail list using cart for the current order
                    //$orderDetail = new OrderDetail(null, null, $this->context);
                    //$orderDetail->createList($order, $this->context->cart, $id_order_state);

                    // Construct order detail table for the email
                    $products_list = '';
                    $virtual_product = true;

                    $product_var_tpl_list = array();
                    foreach ($order->product_list as $product) {
                        $price = Product::getPriceStatic((int)$product['id_product'], false, ($product['id_product_attribute'] ? (int)$product['id_product_attribute'] : null), 6, null, false, true, $product['cart_quantity'], false, (int)$order->id_customer, (int)$order->id_cart, (int)$order->{Configuration::get('PS_TAX_ADDRESS_TYPE')});
                        $price_wt = Product::getPriceStatic((int)$product['id_product'], true, ($product['id_product_attribute'] ? (int)$product['id_product_attribute'] : null), 2, null, false, true, $product['cart_quantity'], false, (int)$order->id_customer, (int)$order->id_cart, (int)$order->{Configuration::get('PS_TAX_ADDRESS_TYPE')});

                        $product_price = Product::getTaxCalculationMethod() == PS_TAX_EXC ? Tools::ps_round($price, 2) : $price_wt;

                        $product_var_tpl = array(
                            'reference' => $product['reference'],
                            'name' => $product['name'].(isset($product['attributes']) ? ' - '.$product['attributes'] : ''),
                            'unit_price' => Tools::displayPrice($product_price, $this->context->currency, false),
                            'price' => Tools::displayPrice($product_price * $product['quantity'], $this->context->currency, false),
                            'quantity' => $product['quantity'],
                            'customization' => array()
                        );

                        $customized_datas = Product::getAllCustomizedDatas((int)$order->id_cart);
                        if (isset($customized_datas[$product['id_product']][$product['id_product_attribute']])) {
                            $product_var_tpl['customization'] = array();
                            foreach ($customized_datas[$product['id_product']][$product['id_product_attribute']][$order->id_address_delivery] as $customization) {
                                $customization_text = '';
                                if (isset($customization['datas'][Product::CUSTOMIZE_TEXTFIELD])) {
                                    foreach ($customization['datas'][Product::CUSTOMIZE_TEXTFIELD] as $text) {
                                        $customization_text .= $text['name'].': '.$text['value'].'<br />';
                                    }
                                }

                                if (isset($customization['datas'][Product::CUSTOMIZE_FILE])) {
                                    $customization_text .= sprintf(Tools::displayError('%d image(s)'), count($customization['datas'][Product::CUSTOMIZE_FILE])).'<br />';
                                }

                                $customization_quantity = (int)$product['customization_quantity'];

                                $product_var_tpl['customization'][] = array(
                                    'customization_text' => $customization_text,
                                    'customization_quantity' => $customization_quantity,
                                    'quantity' => Tools::displayPrice($customization_quantity * $product_price, $this->context->currency, false)
                                );
                            }
                        }

                        $product_var_tpl_list[] = $product_var_tpl;
                        // Check if is not a virutal product for the displaying of shipping
                        if (!$product['is_virtual']) {
                            $virtual_product &= false;
                        }
                    } // end foreach ($products)

                    $product_list_txt = '';
                    $product_list_html = '';
                    /*
                    if (count($product_var_tpl_list) > 0) {
                        $product_list_txt = $this->getEmailTemplateContent('order_conf_product_list.txt', Mail::TYPE_TEXT, $product_var_tpl_list);
                        $product_list_html = $this->getEmailTemplateContent('order_conf_product_list.tpl', Mail::TYPE_HTML, $product_var_tpl_list);
                    }
                    */
                    
                    $cart_rules_list = array();
                    $total_reduction_value_ti = 0;
                    $total_reduction_value_tex = 0;
                    foreach ($cart_rules as $cart_rule) {
                        $package = array('id_carrier' => $order->id_carrier, 'id_address' => $order->id_address_delivery, 'products' => $order->product_list);
                        $values = array(
                            'tax_incl' => $cart_rule['obj']->getContextualValue(true, $this->context, CartRule::FILTER_ACTION_ALL_NOCAP, $package),
                            'tax_excl' => $cart_rule['obj']->getContextualValue(false, $this->context, CartRule::FILTER_ACTION_ALL_NOCAP, $package)
                        );

                        // If the reduction is not applicable to this order, then continue with the next one
                        if (!$values['tax_excl']) {
                            continue;
                        }

                        // IF
                        //	This is not multi-shipping
                        //	The value of the voucher is greater than the total of the order
                        //	Partial use is allowed
                        //	This is an "amount" reduction, not a reduction in % or a gift
                        // THEN
                        //	The voucher is cloned with a new value corresponding to the remainder
                        if (count($order_list) == 1 && $values['tax_incl'] > ($order->total_products_wt - $total_reduction_value_ti) && $cart_rule['obj']->partial_use == 1 && $cart_rule['obj']->reduction_amount > 0) {
                            // Create a new voucher from the original
                            $voucher = new CartRule((int)$cart_rule['obj']->id); // We need to instantiate the CartRule without lang parameter to allow saving it
                            unset($voucher->id);

                            // Set a new voucher code
                            $voucher->code = empty($voucher->code) ? substr(md5($order->id.'-'.$order->id_customer.'-'.$cart_rule['obj']->id), 0, 16) : $voucher->code.'-2';
                            if (preg_match('/\-([0-9]{1,2})\-([0-9]{1,2})$/', $voucher->code, $matches) && $matches[1] == $matches[2]) {
                                $voucher->code = preg_replace('/'.$matches[0].'$/', '-'.(intval($matches[1]) + 1), $voucher->code);
                            }

                            // Set the new voucher value
                            if ($voucher->reduction_tax) {
                                $voucher->reduction_amount = ($total_reduction_value_ti + $values['tax_incl']) - $order->total_products_wt;

                                // Add total shipping amout only if reduction amount > total shipping
                                if ($voucher->free_shipping == 1 && $voucher->reduction_amount >= $order->total_shipping_tax_incl) {
                                    $voucher->reduction_amount -= $order->total_shipping_tax_incl;
                                }
                            } else {
                                $voucher->reduction_amount = ($total_reduction_value_tex + $values['tax_excl']) - $order->total_products;

                                // Add total shipping amout only if reduction amount > total shipping
                                if ($voucher->free_shipping == 1 && $voucher->reduction_amount >= $order->total_shipping_tax_excl) {
                                    $voucher->reduction_amount -= $order->total_shipping_tax_excl;
                                }
                            }
                            if ($voucher->reduction_amount <= 0) {
                                continue;
                            }

                            if ($this->context->customer->isGuest()) {
                                $voucher->id_customer = 0;
                            } else {
                                $voucher->id_customer = $order->id_customer;
                            }

                            $voucher->quantity = 1;
                            $voucher->reduction_currency = $order->id_currency;
                            $voucher->quantity_per_user = 1;
                            $voucher->free_shipping = 0;
                            if ($voucher->add()) {
                                // If the voucher has conditions, they are now copied to the new voucher
                                CartRule::copyConditions($cart_rule['obj']->id, $voucher->id);

                                $params = array(
                                    '{voucher_amount}' => Tools::displayPrice($voucher->reduction_amount, $this->context->currency, false),
                                    '{voucher_num}' => $voucher->code,
                                    '{firstname}' => $this->context->customer->firstname,
                                    '{lastname}' => $this->context->customer->lastname,
                                    '{id_order}' => $order->reference,
                                    '{order_name}' => $order->getUniqReference()
                                );
                                Mail::Send(
                                    (int)$order->id_lang,
                                    'voucher',
                                    sprintf(Mail::l('New voucher for your order %s', (int)$order->id_lang), $order->reference),
                                    $params,
                                    $this->context->customer->email,
                                    $this->context->customer->firstname.' '.$this->context->customer->lastname,
                                    null, null, null, null, _PS_MAIL_DIR_, false, (int)$order->id_shop
                                );
                            }

                            $values['tax_incl'] = $order->total_products_wt - $total_reduction_value_ti;
                            $values['tax_excl'] = $order->total_products - $total_reduction_value_tex;
                        }
                        $total_reduction_value_ti += $values['tax_incl'];
                        $total_reduction_value_tex += $values['tax_excl'];

                        $order->addCartRule($cart_rule['obj']->id, $cart_rule['obj']->name, $values, 0, $cart_rule['obj']->free_shipping);

                        if ($id_order_state != Configuration::get('PS_OS_ERROR') && $id_order_state != Configuration::get('PS_OS_CANCELED') && !in_array($cart_rule['obj']->id, $cart_rule_used)) {
                            $cart_rule_used[] = $cart_rule['obj']->id;

                            // Create a new instance of Cart Rule without id_lang, in order to update its quantity
                            $cart_rule_to_update = new CartRule((int)$cart_rule['obj']->id);
                            $cart_rule_to_update->quantity = max(0, $cart_rule_to_update->quantity - 1);
                            $cart_rule_to_update->update();
                        }

                        $cart_rules_list[] = array(
                            'voucher_name' => $cart_rule['obj']->name,
                            'voucher_reduction' => ($values['tax_incl'] != 0.00 ? '-' : '').Tools::displayPrice($values['tax_incl'], $this->context->currency, false)
                        );
                    }

                    $cart_rules_list_txt = '';
                    $cart_rules_list_html = '';
                    /*
                    if (count($cart_rules_list) > 0) {
                        $cart_rules_list_txt = $this->getEmailTemplateContent('order_conf_cart_rules.txt', Mail::TYPE_TEXT, $cart_rules_list);
                        $cart_rules_list_html = $this->getEmailTemplateContent('order_conf_cart_rules.tpl', Mail::TYPE_HTML, $cart_rules_list);
                    }
                     * 
                     */

                    // Specify order id for message
                    $old_message = Message::getMessageByCartId((int)$this->context->cart->id);
                    if ($old_message && !$old_message['private']) {
                        $update_message = new Message((int)$old_message['id_message']);
                        $update_message->id_order = (int)$order->id;
                        $update_message->update();

                        // Add this message in the customer thread
                        $customer_thread = new CustomerThread();
                        $customer_thread->id_contact = 0;
                        $customer_thread->id_customer = (int)$order->id_customer;
                        $customer_thread->id_shop = (int)$this->context->shop->id;
                        $customer_thread->id_order = (int)$order->id;
                        $customer_thread->id_lang = (int)$this->context->language->id;
                        $customer_thread->email = $this->context->customer->email;
                        $customer_thread->status = 'open';
                        $customer_thread->token = Tools::passwdGen(12);
                        $customer_thread->add();

                        $customer_message = new CustomerMessage();
                        $customer_message->id_customer_thread = $customer_thread->id;
                        $customer_message->id_employee = 0;
                        $customer_message->message = $update_message->message;
                        $customer_message->private = 0;

                        
                        if (!$customer_message->add()) {
                            $this->errors[] = Tools::displayError('An error occurred while saving message');
                        }
                        
                    }
                    MpCash::addLog('[VALIDATEORDER] Hook validateOrder is about to be called', $id_cart);

                    // Hook validate order
                    Hook::exec('actionValidateOrder', array(
                        'cart' => $this->context->cart,
                        'order' => $order,
                        'customer' => $this->context->customer,
                        'currency' => $this->context->currency,
                        'orderStatus' => $order_status
                    ));

                    foreach ($this->context->cart->getProducts() as $product) {
                        if ($order_status->logable) {
                            ProductSale::addProductSale((int)$product['id_product'], (int)$product['cart_quantity']);
                        }
                    }
                    MpCash::addLog('[VALIDATEORDER] Order Status is about to be added', $id_cart);
                    
                    // Set the order status
                    $new_history = new OrderHistory();
                    $new_history->id_order = (int)$order->id;
                    $new_history->changeIdOrderState((int)$id_order_state, $order, true);
                    $new_history->addWithemail(true, $extra_vars);

                    // Switch to back order if needed
                    if (Configuration::get('PS_STOCK_MANAGEMENT') && ($order_detail->getStockState() || $order_detail->product_quantity_in_stock <= 0)) {
                        $history = new OrderHistory();
                        $history->id_order = (int)$order->id;
                        $history->changeIdOrderState(Configuration::get($order->valid ? 'PS_OS_OUTOFSTOCK_PAID' : 'PS_OS_OUTOFSTOCK_UNPAID'), $order, true);
                        $history->addWithemail();
                    }

                    unset($order_detail);

                    // Order is reloaded because the status just changed
                    $order = new Order((int)$order->id);
                    
                    MpCash::addLog('[VALIDATEORDER] Finalize payment method', $id_cart);
                    $fees = new ClassCashFee();
                    $fees->create($order->id);
                    if (empty($transaction_id)) {
                        MpCash::addLog('[VALIDATEORDER] Transaction id is empty. Generating a new one.', $id_cart);
                        $transaction_id = $fees->generateTransactionId();
                    }
                    
                    $fees->insert();
                    $fees->saveSession();
                    $this->updateOrder($order->id, $id_cart);
                    $this->updateOrderPayment($order->id, $transaction_id, $id_cart);
                    if($order->invoice_number) {
                        $this->updateInvoice($order->id, $id_cart);
                    }
                    
                    //Send notification email
                    $customer = new CustomerCore($order->id_customer);
                    $this->sendNotification($this->context->cart, $order, $customer, $fees, $transaction_id, $id_cart);
                    $this->sendCustomerEmail($this->context->cart, $order, $customer, $fees, $transaction_id, $id_cart);
                    

                    // updates stock in shops
                    if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT')) {
                        $product_list = $order->getProducts();
                        foreach ($product_list as $product) {
                            // if the available quantities depends on the physical stock
                            if (StockAvailable::dependsOnStock($product['product_id'])) {
                                // synchronizes
                                StockAvailable::synchronize($product['product_id'], $order->id_shop);
                            }
                        }
                    }

                    $order->updateOrderDetailTax();
                } else {
                    $error = Tools::displayError('Order creation failed');
                    MpCash::addLog('[VALIDATEORDER] error: ' . $error, $id_cart);
                    die($error);
                }
            } // End foreach $order_detail_list

            // Use the last order as currentOrder
            if (isset($order) && $order->id) {
                $this->currentOrder = (int)$order->id;
            }
            
            MpCash::addLog('[VALIDATEORDER] End of Function', $id_cart);
            
            return true;
        } else {
            $error = Tools::displayError('Cart cannot be loaded or an order has already been placed using this cart');
            MpCash::addLog('[VALIDATEORDER] error: ' . $error, $id_cart);
            die($error);
        }
    }
    
    public function _updateOrder($id_order, $id_cart)
    {
        if ((int)$id_order == 0) {
            MpCash::addLog('[UPDATEORDER] No id order set.', $id_cart);
            return false; 
        }
        
        $classFee = new ClassCashFee();
        $classFee->getSession();
        
        $order = new OrderCore($id_order);
        $totprod = (float)$order->getTotalProductsWithTaxes();
        //PrestaShopLoggerCore::addLog('+++++++++++++++++++++++++++++++++++++');        
        //PrestaShopLoggerCore::addLog('totprod: ' . $totprod);        
        $shipping = $order->getShipping();
        //PrestaShopLoggerCore::addLog('shipping: ' . print_r($shipping, 1));
        $totshipping = 0;
        foreach ($shipping as $ship) {
            //PrestaShopLoggerCore::addLog('shipping :' . $ship['shipping_cost_tax_incl']);
            $totshipping += $ship['shipping_cost_tax_incl'];
        }
        $totfees = (float)$classFee->getFeeTaxIncl();
        //PrestaShopLoggerCore::addLog('Fees: ' . $totfees);
        $totorder = number_format($totprod + $totshipping + $totfees,2);
        //PrestaShopLoggerCore::addLog('totorder: ' . $totorder);
        $totorder_notax = number_format($totorder / 1.22, 2);
        //PrestaShopLoggerCore::addLog('tax: ' . $tax);
        $tax = $totorder - $totorder_notax;
        //PrestaShopLoggerCore::addLog('totorder notax: ' . $totorder_notax);
                
        $db=Db::getInstance();
        $result = $db->update(
                'orders',
                array(
                    'total_paid' => number_format((float)$totorder,6,'.',''),
                    'total_paid_real' => number_format((float)$totorder,6,'.',''),
                    'total_paid_tax_incl' => number_format((float)$totorder,6,'.',''),
                    'total_paid_tax_excl' => number_format((float)$totorder_notax,6,'.',''),
                ),
                'id_order = ' . (int)$id_order);
        return $result;
    }
	
    public function updateOrder($id_order, $id_cart)
    {
        if ((int)$id_order == 0) {
            MpCash::addLog('[UPDATEORDER] No id order set.', $id_cart);
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
    
    public function updateInvoice($id_order, $id_cart)
    {
        if ((int)$id_order == 0) {
            MpCash::addLog('[UPDATEINVOICE] No id order set.', $id_cart);
            return false; 
        }
        
        $payment = new ClassCashFee();
        $payment->getSession();
        
        $db=Db::getInstance();
        $result = $db->update(
                'order_invoice',
                array(
                    'total_paid_tax_incl' => number_format((float)$payment->getTotalDocumentTaxIncl(),6),
                    'total_paid_tax_excl' => number_format((float)$payment->getTotalDocumentTaxExcl(),6),
                ),
                'id_order = ' . (int)$id_order);
        return $result;
    }
    
    public function updateOrderPayment($id_order, $transaction_id = '', $id_cart = 0)
    {
        if ((int)$id_order == 0) {
            MpCash::addLog('[UPDATEORDERPAYMENT] No id order set.', $id_cart);
            return false; 
        }
        
        $payment = new ClassCashFee();
        $payment->getSession();
        $id_order_payment = $payment->getIdOrderPayment($id_order);
        $id_order_invoice = $payment->getIdOrderInvoice($id_order);
        
        if ((int)$id_order_payment==0) {
            MpCash::addLog('[UPDATEORDERPAYMENT] No payment found for order ' . (int)$id_order, $id_cart);
            return false;
        }
        $orderPayment = new OrderPaymentCore($id_order_payment);
        
        $orderPayment->amount = number_format($payment->getTotalDocumentTaxIncl(),2);
        $orderPayment->payment_method = $this->module->l('Cash', 'ClassMpCashPayment');
        if (!empty($transaction_id)) {
            $orderPayment->transaction_id = $payment->getTransactionId();
        }
        $result = $orderPayment->update();
        $payment->setInvoicePayment($id_order_invoice,$id_order_payment,$id_order);
        
        return $result;
    }
    
    public function updateOrderPaymentModule($id_order, $payment_method, $id_cart = 0)
    {
        if ((int)$id_order == 0) {
            MpCash::addLog('[UPDATEORDERPAYMENTMODULE] No id order found.', $id_cart);
            return false; 
        }
        
        if (empty($payment_method)) {
            MpCash::addLog('[UPDATEORDERPAYMENTMODULE] No payment method found.', $id_cart);
            return false; 
        }
        
        $order = new OrderCore($id_order);
        $order->module = $payment_method;
        $result =  $order->update();
        return $result;
    }
    
    /**
     * 
     * @param Cart $cart
     * @param OrderCore $order
     * @param CustomerCore $customer
     * @param ClassCashFee $classFee
     * @param string $transaction_id
     */
    private function sendNotification(
            $cart,
            OrderCore $order,
            CustomerCore $customer,
            $classFee,
            $transaction_id,
            $id_cart = 0 
            )
    {
        //Send email notifications
        
        if (empty($classFee)) {
            $classFee = new ClassCashFee();
            $classFee->create((int)$order->id);
            MpCash::addLog('[SENDNOTIFICATION] SendNotification: recalculate order', $id_cart);
        }
        
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
            $voucher['value'] = number_format($cart_rule['value'], 2);
            $vouchers[] = $voucher;
        }
        
        
        $shop = new ShopCore(ContextCore::getContext()->shop->id);
        $shop_email = strval(Configuration::get('PS_SHOP_EMAIL'));
        $shop_name = strval(ConfigurationCore::get('PS_SHOP_NAME'));
        
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
                    'transaction_id' => $transaction_id,
                    'logo' => _PS_BASE_URL_ . $shop->physical_uri .  'img/' . ConfigurationCore::get('PS_LOGO', null, null, $shop->id),
                )
            );
        $message = $smarty->fetch(MP_CASH_TEMPLATES_FRONT . 'email_notification.tpl');
        
        MpCash::addLog('[SENDNOTIFICATION] Sending email to all recipes.', $id_cart);
        
        foreach ($arrEmails as $email)
        {
            $to = $email;
            $subject = '['  .  Tools::strtoupper($shop_name) . ' ]' 
                    . $this->module->l('Cash payment method notification', 'ClassMpCashPayment');
            
            $headers = "From: " . $shop_email . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=latin-1\r\n";
            
            mail($to, $subject, $message, $headers);
        }
    }
    
    /**
     * 
     * @param Cart $cart
     * @param OrderCore $order
     * @param CustomerCore $customer
     * @param ClassCashFee $classFee
     * @param string $transaction_id
     */
    private function sendCustomerEmail(
            $cart,
            OrderCore $order,
            CustomerCore $customer,
            $classFee,
            $transaction_id,
            $id_cart = 0
            )
    {
        //Send email notifications
        $smarty = Context::getContext()->smarty;
        $db = Db::getInstance();
        $email = $customer->email;
        
        if (empty($classFee)) {
            $classFee = new ClassCashFee();
            $classFee->create((int)$order->id);
            MpCash::addLog('[SENDCUSTOMEREMAIL] recalculate order', $id_cart);
        }
        
        // Join PDF invoice
        if ((int)Configuration::get('PS_INVOICE') && $order->invoice_number) {
            $order_invoice_list = $order->getInvoicesCollection();
            Hook::exec('actionPDFInvoiceRender', array('order_invoice_list' => $order_invoice_list));
            $pdf = new PDFCore($order_invoice_list, PDF::TEMPLATE_INVOICE, $this->context->smarty);
            $content = $pdf->render(false);
            $file_attachement['content'] = chunk_split(base64_encode($content));  
            $file_attachement['name'] = Configuration::get('PS_INVOICE_PREFIX', (int)$order->id_lang, null, $order->id_shop).sprintf('%06d', $order->invoice_number).'.pdf';
            $file_attachement['mime'] = 'application/pdf';
            
            MpCash::addLog('[SENDCUSTOMEREMAIL] Cash Attachment: ' . print_r($file_attachement, 1), $id_cart);
        } else {
            $file_attachement = null;
        }
        
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
            $voucher['value'] = number_format($cart_rule['value'], 2);
            $vouchers[] = $voucher;
        }
        
        $to = $email;
        $shop = new ShopCore(ContextCore::getContext()->shop->id);
        $shop_email = strval(Configuration::get('PS_SHOP_EMAIL'));
        $shop_name = strval(ConfigurationCore::get('PS_SHOP_NAME'));
        $uid = md5(uniqid(time()));
        
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
                    'transaction_id' => $transaction_id,
                    'shop' => $shop_name,
                    'logo' => _PS_BASE_URL_ . $shop->physical_uri .  'img/' . ConfigurationCore::get('PS_LOGO', null, null, $shop->id),
                )
            );
        $message = $smarty->fetch(MP_CASH_TEMPLATES_FRONT . 'email_customer.tpl');
        
        $subject = '['  .  Tools::strtoupper($shop_name) . ' ]' . $this->module->l('Order Confirmation', 'ClassMpCashPayment');
        
        // header
        $headers = "From: ".$shop_name." <".$shop_email.">\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"".$uid."\"\r\n\r\n";
        
        // message & attachment
        $nmessage = "--".$uid."\r\n";
        $nmessage .= "Content-Type: text/html; charset=latin-1\r\n";
        $nmessage .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $nmessage .= $message."\r\n\r\n";
        $nmessage .= "--".$uid."\r\n";
        if(!empty($file_attachement)) {
            $nmessage .= "Content-Type: application/octet-stream; name=\"".$file_attachement['name']."\"\r\n";
            $nmessage .= "Content-Transfer-Encoding: base64\r\n";
            $nmessage .= "Content-Disposition: attachment; filename=\"".$file_attachement['name']."\"\r\n\r\n";
            $nmessage .= $file_attachement['content']."\r\n\r\n";
            $nmessage .= "--".$uid."--";
        }

        mail($to, $subject, $nmessage, $headers);
        
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
}

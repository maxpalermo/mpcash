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

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR
        . 'classes' . DIRECTORY_SEPARATOR 
        . 'ClassCashFee.php';

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR
        . 'classes' . DIRECTORY_SEPARATOR 
        . 'ClassMpCashLogger.php';

class MpCash extends PaymentModule
{
    protected $config_form = false;
    private $dbValues;
    
    public function getModulePath()
    {
        return $this->_path;
    }
    
    public function getModuleUrl()
    {
        return $this->local_path;
    }
    
    public function __construct()
    {
        if (!defined('_PS_VERSION_')) {
            exit;
        }
        
        $this->name = 'mpcash';
        $this->tab = 'payments_gateways';
        $this->version = '1.1.0';
        $this->author = 'Digital Solutions®';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('MP Cash Payment method with fees');
        $this->description = $this->l('With this module you can add fees or discounts to you order when customers choose to pay with cash.');
        $this->limited_countries = array('IT','FR','EU');
        $this->confirmUninstall = $this->l('Are you sure to want to uninstall this module?');
        $this->dbValues = null;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        if (extension_loaded('curl') == false)
        {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        $iso_code = Country::getIsoById(Configuration::get('PS_COUNTRY_DEFAULT'));

        if (in_array($iso_code, $this->limited_countries) == false)
        {
            $this->_errors[] = $this->l('This module is not available in your country');
            return false;
        }

        include(dirname(__FILE__).'/sql/install.php');

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('displayAdminOrder') &&
            $this->registerHook('displayBeforePayment') &&
            $this->registerHook('displayOrderConfirmation') &&
            $this->registerHook('displayPayment') &&
            $this->registerHook('displayPaymentReturn') && 
            $this->registerHook('displayPaymentTop') &&
            $this->registerHook('actionPaymentCCAdd') &&
            $this->registerHook('actionPaymentConfirmation') &&
            $this->registerHook('actionValidateOrder') &&
            $this->registerHook('actionObjectAddAfter') && 
            $this->registerHook('actionObjectDeleteAfter') && 
            $this->registerHook('actionObjectUpdateAfter');
    }
    
    public function copyOverrides()
    {
        $ok = true; 
        
        $sources = array(
            'HTMLTemplateInvoice.php' => _PS_CLASS_DIR_ . 'pdf/',
            'PaymentModule.php' => _PS_CLASS_DIR_,
            'invoice.total-tab.tpl' => _PS_PDF_DIR_,
            'order_conf.html' => _PS_THEME_DIR_ . 'mails/it/',
            'order_conf.txt' => _PS_THEME_DIR_ . 'mails/it/',
            'order_conf.html' => _PS_MAIL_DIR_ . 'it/',
            'order_conf.txt' => _PS_MAIL_DIR_ . 'it/',
        );
        
        $path = dirname(__FILE__) . DIRECTORY_SEPARATOR 
                . 'overrides' . DIRECTORY_SEPARATOR 
                . 'modified' . DIRECTORY_SEPARATOR;
        
        foreach($sources as $key=>$value) {
            MpCash::addLog('copy override ' . $key . ' to ' . $value . $key);
            $result = copy($path . $key, $value . $key);
            if (!$result) {
                $this->_errors[] = 'Can\'t copy ' . $key;
                $ok = false;
            }
        }
        
        return $ok;
    }
    
    public function restoreOverrides()
    {
        $ok = true; 
        
        $sources = array(
            'HTMLTemplateInvoice.php' => _PS_CLASS_DIR_ . 'pdf/',
            'PaymentModule.php' => _PS_CLASS_DIR_,
            'invoice.total-tab.tpl' => _PS_PDF_DIR_,
            'order_conf.html' => _PS_THEME_DIR_ . 'mails/it/',
            'order_conf.txt' => _PS_THEME_DIR_ . 'mails/it/',
            'order_conf.html' => _PS_MAIL_DIR_ . 'it/',
            'order_conf.txt' => _PS_MAIL_DIR_ . 'it/',
        );
        
        $path = dirname(__FILE__) . DIRECTORY_SEPARATOR 
                . 'overrides' . DIRECTORY_SEPARATOR 
                . 'original' . DIRECTORY_SEPARATOR;
        
        foreach($sources as $key=>$value) {
            MpCash::addLog('restore override ' . $key . ' to ' . $value . $key);
            $result = copy($path . $key, $value . $key);
            if (!$result) {
                $this->_errors[] = 'Can\'t restore ' . $key;
                $ok = false;
            }
        }
        
        return $ok;
    }
    
    public function uninstall()
    {
        return parent::uninstall();
    }
    
    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (!(int)Tools::isSubmit('submitMpCashValues')) {
            $this->loadConfiguration();
        } else {
            $this->saveForm();
        }
        if(!isset($this->context)) {
            $this->context = ContextCore::getContext();
        }
        $this->context->smarty->assign('module_dir', $this->_path);
        $this->context->controller->addCSS($this->getModulePath() . 'views/css/front.css');
        $this->context->controller->addJqueryPlugin('chosen');
        $this->context->controller->addJS($this->getModulePath() . 'views/js/front.js');
        $this->context->controller->addJS($this->getModulePath() . 'views/js/getContent.js');

        $output = $this->context->smarty->fetch($this->getModulePath() . 'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitMpCashValues';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(
                array(
                    $this->getConfigForm($this->l('Cash configuration'))
                )
            );
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm($legend)
    {
        $currency = ContextCore::getContext()->currency->iso_code;
        
        return array(
            'form' => array(
                'legend' => array(
                'title' => $legend,
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Activate module?'),
                        'name' => 'input_switch_activate_module',
                        'is_bool' => true,
                        'desc' => $this->l('Display module in payment methods.'),
                        'values' => array(
                            array(
                                'id' => 'input_switch_activate_module_on',
                                'value' => true,
                                'label' => $this->l('YES')
                            ),
                            array(
                                'id' => 'input_switch_activate_module_off',
                                'value' => false,
                                'label' => $this->l('NO')
                            )
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Select fee type:'),
                        'desc' => $this->l('Choose a fee type'),
                        'name' => 'input_select_fee_type',
                        'required' => true,
                        'options' => array(
                            'query' => array(
                                array(
                                  'key' => 1,       
                                  'value' => $this->l('Fixed amount'),
                                ),
                                array(
                                  'key' => 2,
                                  'value' => $this->l('Percent'),
                                ),
                                array(
                                  'key' => 3,
                                  'value' => $this->l('Percent + Fixed amount'),
                                ),
                                array(
                                  'key' => 4,
                                  'value' => $this->l('Discount'),
                                ),
                            ),
                        'id' => 'key',
                        'name' => 'value'
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-chevron-right"></i>',
                        'suffix' => '%',
                        'desc' => $this->l('Enter discount percent'),
                        'name' => 'input_text_discount',
                        'label' => $this->l('Discount'),
                        'class' => 'text-right input fixed-width-sm'
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-chevron-right"></i>',
                        'suffix' => $currency,
                        'desc' => $this->l('Enter fixed amount'),
                        'name' => 'input_text_fee_fixed',
                        'label' => $this->l('Fixed fee to add to total order.'),
                        'class' => 'text-right input fixed-width-sm'
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-chevron-right"></i>',
                        'suffix' => '%',
                        'desc' => $this->l('Enter percent amount'),
                        'name' => 'input_text_fee_percent',
                        'label' => $this->l('Percent fee to add to total order.'),
                        'class' => 'text-right input fixed-width-sm'
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-chevron-right"></i>',
                        'suffix' => $currency,
                        'desc' => $this->l('Enter minimum fee'),
                        'name' => 'input_text_fee_min',
                        'label' => $this->l('Minimum fee to apply to total order.'),
                        'class' => 'text-right input fixed-width-sm'
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-chevron-right"></i>',
                        'suffix' => $currency,
                        'desc' => $this->l('Enter maximum fee'),
                        'name' => 'input_text_fee_max',
                        'label' => $this->l('Maximum fee to apply to total order.'),
                        'class' => 'text-right input fixed-width-sm'
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-chevron-right"></i>',
                        'suffix' => $currency,
                        'desc' => $this->l('Enter minimum order'),
                        'name' => 'input_text_order_min',
                        'label' => $this->l('Minimum order to apply fees.'),
                        'class' => 'text-right input fixed-width-sm'
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-chevron-right"></i>',
                        'suffix' => $currency,
                        'desc' => $this->l('Enter maximum order'),
                        'name' => 'input_text_order_max',
                        'label' => $this->l('Maximum order to apply fees.'),
                        'class' => 'text-right input fixed-width-sm'
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-chevron-right"></i>',
                        'suffix' => $currency,
                        'desc' => $this->l('Enter minimum order to display'),
                        'name' => 'input_text_order_min_display',
                        'label' => $this->l('Minimum order to display this payment method.'),
                        'class' => 'text-right input fixed-width-sm'
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-chevron-right"></i>',
                        'suffix' => $currency,
                        'desc' => $this->l('Enter maximum order to display'),
                        'name' => 'input_text_order_max_display',
                        'label' => $this->l('Maximum order to display this payment method.'),
                        'class' => 'text-right input fixed-width-sm'
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Tax included?'),
                        'name' => 'input_switch_tax_included',
                        'is_bool' => true,
                        'desc' => $this->l('Prices include taxes?'),
                        'values' => array(
                            array(
                                'id' => 'input_switch_tax_included_on',
                                'value' => true,
                                'label' => $this->l('YES')
                            ),
                            array(
                                'id' => 'input_switch_tax_included_off',
                                'value' => false,
                                'label' => $this->l('NO')
                            )
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Select tax:'),
                        'desc' => $this->l('Choose a tax'),
                        'name' => 'input_select_tax_rate',
                        'required' => true,
                        'options' => array(
                            'query' => $this->getTaxList(),
                            'id' => 'key',
                            'name' => 'value'
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Force carrier:'),
                        'desc' => $this->l('This option force the order to be sent by selected carrier'),
                        'name' => 'input_select_carriers',
                        'required' => true,
                        'options' => array(
                            'query' => $this->getListCarriers(),
                            'id' => 'key',
                            'name' => 'value'
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Exclude categories:'),
                        'desc' => $this->l('If an order iclude these product categories, this module will not be displayed.'),
                        'name' => 'input_select_multiple_categories[]',
                        'required' => true,
                        'options' => array(
                            'query' => $this->getListCategories(),
                            'id' => 'key',
                            'name' => 'value'
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'hidden',
                        'name' => 'input_select_multiple_categories_hidden',
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Exclude manufacturers:'),
                        'desc' => $this->l('If an order iclude these manufacturers, this module will not be displayed.'),
                        'name' => 'input_select_multiple_manufacturers[]',
                        'required' => true,
                        'options' => array(
                            'query' => $this->getListManufacturers(),
                            'id' => 'key',
                            'name' => 'value'
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'hidden',
                        'name' => 'input_select_multiple_manufacturers_hidden',
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Exclude suppliers:'),
                        'desc' => $this->l('If an order iclude these suppliers, this module will not be displayed.'),
                        'name' => 'input_select_multiple_suppliers[]',
                        'required' => true,
                        'options' => array(
                            'query' => $this->getListSuppliers(),
                            'id' => 'key',
                            'name' => 'value'
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'hidden',
                        'name' => 'input_select_multiple_suppliers_hidden',
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Exclude products:'),
                        'desc' => $this->l('If an order iclude these products, this module will not be displayed.'),
                        'name' => 'input_select_multiple_products[]',
                        'required' => true,
                        'options' => array(
                            'query' => $this->getListProducts(),
                            'id' => 'key',
                            'name' => 'value'
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'hidden',
                        'name' => 'input_select_multiple_products_hidden',
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Select id order state:'),
                        'desc' => $this->l('If selected, order state will be updated.'),
                        'name' => 'input_select_id_order_state',
                        'required' => true,
                        'options' => array(
                            'query' => $this->getListOrderState(),
                            'id' => 'key',
                            'name' => 'value'
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'textarea',
                        'desc' => $this->l('Insert Google script for conversion rate'),
                        'name' => 'input_textarea_google_script',
                        'label' => $this->l('Gogle script:'),
                        'class' => 'text-left input fixed-width-xxl',
                        'rows' => 10,
                        'cols' => 80,
                    ),
                    array(
                        'col' => 3,
                        'type' => 'textarea',
                        'desc' => $this->l('Insert one email per row.'),
                        'name' => 'input_textarea_email_notification',
                        'label' => $this->l('Email notifications:'),
                        'class' => 'text-left input fixed-width-xxl',
                        'rows' => 10,
                        'cols' => 80,
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }
    
    /**
     * Save form data.
     */
    protected function saveForm()
    {
        if (Tools::isSubmit('submitMpCashValues')) {
            //GET VALUES FROM INPUT
            $arrayValues = $this->getArrayInputValues();
            $values = Tools::getAllValues();
            
            foreach ($arrayValues as $key=>$value) {
                if (Tools::strpos($key, '[]')!==false) {
                    $key2 = Tools::str_replace_once('[]', '', $key);
                } else {
                    $key2 = $key;
                }
                if(isset($values[$key2])) {
                    $arrayValues[$key] = $values[$key2];
                }
            }
            
            $db = Db::getInstance();
            $result = $db->insert(
                    'mp_advpayment_configuration', 
                    array(                   
                        'fee_type' => $values['input_select_fee_type'],
                        'fee_amount' => $values['input_text_fee_fixed'],
                        'fee_percent' => $values['input_text_fee_percent'],
                        'fee_min' => $values['input_text_fee_min'],
                        'fee_max' => $values['input_text_fee_max'],
                        'order_min' => $values['input_text_order_min'],
                        'order_max' => $values['input_text_order_max'],
                        'order_free' => $values['input_text_order_free'],
                        'order_min_display' => $values['input_text_order_min_display'],
                        'order_max_display' => $values['input_text_order_max_display'],
                        'discount' => $values['input_text_discount'],
                        'tax_included' => $values['input_switch_tax_included'],
                        'tax_rate' => $values['input_select_tax_rate'],
                        'carriers' => $values['input_select_carriers'],
                        'categories' => implode(',', $arrayValues['input_select_multiple_categories[]']),
                        'manufacturers' => implode(',', $arrayValues['input_select_multiple_manufacturers[]']),
                        'suppliers' => implode(',', $arrayValues['input_select_multiple_suppliers[]']),
                        'products' => implode(',', $arrayValues['input_select_multiple_products[]']),
                        'id_order_state' => $values['input_select_id_order_state'],
                        'payment_method' => 'cash',
                        'is_active' =>  $values['input_switch_activate_module'],
                    ),
                    false,
                    false,
                    Db::REPLACE
                );
                ConfigurationCore::updateValue(
                    'MP_CASH_GOOGLE_SCRIPT', 
                    htmlspecialchars($values['input_textarea_google_script'])
                    );
                ConfigurationCore::updateValue(
                    'MP_CASH_EMAIL_NOTIFICATION', 
                    htmlspecialchars($values['input_textarea_email_notification'])
                    );
            if ($result) {
                $this->context->smarty->assign('message', $this->displayConfirmation($this->l('Configuration saved successfully')));
            } else {
                $this->context->smarty->assign('message', $this->displayError($this->l('ERROR:: Configuration not saved')));
            }
        }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJqueryPlugin('chosen');
            $this->context->controller->addJS($this->_path.'views/js/getContent.js');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'/views/js/front.js');
            $this->context->controller->addCSS($this->_path.'/views/css/front.css');
        }
    }
    
    /**
     * Show payment button
     * @return html Page to display
     */
    public function hookDisplayPayment()
    {   
		$db=Db::getInstance();
        $sql = new DbQueryCore();
        $sql->select('is_active, order_min_display, order_max_display')
                ->from('mp_advpayment_configuration')
                ->where('payment_method = \'cash\'');
        $row = $db->getRow($sql);
        $id_cart = (int)$this->context->cart->id;
        $cart = new Cart($id_cart);        
        $total_cart = $cart->getOrderTotal();
        
        if ((int)$row['is_active']==0) {
            //Module is not active, nothing to display
            return false;
        }
        
        if((float)$row['order_min_display']>(float)$total_cart) {
            //Total cart is less than minimum, nothing to display
            return false;
        }
        
        if((float)$row['order_max_display']!=0 && (float)$row['order_max_display']<(float)$total_cart) {
            //Total cart is greater than maximum, nothing to display
            return false;
        }
        
		if (!$this->checkProducts()) {
			return false;
		}
		
        $link = new LinkCore();
        $this->context->controller->addCSS($this->_path.'views/css/displayPayment.css');
        
        $classFee = new ClassCashFee();
        $classFee->create();
        $classFee->saveSession();
        $this->context->smarty->assign(
                array(
                    'total_cart' => $classFee->getTotalCartTaxIncl(),
                    'fees' => $classFee->getFeeTaxIncl(),
                    'total_to_pay' => $classFee->getTotalDocumentTaxIncl(),
                    'confirmationLink' => $link->getModuleLink($this->name, 'confirmation'),
                )
        );
        return $this->display(__FILE__, 'displayPayment.tpl');
    }
    
    /**
     * Displays the payment confirmation
     * @param array $params
     * @return html Page to display
     */
    public function hookDisplayPaymentReturn($params)
    {
        $fee = new ClassCashFee();
        $order = $params['objOrder'];
        $total_paid = $params['total_to_pay'];
        $fee->load($order->id);
        
        Context::getContext()->smarty->assign(
                array(
                    'status' => 'ok',
                    'total_paid' => $total_paid,
                    'transaction_id' => $fee->getTransactionId(),
                    'reference' => $order->reference,
                    'id_order' => $order->id,
                )
            );
        $fee->delSession();
        return $this->display(__FILE__, 'return.tpl');
    }
    
    public function hookDisplayAdminOrder($params)
    {
        $id_order = (int)Tools::getValue('id_order');
        $order = new OrderCore($id_order);
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        $sql->select('fee_tax_incl')
                ->from('mp_advpayment_fee')
                ->where('id_order = ' . (int)$id_order);
        $fee = (float)$db->getValue($sql);
        $link = new LinkCore();
        $module = $link->getModuleLink($this->name, 'ajaxDispatcher') . '.php';
        $ajax_fixOrder_url = str_replace('module/', 'modules/', $module);
        $token = Tools::encrypt($this->name);
        $this->context->smarty->assign(
            array(
                'fee_amount' => $fee,
                'ajax_fixOrder_url' => $ajax_fixOrder_url,
                'id_order' => $id_order,
                'token' => $token
            )
        );
        
        if ($order->module=='mpCash') {
            return $this->display(__FILE__, 'displayFixButton.tpl')
                . $this->display(__FILE__, 'displayFeeAmount.tpl');
        }
    }
    
	public function ajaxProcessFixOrder()
    {
        $token = Tools::getValue('token', '');
        $id_order = (int)Tools::getValue('id_order', 0);
        if (empty($token) || $token != Tools::encrypt($this->name)) {
            print Tools::jsonEncode(
                array(
                    'error' => true,
                    'msg' => $this->l('Token not valid.'),
                )
            );
            exit();
        }
        if ($id_order == 0) {
            print Tools::jsonEncode(
                array(
                    'error' => true,
                    'msg' => $this->l('Id order not valid.'),
                )
            );
            exit();
        }
        
        /**
         * SELECT ORDER
         */
        $order = new OrderCore($id_order);
        $discount_tax_excl = $order->total_discounts_tax_excl;
        $discount_tax_incl = $order->total_discounts_tax_incl;
        $shipping_tax_excl = $order->total_shipping_tax_excl;
        $shipping_tax_incl = $order->total_shipping_tax_incl;
        $wrapping_tax_excl = $order->total_wrapping_tax_excl;
        $wrapping_tax_incl = $order->total_wrapping_tax_incl;
        $products_tax_excl = $order->total_products;
        $products_tax_incl = $order->total_products_wt;
        $total_order_tax_excl = $products_tax_excl - $discount_tax_excl + $wrapping_tax_excl + $shipping_tax_excl;
        $total_order_tax_incl = $products_tax_incl - $discount_tax_incl + $wrapping_tax_incl + $shipping_tax_incl;
        PrestaShopLoggerCore::addLog('total_order_tax_excl: ' . $total_order_tax_excl);
        PrestaShopLoggerCore::addLog('total_order_tax_incl: ' . $total_order_tax_incl);
        
        /**
         * SELECT FEE
         */
        $total_fee_tax_excl = 0;
        $total_fee_tax_incl = 0;
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        $sql->select('fee_amount')
                ->select('fee_percent')
                ->select('tax_rate')
                ->select('tax_included')
                ->from('mp_advpayment_configuration')
                ->where('payment_method = \'' . pSQL('cash') . '\'');
        $fees = $db->getRow($sql);
        if (!$fees) {
            print Tools::jsonEncode(
                array(
                    'error' => true,
                    'msg' => $this->l('Error reading fee configuration.'),
                )
            );
            exit();
        }
        
        PrestaShopLoggerCore::addLog('fee_amount: ' . $fees['fee_amount']);
        PrestaShopLoggerCore::addLog('fee_percent: ' . $fees['fee_percent']);
        PrestaShopLoggerCore::addLog('tax_rate: ' . $fees['tax_rate']);
        PrestaShopLoggerCore::addLog('tax_included: ' . $fees['tax_included']);
        
        if ($fees['tax_included']) {
            $fee_tax = 1+($fees['tax_rate']/100);
            $fees['fee_amount_tax_excl'] = $fees['fee_amount'] / $fee_tax;
            $fees['fee_amount_tax_incl'] = $fees['fee_amount'];
            PrestaShopLoggerCore::addLog('amount_tax_incl: ' . $fees['fee_amount_tax_incl']);
            PrestaShopLoggerCore::addLog('amount_tax_excl: ' . $fees['fee_amount_tax_excl']);
        } else {
            $fee_tax = (100 + $fees['tax_rate']) / 100;
            $fees['fee_amount_tax_excl'] = $fees['fee_amount'];
            $fees['fee_amount_tax_incl'] = $fees['fee_amount'] * $fee_tax;
            PrestaShopLoggerCore::addLog('amount_tax_incl: ' . $fees['fee_amount_tax_incl']);
            PrestaShopLoggerCore::addLog('amount_tax_excl: ' . $fees['fee_amount_tax_excl']);
        }
        
        if ($fees['fee_percent']) {
            $total_fee_tax_excl = $total_order_tax_excl * $fees['fee_percent'] / 100;
            $total_fee_tax_incl = $total_order_tax_incl * $fees['fee_percent'] / 100;
            PrestaShopLoggerCore::addLog('fee_tax_incl: ' . $total_fee_tax_incl);
            PrestaShopLoggerCore::addLog('fee_tax_excl: ' . $total_fee_tax_excl);
        }
        
        if ($fees['fee_amount']) {
            $total_fee_tax_excl += $fees['fee_amount_tax_excl'];
            $total_fee_tax_incl += $fees['fee_amount_tax_incl'];
            PrestaShopLoggerCore::addLog('fee_tax_incl amount: ' . $total_fee_tax_incl);
            PrestaShopLoggerCore::addLog('fee_tax_excl amount: ' . $total_fee_tax_excl);
        }
        
        $total_paid_tax_excl = number_format($total_order_tax_excl + $total_fee_tax_excl, 6, '.', '');
        $total_paid_tax_incl = number_format($total_order_tax_incl + $total_fee_tax_incl, 6, '.', '');
        
        /**
         * UPDATE ORDER
         */
        $order->total_paid = $total_paid_tax_incl;
        $order->total_paid_real = $total_paid_tax_incl;
        $order->total_paid_tax_excl = $total_paid_tax_excl;
        $order->total_paid_tax_incl = $total_paid_tax_incl;
        $result = $order->update();
        if (!$result) {
           print Tools::jsonEncode(
                array(
                    'error' => true,
                    'msg' => $this->l('Error updating order.') . $db->getMsgError(),
                )
            );
            exit(); 
        }
        
        /**
         * UPDATE ORDER PAYMENT
         */
        $result = $db->update(
            'order_payment',
            array(
                'amount' => $total_paid_tax_incl,
            ),
            'order_reference = \'' . pSQL($order->reference) . '\''
        );
        if (!$result) {
           print Tools::jsonEncode(
                array(
                    'error' => true,
                    'msg' => $this->l('Error updating order payment.') . $db->getMsgError(),
                )
            );
            exit(); 
        }
        
        /**
         * UPDATE ORDER INVOICE
         */
        $result = $db->update(
            'order_invoice',
            array(
                'total_discount_tax_excl' => number_format($discount_tax_excl, 6, '.', ''),
                'total_discount_tax_incl' => number_format($discount_tax_incl, 6, '.', ''),
                'total_paid_tax_excl' => number_format($total_paid_tax_excl, 6, '.', ''),
                'total_paid_tax_incl' => number_format($total_paid_tax_incl, 6, '.', ''),
            ),
            'id_order = ' . (int)$order->id
        );
        if (!$result) {
           print Tools::jsonEncode(
                array(
                    'error' => true,
                    'msg' => $this->l('Error updating order invoice.') . $db->getMsgError(),
                )
            );
            exit(); 
        }
        
        /**
         * UPDATE FEES
         */
        $sql_fees = new DbQueryCore();
        $sql_fees->select(count('*'))
                ->from('mp_advpayment_fee')
                ->where('id_order = ' . (int)$order->id);
        $count = $db->getValue($sql_fees);
        $data = array(
            'id_order' => (int)$order->id,
            'total_paid_tax_excl' => number_format($total_order_tax_excl, 6, '.', ''),
            'total_paid_tax_incl' => number_format($total_order_tax_incl, 6, '.', ''),
            'fee_tax_excl' => number_format($total_fee_tax_excl, 6, '.', ''),
            'fee_tax_incl' => number_format($total_fee_tax_incl, 6, '.', ''),
            'fee_tax_rate' => (float)$fees['tax_rate'],
            'transaction_id' => '',
            'payment_method' => 'cash',
            'date_add' => date('y-m-d h:i:s'),
        );
        if ($count == 0) {
            $result = $db->insert(
                'mp_advpayment_fee',
                $data
            );
        } else {
            $result = $db->update(
                'mp_advpayment_fee',
                $data,
                'id_order = ' . (int)$id_order
            );
        }
        if (!$result) {
            print Tools::jsonEncode(
                array(
                    'error' => true,
                    'msg' => $this->l('Error updating order fees.') . $db->getMsgError(),
                )
            );
            exit(); 
        }
        print Tools::jsonEncode(
            array(
                'error' => false,
                'msg' => $this->l('Operation done.'),
            )
        );
        
        exit();
    }
	
	public function checkProducts()
    {
        $output = array();
        $db = Db::getInstance();
        $sql_config = new DbQueryCore();
        $sql_config->select('*')
                ->from('mp_advpayment_configuration')
                ->where('payment_method = \'cash\'');
        $config = $db->getRow($sql_config);
        if (!$config) {
            return true;
        }
        //check categories
        if ($config['categories'] != '0') {
            $qry = 'select id_product from ' . _DB_PREFIX_ . 'category_product where id_category in (' . $config['categories'] . ')';
            $result = $db->executeS($qry);
            if ($result) {
                //PrestaShopLoggerCore::addLog('product categories: ' . print_r($result, 1));
                foreach ($result as $row) {
                    $output[] = $row['id_product'];
                }
            }
        }
        //check manufacturers
        if ($config['manufacturers'] != '0') {
            $qry = 'select id_product from ' . _DB_PREFIX_ . 'product where id_manufacturer in (' . $config['manufacturers'] . ')';
            $result = $db->executeS($qry);
            if ($result) {
                //PrestaShopLoggerCore::addLog('product manufacturers: ' . print_r($result, 1));
                foreach ($result as $row) {
                    $output[] = $row['id_product'];
                }
            }
        }
        //check suppliers
        if ($config['suppliers'] != '0') {
            $qry = 'select id_product from ' . _DB_PREFIX_ . 'product_supplier where id_supplier in (' . $config['suppliers'] . ')';
            $result = $db->executeS($qry);
            if ($result) {
                //PrestaShopLoggerCore::addLog('product suppliers: ' . print_r($result, 1));
                foreach ($result as $row) {
                    $output[] = $row['id_product'];
                }
            }
        }
        //check products
        $id_products = explode(',', $config['products']);
        //PrestaShopLoggerCore::addLog('products excluded: ' . print_r($id_products, 1));
        foreach ($id_products as $id_product) {
            $output[] = (int)$id_product;
        }
        
        $id_cart = (int)Context::getContext()->cart->id;
        if (!$id_cart) {
            return false;
        }
        $sql_cart_products = new DbQueryCore();
        $sql_cart_products->select('id_product')
                ->from('cart_product')
                ->where('id_cart = ' . (int)$id_cart);
        $cart_products = $db->executeS($sql_cart_products);
        if (!$cart_products) {
            return false;
        }
        
        $excluded = array();
        foreach ($cart_products as $id_product) {
            $excluded[] = $id_product['id_product'];
        }
        //check excluded products
        foreach ($excluded as $id_product) {
            $is_excluded = in_array($id_product, $output);
            PrestaShopLoggerCore::addLog('excluded ' . $id_product . ' in array ' . print_r($output, 1) . ": " . (int)$is_excluded);
            if ($is_excluded) {
                return false;
            }
        }
        
        return true;
    }
	
    /**
     * Get the list of activated modules
     * @return array associative array of activated payment modules [payment_type=>is_active]
     */
    public function getActiveModules()
    {
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        
        $sql    ->select("is_active")
                ->select("payment_type")
                ->from("mp_advpayment_configuration")
                ->orderBy("payment_type");
        $result = $db->executeS($sql);
        $output = array();
        foreach ($result as $record) {
            $output[$record['payment_type']] = $record['is_active'];
        }
        
        //Check if PaypalPro is active
        $paypal_pro = (bool)ConfigurationCore::get('MP_ADVPAYMENT_PAYPAL_PRO_API');
        if ($paypal_pro) {
            $output['paypal pro'] = 1;
            $output['paypal'] = 0;
        }
        
        //Check module restrictions
        $Payment = new classMpPaymentCalc;
        $cashExclusions = $Payment->getListProductsExclusion(classMpPayment::CASH);
        $bankExclusions = $Payment->getListProductsExclusion(classMpPayment::BANKWIRE);
        $paypalExclusions = $Payment->getListProductsExclusion(classMpPayment::PAYPAL);
        $cartProducts = Context::getContext()->cart->getProducts();
        
        //print_r($cartProducts);
        
        foreach ($cartProducts as $product) {
            if (in_array($product['id_product'], $cashExclusions)) {
                $output['cash']=false;
            }
            if (in_array($product['id_product'], $bankExclusions)) {
                $output['bankwire']=false;
            }
            if (in_array($product['id_product'], $paypalExclusions)) {
                $output['paypal']=false;
                $output['paypal pro']=false;
            }
        }
        
        return $output;
    }
    
    public function getTaxList()
    {
        $lang = Context::getContext()->language->id;
        $taxes = TaxCore::getTaxes($lang);
        $options = array();
        foreach ($taxes as $item) {
            $options[] = array('key' => $item['rate'], 'value' => $item['name']);
        }
        return $options;
    }
    
    public function getListCarriers()
    {
        $lang = Context::getContext()->language->id;
        $carriers = CarrierCore::getCarriers($lang);
        $options = array();
        $options[] = array('key' => 0, 'value' => $this->l('None'));
        foreach ($carriers as $item) {
            $options[] = array('key' => $item['id_carrier'], 'value' => $item['name']);
        }
        return $options;
    }
    
    public function getListCategories()
    {
        $lang = Context::getContext()->language->id;
        $categories = CategoryCore::getCategories($lang);
        $options = array();
        $options[] = array('key' => 0, 'value' => $this->l('None'));
        foreach ($categories as $category) {
            foreach ($category as $item) {
                $options[] = array(
                    'key' => $item['infos']['id_category'],
                    'value' => Tools::strtoupper($item['infos']['name'])
                        );
            }
        }
        return $options;
    }
    
    public function getListManufacturers()
    {
        $items = ManufacturerCore::getManufacturers();
        $options = array();
        $options[] = array('key' => 0, 'value' => $this->l('None'));
        foreach ($items as $item) {
            $options[] = array(
                    'key' => $item['id_manufacturer'],
                    'value' => Tools::strtoupper($item['name'])
                        );
        }
        return $options;
    }
    
    public function getListSuppliers()
    {
        $items = SupplierCore::getSuppliers();
        $options = array();
        $options[] = array('key' => 0, 'value' => $this->l('None'));
        foreach ($items as $item) {
            $options[] = array(
                    'key' => $item['id_supplier'],
                    'value' => Tools::strtoupper($item['name'])
                        );
        }
        return $options;
    }
    
    public function getListProducts()
    {
        $lang = Context::getContext()->language->id;
        $items = ProductCore::getSimpleProducts($lang);
        $options = array();
        $options[] = array('key' => 0, 'value' => $this->l('None'));
        foreach ($items as $item) {
            $options[] = array(
                    'key' => $item['id_product'],
                    'value' => Tools::strtoupper($item['name'])
                        );
        }
        return $options;
    }
    
    public function getListOrderState()
    {
        $lang = Context::getContext()->language->id;
        $items = OrderStateCore::getOrderStates($lang);
        $options = array();
        $options[] = array('key' => 0, 'value' => $this->l('None'));
        foreach ($items as $item) {
            $options[] = array(
                    'key' => $item['id_order_state'],
                    'value' => Tools::strtoupper($item['name'])
                        );
        }
        return $options;
    }
    
    public function toArray($input_string, $separator = ",")
    {
        if (empty($input_string)) {
            return array();
        }
        
        if (is_array($input_string)) {
            return $input_string;
        }
        
        return explode($separator, $input_string);
    }
    
    private function loadConfiguration()
    {
        $db = Db::getInstance();
        $sql = new DbQueryCore();
        $sql->select('*')
                ->from('mp_advpayment_configuration')
                ->where('payment_method = \'cash\'');
        $this->dbValues = $db->getRow($sql);
        return $db->getRow($sql);
    }
    
    private function getArrayInputValues()
    {
        return array(
            'input_switch_activate_module' => 0,
            'input_select_fee_type' => 0,
            'input_text_discount' => 0,
            'input_text_fee_fixed' => 0,
            'input_text_fee_percent' => 0,
            'input_text_fee_min' => 0,
            'input_text_fee_max' => 0,
            'input_text_order_min' => 0,
            'input_text_order_max' => 0,
            'input_text_order_free' => 0,
            'input_text_order_min_display' => 0,
            'input_text_order_max_display' => 0,
            'input_switch_tax_included' => 0,
            'input_select_tax_rate' => 0,
            'input_select_carriers' => array(),        
            'input_select_multiple_categories[]' => array(),
            'input_select_multiple_categories_hidden' => '',
            'input_select_multiple_manufacturers[]' => array(),
            'input_select_multiple_manufacturers_hidden' => '',
            'input_select_multiple_suppliers[]' => array(),
            'input_select_multiple_suppliers_hidden' => '',
            'input_select_multiple_products[]' => array(),
            'input_select_multiple_products_hidden' => '',
            'input_select_id_order_state' => 0,
            'input_textarea_google_script' => htmlspecialchars_decode(
                    ConfigurationCore::get('MP_CASH_GOOGLE_SCRIPT')
                    ),
            'input_textarea_email_notification' => htmlspecialchars_decode(
                    ConfigurationCore::get('MP_CASH_EMAIL_NOTIFICATION')
                    ),
        );
    }
    
    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        if(isset($this->dbValues)) {
            return $this->getValuesFromDB();
        } else {
            return $this->getValuesFromPOST();
        }
    }
    
    private function getValuesFromDB()
    {
        $output = array(
            'input_switch_activate_module' => (int)$this->dbValues['is_active'],
            'input_select_fee_type' => (int)$this->dbValues['fee_type'],
            'input_text_discount' => (float)$this->dbValues['discount'],
            'input_text_fee_fixed'=> (float)$this->dbValues['fee_amount'],
            'input_text_fee_percent'=> (float)$this->dbValues['fee_percent'],
            'input_text_fee_min' => (float)$this->dbValues['fee_min'],
            'input_text_fee_max' => (float)$this->dbValues['fee_max'],
            'input_text_order_min' => (float)$this->dbValues['order_min'],
            'input_text_order_max' => (float)$this->dbValues['order_max'],
            'input_text_order_free' => (float)$this->dbValues['order_free'],
            'input_text_order_min_display' => (float)$this->dbValues['order_min_display'],
            'input_text_order_max_display' => (float)$this->dbValues['order_max_display'],
            'input_switch_tax_included' => (int)$this->dbValues['tax_included'],
            'input_select_tax_rate' => (float)$this->dbValues['tax_rate'],
            'input_select_carriers' => $this->dbValues['carriers'],           
            'input_select_multiple_categories[]' => $this->dbValues['categories'],        
            'input_select_multiple_categories_hidden' => $this->dbValues['categories'],        
            'input_select_multiple_manufacturers[]' => $this->dbValues['manufacturers'],        
            'input_select_multiple_manufacturers_hidden' => $this->dbValues['manufacturers'],        
            'input_select_multiple_suppliers[]' => $this->dbValues['suppliers'],        
            'input_select_multiple_suppliers_hidden' => $this->dbValues['suppliers'],        
            'input_select_multiple_products[]' => $this->dbValues['products'],        
            'input_select_multiple_products_hidden' => $this->dbValues['products'],        
            'input_select_id_order_state' => (int)$this->dbValues['id_order_state'],        
            'input_textarea_google_script' => htmlspecialchars_decode(
                    ConfigurationCore::get('MP_CASH_GOOGLE_SCRIPT')
                    ),
            'input_textarea_email_notification' => htmlspecialchars_decode(
                    ConfigurationCore::get('MP_CASH_EMAIL_NOTIFICATION')
                    ),
        );
        
        return $output;
    }
    
    private function getValuesFromPOST()
    {
        $inputs = array(
            'input_switch_activate_module' => array(
                'type' => 'switch',
                'value' => '0'
            ),
            'input_select_fee_type' => array(
                'type' => 'select',
                'value' => '0'
            ),
            'input_text_discount' => array(
                'type' => 'percent',
                'value' => '0'
            ),
            'input_text_fee_fixed' => array(
                'type' => 'price',
                'value' => '0'
            ),
            'input_text_fee_percent' => array(
                'type' => 'percent',
                'value' => '0'
            ),
            'input_text_fee_min' => array(
                'type' => 'price',
                'value' => '0'
            ),
            'input_text_fee_max' => array(
                'type' => 'price',
                'value' => '0'
            ),
            'input_text_order_min' => array(
                'type' => 'price',
                'value' => '0'
            ),
            'input_text_order_max' => array(
                'type' => 'price',
                'value' => '0'
            ),
            'input_text_order_free' => array(
                'type' => 'price',
                'value' => '0'
            ),
            'input_text_order_min_display' => array(
                'type' => 'price',
                'value' => '0'
            ),
            'input_text_order_max_display' => array(
                'type' => 'price',
                'value' => '0'
            ),
            'input_switch_tax_included' => array(
                'type' => 'switch',
                'value' => '0'
            ),
            'input_select_tax_rate' => array(
                'type' => 'percent',
                'value' => '0'
            ),
            'input_select_carriers' => array(
                'type' => 'array',
                'value' => ''
            ),        
            'input_select_multiple_categories' => array(
                'type' => 'array',
                'value' => ''
            ),
            'input_select_multiple_categories_hidden' => array(
                'type' => 'hidden',
                'value' => ''
            ),
            'input_select_multiple_manufacturers' => array(
                'type' => 'array',
                'value' => ''
            ),
            'input_select_multiple_manufacturers_hidden' => array(
                'type' => 'hidden',
                'value' => ''
            ),
            'input_select_multiple_suppliers' => array(
                'type' => 'array',
                'value' => ''
            ),
            'input_select_multiple_suppliers_hidden' => array(
                'type' => 'hidden',
                'value' => ''
            ),
            'input_select_multiple_products' => array(
                'type' => 'array',
                'value' => ''
            ),
            'input_select_multiple_products_hidden' => array(
                'type' => 'hidden',
                'value' => ''
            ),
            'input_select_id_order_state' => array(
                'type' => 'text',
                'value' => ''
            ),
            'input_textarea_google_script' => array(
                'type' => 'text',
                'value' => ''
            ),
            'input_textarea_email_notification' => array(
                'type' => 'text',
                'value' => ''
            ),
        );
        $output = array();
        
        if(Tools::isSubmit('submitMpBankwireValues')) {
            foreach($inputs as $key=>$value)
            {
                switch ($inputs[$key]['type']) {
                    case 'array':
                        $postData = Tools::getValue($key, array());
                        $hidden = $key.'_hidden';
                        $select = $key . '[]';
                        $output[$hidden] = implode(',',$postData);
                        $output[$select] = $output[$hidden];
                        break;
                    case 'price':
                        $postData = Tools::getValue($key, 0);
                        $output[$key] = number_format($postData, 2);
                        break;
                    case 'percent':
                        $postData = Tools::getValue($key, 0);
                        $output[$key] = number_format($postData, 2);
                        break;
                    case 'text':
                        $postData = Tools::getValue($key, '');
                        $output[$key] = htmlspecialchars_decode($postData);
                        break;
                    case 'switch':
                        $postData = Tools::getValue($key, 0);
                        $output[$key] = (int)$postData;
                        break;
                    case 'select':
                        $postData = Tools::getValue($key, '');
                        $output[$key] = (int)$postData;
                        break;
                    default:
                        break;
                }
            }
        }
        return $output;
    }
    
    public static function addLog($msg, $id_cart = 0)
    {
        if(!empty(ContextCore::getContext()->employee->id)) {
            $id_employee = (int)ContextCore::getContext()->employee->id;
        } else {
            $id_employee = 0;
        }
        $db = Db::getInstance();
        $result = $db->insert(
                'mp_advpayment_log',
                array(
                    'message' => pSQL($msg),
                    'id_employee' => (int)$id_employee,
                    'id_cart' => (int)$id_cart,
                    'module' => pSQL('CASH')
                )
            );
        if (!$result) {
            PrestaShopLoggerCore::addLog('Can\'t add message to log mp_advpayment_log', 1);
        }
    }
    
    public static function getLogs($TotRows=0)
    {
        $db = Db::getInstance();
        
        $sqlStart = new DbQueryCore();
        if ($TotRows==0) {
            $sqlStart->select('max(id_log)')
                    ->from('mp_advpayment_log')
                    ->where("message = '[ajaxProcessImportProductsByReference]'");
        } else {
            $sqlStart->select('max(id_log)')
                    ->from('mp_advpayment_log');
        }
        //PrestaShopLoggerCore::addLog('getLogs query: ' . $sqlStart->__toString());
        
        $id_log = (int)$db->getValue($sqlStart);
        if((int)$TotRows) {
            $id_log = $id_log - $TotRows;
        }
        
        $sql = new DbQueryCore();
        $sql->select('log.*')
                ->select('e.firstname')
                ->select('e.lastname')
                ->from('mp_advpayment_log', 'log')
                ->innerJoin('employee', 'e', 'e.id_employee = log.id_employee')
                ->where('log.id_log > ' . ($id_log -1))
                ->orderBy('id_log DESC');
        //PrestaShopLoggerCore::addLog('getLogs query log: ' . $sql->__toString());
        return $db->executeS($sql);
    }
    
    public static function clearLogs()
    {
        $db = Db::getInstance();
        return $db->delete('mp_advpayment_log');
    }
    
    public function getModule()
    {
        return $this->module;
    }
}

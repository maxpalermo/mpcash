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

// Located in /modules/mymodule/ajax.php
require_once(dirname(__FILE__).'../../../config/config.inc.php');
require_once(dirname(__FILE__).'../../../init.php');
require_once(dirname(__FILE__).'/mpCash.php');

$module = new MpCash();

if (Tools::isSubmit('ajax') && tools::isSubmit('action') && tools::isSubmit('token')) {
    if (Tools::getValue('token') != Tools::encrypt($module->name)) {
        print $module->displayError($module->l('INVALID TOKEN'));
        exit();
    }
    if (Tools::getValue('action') == 'fixOrder') {
        $module->ajaxProcessFixOrder();
    } else {
        print $module->displayError($module->l('INVALID AJAX PARAMETERS'));
    }
    exit();
} else {
    print $module->displayError($module->l('INVALID SUBMIT VALUES'));
    print "<br>ajax=" . (int)Tools::getValue('ajax');
    print "<br>action=" . Tools::getValue('action');
    print "<br>token=" . Tools::getValue('token');
    exit();
}

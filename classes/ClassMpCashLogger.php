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

class ClassMpCashLogger {
    
    private static function getFileName()
    {
        $filename = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . 'log.txt';
        return $filename;
    }
    
    private static function openFile()
    {
        $debug = true;
        
        if ($debug==false) {
           return false; 
        }
        
        $filename = self::getFileName();
        $handle = fopen($filename, 'a');
        
        return $handle;
    }
    
    private static function closeFile($handle)
    {
        fclose($handle);
        chmod(self::getFileName(), 0777);
    }
    
    public static function addEvidencedMsg($message)
    {
        $message = '*** ' . $message . ' ***';
        $count = Tools::strlen($message);
        $stars = str_repeat("*", $count);
        self::add($stars);
        self::add($message);
        self::add($stars);
    }
    
    public static function addStartFunction($function)
    {
        self::blank();
        self::addEvidencedMsg('START FUNCTION ' . Tools::strtoupper($function));
    }
    
    public static function addEndFunction($function)
    {
        self::addEvidencedMsg('END FUNCTION ' . Tools::strtoupper($function));
        self::blank();
    }
    
    public static function add($message)
    {
        $handle = self::openFile();
        if($handle===false) {
            return false;
        }
        
        $function = debug_backtrace()[1]['function'];
        $log = date('Y-m-d h:i:s') . " [" . $function . '] => ' . $message;
        fwrite($handle,$log);
        fwrite($handle,PHP_EOL);
        
        self::closeFile($handle);
        
    }
    
    public static function blank()
    {
        $handle = self::openFile();
        if($handle===false) {
            return false;
        }
        
        fwrite($handle,PHP_EOL);
        
        self::closeFile($handle);
    }
    
    public static function clear()
    {
        $filename = dirname(__FILE__) . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . 'log.txt';
        if (file_exists($filename)) {
            unlink($filename);
        }
    }
    
    public static function exists()
    {
        return true;
    }
}

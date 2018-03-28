<?php
$sql = "
CREATE TABLE IF NOT EXISTS `{_DB_PREFIX_}mp_advpayment_configuration` ( 
    `fee_type` INT NOT NULL , 
    `fee_amount` DECIMAL(20,6) NOT NULL , 
    `fee_percent` DECIMAL(20,6) NOT NULL , 
    `fee_min` DECIMAL(20,6) NOT NULL , 
    `fee_max` DECIMAL(20,6) NOT NULL , 
    `order_min` DECIMAL(20,6) NOT NULL , 
    `order_max` DECIMAL(20,6) NOT NULL , 
    `order_free` DECIMAL(20,6) NOT NULL , 
    `order_min_display` DECIMAL(20,6) NOT NULL , 
    `order_max_display` DECIMAL(20,6) NOT NULL , 
    `discount`  DECIMAL(20,6) NOT NULL,
    `tax_included` BOOLEAN NOT NULL,
    `tax_rate` DECIMAL(20,6) NOT NULL,
    `carriers` TEXT NOT NULL,
    `categories` TEXT NOT NULL,
    `manufacturers` TEXT NOT NULL,
    `suppliers` TEXT NOT NULL,
    `products` TEXT NOT NULL,
    `id_order_state` INT NOT NULL,
    `payment_method` VARCHAR(30) NOT NULL,
    `is_active` BOOLEAN NOT NULL,
    PRIMARY KEY (`payment_method`)
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `{_DB_PREFIX_}mp_advpayment_fee` ( 
    `id_fee` INT NOT NULL AUTO_INCREMENT, 
    `id_order` INT NOT NULL ,  
    `total_paid_tax_incl` DECIMAL(20,6) NOT NULL , 
    `total_paid_tax_excl` DECIMAL(20,6) NOT NULL ,   
    `fee_tax_incl` DECIMAL(20,6) NOT NULL , 
    `fee_tax_excl` DECIMAL(20,6) NOT NULL , 
    `fee_tax_rate` DECIMAL(20,6) NOT NULL ,
    `fee_tax_amount` DECIMAL(20,6) NOT NULL ,
    `transaction_id` VARCHAR(255) NOT NULL , 
    `payment_method` VARCHAR(30),
    `date_add` DATE NOT NULL , 
    `date_upd` TIMESTAMP NOT NULL , 
    PRIMARY KEY (`id_fee`)
) ENGINE = InnoDB;";

$result_sql = Db::getInstance()->execute($sql);
if (!$result_sql) {
    return false;
}

$indexes = "
ALTER TABLE `{_DB_PREFIX_}mp_advpayment_fee` 
ADD UNIQUE INDEX `order_unique` (`id_order`);";

try {
    $result_idx = Db::getInstance()->execute($sql);
} catch (Exception $ex) {
    $this->_errors[] = $ex->getMessage();
    $result_idx = true;
}

if (!$result_idx) {
    return false;
}

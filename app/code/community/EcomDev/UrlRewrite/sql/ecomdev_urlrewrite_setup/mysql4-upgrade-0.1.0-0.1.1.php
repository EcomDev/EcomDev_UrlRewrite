<?php
/**
 * Alternative Url Rewrite Indexer
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   EcomDev
 * @package    EcomDev_UrlRewrite
 * @copyright  Copyright (c) 2011 EcomDev BV (http://www.ecomdev.org)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author     Ivan Chepurnyi <ivan.chepurnyi@ecomdev.org>
 */

/* @var $this Mage_Core_Model_Resource_Setup */
$this->startSetup();

$this->getConnection()->dropColumn(
    $this->getTable('ecomdev_urlrewrite/category_request_path'), 
    'url_key'
);

$this->getConnection()->dropColumn(
    $this->getTable('ecomdev_urlrewrite/product_request_path'), 
    'url_key'
);

$this->getConnection()->query(
    'DROP TABLE IF EXISTS ' . $this->getTable('ecomdev_urlrewrite_category_request_path_tmp')
);

// This table provides data that can be used for LIKE path expressions 
// with categories
$table = $this->getConnection()->newTable(
    $this->getTable('ecomdev_urlrewrite/root_category')
);

$table
    ->addColumn(
        'store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null,
        array('unsigned' => true, 'nullable' => false, 'primary' => true)
    )
    ->addColumn(
        'path', Varien_Db_Ddl_Table::TYPE_CHAR, 16,
        array('unsigned' => true, 'nullable' => false)
    )
    ->addIndex('IDX_CATEGORY_PATH', array('path'))
    ->setOption('collate', null);

$this->getConnection()->createTable($table);

// This tables will not have any foreign keys
// They will not be cleared automatically if product/category 
// or store will be deleted
// They created to minimize time on update of the url key via clean_url_key 
// stored function
$table = $this->getConnection()->newTable(
    $this->getTable('ecomdev_urlrewrite/category_url_key')
);


$table
    ->addColumn(
        'store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null,
        array('unsigned' => true, 'nullable' => false, 'primary' => true)
    )
    ->addColumn(
        'category_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
        array('unsigned' => true, 'nullable' => false, 'primary' => true)
    )
    ->addColumn(
        'level', Varien_Db_Ddl_Table::TYPE_SMALLINT, null,
        array('unsigned' => true, 'nullable' => false)
    )
    ->addColumn(
        'updated', Varien_Db_Ddl_Table::TYPE_TINYINT, 1,
        array('unsigned' => true, 'nullable' => false, 'default' => 1)
    )
    ->addColumn(
        'url_key_source', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255,
        array('nullable' => false)
    )
    ->addColumn(
        'url_key', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255,
        array('nullable' => false)
    )
    ->addIndex(
       'IDX_UPDATED', array('updated')
    )
    ->setOption('collate', null);
    
$this->getConnection()->createTable($table);
    
$table = $this->getConnection()->newTable(
    $this->getTable('ecomdev_urlrewrite/product_url_key')
);

$table
    ->addColumn(
        'store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null,
        array('unsigned' => true, 'nullable' => false, 'primary' => true)
    )
    ->addColumn(
        'product_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
        array('unsigned' => true, 'nullable' => false, 'primary' => true)
    )
    ->addColumn(
        'updated', Varien_Db_Ddl_Table::TYPE_TINYINT, 1,
        array('unsigned' => true, 'nullable' => false, 'default' => 1)
    )
    ->addColumn(
        'url_key_source', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255,
        array('nullable' => false)
    )
    ->addColumn(
        'url_key', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255,
        array('nullable' => false)
    )
    ->addIndex(
       'IDX_UPDATED', array('updated')
    )
    ->setOption('collate', null);

$this->getConnection()->createTable($table);
    
$this->getConnection()->query('DROP TABLE ' . $this->getTable('ecomdev_urlrewrite/duplicate'));

$table = $this->getConnection()->newTable(
    $this->getTable('ecomdev_urlrewrite/duplicate')
);

$table
    ->addColumn(
        'duplicate_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
        array('unsigned' => true, 'nullable' => false, 'primary' => true, 'identity' => true)
    )
    ->addColumn(
        'store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, 1,
        array('unsigned' => true, 'nullable' => false, 'default' => 1)
    )
    ->addColumn(
        'id_path', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255,
        array('unsigned' => true, 'nullable' => false, 'default' => 1)
    )
    ->addColumn(
        'is_duplicate', Varien_Db_Ddl_Table::TYPE_TINYINT, 1,
        array('nullable' => false, 'default' => 1)
    )
    ->addColumn(
        'duplicate_key', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255,
        array('nullable' => false)
    )
    ->addColumn(
        'duplicate_index', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
        array('nullable' => false)
    )
    ->addIndex(
       'IDX_STORE_ID_PATH', array('store_id', 'id_path')
    )
    ->addIndex(
       'IDX_STORE_DUPLICATE_KEY', array('store_id', 'duplicate_key')
    )
    ->setOption('collate', null);

$this->getConnection()->createTable($table);

// If lower then 1.6, then there is a bug with auto_increment field
// So we need to modify our column
if (!method_exists($this->getConnection(), 'insertFromSelect')) {
    $this->getConnection()->modifyColumn(
        $this->getTable('ecomdev_urlrewrite/duplicate'), 
        'duplicate_id', 'INT(10) UNSIGNED NOT NULL AUTO_INCREMENT'
    );
}

$table = $this->getConnection()->newTable(
    $this->getTable('ecomdev_urlrewrite/duplicate_aggregate')
);

$table
    ->addColumn(
        'duplicate_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
        array('unsigned' => true, 'nullable' => false, 'primary' => true)
    )
    ->addColumn(
        'max_index', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255,
        array('nullable' => false)
    )
    ->addColumn(
        'min_duplicate_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
        array('nullable' => true, 'unsigned' => true)
    )
    ->setOption('collate', null);

$this->getConnection()->createTable($table);


$this->endSetup();
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

$this->getConnection()->query(
    'DROP TABLE ' . $this->getTable('ecomdev_urlrewrite/duplicate_aggregate')
);

$table = $this->getConnection()->newTable(
    $this->getTable('ecomdev_urlrewrite/duplicate_aggregate')
);

$table
    ->addColumn(
        'store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null,
        array('unsigned' => true, 'nullable' => false, 'primary' => true)
    )
    ->addColumn(
        'duplicate_key', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255,
        array('nullable' => false, 'primary' => true)
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

$this->getConnection()->addKey(
    $this->getTable('ecomdev_urlrewrite/rewrite'),
    'IDX_STORE_REQ_PATH_DUPLICATE',
    array('store_id', 'duplicate_key', 'duplicate_index')
);

$this->endSetup();

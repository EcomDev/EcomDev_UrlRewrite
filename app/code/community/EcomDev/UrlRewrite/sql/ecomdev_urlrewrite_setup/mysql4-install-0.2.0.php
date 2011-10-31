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

// These two tables will not have any foreign keys
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
    ->addIndex(
        'IDX_LEVEL', array('level')
    )
    ->addIndex(
       'IDX_CATEGORY_STORE', array('category_id', 'store_id')
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
    ->addIndex(
       'IDX_PRODUCT_STORE', array('product_id', 'store_id')
    )
    ->setOption('collate', null);

$this->getConnection()->createTable($table);

$table = $this->getConnection()->newTable(
    $this->getTable('ecomdev_urlrewrite/category_request_path')
);

// Category request path index table
$table
    ->addColumn(
        'store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null,
        array('unsigned' => true, 'nullable' => false, 'primary' => true)
    )
    ->addColumn(
        'id_path', Varien_Db_Ddl_Table::TYPE_CHAR, 32, 
        array('nullable' => false, 'primary' => true)
    )
    ->addColumn(
        'category_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
        array('unsigned' => true, 'nullable' => false)
    )
    ->addColumn(
        'level', Varien_Db_Ddl_Table::TYPE_SMALLINT, null,
        array('unsigned' => true, 'nullable' => false)
    )
    ->addColumn(
        'request_path', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255,
        array('nullable' => false)
    )
    ->addColumn(
        'updated', Varien_Db_Ddl_Table::TYPE_TINYINT, 1,
        array('unsigned' => true, 'nullable' => false, 'default' => 1)
    )
    ->addIndex(
        'IDX_STORE_CATEGORY', array('store_id', 'category_id')
    )
    ->addIndex(
        'IDX_LEVEL', array('level')
    )
    ->addIndex(
        'IDX_UPDATED', array('updated')
    )
    ->setOption('collate', null);

$this->getConnection()->createTable($table);

$table = $this->getConnection()->newTable(
    $this->getTable('ecomdev_urlrewrite/product_request_path')
);

// Product request path index table
$table
    ->addColumn(
        'store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null,
        array('unsigned' => true, 'nullable' => false, 'primary' => true)
    )
    ->addColumn(
        'id_path', Varien_Db_Ddl_Table::TYPE_CHAR, 32, 
        array('nullable' => false, 'primary' => true)
    )
    ->addColumn(
        'product_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
        array('unsigned' => true, 'nullable' => false)
    )
    ->addColumn(
        'category_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
        array('unsigned' => true, 'nullable' => true)
    )
    ->addColumn(
        'request_path', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255,
        array('nullable' => false)
    )
    ->addColumn(
        'updated', Varien_Db_Ddl_Table::TYPE_TINYINT, 1,
        array('unsigned' => true, 'nullable' => false, 'default' => 1)
    )
    ->addIndex(
        'IDX_CATEGORY', 
        array('category_id')
    )
    ->addIndex(
        'IDX_PRODUCT', 
        array('product_id')
    )
    ->addIndex(
        'IDX_UPDATED', 
        array('updated')
    )
    ->setOption('collate', null);

$this->getConnection()->createTable($table);

// Rewrite table
$table = $this->getConnection()->newTable(
    $this->getTable('ecomdev_urlrewrite/rewrite')
);

$table
    ->addColumn(
        'store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null,
        array('unsigned' => true, 'nullable' => false, 'primary' => true)
    )
    ->addColumn(
        'id_path', Varien_Db_Ddl_Table::TYPE_CHAR, 32, 
        array('nullable' => false, 'primary' => true)
    )
    ->addColumn(
        'rewrite_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
        array('unsigned' => true, 'nullable' => true)
    )
    ->addColumn(
        'product_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
        array('unsigned' => true, 'nullable' => true)
    )
    ->addColumn(
        'category_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
        array('unsigned' => true, 'nullable' => true)
    )
    ->addColumn(
        'target_path', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255,
        array('nullable' => false)
    )
    ->addColumn(
        'request_path', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255,
        array('nullable' => false)
    )
    ->addColumn(
        'duplicate_key', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255,
        array('nullable' => false)
    )
    ->addColumn(
        'duplicate_index', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
        array('nullable' => true, 'unsigned' => true)
    )
    ->addColumn(
        'original_request_path', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255,
        array('nullable' => true)
    )
    ->addColumn(
        'updated', Varien_Db_Ddl_Table::TYPE_TINYINT, 1,
        array('unsigned' => true, 'nullable' => false, 'default' => 1)
    )
    ->addIndex(
        'IDX_REWRITE',
        array('rewrite_id')
    )
    ->addIndex(
        'IDX_CATEGORY',
        array('category_id')
    )
    ->addIndex(
        'IDX_PRODUCT',
        array('product_id')
    )
    ->addIndex(
        'IDX_REQ_PATH',
        array('request_path')
    )
    ->addIndex(
        'IDX_ORIG_REQ_PATH_PAIR',
        array('request_path', 'original_request_path')
    )
    ->addIndex(
        'IDX_DUPLICATE_STORE',
        array('duplicate_key', 'store_id',  'duplicate_index')
    )
    ->addIndex(
        'IDX_DUPLICATE_KEY_STORE',
        array('duplicate_key', 'store_id')
    )
    ->addIndex(
        'IDX_UPDATED_DUPLICATE',
        array('updated', 'store_id', 'duplicate_key')
    )
    ->addIndex(
        'IDX_UPDATED',
        array('updated')
    )
    ->addForeignKey(
        'FK_ECOMDEV_URLREWRITE_REW_REWRITE_ID', 
        'rewrite_id',
        $this->getTable('core/url_rewrite'),
        'url_rewrite_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE
    )
    ->setOption('collate', null);
    
$this->getConnection()->createTable($table);

// Information about wich duplicate keys were updated
$table = $this->getConnection()->newTable(
    $this->getTable('ecomdev_urlrewrite/duplicate_updated')
);

$table
    ->addColumn(
        'duplicate_key', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255,
        array('nullable' => false, 'primary' => true)
    )
    ->addColumn(
        'store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null,
        array('unsigned' => true, 'nullable' => false, 'primary' => true)
    )
    ->setOption('collate', null);

$this->getConnection()->createTable($table);

// Contains infromation about really duplicated keys
$table = $this->getConnection()->newTable(
    $this->getTable('ecomdev_urlrewrite/duplicate_key')
);

$table
    ->addColumn(
        'duplicate_key', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255,
        array('nullable' => false, 'primary' => true)
    )
    ->addColumn(
        'store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null,
        array('unsigned' => true, 'nullable' => false, 'primary' => true)
    )
    ->setOption('collate', null);

$this->getConnection()->createTable($table);

// Duplicates main table
$table = $this->getConnection()->newTable($this->getTable('ecomdev_urlrewrite/duplicate'))
    ->addColumn(
        'store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null,
        array('unsigned' => true, 'nullable' => false, 'primary' => true)
    )
    ->addColumn(
        'id_path', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, 
        array('nullable' => false, 'primary' => true)
    )
    ->addColumn(
        'duplicate_key', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255,
        array('nullable' => false)
    )
    ->addColumn(
        'duplicate_index', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
        array('nullable' => true, 'unsigned' => true)
    )
    ->addIndex(
        'IDX_STORE_DUPLICATE_KEY',
        array('duplicate_key', 'store_id')
    )
    ->setOption('collate', null);

$this->getConnection()->createTable($table);


$table = $this->getConnection()->newTable(
    $this->getTable('ecomdev_urlrewrite/duplicate_increment')
);

$table
    ->addColumn(
        'duplicate_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
        array('unsigned' => true, 'nullable' => false, 'primary' => true, 'identity' => true)
    )
    ->addColumn(
        'store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null,
        array('unsigned' => true, 'nullable' => false)
    )
    ->addColumn(
        'id_path', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255,
        array('unsigned' => true, 'nullable' => false)
    )
    ->addColumn(
        'duplicate_key', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255,
        array('nullable' => false)
    )
    ->addColumn(
        'duplicate_index', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
        array('nullable' => true, 'unsigned' => true)
    )
    ->addIndex('IDX_STORE_ID_PATH', array('store_id', 'id_path'))
    ->addIndex('IDX_STORE_DUPLICATE_KEY', array('duplicate_key', 'store_id'))
    ->setOption('collate', null);

$this->getConnection()->createTable($table);

// If lower then 1.6, then there is a bug with auto_increment field
// So we need to modify our column
if (!method_exists($this->getConnection(), 'insertFromSelect')) {
    $this->getConnection()->modifyColumn(
        $this->getTable('ecomdev_urlrewrite/duplicate_increment'), 
        'duplicate_id', 'INT(10) UNSIGNED NOT NULL AUTO_INCREMENT'
    );
}

$table = $this->getConnection()->newTable(
    $this->getTable('ecomdev_urlrewrite/duplicate_aggregate')
);

$table
    ->addColumn(
        'duplicate_key', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255,
        array('nullable' => false, 'primary' => true)
    )
    ->addColumn(
        'store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null,
        array('unsigned' => true, 'nullable' => false, 'primary' => true)
    )
    ->addColumn(
        'max_index', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
        array('nullable' => true, 'unsigned' => true)
    )
    ->addColumn(
        'min_duplicate_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
        array('nullable' => false, 'unsigned' => true)
    )
    ->setOption('collate', null);

$this->getConnection()->createTable($table);


$table = $this->getConnection()->newTable(
    $this->getTable('ecomdev_urlrewrite/category_relation')
);

// Category relation index table
$table
    ->addColumn(
        'category_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
        array('unsigned' => true, 'nullable' => false, 'primary' => true)
    )
    ->addColumn(
        'related_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
        array('unsigned' => true, 'nullable' => false, 'primary' => true)
    )
    ->addColumn(
        'type', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32,
        array('nullable' => false, 'primary' => true)
    )
    ->addIndex('IDX_RELATION_BY_TYPE', array('category_id', 'type', 'related_id'));

$this->getConnection()->createTable($table);
    
$table = $this->getConnection()->newTable(
    $this->getTable('ecomdev_urlrewrite/product_relation')
);

// Category product relation index table, 
// filled in before generation of request path
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
        'product_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null,
        array('unsigned' => true, 'nullable' => false, 'primary' => true)
    );
    

$this->getConnection()->createTable($table);

// Transliterate characters table
$table = $this->getConnection()->newTable($this->getTable('ecomdev_urlrewrite/transliterate'))
    ->addColumn(
        'character_from', Varien_Db_Ddl_Table::TYPE_CHAR, 1,
        array('nullable' => false, 'primary' => true)
    )
    ->addColumn(
        'character_to', Varien_Db_Ddl_Table::TYPE_VARCHAR, 8,
        array('nullable' => false)
    )
    ->setOption('collate', 'utf8_bin')
    ->setOption('type', 'MEMORY');

$this->getConnection()->createTable($table);

// Url path formatter function Works only with mysql starting of 5.0
$this->getConnection()->query('DROP FUNCTION IF EXISTS ECOMDEV_CLEAN_URL_KEY');

$this->getConnection()->query("
CREATE FUNCTION ECOMDEV_CLEAN_URL_KEY(
        _url_key VARCHAR(255) CHARSET utf8
    )  RETURNS varchar(255) CHARSET utf8 READS SQL DATA
BEGIN 
    DECLARE _char_position SMALLINT(5);
    DECLARE _char CHAR(8) CHARSET utf8;
    DECLARE _url_key_length SMALLINT(5); 
    DECLARE _clean_url_key VARCHAR(255) CHARACTER SET utf8;
    DECLARE _translate_to_char VARCHAR(8) CHARSET utf8;
    DECLARE _normal_characters VARCHAR(72) 
        CHARSET utf8 DEFAULT '!@#$%^&*()<>?:;\\'\"\\\\|[]_+=-01234567890abcdefghijklmnopqrstuvwxyz';
    
    SET _url_key = LCASE(_url_key);
    SET _clean_url_key = ''; 
    SET _url_key_length  = LENGTH(_url_key); 
    SET _char_position = 1;

    WHILE _char_position <= _url_key_length DO 
        SET _char = SUBSTRING(_url_key, _char_position, 1);
        
        IF NOT LOCATE(_char, _normal_characters) THEN
           SELECT character_to INTO _translate_to_char 
               FROM {$this->getTable('ecomdev_urlrewrite/transliterate')}
               WHERE character_from = _char LIMIT 1;
           SET _char = IFNULL(_translate_to_char, ''); 
        END IF;

        IF _char REGEXP '[0-9a-z]' THEN 
           SET _clean_url_key = CONCAT(_clean_url_key, _char);
        ELSE 
           SET _clean_url_key = CONCAT(_clean_url_key, '-');
        END IF;

        IF _char_position > 1 AND SUBSTR(_clean_url_key, LENGTH(_clean_url_key)-1, 2) = '--' THEN
           SET _clean_url_key = SUBSTR(_clean_url_key, 1, LENGTH(_clean_url_key)-1);
        END IF;

        SET _char_position = _char_position + 1;
   END WHILE;

RETURN TRIM(BOTH '-' FROM CONCAT('', _clean_url_key));
END
");

$this->getConnection()->update(
    $this->getTable('index/process'), 
    array(
        'status' => 'require_reindex'
    ),
    array(
        'indexer_code = ?' => 'catalog_url' 
    )
);

$this->endSetup();

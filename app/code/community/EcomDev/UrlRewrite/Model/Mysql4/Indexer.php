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

/**
 * Url rewrite indexer resource model 
 * 
 */
class EcomDev_UrlRewrite_Model_Mysql4_Indexer extends Mage_Index_Model_Mysql4_Abstract
{
    const TRANSLITERATE = 'transliterate';
    const ROOT_CATEGORY = 'root_category';
    const CATEGORY_URL_KEY = 'category_url_key';
    const CATEGORY_REQUEST_PATH = 'category_request_path';
    const CATEGORY_RELATION = 'category_relation';
    const PRODUCT_URL_KEY = 'product_url_key';
    const PRODUCT_REQUEST_PATH = 'product_request_path';
    const REWRITE = 'rewrite';
    const DUPLICATE = 'duplicate';
    const DUPLICATE_INCREMENT = 'duplicate_increment';
    const DUPLICATE_AGGREGATE = 'duplicate_aggregate';

    const MAX_LENGTH_URL_PATH = 245;

    const ENTITY_CATEGORY = Mage_Catalog_Model_Category::ENTITY;
    const ENTITY_PRODUCT = Mage_Catalog_Model_Product::ENTITY;
    
    const RELATION_TYPE_NESTED = 'nested';
    const RELATION_TYPE_ANCHOR = 'anchor';
    
    /**
     * Id path templates for entities
     * 
     * @var string
     */
    const ID_PATH_CATEGORY         = 'category/#id';
    const ID_PATH_PRODUCT          = 'product/#id';
    const ID_PATH_PRODUCT_CATEGORY = 'product/#id/#cat';
    
    /**
     * Target path templates for entities
     * 
     * @var string
     */
    const TARGET_PATH_CATEGORY         = 'catalog/category/view/id/#id';
    const TARGET_PATH_PRODUCT          = 'catalog/product/view/id/#id';
    const TARGET_PATH_PRODUCT_CATEGORY = 'catalog/product/view/id/#id/category/#cat';
    
    /**
     * Replacement expressions for the templates above
     * @var string
     */
    const REPLACE_CATEGORY         = "REPLACE(?,'#id',%s)";
    const REPLACE_PRODUCT          = "REPLACE(?,'#id',%s)";
    const REPLACE_PRODUCT_CATEGORY = "REPLACE(REPLACE(?,'#id',%s),'#cat',%s)";
    
    /**
     * Save url history flag for forced history save
     * 
     * @var boolean|null
     */
    protected $_isSaveHistory = null;

    /**
     * List of path generation expressions
     * 
     * @var array
     */
    protected $_pathGenerateExpr = array(
        self::ID_PATH_CATEGORY => self::REPLACE_CATEGORY,
        self::ID_PATH_PRODUCT =>  self::REPLACE_PRODUCT,
        self::ID_PATH_PRODUCT_CATEGORY => self::REPLACE_PRODUCT_CATEGORY,
        self::TARGET_PATH_CATEGORY => self::REPLACE_CATEGORY,
        self::TARGET_PATH_PRODUCT =>  self::REPLACE_PRODUCT,
        self::TARGET_PATH_PRODUCT_CATEGORY => self::REPLACE_PRODUCT_CATEGORY,
    );
    
    /**
     * Initialize resource model with tables lists
     * 
     */
    protected function _construct()
    {
        $tables =  array(
            self::TRANSLITERATE => '',
            self::ROOT_CATEGORY => '',
            self::CATEGORY_URL_KEY => '',
            self::CATEGORY_REQUEST_PATH => '',
            self::CATEGORY_RELATION => '',
            self::PRODUCT_URL_KEY => '',
            self::PRODUCT_REQUEST_PATH => '',
            self::REWRITE => '',
            self::DUPLICATE => '',
            self::DUPLICATE_INCREMENT => '',
            self::DUPLICATE_AGGREGATE => ''
        );
        
        foreach ($tables as $key => &$table) {
            $table = 'ecomdev_urlrewrite/' . $key;
        }
    
        $this->_setResource('ecomdev_urlrewrite', $tables);
    }
    
    /**
     * Checks if save history mode is enabled. 
     * If $flag parameter is passed, then history save is forced 
     * 
     * @param boolean $flag
     * @param mixed $storeId
     * @return boolean
     */
    public function isSaveHistory($flag = null)
    {
        if ($flag !== null) {
            $this->_isSaveHistory = (bool) $flag;
        }
        
        
        return $this->_isSaveHistory;
    }
    
    /**
     * Checks if save history mode is enabled on a particular store
     * 
     * @param mixed $storeId
     * @return boolean
     */
    public function isSaveHistoryStore($storeId)
    {
        if ($this->_isSaveHistory !== null) {
            return $this->_isSaveHistory;
        }

        return Mage::helper('catalog')->shouldSaveUrlRewritesHistory($storeId);
    }

    /**
     * Resets force history save flag  
     * 
     * @return EcomDev_UrlRewrite_Model_Mysql4_Indexer
     */
    public function resetSaveHistory()
    {
        $this->_isSaveHistory = null;
        return $this;
    }
    
    /**
     * Returns EAV config model
     * 
     * @return Mage_Eav_Model_Config
     */
    protected function _getEavConfig()
    {
        return Mage::getSingleton('eav/config');
    }
    
    /**
     * Shortcut for DB Adapter quoteInto() method
     * 
     * @param string $expr
     * @param mixed $replacement
     * @return string
     */
    protected function _quoteInto($expr, $replacement)
    {
        return $this->_getIndexAdapter()->quoteInto($expr, $replacement);
    }
    
    /**
     * Check that character is unicode single char.
     * Should not translate chars combination in tranlstate source
     * 
     * @param string $item
     * @return boolean
     */
    protected function _isSingleUtf8Char($item)
    {
        return iconv_strlen($item, 'UTF-8') == 1;
    }
    
    /**
     * Generates transliteration data for ECOMDEV_CLEAN_URL_KEY from logic 
     * specified in url helpers
     * 
     * @param boolean $checkIfEmpty if equals true, then generates data only if table is not empty
     * @return EcomDev_UrlRewrite_Model_Mysql4_Indexer
     */
    protected function _generateTransliterateData($checkIfEmpty = false)
    {
        $translateTable = Mage::helper('catalog/product_url')->getConvertTable();
        $validChars = array_filter(array_keys($translateTable), array($this, '_isSingleUtf8Char'));
        
        if ($validChars) {
            if (!$checkIfEmpty) {
                $this->_getIndexAdapter()->delete($this->getTable(self::TRANSLITERATE));
            } else {
                $select = $this->_getIndexAdapter()->select();
                $numberOfRows = $this->_getIndexAdapter()->fetchOne(
                    $select->from($this->getTable(self::TRANSLITERATE), 'COUNT(character_to)')
                );
                
                if (!$numberOfRows) {
                    return $this;
                }
            }
            
            $insert = array();
            foreach ($validChars as $char) {
                $insert[] = array(
                    'character_from' => new Zend_Db_Expr($this->_quoteInto(
                        'LCASE(?)', 
                        $char
                    )),
                    'character_to'   => new Zend_Db_Expr($this->_quoteInto(
                       'LCASE(?)', 
                       $translateTable[$char]
                    ))
                );
            }
            
           
            $this->_getIndexAdapter()->insertOnDuplicate(
                $this->getTable(self::TRANSLITERATE), 
                $insert,
                array('character_to')
            );
        }
        
        return $this;
    }
    
    /**
     * Retrieves list of columns from select object
     * 
     * @param Varien_Db_Select $select
     * @return array
     */
    protected function _getColumnsFromSelect(Varien_Db_Select $select)
    {
        $rawColumns = $select->getPart(Varien_Db_Select::COLUMNS);
        $columns = array();
        foreach ($rawColumns as $columnEntry) {
            list(, $column, $alias) = $columnEntry;
            if (empty($alias)) {
                $alias = $column;
            }
            $columns[] = $alias;
        }
        
        return $columns;
    }
    
    /**
     * Prepares category url key select
     * 
     * @return Varien_Db_Select
     */
    protected function _getCategoryUrlKeySelect()
    {
        $urlKeyAttribute = $this->_getEavConfig()->getAttribute(self::ENTITY_CATEGORY, 'url_key');
        $nameAttribute = $this->_getEavConfig()->getAttribute(self::ENTITY_CATEGORY, 'name');
        
        $select = $this->_getIndexAdapter()->select();
        // Initialize tables for fullfilment of url key index for categories
        $select
            // Data should be generated for each store view
            // And only for categories in its store group
            ->from(array('root_index' => $this->getTable(self::ROOT_CATEGORY)), array())
            ->join(
                array('category' => $this->getTable('catalog/category')),
                'category.path LIKE root_index.path',
                array()
            )
            ->joinLeft(
                array('original' => $this->getTable(self::CATEGORY_URL_KEY)),
                'original.category_id = category.entity_id AND original.store_id = root_index.store_id',
                array()
            )
            // Name attribute values retrieval (for default and store)
            ->joinLeft(array('name_default' => $nameAttribute->getBackendTable()), 
                   'name_default.entity_id = category.entity_id' 
                   . ' AND name_default.store_id = 0'
                   . $this->_quoteInto(
                       ' AND name_default.attribute_id = ?',
                       $nameAttribute->getAttributeId()),
                   array())
            ->joinLeft(array('name_store' => $nameAttribute->getBackendTable()), 
                   'name_store.entity_id = category.entity_id' 
                   . ' AND name_store.store_id = root_index.store_id'
                   . $this->_quoteInto(
                       ' AND name_store.attribute_id = ?',
                       $nameAttribute->getAttributeId()),
                   array())
            // Url key attribute retrieval (for default and store)
            ->joinLeft(array('url_key_default' => $urlKeyAttribute->getBackendTable()), 
                   'url_key_default.entity_id = category.entity_id' 
                   . ' AND url_key_default.store_id = 0'
                   . $this->_quoteInto(
                       ' AND url_key_default.attribute_id = ?',
                       $urlKeyAttribute->getAttributeId()),
                   array())
            ->joinLeft(array('url_key_store' => $urlKeyAttribute->getBackendTable()), 
                   'url_key_store.entity_id = category.entity_id' 
                   . ' AND url_key_store.store_id = root_index.store_id'
                   . $this->_quoteInto(
                       ' AND url_key_store.attribute_id = ?',
                       $urlKeyAttribute->getAttributeId()),
                   array());

        $urlKeySourceExpr =  new Zend_Db_Expr(
            'IFNULL(' 
                  . ' IFNULL(url_key_store.value, url_key_default.value), ' 
                  . ' IFNULL(name_store.value, name_default.value) '
            . ')'
        );
        
        $columns = array(
            'store_id' => 'root_index.store_id',
            'category_id' => 'category.entity_id',
            'level' => 'category.level',
            'url_key_source' => $urlKeySourceExpr,
            'updated' => new Zend_Db_Expr(
                'IF(original.category_id IS NULL, 1, ' 
                . $urlKeySourceExpr . ' != original.url_key_source)'
            )
        );
        
        $select->columns($columns);
            
        Mage::dispatchEvent(
            'ecomdev_urlrewrite_indexer_get_category_url_key_select', 
            array('select' => $select, 'columns' => $columns, 'resource' => $this)
        );
        
        return $select;
    }
    
    /**
     * Prepares product url key select
     * 
     * @return Varien_Db_Select
     */
    protected function _getProductUrlKeySelect()
    {
        $urlKeyAttribute = $this->_getEavConfig()->getAttribute(self::ENTITY_PRODUCT, 'url_key');
        $nameAttribute = $this->_getEavConfig()->getAttribute(self::ENTITY_PRODUCT, 'name');
        
        $select = $this->_getIndexAdapter()->select();
        // Initialize tables for fullfilment of url key index for categories
        $select
            ->from(array('product' => $this->getTable('catalog/product')), array())
            // Data should be generated only for products that are assigned for that are available on the website
            ->join(
                array('product_website' => $this->getTable('catalog/product_website')), 
                'product_website.product_id = product.entity_id', 
                array())
            ->join(
                array('store' => $this->getTable('core/store')), 
               'store.website_id = product_website.website_id',
                array())
            ->joinLeft(
                array('original' => $this->getTable(self::PRODUCT_URL_KEY)),
                'original.product_id = product.entity_id AND original.store_id = store.store_id',
                array()
            )
            // Name attribute values retrieval (for default and store)
            ->join(array('name_default' => $nameAttribute->getBackendTable()), 
                   'name_default.entity_id = product.entity_id' 
                   . ' AND name_default.store_id = 0'
                   . $this->_quoteInto(
                       ' AND name_default.attribute_id = ?',
                       $nameAttribute->getAttributeId()),
                   array())
            ->joinLeft(array('name_store' => $nameAttribute->getBackendTable()), 
                   'name_store.entity_id = product.entity_id' 
                   . ' AND name_store.store_id = store.store_id'
                   . $this->_quoteInto(
                       ' AND name_store.attribute_id = ?',
                       $nameAttribute->getAttributeId()),
                   array())
            // Url key attribute retrieval (for default and store)
            ->joinLeft(array('url_key_default' => $urlKeyAttribute->getBackendTable()), 
                   'url_key_default.entity_id = product.entity_id' 
                   . ' AND url_key_default.store_id = 0'
                   . $this->_quoteInto(
                       ' AND url_key_default.attribute_id = ?',
                       $urlKeyAttribute->getAttributeId()),
                   array())
            ->joinLeft(array('url_key_store' => $urlKeyAttribute->getBackendTable()), 
                   'url_key_store.entity_id = product.entity_id' 
                   . ' AND url_key_store.store_id = store.store_id'
                   . $this->_quoteInto(
                       ' AND url_key_store.attribute_id = ?',
                       $urlKeyAttribute->getAttributeId()),
                   array());

        $urlKeySourceExpr =  new Zend_Db_Expr(
            'IFNULL(' 
                  . ' IFNULL(url_key_store.value, url_key_default.value), ' 
                  . ' IFNULL(name_store.value, name_default.value) '
            . ')'
        );
        
        $columns = array(
            'store_id' => 'store.store_id',
            'product_id' => 'product.entity_id',
            'url_key_source' => $urlKeySourceExpr,
            'updated' => new Zend_Db_Expr(
                'IF(original.product_id IS NULL, 1, ' 
                . $urlKeySourceExpr . ' != original.url_key_source)'
            )
        );
        
        $select->columns($columns);
            
        Mage::dispatchEvent(
            'ecomdev_urlrewrite_indexer_get_product_url_key_select', 
            array('select' => $select, 'columns' => $columns, 'resource' => $this)
        );
        
        return $select;
    }
    
    /**
     * Prepares category request path select
     * 
     * @return Varien_Db_Select
     */
    protected function _getCategoryRequestPathSelect()
    {
        $select = $this->_getIndexAdapter()->select();
        
        // Initialize tables for fullfilment of request path index for categories
        $select
            // Generate path index from already generated url url keys 
            ->from(
                array('url_key' => $this->getTable(self::CATEGORY_URL_KEY)), 
                array()
            )
            ->joinLeft(
                array('relation' => $this->getTable(self::CATEGORY_RELATION)),
                $this->_quoteInto(
                   'relation.related_id = url_key.category_id AND relation.type = ?', self::RELATION_TYPE_NESTED
                ),
                array()
            )
            ->joinLeft(
                array('parent_url_key' => $this->getTable(self::CATEGORY_URL_KEY)), 
                'parent_url_key.store_id = url_key.store_id AND parent_url_key.category_id = relation.category_id',
                array()
            );

        $requestPathExpr = $this->_quoteInto(
            'TRIM(LEADING ? FROM CONCAT(' 
                . 'IFNULL(GROUP_CONCAT(parent_url_key.url_key ORDER BY parent_url_key.level ASC SEPARATOR ?), ?), ' 
                . '?, url_key.url_key' 
            . '))',
            '/'
        );
        $columns = array(
            'store_id' => 'url_key.store_id',
            'id_path' => new Zend_Db_Expr($this->_quoteInto(
                sprintf(
                    $this->_pathGenerateExpr[self::ID_PATH_CATEGORY], 
                    'url_key.category_id'
                ),
                self::ID_PATH_CATEGORY
            )),
            'category_id' => 'url_key.category_id',
            'level' => 'url_key.level', 
            'request_path' => new Zend_Db_Expr($this->_quoteInto(
                sprintf('SUBSTRING(%s FROM 1 FOR ?)', $requestPathExpr),
                self::MAX_LENGTH_URL_PATH
            )),
            'updated' => new Zend_Db_Expr('1')
        );

        $select
            ->columns($columns)
            ->group(array('url_key.store_id', 'url_key.category_id'));

        Mage::dispatchEvent(
            'ecomdev_urlrewrite_indexer_get_category_request_path_select', 
            array('select' => $select, 'columns' => $columns, 'resource' => $this)
        );
        
        return $select;
    }
    
    /**
     * Prepares product request path select
     * 
     * @param string $category category direct, category nested or root level rewrite?
     * @return Varien_Db_Select
     */
    protected function _getProductRequestPathSelect($category = false)
    {
        $select = $this->_getIndexAdapter()->select();
        
        // Initialize tables for fullfilment of request path index for products
        if ($category !== false) {
            $select->useStraightJoin(true)
                ->from(
                    array('category' => $this->getTable(self::CATEGORY_REQUEST_PATH)),
                    array()
                );
            
            if ($category === self::RELATION_TYPE_NESTED) {
                $joinCondition = 'category.category_id = category_product.category_id ';
            } else {
                $select->join(
                    array('category_relation' => $this->getTable(self::CATEGORY_RELATION)),
                    $this->_quoteInto(
                       'category_relation.category_id = category.category_id and category_relation.type = ?', 
                        self::RELATION_TYPE_ANCHOR
                    ),
                    array()
                );
                $joinCondition = 'category.category_id = category_relation.related_id ';
            }
            
            // If we need to gather url path for product category association
            $select
                ->join(
                    array('category_product' => $this->getTable('catalog/category_product')), 
                    $joinCondition,
                    array()
                );
            
            $select
                ->join(
                    array('url_key' => $this->getTable(self::PRODUCT_URL_KEY)), 
                    'url_key.store_id = category.store_id AND url_key.product_id = category_product.product_id',
                    array()
                );
        } else {
            $select
                ->from(array('url_key' => $this->getTable(self::PRODUCT_URL_KEY)), array());
        }
                
        $requestPathExpr = $this->_quoteInto( 
            'CONCAT(category.request_path, ?, url_key.url_key)',
            '/'
        );
        
        $idPathExprKey = ($category !==false ? self::ID_PATH_PRODUCT_CATEGORY : self::ID_PATH_PRODUCT);
        
        
        $columns = array(
            'store_id' => 'url_key.store_id',
            'id_path' => new Zend_Db_Expr($this->_quoteInto(
                sprintf(
                    $this->_pathGenerateExpr[$idPathExprKey], 
                    'url_key.product_id',
                    'category.category_id'
                ),
                $idPathExprKey
            )),
            'product_id' => 'url_key.product_id',
            'category_id' => ($category === false ? new Zend_Db_Expr('NULL') : 'category.category_id'),
            'request_path' => new Zend_Db_Expr($this->_quoteInto(
                sprintf(
                    'SUBSTRING(%s FROM 1 FOR ?)', 
                    $category === false ? 'url_key.url_key' : $requestPathExpr
                ),
                self::MAX_LENGTH_URL_PATH
            )),
            'updated' => new Zend_Db_Expr('1')
        );

        $select->columns($columns);
        
        Mage::dispatchEvent(
            'ecomdev_urlrewrite_indexer_get_product_request_path_select', 
            array('select' => $select, 'is_category' => $category)
        );
        
        return $select;
    }
    
    /**
     * Generate root category list
     * 
     * @return array
     */
    public function getRootCategories()
    {
        
        $select = $this->_getReadAdapter()->select()
            ->from($this->getTable(self::ROOT_CATEGORY));
        
        return $this->_getReadAdapter()->fetchPairs($select);
    }
    
    /**
     * Generates root categories index
     * 
     * @return int
     */
    protected function _generateRootCategoryIndex()
    {
        $this->_getIndexAdapter()->truncate($this->getTable(self::ROOT_CATEGORY));
        
        $select = $this->_getWriteAdapter()->select();
        
        $select
            ->from(array('store' => $this->getTable('core/store')), 'store_id')
            ->join(
                array('store_group' => $this->getTable('core/store_group')),
                'store_group.group_id = store.group_id',
                array()
            )
            ->join(
                array('category' => $this->getTable('catalog/category')),
                'category.level = 1 AND category.entity_id = store_group.root_category_id', 
                array('path' => new Zend_Db_Expr($this->_quoteInto(
                    'CONCAT(path, ?)', '/%'
                )))
            );
        
        $this->insertFromSelect($select, $this->getTable(self::ROOT_CATEGORY), $this->_getColumnsFromSelect($select));
        return $this;
    }
    
    /**
     * Returns data from category relations index table. 
     * Result is an array where key is category id and value is an array of related ids
     * 
     * @param array|int $categoryIds
     * @param string $type
     * @return array
     */
    public function getCategoryRelations($categoryIds, $type)
    {
        if (!is_array($categoryIds)) {
            $categoryIds = array($categoryIds);
        }
        
        $select = $this->_getRelatedCategoryIdsSelect($categoryIds, $type)
            ->columns('category_id');
        
        $result = array();
        foreach ($this->_getReadAdapter()->fetchAll($select) as $relation) {
            $result[$relation['category_id']][] = $relation['related_id'];
        }
        
        return $result;
    }
    
    /**
     * Return select for retrieving all affected category ids for index
     * 
     * @param array $categoryIds
     * @param string $type relation type
     * @return Varien_Db_Select
     */
    protected function _getRelatedCategoryIdsSelect(array $categoryIds, $type = self::RELATION_TYPE_NESTED)
    {
        $select = $this->_getIndexAdapter()->select()
            ->from(array('relation' => $this->getTable(self::CATEGORY_RELATION)), 'related_id')
            ->where('relation.category_id IN(?)', $categoryIds)
            ->where('relation.type = ?', $type);
        
        Mage::dispatchEvent(
            'ecomdev_urlrewrite_indexer_get_related_category_ids_select', 
            array('select' => $select)
        );
        
        return $select;
    }
    
    /**
     * Generates category url path index table data
     * 
     * @param array|null $categoryIds
     * @return EcomDev_UrlRewrite_Model_Mysql4_Indexer
     */
    protected function _generateCategoryRelationIndex(array $categoryIds = null)
    {
        if ($categoryIds !== null) {
            $childSelect = $this->_getIndexAdapter()->select();
            $childSelect
                ->from(array('main' => $this->getTable('catalog/category')), array())
                ->join(array('child' => $this->getTable('catalog/category')), 
                   'child.path LIKE CONCAT(main.path, \'/%\')', 
                   array('entity_id'))
                ->where('main.children_count > ?', 0)
                ->where('child.children_count > ?', 0)
                ->where('main.entity_id IN(?)', $categoryIds);
            
            $categoryIds = array_merge(
                $categoryIds, 
                $this->_getIndexAdapter()->fetchCol($childSelect)
            );
        }
        
        Mage::dispatchEvent(
            'ecomdev_urlrewrite_indexer_generate_category_relation_index_before', 
            array('resource' => $this, 'category_ids' => $categoryIds)
        );
        
        $condition = array();
        
        if ($categoryIds !== null) {
            $condition['category_id IN(?)'] = $categoryIds;
        }
        
        $this->_getIndexAdapter()->delete(
            $this->getTable(self::CATEGORY_RELATION), 
            $condition
        );
        
        // Generate nested categories index
        $select = $this->_getIndexAdapter()->select();
        $select->from(array('main' => $this->getTable('catalog/category')), array('category_id' => 'entity_id'))
            ->join(array('child' => $this->getTable('catalog/category')), 
                   'child.path LIKE CONCAT(main.path, \'/%\')', 
                   array('related_id' => 'entity_id'))
            ->columns(array('type' => new Zend_Db_Expr($this->_quoteInto('?', self::RELATION_TYPE_NESTED))));
        
        $select->where('main.level > ?', 1);
        $select->where('main.children_count > ?', 0);
        
        if ($categoryIds !== null) {
            $select->where('main.entity_id IN(?)', $categoryIds);
        }
        
        Mage::dispatchEvent(
            'ecomdev_urlrewrite_indexer_generate_category_relation_index_nested', 
            array('select' => $select, 'category_ids' => $categoryIds)
        );
        
        $this->_getIndexAdapter()->query(
            $select->insertFromSelect(
                $this->getTable(self::CATEGORY_RELATION), 
                array('category_id', 'related_id', 'type')
            )
        );
        
        // Generate index for achor relations
        $select->reset();
        $isAnchorAttribute = $this->_getEavConfig()->getAttribute(self::ENTITY_CATEGORY, 'is_anchor');
        
        $select
            ->from(
                array('relation' => $this->getTable(self::CATEGORY_RELATION)),
                array(
                    'category_id', 'related_id',
                    new Zend_Db_Expr($this->_quoteInto('?', self::RELATION_TYPE_ANCHOR))
                )
            )
            ->join(
                array('is_anchor' => $isAnchorAttribute->getBackendTable()), 
                'is_anchor.entity_id = relation.category_id' 
                . ' AND is_anchor.store_id = 0'
                . $this->_quoteInto(' AND is_anchor.attribute_id = ?', $isAnchorAttribute->getAttributeId()),
                array())
            ->where('is_anchor.value = ?', 1);
        
        if ($categoryIds !== null) {
            $select->where('relation.category_id IN(?)', $categoryIds);
        }

        Mage::dispatchEvent(
            'ecomdev_urlrewrite_indexer_generate_category_relation_index_anchor', 
            array('select' => $select, 'category_ids' => $categoryIds)
        );
        
        $this->_getIndexAdapter()->query(
            $select->insertFromSelect(
                $this->getTable(self::CATEGORY_RELATION), 
                array('category_id', 'related_id', 'type')
            )
        );
        
        Mage::dispatchEvent(
            'ecomdev_urlrewrite_indexer_generate_category_relation_index_after', 
            array('resource' => $this, 'category_ids' => $categoryIds)
        );
        return $this;
    }
    
    /**
     * Returns data from category request path index table. 
     * Result is an array where key is category id and value is an array of request path 
     * for each store view where its applicable
     * 
     * @param array|int $categoryIds
     * @return array
     */
    public function getCategoryRequestPathIndex($categoryIds)
    {
        if (!is_array($categoryIds)) {
            $categoryIds = array($categoryIds);
        }
        
        $select = $this->_getIndexAdapter()->select()
            ->from($this->getTable(self::CATEGORY_REQUEST_PATH))
            ->where('category_id IN(?)', $categoryIds);
        
        $result = array();
        foreach ($this->_getReadAdapter()->fetchAll($select) as $requestPath) {
            $result[$requestPath['category_id']][$requestPath['store_id']] = $requestPath['request_path'];
        }
        
        return $result;
    }
    
    /**
     * Get all category ids including releated
     * 
     * @param array $categoryIds
     * @return array
     */
    protected function _getCategoryIds(array $categoryIds, $type = self::RELATION_TYPE_NESTED)
    {
        $select = $this->_getRelatedCategoryIdsSelect($categoryIds, $type);
        return array_merge(
            $this->_getReadAdapter()->fetchCol($select), 
            $categoryIds
        );
    }
    
    /**
     * Update category url key index 
     * 
     * @param array $categoryIds
     * @return EcomDev_UrlRewrite_Model_Mysql4_Indexer
     */
    protected function _generateCategoryUrlKeyIndex(array $categoryIds = null)
    {
        $this->beginTransaction();
        $select = $this->_getCategoryUrlKeySelect();
        
        if ($categoryIds !== null) {
            $select->where('category.entity_id IN(?)', $categoryIds);
        }
        
        $result = $this->_getIndexAdapter()->query($select->insertFromSelect(
            $this->getTable(self::CATEGORY_URL_KEY), 
            $this->_getColumnsFromSelect($select)
        ));
        
        if ($result->rowCount()) {
            $this->_getIndexAdapter()->update(
                $this->getTable(self::CATEGORY_URL_KEY),
                array(
                    'url_key' => new Zend_Db_Expr('ECOMDEV_CLEAN_URL_KEY(url_key_source)'),
                    'updated' => 0
                ),
                array(
                    'updated = ?' => 1
                )
            );
        }
        
        // Clear not existent rows
        $select->reset()
            ->from(array('url_key' => $this->getTable(self::CATEGORY_URL_KEY)), array())
            ->joinLeft(
                array('root_category' => $this->getTable(self::ROOT_CATEGORY)),
                'root_category.store_id = url_key.store_id',
                array()
            )
            ->joinLeft(
                array('category' => $this->getTable('catalog/category')),
                'category.entity_id = url_key.category_id  AND category.path LIKE root_category.path',
                array()
            )
            ->where('category.entity_id IS NULL');
        
        $this->_getIndexAdapter()->query(
            $select->deleteFromSelect('url_key')
        );
        $this->commit();
        
        return $this;
    }
    
    /**
     * Generates category url path index table data
     * 
     * @param array|null $categoryIds
     * @return EcomDev_UrlRewrite_Model_Mysql4_Indexer
     */
    protected function _generateCategoryRequestPathIndex(array $categoryIds = null)
    {
        Mage::dispatchEvent(
            'ecomdev_urlrewrite_indexer_generate_category_url_path_index_before', 
            array('resource' => $this, 'category_ids' => $categoryIds)
        );
        
        $this->_generateCategoryRelationIndex($categoryIds);
        
        if ($categoryIds !== null) {
            $categoryIds = $this->_getCategoryIds($categoryIds);
        } else {
            $condition = '';
        }
        
        $this->_generateCategoryUrlKeyIndex($categoryIds);
        
        if ($categoryIds === null) {
            $this->_getIndexAdapter()->truncate(
                $this->getTable(self::CATEGORY_REQUEST_PATH)
            );
        } else {
            $this->_getIndexAdapter()->delete(
                $this->getTable(self::CATEGORY_REQUEST_PATH),
                array('category_id IN(?)' => $categoryIds)
            );
        }

        $select = $this->_getCategoryRequestPathSelect();
        
        if ($categoryIds !== null) {
            $select->where('url_key.category_id IN(?)', $categoryIds);
        }
        
        $this->_getIndexAdapter()->query(
            $select->insertFromSelect(
                $this->getTable(self::CATEGORY_REQUEST_PATH), 
                $this->_getColumnsFromSelect($select),
                false
            )
        );

        Mage::dispatchEvent(
            'ecomdev_urlrewrite_indexer_generate_category_url_path_index_after', 
            array('resource' => $this, 'category_ids' => $categoryIds)
        );
        
        return $this;
    }
    
    /**
     * Returns data from product request path index table. 
     * Result is an array where key is product id and value is a multidimensional 
     * array of store-category-request-path information. 
     * 
     * For store root it has record with 0 category id
     * 
     * @param array|int $productIds
     * @return array
     */
    public function getProductRequestPathIndex($productIds)
    {
        if (!is_array($productIds)) {
            $productIds = array($productIds);
        }
        
        $select = $this->_getIndexAdapter()->select()
            ->from($this->getTable(self::PRODUCT_REQUEST_PATH))
            ->where('product_id IN(?)', $productIds);
        
        $result = array();
        foreach ($this->_getReadAdapter()->fetchAll($select) as $requestPath) {
            $categoryKey = (isset($requestPath['category_id']) ? $requestPath['category_id'] : 0);
            $result[$requestPath['product_id']][$requestPath['store_id']][$categoryKey] = $requestPath['request_path'];
        }
        
        return $result;
    }
    
    /**
     * Update product url key index 
     * 
     * @param array|Varien_Db_Select|null $productIds
     * @return EcomDev_UrlRewrite_Model_Mysql4_Indexer
     */
    protected function _generateProductUrlKeyIndex($productIds = null)
    {
        $this->beginTransaction();
        $select = $this->_getProductUrlKeySelect();
        
        if ($productIds !== null) {
            $select->where('product.entity_id IN(?)', $productIds);
        }
        
        $result = $this->_getIndexAdapter()->query($select->insertFromSelect(
            $this->getTable(self::PRODUCT_URL_KEY), 
            $this->_getColumnsFromSelect($select)
        ));
        
        if ($result->rowCount()) {
            $this->_getIndexAdapter()->update(
                $this->getTable(self::PRODUCT_URL_KEY),
                array(
                    'url_key' => new Zend_Db_Expr('ECOMDEV_CLEAN_URL_KEY(url_key_source)'),
                    'updated' => 0
                ),
                array(
                    'updated = ?' => 1
                )
            );
        }
        
        // Clear not existent rows
        $select->reset()
            ->from(array('url_key' => $this->getTable(self::PRODUCT_URL_KEY)), array())
            ->joinLeft(
                array('store' => $this->getTable('core/store')),
                'store.store_id = url_key.store_id',
                array()
            )
            ->joinLeft(
                array('product_website' => $this->getTable('catalog/product_website')),
                'product_website.product_id = url_key.product_id AND store.website_id = product_website.website_id',
                array()
            )
            ->where('product_website.product_id IS NULL');
        
        $this->_getIndexAdapter()->query(
            $select->deleteFromSelect('url_key')
        );
        $this->commit();
        
        return $this;
    }
    
    /**
     * Generates product url path index by category ids or by product ids
     * If no parameters specified, rebuilds all index
     * 
     * @param array|null $categoryIds
     * @param array|null $productIds
     * @return EcomDev_UrlRewrite_Model_Mysql4_Indexer
     */
    protected function _generateProductRequestPathIndex(array $categoryIds = null, array $productIds = null)
    {
        Mage::dispatchEvent(
            'ecomdev_urlrewrite_indexer_generate_product_url_path_index_before', 
            array('resource' => $this, 'category_ids' => $categoryIds, 'product_ids' => $productIds)
        );
        if ($categoryIds !== null) {
            $categoryIds = $this->_getCategoryIds($categoryIds);
            $conditionSelect = $this->_getIndexAdapter()->select();
            $conditionSelect
                ->from(
                    array('category_product' => $this->getTable('catalog/category_product')), 
                    array('product_id')
                )
                ->where('category_product.category_id IN(?)', $categoryIds);
            
            $condition = array(
                'product_id IN(?)' => $conditionSelect
            );
        } elseif ($productIds !== null) {
            $conditionSelect = $productIds;
            $condition = array(
                'product_id IN(?)' => $conditionSelect
            );
        } else {
            $condition = '';
            $conditionSelect = null;
        }
        
        $this->_generateProductUrlKeyIndex($conditionSelect);
        
        if ($condition == '') {
            $this->_getIndexAdapter()->truncate($this->getTable(self::PRODUCT_REQUEST_PATH));
        } else {
            $this->_getIndexAdapter()->delete($this->getTable(self::PRODUCT_REQUEST_PATH), $condition);
        }
        
        $this->beginTransaction();
        
        // Initialize rewrite request path data
        foreach (array(false, self::RELATION_TYPE_NESTED, self::RELATION_TYPE_ANCHOR) as $categoryRewriteFlag) {
            $select = $this->_getProductRequestPathSelect($categoryRewriteFlag);
            
            if (isset($conditionSelect)) {
                $select->where('url_key.product_id IN(?)', $conditionSelect);
            }
            
            $this->_getIndexAdapter()->query(
                $select->insertIgnoreFromSelect(
                    $this->getTable(self::PRODUCT_REQUEST_PATH),
                    $this->_getColumnsFromSelect($select)
                )
            );
        }
        
        $this->commit();
        
        Mage::dispatchEvent(
            'ecomdev_urlrewrite_indexer_generate_product_url_path_index_after', 
            array('resource' => $this, 'category_ids' => $categoryIds, 'product_ids' => $productIds)
        );
        return $this;
    }
    
    /**
     * This method clears invalid rewrites from core url rewrite
     * 
     * @return EcomDev_UrlRewrite_Model_Mysql4_Indexer
     */
    public function clearInvalidRewrites()
    {
        $this->_clearInvalidCategoryRewrites()
            ->_clearInvalidProductRewrites();
        return $this;
    }
    
    /**
     * Clears dirty records in url rewrite for invalid category-store combination
     * 
     * @return EcomDev_UrlRewrite_Model_Mysql4_Indexer
     */
    protected function _clearInvalidCategoryRewrites()
    {
        $select = $this->_getIndexAdapter()->select();
        $select
            ->from(array('rewrite' => $this->getTable('core/url_rewrite')), 'url_rewrite_id')
            ->join(
                array('category' => $this->getTable('catalog/category')),
                'category.entity_id = rewrite.category_id'
            )
            ->join(
                array('root_category' => $this->getTable(self::ROOT_CATEGORY)), 
                'root_category.store_id = rewrite.store_id '
            )
            // If category is not in a store root category where rewrite is. 
            ->where('category.path NOT LIKE root_category.path');

        Mage::dispatchEvent(
            'ecomdev_urlrewrite_indexer_clear_invalid_category_rewrites_select', 
            array('resource' => $this, 'select' => $select)
        );

        $this->_getIndexAdapter()->query(
            $select->deleteFromSelect('rewrite')
        );
        
        return $this;
    }
    
    /**
     * Clears dirty records in url rewrite for invalid product-website combination
     * 
     * @return EcomDev_UrlRewrite_Model_Mysql4_Indexer
     */
    protected function _clearInvalidProductRewrites()
    {
        $select = $this->_getIndexAdapter()->select();
        $select
            ->from(array('rewrite' => $this->getTable('core/url_rewrite')), 'url_rewrite_id')
            ->join(
                array('store' => $this->getTable('core/store')), 
                'store.store_id = rewrite.store_id '
            )
            ->joinLeft(
                array('product_website' => $this->getTable('catalog/product_website')), 
                'product_website.website_id = store.website_id '
                . ' AND product_website.product_id = rewrite.product_id'
            )
            // If product is not assigned to a website where url rewrite is. 
            ->where('rewrite.product_id IS NOT NULL')
            ->where('product_website.product_id IS NULL');
            
        Mage::dispatchEvent(
            'ecomdev_urlrewrite_indexer_clear_invalid_product_rewrites_select_root', 
            array('resource' => $this, 'select' => $select)
        );

        $this->_getIndexAdapter()->query(
            $select->deleteFromSelect('rewrite')
        );
        
        $select
            ->reset()
            ->from(array('rewrite' => $this->getTable('core/url_rewrite')), 'url_rewrite_id')
            ->joinLeft(
                array('product_category' => $this->getTable('catalog/category_product')), 
                'product_category.category_id =  rewrite.category_id '
                . ' AND product_category.product_id = rewrite.product_id'
            )
            // If product is not assigned to a category where url rewrite is. 
            ->where('rewrite.product_id IS NOT NULL')
            ->where('rewrite.category_id IS NOT NULL')
            ->where('product_category.category_id IS NULL');
            
        Mage::dispatchEvent(
            'ecomdev_urlrewrite_indexer_clear_invalid_product_rewrites_select_category', 
            array('resource' => $this, 'select' => $select)
        );

        $this->_getIndexAdapter()->query(
            $select->deleteFromSelect('rewrite')
        );
        
        return $this;
    }
    
    /**
     * Returns list of suffixes for every store view 
     * if it differs from default. 
     * 
     * @param string $entity
     * @return array
     */
    public function getUrlSuffixList($entity)
    {
        $callbacks = array(
            self::ENTITY_CATEGORY => array(
                Mage::helper('catalog/category'),
                'getCategoryUrlSuffix'
            ),
            self::ENTITY_PRODUCT => array(
                Mage::helper('catalog/product'),
                'getProductUrlSuffix'
            )
        );
        
        if (!isset($callbacks[$entity])) {
            $entity = self::ENTITY_CATEGORY;
        }
        
        $result[] = call_user_func($callbacks[$entity], Mage_Core_Model_App::ADMIN_STORE_ID);
        
        foreach (Mage::app()->getStores() as $store) {
            $suffix = call_user_func($callbacks[$entity], $store->getId());
            if ($suffix != $result[0]) {
                $result[$store->getId()] = $suffix;
            }
        }
        
        return $result;
    }
    
    /**
     * Return unique url suffix list 
     * 
     * @return array
     */
    public function getUniqueUrlSuffixList()
    {
        $result = array_values($this->getUrlSuffixList(self::ENTITY_CATEGORY));
        $result = array_merge($result, array_values($this->getUrlSuffixList(self::ENTITY_PRODUCT)));
        
        return array_filter(array_unique($result));
    }
    
    /**
     * Updates data from core url rewrite table
     * 
     * Created to keep information up to date
     * 
     * @return EcomDev_UrlRewrite_Model_Mysql4_Indexer
     */
    protected function _importFromRewrite()
    {
        $select = $this->_getIndexAdapter()->select();
        $select
            ->from(array('core_rewrite' => $this->getTable('core/url_rewrite')), array())
            ->joinLeft(
                array('rewrite_index' => $this->getTable(self::REWRITE)),
                'rewrite_index.store_id = core_rewrite.store_id AND rewrite_index.id_path = core_rewrite.id_path',
                array()
            )
            ->where('rewrite_index.store_id IS NULL');

        $suffixExpr = 'core_rewrite.request_path';
        
        foreach ($this->getUniqueUrlSuffixList() as $suffix) {
            $suffixExpr = $this->_quoteInto('TRIM(TRAILING ? FROM ' . $suffixExpr . ')', $suffix);
        }
        
        $duplicateKeyExpr = new Zend_Db_Expr(
            $suffixExpr
        );
        
        $columns = array(
            'rewrite_id' => 'url_rewrite_id',
            'id_path'    => 'id_path',
            'store_id'   => 'store_id',
            'category_id' => 'category_id',
            'product_id' => 'product_id',
            'duplicate_key' => $duplicateKeyExpr,
            'original_request_path' => 'request_path',
            'request_path' => 'request_path',
            'target_path' => 'target_path',
            'updated'     => new Zend_Db_Expr('1')
        );
        
        $select->columns($columns);
        
        Mage::dispatchEvent(
            'ecomdev_urlrewrite_indexer_import_from_rewrite_select_insert', 
            array('resource' => $this, 'select' => $select, 'columns' => $columns)
        );
        
        $this->_getIndexAdapter()->query(
            $select->insertFromSelect(
                $this->getTable(self::REWRITE), 
                $this->_getColumnsFromSelect($select),
                false
            )
        );
        
        foreach (array('id_path', 'store_id', 'target_path', 
                       'category_id', 'product_id') as $key) {
            unset($columns[$key]);
        }
        
        $select->reset()
            ->join(
                array('core_rewrite' => $this->getTable('core/url_rewrite')),
                'core_rewrite.store_id = rewrite_index.store_id AND core_rewrite.id_path = rewrite_index.id_path',
                $columns
            )
            ->where('rewrite_index.updated = ?', 0)
            ->where('rewrite_index.request_path != core_rewrite.request_path');
        
        $this->_getIndexAdapter()->query(
            $select->crossUpdateFromSelect(
                array('rewrite_index' => $this->getTable(self::REWRITE)) 
            )
        );

        $this->_importDuplicatedKeys();
        $this->_finalizeRowsUpdate(self::REWRITE);
        return $this;
    }
    
    /**
     * Marks records as updated in a particular index table
     * 
     * @return EcomDev_UrlRewrite_Model_Mysql4_Indexer
     */
    protected function _finalizeRowsUpdate($table)
    {
        $this->_getIndexAdapter()->update(
            $this->getTable($table),
            array('updated' => 0),
            array('updated = ?' => 1)
        );
    }
    
    /**
     * Updates duplicate keys information that was inserted to rewrite table
     * 
     * @return EcomDev_UrlRewrite_Model_Mysql4_Indexer
     */
    protected function _importDuplicatedKeys()
    {
        $select = $this->_getIndexAdapter()->select();
        $select
            ->from(
                array('rewrite' => $this->getTable(self::REWRITE)), 
                array()
            )            
            ->where('rewrite.updated=?', 1)
            ->where('rewrite.duplicate_index IS NULL')
            ->where('rewrite.duplicate_key REGEXP ?', '^[0-9a-z\\-]+-[0-9]+$');
            
        $columns = array(
            'store_id' => 'store_id',
            'id_path'  => 'id_path',
            'duplicate_key' => new Zend_Db_Expr($this->_quoteInto(
                'SUBSTR('
                   . 'rewrite.duplicate_key, 1, ' 
                   . 'LENGTH(rewrite.duplicate_key) - 1 -' 
                   . 'LENGTH(SUBSTRING_INDEX(rewrite.duplicate_key, ?, -1))'
                 . ')',
                 '-'
            )),
            'duplicate_index' => new Zend_Db_Expr(
                $this->_quoteInto('SUBSTRING_INDEX(rewrite.duplicate_key, ?, -1)', '-')
            ),
            'is_duplicate' => new Zend_Db_Expr('0')
        );
        
        $select->columns($columns);
        
        Mage::dispatchEvent(
            'ecomdev_urlrewrite_indexer_import_duplicated_keys_select', 
            array('resource' => $this, 'select' => $select, 'columns' => $columns)
        );
        
        $result = $this->_getIndexAdapter()->query(
            $select->insertIgnoreFromSelect(
                $this->getTable(self::DUPLICATE), 
                $this->_getColumnsFromSelect($select)
            )
        );
        
        $select->reset()
            ->join(
                array('duplicate' => $this->getTable(self::DUPLICATE)),
                'duplicate.id_path != original.id_path'
                . ' AND duplicate.store_id = original.store_id '
                . ' AND duplicate.duplicate_key = original.duplicate_key ',
                array('is_duplicate' => new Zend_Db_Expr('1'))
            );
        
        $this->_getIndexAdapter()->query(
            $select->crossUpdateFromSelect(array('original' => $this->getTable(self::DUPLICATE)))
        );
        
        $this->_updateRewriteDuplicates();
        
        return $this;
    }
    
    /**
     * Update rewrites from duplicates
     * 
     * @return EcomDev_UrlRewrite_Model_Mysql4_Indexer
     */
    protected function _updateRewriteDuplicates()
    {
        $select = $this->_getIndexAdapter()->select();
        
        $columns = array(
            'duplicate_key' => 'duplicate_key',
            'duplicate_index' => 'duplicate_index'
        );
        
        $select->join(
            array('duplicate' => $this->getTable(self::DUPLICATE)),
            'duplicate.store_id = rewrite.store_id ' 
            . 'AND duplicate.id_path = rewrite.id_path ' 
            . 'AND duplicate.is_duplicate = 1',
            $columns
        );
        
        $this->_getIndexAdapter()->query(
            $select->crossUpdateFromSelect(array('rewrite' => $this->getTable(self::REWRITE)))
        );
        
        $this->_getIndexAdapter()->truncate($this->getTable(self::DUPLICATE));
        $this->_getIndexAdapter()->truncate($this->getTable(self::DUPLICATE_INCREMENT));
        $this->_getIndexAdapter()->truncate($this->getTable(self::DUPLICATE_AGGREGATE));
        return $this;
    }
    
    /**
     * Updates rewrites from category url path indexe
     * 
     * @return EcomDev_UrlRewrite_Model_Mysql4_Indexer
     */
    protected function _importFromCategoryRequestPath()
    {
        $select = $this->_getIndexAdapter()->select();
        $select
            ->from(
                array('request_path' => $this->getTable(self::CATEGORY_REQUEST_PATH)), 
                array()
            )
            ->joinLeft(
                array('rewrite' => $this->getTable(self::REWRITE)), 
                'rewrite.store_id = request_path.store_id' 
                . ' AND rewrite.id_path = request_path.id_path', 
                array()
            )
            ->where('request_path.updated = ?', 1);
        
        $columns = array(
            'store_id'    => 'request_path.store_id',
            'id_path'     => 'request_path.id_path',
            'category_id' => 'request_path.category_id',
            'rewrite_id'  => 'rewrite.rewrite_id',
            'target_path' => new Zend_Db_Expr($this->_quoteInto(
                sprintf(
                    $this->_pathGenerateExpr[self::TARGET_PATH_CATEGORY],
                    'request_path.category_id'
                ),
                self::TARGET_PATH_CATEGORY
            )),
            'duplicate_key' => 'request_path.request_path',
            'duplicate_index' => new Zend_Db_Expr($this->_quoteInto(
                'IF(rewrite.duplicate_index IS NOT NULL ' 
                    . ' AND SUBSTRING_INDEX(rewrite.duplicate_key, ?, -1) = SUBSTRING_INDEX(request_path.request_path, ?, -1), '
                    . ' rewrite.duplicate_index, '
                    . ' IF(request_path.request_path REGEXP \'[0-9]$\', 0, NULL))',
                '/'
            )),
            'updated' => new Zend_Db_Expr('1')
        );
        
        $select->columns($columns);
        
        $this->_getIndexAdapter()->query(
            $select->insertFromSelect($this->getTable(self::REWRITE), array_keys($columns))
        );
        
        $this->_finalizeRowsUpdate(self::CATEGORY_REQUEST_PATH);
        return $this;
    }
    
    /**
     * Updates rewrites from product url path indexe
     * 
     * @return EcomDev_UrlRewrite_Model_Mysql4_Indexer
     */
    protected function _importFromProductRequestPath()
    {
        // Import store root records first
        $select = $this->_getIndexAdapter()->select();
        $select
            ->from(
                array('request_path' => $this->getTable(self::PRODUCT_REQUEST_PATH)), 
                array()
            )
            ->joinLeft(
                array('rewrite' => $this->getTable(self::REWRITE)), 
                'rewrite.store_id = request_path.store_id' 
                . ' AND rewrite.id_path = request_path.id_path ',
                array()
            )
            ->where('request_path.updated = ?', 1)
            ->where('request_path.category_id IS NULL');

        $columns = array(
            'store_id'    => 'request_path.store_id',
            'id_path'     => 'id_path',
            'category_id' => 'request_path.category_id',
            'product_id' => 'request_path.product_id',
            'rewrite_id'  => 'rewrite.rewrite_id',
            'target_path' => new Zend_Db_Expr($this->_quoteInto(
                sprintf(
                    $this->_pathGenerateExpr[self::TARGET_PATH_PRODUCT], 
                    'request_path.product_id'
                ),
                self::TARGET_PATH_PRODUCT
            )),
            'duplicate_key' => 'request_path.request_path',
            'duplicate_index' => new Zend_Db_Expr($this->_quoteInto(
                'IF(rewrite.duplicate_index IS NOT NULL ' 
                    . ' AND SUBSTRING_INDEX(rewrite.duplicate_key, ?, -1) = SUBSTRING_INDEX(request_path.request_path, ?, -1), '
                    . ' rewrite.duplicate_index, '
                    . ' IF(request_path.request_path REGEXP \'[0-9]$\', 0, NULL))',
                '/'
            )),
            'updated' => new Zend_Db_Expr('1')
        );
        
        $select->columns($columns);
        
        $this->_getIndexAdapter()->query(
            $select->insertFromSelect(
                $this->getTable(self::REWRITE), 
                $this->_getColumnsFromSelect($select)
            )
        );
        
        // Import product and category url rewrite pair
        $select->reset(Varien_Db_Select::WHERE)
            ->reset(Varien_Db_Select::COLUMNS)
            ->where('request_path.updated = ?', 1)
            ->where('request_path.category_id IS NOT NULL');
        
        $columns['target_path'] = new Zend_Db_Expr($this->_quoteInto(
            sprintf(
                $this->_pathGenerateExpr[self::TARGET_PATH_PRODUCT_CATEGORY],
                'request_path.product_id',
                'request_path.category_id'
            ),
            self::TARGET_PATH_PRODUCT_CATEGORY
        ));
        
        $select->columns($columns);
        
        $this->_getIndexAdapter()->query(
            $select->insertFromSelect(
                $this->getTable(self::REWRITE), 
                $this->_getColumnsFromSelect($select)
            )
        );
        
        $this->_finalizeRowsUpdate(self::PRODUCT_REQUEST_PATH);
        return $this;
    }
    
    /**
     * Resolve duplicates with request_path
     * 
     * @return EcomDev_UrlRewrite_Model_Mysql4_Indexer
     */
    protected function _resolveDuplicates()
    {
        $select = $this->_getIndexAdapter()->select();
        
        // Preparing data for walking data
        $select
            ->from(
                array('rewrite' => $this->getTable(self::REWRITE)),
                array()
            )
            ->joinLeft(
                array('duplicate' => $this->getTable(self::REWRITE)),
                'duplicate.store_id = rewrite.store_id ' 
                . ' AND duplicate.duplicate_key = rewrite.duplicate_key'
                . ' AND duplicate.id_path != rewrite.id_path',
                array()
            )
            ->where('rewrite.updated = ?', 1)
            // Second condition for request path values that have digits in the end
            ->where('duplicate.id_path IS NOT NULL OR rewrite.duplicate_index = ?', 0)
            ->where('rewrite.duplicate_index IS NULL OR rewrite.duplicate_index = ?', 0)
            ->group(array('rewrite.store_id', 'rewrite.id_path'));
         
        $columns = array(
             'store_id' => 'rewrite.store_id',
             'id_path'  => 'rewrite.id_path',
             'duplicate_key' => 'rewrite.duplicate_key',
             'is_duplicate' => new Zend_Db_Expr('1')
        );

        $select->columns($columns);

        $result = $this->_getWriteAdapter()->query(
            $select->insertIgnoreFromSelect(
                $this->getTable(self::DUPLICATE), 
                $this->_getColumnsFromSelect($select)
            )
        );
         
        $select->reset()
            ->from($this->getTable(self::DUPLICATE), array('store_id', 'id_path', 'duplicate_key'))
            ->order(array('store_id ASC', 'duplicate_key ASC'));
        
        $this->_getIndexAdapter()->query(
            $select->insertIgnoreFromSelect(
                $this->getTable(self::DUPLICATE_INCREMENT), 
                $this->_getColumnsFromSelect($select)
            )
        );
            
        $select->reset()
            ->from(
                array('duplicate' => $this->getTable(self::DUPLICATE_INCREMENT)),
                'duplicate_id'
            )
            ->join(
                array('rewrite' => $this->getTable(self::REWRITE)), 
                'rewrite.store_id = duplicate.store_id AND rewrite.duplicate_key = duplicate.duplicate_key',
                array('max_index' => new Zend_Db_Expr('IFNULL(MAX(rewrite.duplicate_index), 0)'))
            )
            ->join(
                 array('min_duplicate' => $this->getTable(self::DUPLICATE_INCREMENT)), 
                 'min_duplicate.store_id = duplicate.store_id AND min_duplicate.duplicate_key = duplicate.duplicate_key',
                 array('min_duplicate_id' => new Zend_Db_Expr('MIN(min_duplicate.duplicate_id)'))
            )->group('duplicate.duplicate_id');

        $this->_getIndexAdapter()->query(
            $select->insertIgnoreFromSelect(
                $this->getTable(self::DUPLICATE_AGGREGATE), 
                $this->_getColumnsFromSelect($select)
            )
        );

        $columns = array(
            'duplicate_index' => new Zend_Db_Expr( 
                'aggregate.max_index + 1 + duplicate_increment.duplicate_id - aggregate.min_duplicate_id'
            )
        );
         
        $select->reset()
            ->join(
                array('duplicate_increment' => $this->getTable(self::DUPLICATE_INCREMENT)),
                'duplicate_increment.store_id = duplicate.store_id AND duplicate_increment.id_path = duplicate.id_path', 
                $columns
            )
            ->join(
                array('aggregate' => $this->getTable(self::DUPLICATE_AGGREGATE)),
                'aggregate.duplicate_id = duplicate_increment.duplicate_id', 
                $columns
            );

        $this->_getIndexAdapter()->query(
            $select->crossUpdateFromSelect(array('duplicate' => $this->getTable(self::DUPLICATE)))
        );
        
        $this->_updateRewriteDuplicates();
        return $this;
    }
    
    /**
     * Synchornizes data imported into rewrite table
     * (resolves duplicates, generates final request path,
     * updates core url rewrite tables)
     * 
     * @return EcomDev_UrlRewrite_Model_Mysql4_Indexer
     */
    protected function _updateRewrites()
    {
        $this->_resolveDuplicates();

        $this->beginTransaction();
        $categoryRequestPathExpr = $this->_getRequestPathExpr(self::ENTITY_CATEGORY);
        $productRequestPathExpr = $this->_getRequestPathExpr(self::ENTITY_PRODUCT);
        
        $originalRequestPathExpr = 'IFNULL(request_path, original_request_path)';
        $requestPathExpr = 'IF(product_id IS NULL, ' 
                         . $categoryRequestPathExpr . ', ' 
                         . $productRequestPathExpr . ')';
        
        $this->_getIndexAdapter()->update(
            $this->getTable(self::REWRITE),
           array(
                'original_request_path' => new Zend_Db_Expr(
                    $originalRequestPathExpr
                 ),
                'request_path' => new Zend_Db_Expr(
                    $requestPathExpr
                ),
                // Only update changed rows in core url rewrite
                'updated' => new Zend_Db_Expr(
                    $originalRequestPathExpr . ' IS NULL ' 
                    . 'OR  ' . $originalRequestPathExpr .  ' = ' 
                    . $requestPathExpr
                )
            ),
            array(
                'category_id IS NOT NULL OR product_id IS NOT NULL',
                'updated = ?' => 1
            )
        );
        
        $this->_saveUrlHistory();
        
        $select = $this->_getIndexAdapter()->select();
        $select->from($this->getTable(self::REWRITE), array())
            ->where('updated = ?', 1);

        $isSystemExpr = $this->_quoteInto('id_path REGEXP ?', '/');
        $columns = array(
            'store_id' => 'store_id',
            'id_path'  => 'id_path',
            'category_id' => 'category_id',
            'product_id' => 'product_id',
            'request_path' => 'request_path',
            'target_path' => 'target_path',
            'is_system' => new Zend_Db_Expr(
                'IF(' . $isSystemExpr . ', 1, 0)' 
            ),
            'options' => new Zend_Db_Expr($this->_quoteInto(
                'IF(' . $isSystemExpr .', \'\', ?)',
                'RP'
            ))
        );
        
        $select->columns($columns);
        $this->_getIndexAdapter()->query(
            $select->insertFromSelect(
                $this->getTable('core/url_rewrite'), 
                $this->_getColumnsFromSelect($select)
            ) 
        );
        
        $this->_finalizeRowsUpdate(self::REWRITE);
        
        $this->commit();
        return $this;
    }
    
    /**
     * Get url suffix db expr for specific entity and store id field
     * 
     * @param string $entity
     * @param string $storeField
     * @param string $requestPathField
     * 
     */
    protected function _getRequestPathExpr($entity)
    {
        $suffixes = $this->getUrlSuffixList($entity);
        
        $default = array_shift($suffixes);
        
        if (!$suffixes) {
            $suffixExpr = $this->_quoteInto('?', $default);
        } else {
            $suffixExpr = 'CASE store_id';
            
            foreach ($suffixes as $storeId => $suffix) {
                $suffixExpr .= $this->_quoteInto(' WHEN ? ', $storeId) 
                         . $this->_quoteInto(' THEN ? ', $suffix);
            }
            
            $suffixExpr .= $this->_quoteInto(' ELSE ? END', $default);
        }
        
        $dublicateExpr = $this->_quoteInto('CONCAT(?, duplicate_index)', '-');
        
        return 'CONCAT(duplicate_key, IFNULL(' . $dublicateExpr . ', \'\'), ' . $suffixExpr . ')';
    }
    
    /**
     * Saves url history if flag is set for store
     * or forced
     * 
     * @return EcomDev_UrlRewrite_Model_Mysql4_Indexer
     */
    protected function _saveUrlHistory()
    {
        if ($this->isSaveHistory() === false) {
            return $this;
        }
        
        $storeIds = array();
        foreach (Mage::app()->getStores() as $store) {
            if ($this->isSaveHistoryStore($store->getId())) {
                $storeIds[] = $store->getId();
            }
        }
        
        if(empty($storeIds)) {
            return $this;
        }
        
        $select = $this->_getIndexAdapter()->select();
        $select->from($this->getTable(self::REWRITE), array())
            ->where('original_request_path != request_path')
            ->where('store_id IN(?)', $storeIds)
            ->where('rewrite_id IS NOT NULL')
            ->where('updated = ?', 1);
            
        $columns = array(
            'store_id' => 'store_id',
            // Alternative to generateUniqueId() method
            'id_path'  => new Zend_Db_Expr($this->_quoteInto(
                'CONCAT(CRC32(CONCAT(NOW(), id_path)), ?, SUBSTR(RAND(), 3))',
                '_'
            )),
            'category_id' => 'category_id',
            'product_id' => 'product_id',
            'request_path' => 'original_request_path',
            'target_path' => 'request_path'
        );
        
        $select->columns($columns);
        $this->_getIndexAdapter()->query(
            $select->insertFromSelect(
                $this->getTable(self::REWRITE), 
                $this->_getColumnsFromSelect($select)
            )
        );
        
        return $this;
    }
    
    /**
     * Reindex all urls for categories and products
     * (non-PHPdoc)
     * @see Mage_Index_Model_Mysql4_Abstract::reindexAll()
     */
    public function reindexAll()
    {
        $this
            ->_generateTransliterateData()
            ->_generateRootCategoryIndex()
            ->clearInvalidRewrites()
            ->_generateCategoryRequestPathIndex()
            ->_generateProductRequestPathIndex()
            ->_importFromRewrite()
            ->_importFromCategoryRequestPath()
            ->_importFromProductRequestPath()
            ->_updateRewrites();
    }
    
    /**
     * Reindex urls for specified categories and products
     * 
     * @param array $categoryIds
     * @return EcomDev_UrlRewrite_Model_Mysql4_Indexer
     */
    public function updateCategoryRewrites(array $cateoryIds)
    {
        $this
            ->_generateTransliterateData(true)
            ->_generateRootCategoryIndex()
            ->clearInvalidRewrites()
            ->_generateCategoryRequestPathIndex($cateoryIds)
            ->_generateProductRequestPathIndex($cateoryIds)
            ->_importFromRewrite()
            ->_importFromCategoryRequestPath()
            ->_importFromProductRequestPath()
            ->_updateRewrites();
        return $this;
    }
    
    /**
     * Reindex urls for specified product ids or for products 
     * that are in specified category ids
     * 
     * @param array|null $productIds ignored if category ids is not null
     * @param array|null $categoryIds
     * @return EcomDev_UrlRewrite_Model_Mysql4_Indexer
     */
    public function updateProductRewrites(array $productIds, array $categoryIds = null)
    {
        $this
            ->_generateTransliterateData(true)
            ->_generateProductRequestPathIndex($categoryIds, $productIds)
            ->_importFromRewrite()
            ->_importFromProductRequestPath()
            ->_updateRewrites();
        return $this;
    }
    
    /**
     * Updates product rewrite on after save event operation
     * 
     * @param Mage_Index_Model_Event $event
     */
    public function catalogProductSave(Mage_Index_Model_Event $event)
    {
        $eventData = $event->getNewData();
        $productIds = isset($eventData['rewrite_product_ids']) ? $eventData['rewrite_product_ids'] : null;
        $categoryIds = isset($eventData['rewrite_category_ids']) ? $eventData['rewrite_category_ids'] : null;
        $this->updateProductRewrites($productIds, $categoryIds);
    }
    
    /**
     * Updates category rewrites on after save event operation
     * 
     * @param Mage_Index_Model_Event $event
     */
    public function catalogCategorySave(Mage_Index_Model_Event $event)
    {
        $eventData = $event->getNewData();
        $this->updateCategoryRewrites($eventData['rewrite_category_ids']);
    }
}

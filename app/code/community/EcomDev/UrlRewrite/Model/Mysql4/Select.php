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
 * Custom select object, because Varien_Db_Select 
 * doesn't support real cross update from select!
 * 
 * Also has some addtional nice helper methods 
 * for working with data
 * 
 */
class EcomDev_UrlRewrite_Model_Mysql4_Select extends Varien_Db_Select
{
    const SQL_UPDATE = 'UPDATE';
    const SQL_SET = 'SET';
    
    /**
     * Fix of adapter from possible call via singleton
     * 
     * @param array|Zend_Db_Adapter_Abstract
     * 
     */
    public function __construct($adapter)
    {
        if (is_array($adapter)) {
            $adapter = current($adapter);
        }
        
        parent::__construct($adapter);
    }
    
    /**
     * Returns used tables in select
     * 
     * @return array
     */
    public function getUsedTables()
    {
        $tables = array();
        foreach ($this->_parts[self::FROM] as $correlationName => $table) {
            $tables[$correlationName] = $table['tableName'];
        }
        
        return $tables;
    }
    
    /**
     * Return list of used column aliases
     * 
     * @return array
     */
    public function getColumnAliases()
    {
        $aliases = array();
        foreach ($this->_parts[self::COLUMNS] as $columnEntry) {
            list(, $column, $alias) = $columnEntry;
            if (empty($alias)) {
                $alias = $column;
            }
            $aliases[] = $alias;
        }
        
        return $aliases;
    }
    
    /**
     * Return list of used columns
     * 
     * @return array
     */
    public function getColumns()
    {
        $columns = array();
        
        foreach ($this->_parts[self::COLUMNS] as $columnEntry) {
            list($correlationName, $column, $alias) = $columnEntry;
            if (empty($alias)) {
                $alias = $column;
            }
            
            $columns[(string)$alias] = ( 
                $column instanceof Zend_Db_Expr ? 
                $column : 
                $this->_adapter->quoteIdentifier(array($correlationName, $column))
            );
        }
        
        return $columns;
    }
    
    /**
     * Executes cross select from update based on the current select object
     *
     * @return Zend_Db_Statement
     */
    public function crossUpdateFromSelectImproved() 
    {
        $parts[] = self::SQL_UPDATE . ' ' . ltrim($this->_renderFrom(''), ' ' . self::SQL_FROM);
        
        $tableAliases = $this->getUsedTables();
        $defaultTableAlias = key($tableAliases);
        
        $columns = array();
        foreach ($this->getColumns() as $alias => $column) {
            if (strpos($alias, '.') !== false) {
                $tableAlias = substr($alias, 0, strpos($alias, '.'));
                $alias = substr($alias, strpos($alias, '.') + 1);
            } else {
                $tableAlias = $defaultTableAlias;
            }
            
            $columns[] = $this->_adapter->quoteIdentifier(array($tableAlias, $alias))
                . ' = ' . $column;
        }

        $parts[] = self::SQL_SET . ' ' . implode(", ", $columns);
        $parts[] = $this->_renderWhere('');

        return $this->_adapter->query(implode("\n", $parts), $this->getBind());
    }
}

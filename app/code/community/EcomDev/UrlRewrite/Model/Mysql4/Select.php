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
 * DO NOT SUPPORT MDMS, DON'T USE IT WITH NOT MYSQL RESOURCES
 */
class EcomDev_UrlRewrite_Model_Mysql4_Select extends Varien_Db_Select
{
    const SQL_UPDATE = 'UPDATE';
    const SQL_SET = 'SET';
    
    // Index part key
    const INDEX = 'INDEX';

    // Type of index specification for select
    const INDEX_FORCE = 'FORCE';
    const INDEX_IGNORE = 'IGNORE';
    const INDEX_USE = 'USE';
    
    // Index information specification
    const SQL_INDEX_FORCE = 'FORCE INDEX(%s)';
    const SQL_INDEX_IGNORE = 'IGNORE INDEX(%s)';
    const SQL_INDEX_USE = 'USE INDEX(%s)';
    
    /**
     * Types that is available for index instructions in select
     * 
     * @var array
     */
    protected $_indexSqlTypes = array(
        self::INDEX_USE => self::SQL_INDEX_USE,
        self::INDEX_IGNORE => self::SQL_INDEX_IGNORE,
        self::INDEX_FORCE => self::SQL_INDEX_FORCE
    );
    
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
        
        self::$_joinTypes[] = self::SQL_STRAIGHT_JOIN;
        
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
    
    
    
    /**
     * Add index information statement
     * 
     * @param string $correlationName
     * @param string $type
     * @param array|type $indexes
     * @return EcomDev_UrlRewrite_Model_Mysql4_Select
     */
    protected function _index($correlationName, $type, $indexes)
    {
        if (!is_array($indexes)) {
            $indexes = array($indexes);
        }
        
        $this->_parts[self::INDEX][$correlationName] = array('type' => $type, 'indexes' => $indexes);
        return $this;
    }
    
    /**
     * Force using of a specific index for the table
     * 
     * @param string $correlationName table alias
     * @param string|array $indexes index name(s)
     * @return EcomDev_UrlRewrite_Model_Mysql4_Select
     */
    public function indexForce($correlationName, $indexes)
    {
        $this->_index($correlationName, self::INDEX_FORCE, $indexes);
        return $this;
    }
    
    /**
     * Tells MySQL optimazer which indexes it should use
     * 
     * @param string $correlationName table alias
     * @param string|array $indexes index name(s)
     * @return EcomDev_UrlRewrite_Model_Mysql4_Select
     */
    public function indexUse($correlationName, $indexes)
    {
        $this->_index($correlationName, self::INDEX_USE, $indexes);
        return $this;
    }
    
    /**
     * Tells MySQL optimazer wich indexes should be ignored
     * 
     * @param string $correlationName table alias
     * @param string|array $indexes index name(s)
     * @return EcomDev_UrlRewrite_Model_Mysql4_Select
     */
    public function indexIgnore($correlationName, $indexes)
    {
        $this->_index($correlationName, self::INDEX_IGNORE, $indexes);
        return $this;
    }
    
    /**
     * Render FROM clause with using of addotional specifications for index usage
     *
     * @param string   $sql SQL query
     * @return string
     */
    protected function _renderFrom($sql)
    {
        $sql = parent::_renderFrom($sql);
        // Add index definitions for MySQL optimizer
        $replace = array();
        foreach ($this->_parts[self::FROM] as $correlationName => $table) {
            if (!isset($this->_parts[self::INDEX][$correlationName]) 
                || !isset($this->_indexSqlTypes[$this->_parts[self::INDEX][$correlationName]['type']])) {
                continue;
            }

            
            $indexInstruction = $this->_parts[self::INDEX][$correlationName];
            
            $replace['from'][] = $this->_getQuotedTable($table['tableName'], $correlationName);
            $replace['to'][] = $this->_getQuotedTable($table['tableName'], $correlationName)
                . ' ' 
                . sprintf(
                    $this->_indexSqlTypes[$indexInstruction['type']], 
                    implode(',', array_map(
                        array($this->_adapter, 'quoteIdentifier'), 
                        $indexInstruction['indexes']
                    ))
                );
        }
        
        if ($replace) {
            $sql = str_replace($replace['from'], $replace['to'], $sql);
        }
        
        return $sql;
    }
    
    
    // Backward compatibility issue with method in Magento core!!
    /**
     * Straight join BC method, 
     * since Varien just removed it in 1.6 instead of marking as depracated
     *
     * @param  array|string|Zend_Db_Expr $name The table name.
     * @param  string $cond Join on this condition.
     * @param  array|string $cols The columns to select from the joined table.
     * @param  string $schema The database name to specify, if any.
     * @return Zend_Db_Select This Zend_Db_Select object.
     */
    public function joinStraight($name, $cond, $cols = self::SQL_WILDCARD, $schema = null)
    {
        return $this->_join(self::SQL_STRAIGHT_JOIN, $name, $cond, $cols, $schema);
    }
}

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
 * Url rewrite indexer model
 * 
 */
class EcomDev_UrlRewrite_Model_Indexer extends Mage_Catalog_Model_Indexer_Url
{
    /**
     * Define resource model for indexer
     * 
     * (non-PHPdoc)
     * @see Varien_Object::_construct()
     */
    protected function _construct()
    {
        $this->_init('ecomdev_urlrewrite/indexer');
    }
    
    /**
     * Get Indexer description
     *
     * @return string
     */
    public function getDescription()
    {
        return Mage::helper('ecomdev_urlrewrite')->__('Index product and categories URL rewrites (Alternative by EcomDev)');
    }
    
   /**
     * Register event data during category save process
     *
     * @param Mage_Index_Model_Event $event
     */
    protected function _registerCategoryEvent(Mage_Index_Model_Event $event)
    {
        $category = $event->getDataObject();
        if (!$category->getInitialSetupFlag() && $category->getLevel() > 1) {
            if ($category->dataHasChangedFor('is_anchor')) {
                $event->addNewData('rewrite_category_ids', array($category->getId()));
            }
        }
        
        return parent::_registerCategoryEvent($event);
    }
    
    /**
     * Process event
     *
     * @param Mage_Index_Model_Event $event
     */
    protected function _processEvent(Mage_Index_Model_Event $event)
    {
        $data = $event->getNewData();
        if (!empty($data['catalog_url_reindex_all'])) {
            $this->reindexAll();
            return $this;
        }

        // Force rewrites history saving
        $dataObject = $event->getDataObject();
        if ($dataObject instanceof Varien_Object && $dataObject->hasData('save_rewrites_history')) {
            $this->_getResource()->isSaveHistory($dataObject->getData('save_rewrites_history'));
        }

        if (isset($data['rewrite_category_ids']) || isset($data['rewrite_product_ids'])) {
            $this->callEventHandler($event);
        }
        
        $this->_getResource()->resetSaveHistory();
        return $this;
    }

    /**
     * Rebuild all index data
     * 
     */
    public function reindexAll()
    {
        $this->_getResource()->reindexAll();
    }
}

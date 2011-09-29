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
 * Configuration test
 *
 */
class EcomDev_UrlRewrite_Test_Config_Main extends EcomDev_PHPUnit_Test_Case_Config
{
    /**
     * Test model definitions for module
     * 
     * @test
     */
    public function modelDefinitions()
    {
        $this->assertModelAlias(
            'ecomdev_urlrewrite/indexer', 
            'EcomDev_UrlRewrite_Model_Indexer'
        );
        
        $this->assertResourceModelAlias(
            'ecomdev_urlrewrite/indexer', 
            'EcomDev_UrlRewrite_Model_Mysql4_Indexer'
        );
    }
}

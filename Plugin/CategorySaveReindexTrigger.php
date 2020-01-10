<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace CodingMice\VsBridgeIndexerExtension\Plugin;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Indexer\Category\Product\Processor;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Checks if a category has changed products and depends on indexer configuration.
 */
class CategorySaveReindexTrigger implements ObserverInterface
{
    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        $category = $observer->getEvent()->getData('category');
        /**
         * @var $category Category
         */
        $positions = $category->getProductsPosition();

        if (!empty($positions)) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        }

        //$indexerFactory = $objectManager->get('Magento\Indexer\Model\IndexerFactory');
        /* @var \Magento\Indexer\Model\IndexerFactory $indexerFactory */

        //$productIndexer = $indexerFactory->create();
        //$productIndexer->load('vsbridge_product_indexer');

        /* Approach 1: doesn't work */
        /*$productProcessor = $objectManager->get('\Divante\VsbridgeIndexerCatalog\Model\Indexer\ProductProcessor');
        foreach ($positions as $productId => $position) {
                    $productProcessor->reindexRow($productId);
        }*/

        /* Approach 2: does not work */
        /*foreach ($positions as $productId => $position) {
            $product = $objectManager->get('Magento\Catalog\Model\Product')->load($productId);
            if($product) {
                $product->reindex();
            }
        }*/

        foreach ($positions as $productId => $position) {
            $product = $objectManager->get('Magento\Catalog\Model\Product')->load($productId);
            if ($product) {
                $product->save();
                $product->reindex();
            }
        }
    }
}

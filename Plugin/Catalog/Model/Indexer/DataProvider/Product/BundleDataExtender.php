<?php

namespace CodingMice\VsBridgeIndexerExtension\Plugin\Catalog\Model\Indexer\DataProvider\Product;

use Divante\VsbridgeIndexerCatalog\Model\Indexer\DataProvider\Product\BundleOptionsData;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ObjectManager;

class BundleDataExtender
{
    public function afterAddData(BundleOptionsData $subject, $indexData, $data, $storeId)
    {
        $indexData = $this->addDiscountAmount($indexData, $storeId);

        return $indexData;
    }

    private function addDiscountAmount($indexData, $storeId)
    {
        $objectManager = ObjectManager::getInstance();
        $productRepository = $objectManager->create(ProductRepositoryInterface::class);

        foreach ($indexData as $product_id => $indexDataItem) {
            if ($indexData[$product_id]['type_id'] != 'bundle') {
                continue;
            }

            $product = $productRepository->get($indexData[$product_id]['sku'], false, $storeId);
            $final_price = $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
            $regular_price = $product->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue();

            $indexData[$product_id]['discount_amount'] = intval(round(100 - (($final_price / $regular_price) * 100)));
        }
        return $indexData;
    }
}

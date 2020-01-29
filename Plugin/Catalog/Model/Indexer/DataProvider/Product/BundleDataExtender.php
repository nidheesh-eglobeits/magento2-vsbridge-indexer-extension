<?php

namespace CodingMice\VsBridgeIndexerExtension\Plugin\Catalog\Model\Indexer\DataProvider\Product;

use Divante\VsbridgeIndexerCatalog\Model\Indexer\DataProvider\Product\BundleOptionsData;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ObjectManager;

class BundleDataExtender
{
    public function afterAddData(BundleOptionsData $subject, $result, $indexData, $storeId)
    {
        $result = $this->addDiscountAmount($result, $storeId);

        return $result;
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

            $regular_price = 0;
            foreach ($indexDataItem['bundle_options'] as $bundleOption) {
                if (isset($bundleOption['product_links'][0]['price'])) {
                    $bundleOptionPrice = $bundleOption['product_links'][0]['price'];
                    $regular_price += $bundleOptionPrice;
                }
            }

            $discountAmount = null;
            if ($regular_price) {
                $discountAmount = intval(round(100 - (($final_price / $regular_price) * 100)));
            }
            $indexData[$product_id]['discount_amount'] = $discountAmount;
        }
        return $indexData;
    }
}

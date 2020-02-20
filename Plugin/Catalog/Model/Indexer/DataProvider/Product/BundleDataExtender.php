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

            // Add slug_from_name for pretty URLs
            // I did same for Configurables
            // These functions are just equal `slugify`
            $indexDataItem['slug_from_name'] = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $indexDataItem['name'])));

            $bundlePrice = $indexDataItem['price'] ?? null;

            if (!array_key_exists('bundle_options', $indexDataItem)) {
                continue;
            }

            $childrenRegularPrize = [
                'min' => 0,
                'max' => 0
            ];
            foreach ($indexDataItem['bundle_options'] as $bundleOption) {
                $optionPrizes = [];
                if(empty($bundleOption['product_links'])){
                    continue;
                }
                foreach ($bundleOption['product_links'] as $child) {
                    $childSKU = $child['sku'];
                    $childProduct = $productRepository->get($childSKU, false, $storeId);
                    $childRegularPrize = $childProduct->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue();
                    $optionPrizes[] = intval(round($childRegularPrize));
                }
                sort($optionPrizes);
                $childrenRegularPrize['min'] += $optionPrizes[0];
                $childrenRegularPrize['max'] += end($optionPrizes);
            }

            $indexData[$product_id]['bundle_children_price'] = $childrenRegularPrize['min'] . '-' . $childrenRegularPrize['max'];

            $discountAmount = null;
            if ($childrenRegularPrize['min'] == $childrenRegularPrize['max']) {
                $childrenRegularPrize = $childrenRegularPrize['max'];
                $discountAmount = 0;
                if($childrenRegularPrize > 0) {
                    $discountAmount = intval(round(100 - (($bundlePrice / $childrenRegularPrize) * 100)));
                }
            } else {
                $discountAmountMin = 0;
                if($childrenRegularPrize['min'] > 0) {
                    $discountAmountMin = intval(round(100 - (($bundlePrice / $childrenRegularPrize['min']) * 100)));
                }

                $discountAmountMax = 0;
                if($childrenRegularPrize['max'] > 0) {
                    $discountAmountMax = intval(round(100 - (($bundlePrice / $childrenRegularPrize['max']) * 100)));
                }
                $discountAmount = $discountAmountMin . '-' . $discountAmountMax;
            }

            $indexData[$product_id]['discount'] = $discountAmount;
        }
        return $indexData;
    }
}

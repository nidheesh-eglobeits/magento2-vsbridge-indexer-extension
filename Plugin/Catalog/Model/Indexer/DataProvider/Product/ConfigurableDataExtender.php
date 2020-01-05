<?php

namespace CodingMice\VsBridgeIndexerExtension\Plugin\Catalog\Model\Indexer\DataProvider\Product;

use Divante\VsbridgeIndexerCatalog\Model\ResourceModel\Product\Category as CategoryResource;
use Divante\VsbridgeIndexerCatalog\Model\Indexer\DataProvider\Product\ConfigurableData;
use Divante\VsbridgeIndexerCatalog\Model\Indexer\DataProvider\Product\MediaGalleryData;
use Divante\VsbridgeIndexerCore\Api\DataProviderInterface;
use Divante\VsbridgeIndexerCore\Console\Command\RebuildEsIndexCommand;
use Divante\VsbridgeIndexerCore\Config\IndicesSettings;
use Magento\Framework\App\ObjectManager;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Api\Data\StoreInterface;


class ConfigurableDataExtender {

    /* @var CategoryResource $categoryResource */
    private $categoryResource;

    public $storeId;

    public function beforeAddData(ConfigurableData $subject, $docs, $storeId){
        $this->storeId = $storeId;
    }

    /**
     * This method will take ES docs prepared by Divante Extension and modify them
     * before they are added to ES in \Divante\VsbridgeIndexerCore\Indexer\GenericIndexerHandler::saveIndex
     */
    public function afterAddData(ConfigurableData $subject, $docs){
        $storeId = $this->storeId;
        $docs = $this->extendDataWithGallery($subject, $docs,$storeId);

        $objectManager = ObjectManager::getInstance();
        /* @var \Divante\VsbridgeIndexerCore\Index\IndexOperations $indexOperations */
        $this->categoryResource = $objectManager->create("Divante\VsbridgeIndexerCatalog\Model\ResourceModel\Product\Category");

        $docs = $this->cloneConfigurableColors($docs,$storeId);
        return [$docs,$storeId];
    }

    private function cloneConfigurableColors($indexData,$storeId)
    {
        $clones = [];

        foreach ($indexData as $product_id => $indexDataItem) {

            if ($indexDataItem['type_id'] !== 'configurable') {
                continue;
            }

            if ( ! isset($indexDataItem['configurable_options']) ) {
                continue;
            }

            $has_colors = false;
            foreach ($indexDataItem['configurable_options'] as $option) {
                if ( $option['attribute_code'] == 'color' ) {
                    $has_colors = true;
                    $colors = $option['values'];
                    break;
                }
            }

            if ( ! $has_colors ) {
                $cloneId = $product_id.'-'.$indexDataItem['color'];
                $clones[$cloneId] = $indexDataItem;
                $clones[$cloneId]['is_clone'] = 2;
                $clones[$cloneId]['clone_color_id'] = $indexDataItem['color'];
                $clones[$cloneId]['sku'] = $indexDataItem['sku'].'-'.$indexDataItem['color'];

                $wasChildInThisColor = false;

                foreach($clones[$cloneId]['configurable_children'] as $child_data) {

                    if (!$wasChildInThisColor) {
                        $wasChildInThisColor = true;

                        $category_data =  $this->getCategoryData($storeId, $child_data['id']);

                        $clones[$cloneId]['category_new'] = $category_data['category_new'];
                        $clones[$cloneId]['category'] = $category_data['category'];
                        continue;
                    } else {
                        $categories_data =  $this->getCategoryData($storeId, $child_data['id']);
                        foreach ($categories_data['category_new'] as $category_id => $valueToCheck) {
                            if (!isset($clones[$cloneId]['category_new'])) {
                                // Is it even possible?
                                continue;
                            }
                            $currentValue = isset($clones[$cloneId]['category_new'][$category_id]) ? $clones[$cloneId]['category_new'][$category_id] : 0;
                            // If new value is 0, do nothing
                            if ($valueToCheck == 0) {
                                continue;
                            }
                            // If current value is 0, and new is not 0. Set it
                            if ($currentValue == 0) {
                                // $clones[$cloneId]['category'][$category_id] = $valueToCheck;
                                $clones[$cloneId]['category_new'][$category_id] = $valueToCheck;
                                continue;
                            }

                            // If both are none 0, compare
                            if ($valueToCheck < $currentValue) {
                                // $clones[$cloneId]['category'][$category_id] = $valueToCheck;
                                $clones[$cloneId]['category_new'][$category_id] = $valueToCheck;
                                continue;
                            }
                        }
                    }
                }

            } else {
                foreach ($colors as $color) {
                    $clone_color = strtolower(str_ireplace(' ', '-', $color['label']));
                    $cloneId = $product_id.'-'.$color['value_index'];
                    $clones[$cloneId] = $indexDataItem;
                    $clones[$cloneId]['clone_color_label'] = $color['label'];
                    $clones[$cloneId]['clone_color_id'] = $color['value_index'];
                    $clones[$cloneId]['sku'] = $indexDataItem['sku'].'-'.$color['value_index'];
                    $clones[$cloneId]['is_clone'] = 1;
                    $clones[$cloneId]['url_key'] = $indexDataItem['url_key'].'?color='.$clone_color;
                    $clones[$cloneId]['clone_name'] = $indexDataItem['name'].' '.$color['label'];

                    $wasChildInThisColor = false;
                    //loop through the children and get the values of the smallest size child with the same color
                    foreach($clones[$cloneId]['configurable_children'] as $child_data) {
                        if($child_data['color'] == $color['value_index']){

                            //                         if(isset($child_data['color_group'])){
                            //                             $clones[$cloneId]["color_group"] = $child_data['color_group'];
                            //                         }
                            //                          if(isset($child_data['style'])){
                            //                             $clones[$cloneId]["length"] = $child_data['length'];
                            //                          }
                            //                           if(isset($child_data['color_group'])){
                            //                             $clones[$cloneId]["style"] = $child_data['style'];
                            //                           }
                            // //                        $clones[$cloneId]["print"] = $child_data['print']; //Uncomment this to add print attribute

                            //                         if(isset($child_data['featured']) && !empty(array_filter($child_data['featured']))){
                            //                             $clones[$cloneId]['featured'] = $child_data['featured'];
                            //                         }

                            if (!$wasChildInThisColor) {
                                $wasChildInThisColor = true;


                                $category_data =  $this->getCategoryData($storeId, $child_data['id']);

                                $clones[$cloneId]['category_new'] = $category_data['category_new'];
                                $clones[$cloneId]['category'] = $category_data['category'];
                                continue;
                            } else {
                                $categories_data =  $this->getCategoryData($storeId, $child_data['id']);
                                foreach ($categories_data['category_new'] as $category_id => $valueToCheck) {
                                    if (!isset($clones[$cloneId]['category_new'])) {
                                        // Is it even possible?
                                        continue;
                                    }
                                    $currentValue = isset($clones[$cloneId]['category_new'][$category_id]) ? $clones[$cloneId]['category_new'][$category_id] : 0;
                                    // If new value is 0, do nothing
                                    if ($valueToCheck == 0) {
                                        continue;
                                    }
                                    // If current value is 0, and new is not 0. Set it
                                    if ($currentValue == 0) {
                                        // $clones[$cloneId]['category'][$category_id] = $valueToCheck;
                                        $clones[$cloneId]['category_new'][$category_id] = $valueToCheck;
                                        continue;
                                    }

                                    // If both are none 0, compare
                                    if ($valueToCheck < $currentValue) {
                                        // $clones[$cloneId]['category'][$category_id] = $valueToCheck;
                                        $clones[$cloneId]['category_new'][$category_id] = $valueToCheck;
                                        continue;
                                    }
                                }
                            }

                        }
                    }

                }
            }

        }

        return $indexData + $clones;
    }

    private function getCategoryData($storeId,$productId){



        $categories =  $this->categoryResource->loadCategoryData($storeId, [$productId]);
        $category_data = [
            'category' => [],
            'category_new' => [],
        ];

        foreach ($categories as $cat) {
            $cat_id = (int) $cat["category_id"];
            $cat_postion = (int) $cat['position'];

            $category_data['category'][] = [
                'category_id' => $cat_id,
                'name' => (string)$cat['name'],
                'position' => $cat_postion,
            ];
            $category_data['category_new'][$cat_id] = $cat_postion;
        }

        return $category_data;
    }

    private function extendDataWithGallery(\Divante\VsbridgeIndexerCatalog\Model\Indexer\DataProvider\Product\ConfigurableData $subject, $docs,$storeId)
    {
        $objectManager =  \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->create("Magento\Store\Model\StoreManagerInterface");
        /* @var StoreManagerInterface $storeManager */
        $store = $storeManager->getStore($storeId);
        $index = $this->getIndex($store);
        $type = $index->getType('product');

        /* make this work here */
        $mediaGalleryDataProvider = $type->getDataProvider('media_gallery');

        $allChildren = $subject->configurableResource->getSimpleProducts($storeId);

        if (null === $allChildren) {
            return $docs;
        }

        $stockRowData = $subject->loadInventory->execute($allChildren, $storeId);
        $configurableAttributeCodes = $subject->configurableResource->getConfigurableAttributeCodes();

        $allChildren = $subject->childrenAttributeProcessor
            ->loadChildrenRawAttributesInBatches($storeId, $allChildren, $configurableAttributeCodes);

        // add Media Gallery
        $allChildren = $mediaGalleryDataProvider->addData($allChildren, $storeId);

        foreach ($allChildren as $child) {
            $childId = $child['entity_id'];
            $child['id'] = (int) $childId;
            $parentIds = $child['parent_ids'];

            if (!isset($child['regular_price']) && isset($child['price'])) {
                $child['regular_price'] = $child['price'];
            }

            if (isset($stockRowData[$childId])) {
                $productStockData = $stockRowData[$childId];

                unset($productStockData['product_id']);
                $productStockData = $subject->inventoryProcessor->prepareInventoryData($storeId, $productStockData);
                $child['stock'] = $productStockData;
            }

            foreach ($parentIds as $parentId) {
                $child = $subject->filterData($child);

                if (!isset($docs[$parentId]['configurable_options'])) {
                    $docs[$parentId]['configurable_options'] = [];
                }

                $docs[$parentId]['configurable_children'][] = $child;
            }
        }

        $allChildren = null;

        return $docs;
    }

    /**
     * @param StoreInterface $store
     *
     * @return IndexInterface
     */
    private function getIndex(StoreInterface $store)
    {

        $objectManager = ObjectManager::getInstance();
        /* @var \Divante\VsbridgeIndexerCore\Index\IndexOperations $indexOperations */
        $indexOperations = $objectManager->create("Divante\VsbridgeIndexerCore\Index\IndexOperations");

        try {
            $index = $indexOperations->getIndexByName(RebuildEsIndexCommand::INDEX_IDENTIFIER, $store);
        } catch (\Exception $e) {
            $index = $indexOperations->createIndex(RebuildEsIndexCommand::INDEX_IDENTIFIER, $store);
        }

        return $index;
    }

}



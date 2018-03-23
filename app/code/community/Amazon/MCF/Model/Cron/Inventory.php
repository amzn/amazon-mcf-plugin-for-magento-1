<?php

/**
 * Copyright 2017 Amazon.com, Inc. or its affiliates. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License").
 * You may not use this file except in compliance with the License.
 * A copy of the License is located at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * or in the "license" file accompanying this file. This file is distributed
 * on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
 * express or implied. See the License for the specific language governing
 * permissions and limitations under the License.
 */

/**
 * Class Amazon_MCF_Model_Cron_Inventory
 */
class Amazon_MCF_Model_Cron_Inventory
{

    /**
     * Updates inventory during cron - products must be marked for Amazon updates.
     */
    public function inventoryUpdate()
    {

        /**
         * @var Amazon_MCF_Model_Service_Inventory $service
         */
        $service = Mage::getModel('amazon_mcf/service_inventory');
        /**
         * @var Amazon_MCF_Helper_Data $helper
         */
        $helper = Mage::helper('amazon_mcf');

        // check if next token exists before proceeding with regular call.
        $token = $helper->getInventoryNextToken();
        if (!empty($token)) {
            $response = $service->getListInventorySupplyByNextToken($token);
        } else {
            // used -1 day since inventory changes are from given time to present,
            // current time would not return data.
            $startTime = gmdate(
                Amazon_MCF_Helper_Conversion::ISO8601_FORMAT,
                strtotime('-1 day')
            );
            $response = $service->getFulfillmentInventoryList(array(), $startTime);
        }

        if ($response) {
            // if there is a list of updates to provided skus, process them.
            $supplyList = $response->getListInventorySupplyResult()
                ->getInventorySupplyList()
                ->getmember();
            if ($supplyList) {
                $this->updateInventoryProcessStatus($supplyList);
            }

            $nextToken = $response->getListInventorySupplyResult()->getNextToken();

            if ($nextToken) {
                $helper->setInventoryNextToken($nextToken);
            } else {
                $helper->setInventoryNextToken(false);
            }
        } else {
            // If no response, make sure next token is set to empty string for
            // future calls.  It's possible call was made with invalid token
            $helper->setInventoryNextToken('');
        }
    }

    /**
     * Cron process that increments through a set number of SKUs each time
     * and gets/sets inventory supply values based on Amazon values.
     *
     * see crontab.xml for setup details
     */
    public function cronFullInventoryStatus()
    {
        $helper = Mage::helper('amazon_mcf');

        $status = $helper->getInventoryProcessRunning();

        if ($status) {
            $rowCount = 40;
            $page = $helper->getInventoryProcessPage();

            if ($page == 1) {
                $helper->logInventory('Starting full inventory sync.');
            } else {
                $helper->logInventory('Continuing full sync, page: ' . $page);
            }

            $products = $this->getAmazonFulfilledSkus($page, $rowCount);

            // if there are Amazon MCF enabled products, prepare to update
            // inventory and use alt sku for query if it exists.
            if ($products->count() > 0) {
                if ($products->getLastPageNumber() == $page) {
                    // This is the last batch of products, turn processing
                    // off and reset page
                    $helper->setInventoryProcessPage(1);
                    $helper->setInventoryProcessRunning(false);
                } else {
                    $helper->setInventoryProcessPage($page + 1);
                }

                $skus = array();
                foreach ($products as $product) {
                    $skus[] = $product->getAmazonMcfSku()
                        ? $product->getAmazonMcfSku() : $product->getSku();
                }

                /**
                 *
                 *
                 * @var Amazon_MCF_Model_Service_Inventory $service
                 */
                $service = Mage::getModel('amazon_mcf/service_inventory');
                $response = $service->getFulfillmentInventoryList(
                    array('member' => $skus)
                );

                if ($response) {
                    // if there is a list of updates to provided skus, process them.
                    $supplyList = $response->getListInventorySupplyResult()
                        ->getInventorySupplyList()
                        ->getmember();
                    if ($supplyList) {
                        $this->processSupplyListData($supplyList, $products);
                    }
                }
            } else {
                // turn off process and reset start row
                $helper->setInventoryProcessRunning(false);
                $helper->setInventoryProcessRow(0);
            }
        }
    }

    /**
     * Compare data returned from Amazon MCF with current stock and sku data
     * and update stock values accordingly.
     *
     * @param $supplyList
     * @param $products
     */
    protected function processSupplyListData($supplyList, $products)
    {
        $skuQuantities = array();
        $missingSkus = array();

        foreach ($supplyList as $item) {
            $sku = $item->getSellerSKU();
            $asin = $item->getASIN();
            $earliestAvailability = $item->getEarliestAvailability();
            $availability = empty($earliestAvailability)
                ? '' : $earliestAvailability->getTimepointType();
            $inStock = $item->getInStockSupplyQuantity();

            // check the sku was matched in Amazon
            if ($sku && $asin) {
                $skuQuantities[$sku] = $inStock;
            }
        }


        // now compare Amazon MCF inventory data with original list of Magento
        // inventory data and update stock if needed
        foreach ($products as $product) {
            $sku = $product->getAmazonMcfSku()
                ? $product->getAmazonMcfSku() : $product->getSku();

            if (isset($skuQuantities[$sku])) {
                $stockItem = $product->getStockItem();
                if (empty($stockItem)) {
                    $stockItem = Mage::getModel(
                        'cataloginventory/stock_item'
                    )
                        ->loadByProduct(
                            $product->getId()
                        );
                }
                if ($stockItem->getManageStock()) {
                    $stockItem->setQty($skuQuantities[$sku]);
                    $stockItem->setIsInStock((int)($skuQuantities[$sku] > 0));
                    $stockItem->save();
                }
            } else {
                if ($sku != $product->getSku()) {
                    // include both Magento and Amazon skus in message if different
                    $sku = $product->getSku() . " ($sku)";
                }
                $missingSkus[] = $sku;
            }
        }

        if (!empty($missingSkus)) {
            $message = "Some skus were not found in Amazon Seller Central: "
                . join(', ', $missingSkus);
            /**
             *
             *
             * @var Mage_AdminNotification_Model_Inbox $inbox
             */
            $inbox = Mage::getModel('adminnotification/inbox');
            $inbox->add(
                Mage_AdminNotification_Model_Inbox::SEVERITY_MINOR,
                'Amazon SKU Mismatch for '
                . sizeof($message) . ' product(s)', $message
            );
        }
    }

    /**
     * Gets a product collection of all Amazon Fulfillment enabled sku data related
     * to product entity id
     *
     * @param  $page
     * @param  $rowCount
     * @return \Mage_Catalog_Model_Resource_Product_Collection
     */
    protected function getAmazonFulfilledSkus($page, $rowCount)
    {
        $products = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToSelect('sku')
            ->addAttributeToSelect('amazon_mcf_sku')
            ->addAttributeToFilter('amazon_mcf_enabled', '1')
            ->setPageSize($rowCount)
            ->setCurPage($page)
            ->setOrder('entity_id', 'asc');

        Mage::getSingleton('cataloginventory/stock')
            ->addItemsToProducts($products);

        return $products;
    }

    /**
     * Perform updates on Magento inventory supply based on data returned from
     * Amazon MCF API
     *
     * @param $supplyList
     */
    protected function updateInventoryProcessStatus($supplyList)
    {
        $skuQuantities = $this->getMatchedSkus($supplyList);

        if ($skuQuantities) {
            $matches = $this->getMagentoInventoryData($skuQuantities);

            if ($matches) {
                foreach ($matches as $productId => $stockValue) {
                    $stockItem = Mage::getModel(
                        'cataloginventory/stock_item'
                    )->loadByProduct($productId);
                    if ($stockItem->getManageStock()) {
                        $stockItem->setQty($stockValue['qty']);
                        $stockItem->setIsInStock((int)($stockValue['qty'] > 0));
                        $stockItem->save();
                    }
                }
            }

            $this->_createMissingInventoryNotifications($skuQuantities, $matches);
        }
    }

    /**
     * Finds matches with SKUs returned from Amazon MCF API with products in
     * Magento system
     *
     * @param $skuQuantities
     *
     * @return array
     */
    protected function getMagentoInventoryData($skuQuantities)
    {
        $matches = array();

        $skus = array();

        foreach ($skuQuantities as $sku => $productData) {
            $skus[] = $sku;
        }

        if ($skus) {
            $skuFilter = array('attribute' => 'sku', 'in' => $skus);
            $overriddenSkuFilter = array(
                'attribute' => 'amazon_mcf_sku',
                'in' => $skus
            );
            // match sku OR amazon_mcf_sku
            $products = Mage::getResourceModel('catalog/product_collection')
                ->addAttributeToSelect('amazon_mcf_sku')
                ->addAttributeToFilter('amazon_mcf_enabled', '1')
                ->addAttributeToFilter(array($skuFilter, $overriddenSkuFilter));

            Mage::getSingleton('cataloginventory/stock')
                ->addItemsToProducts($products);

            foreach ($products as $product) {
                $sku = $product->getAmazonMcfSku()
                    ? $product->getAmazonMcfSku() : $product->getSku();
                $matches[$product->getId()] = array(
                    'sku' => $sku,
                    'qty' => isset($skuQuantities[$sku]) ? $skuQuantities[$sku] : 0,
                );
            }
        }

        return $matches;
    }

    /**
     * Extracts simplified array of skus associated with quantities
     *
     * @param $supplyList
     *
     * @return array
     */
    protected function getMatchedSkus($supplyList)
    {
        $skuQuantities = array();

        foreach ($supplyList as $item) {
            $sku = $item->getSellerSKU();

            $inStock = $item->getInStockSupplyQuantity();
            $skuQuantities[$sku] = $inStock;

        }

        return $skuQuantities;

    }

    /**
     * Adds notification for items that do not have corresponding data on
     * Amazon MCF
     *
     * @param $skuData
     */
    private function _createInventoryNotifications($skuData)
    {
        $skus = array();

        foreach ($skuData as $entity_id => $productData) {

            if (!$productData['hasData']) {
                if (isset($productData['alt_sku'])) {
                    $skus[] = $productData['alt_sku'];
                } else {
                    $skus[] = $productData['sku'];
                }
            }
        }

        if ($skus) {
            $message = 'The following SKUs have no associated Amazon Fulfillment 
            data or no available inventory: ' . implode(', ', $skus)
                . '. The stock quantities for these SKUs has been set to 0. If 
                this is incorrect, please disable Amazon Fulfillment for these 
                products or ensure Amazon Merchant SKU is correct.';
            Mage::helper('amazon_mcf')->logInventory($message);
        }
    }

    /**
     * Creates appropriate notification explaining that Amazon returned values
     * for SKUs/Products that do not exist in Magento
     *
     * @param $skuQuantities
     * @param $matches
     */
    private function _createMissingInventoryNotifications($skuQuantities, $matches)
    {

        $updated = array();
        $skus = array();

        foreach ($matches as $sku => $productData) {
            $updated[] = $productData['sku'];
        }

        foreach ($skuQuantities as $sku => $value) {
            if (!in_array($sku, $updated)) {
                $skus[] = $sku;
            }
        }

        if ($skus) {
            $message = 'The following SKUs have no associated Magento Product: '
                . implode(', ', $skus) . '. Please create these products and 
                assign the Amazon Sku to the Merchant Sku field and enable the 
                product for Amazon Fulfillment.';
            Mage::helper('amazon_mcf')->logInventory($message);
        }
    }
}

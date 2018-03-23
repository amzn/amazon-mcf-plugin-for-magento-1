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
 * Class Amazon_MCF_Model_Observer
 */
class Amazon_MCF_Model_Observer
{
    /**
     * Submit fulfillment order to Amazon.
     *
     * @param Varien_Event_Observer $observer
     */
    public function submitOrderToAmazon(Varien_Event_Observer $observer)
    {
        $helper = Mage::helper('amazon_mcf');
        $order = $observer->getOrder();

        // Do we have any items to submit to Amazon?
        $amazonItemInOrder = false;
        foreach ($order->getAllItems() as $item) {
            if ($item->getProduct()->getAmazonMcfEnabled()) {
                $order->setFulfilledByAmazon(true);
                $amazonItemInOrder = true;
                break;
            }
        }

        if (!$helper->isEnabled() || !$amazonItemInOrder) {
            return;
        }

        /**
         * @var Amazon_MCF_Model_Service_Outbound $service
         */
        $service = Mage::getSingleton('amazon_mcf/service_outbound');
        $response = $service->createFulfillmentOrder($order);

        if (!empty($response)) {
            $order->setAmazonOrderStatus(
                Amazon_MCF_Helper_Data::ORDER_STATUS_RECEIVED
            );
        } else {
            $order->setAmazonOrderStatus(
                Amazon_MCF_Helper_Data::ORDER_STATUS_ATTEMPTED
            );
            $order->setAmazonOrderSubmissionAttemptCount(1);
        }
    }

    /**
     * Cancel fulfillment of order in Amazon
     *
     * @param \Varien_Event_Observer $observer
     */
    public function cancelOrderToAmazon(Varien_Event_Observer $observer)
    {
        $order = $observer->getOrder();

        // if order has transient property set, do not send to Amazon
        if ($order->getSkipAmazonCancel()) {
            return;
        }

        /**
         * @var Amazon_MCF_Model_Service_Outbound $service
         */
        $service = Mage::getSingleton('amazon_mcf/service_outbound');
        $response = $service->cancelFulfillmentOrder($order);
    }

    /**
     * Verify that a product configured to be fulfilled by Amazon is
     * found in Seller Central
     *
     * @param \Varien_Event_Observer $observer
     */
    public function verifyAmazonSku(Varien_Event_Observer $observer)
    {
        $product = $observer->getProduct();
        if ($product->getAmazonMcfEnabled()) {
            /**
             * @var Amazon_MCF_Model_Service_Inventory $service
             */
            $service = Mage::getModel('amazon_mcf/service_inventory');
            /**
             *
             *
             * @var Amazon_MCF_Helper_Data $helper
             */
            $helper = Mage::helper('amazon_mcf');
            $sku = $product->getAmazonMcfSku()
                ? $product->getAmazonMcfSku() : $product->getSku();

            $response = $service->getFulfillmentInventoryList(
                array('member' => array($sku))
            );

            if ($response) {
                // if there is a list of updates to provided skus, process them.
                $supplyList = $response->getListInventorySupplyResult()
                    ->getInventorySupplyList()
                    ->getmember();

                $asin = $supplyList[0]->getASIN();
                if (!$asin) {
                    $helper->logInventory(
                        "Product configured to be FBA but sku ($sku) 
                        not matched in Seller Central"
                    );
                    $message = "The SKU entered does not have an associated Seller 
                    SKU at Amazon. Please check the SKU value matches 
                    between systems: ";

                    Mage::getSingleton("adminhtml/session")
                        ->addWarning($helper->__($message) . $sku);
                }
            }
        }
    }
}

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
 * Class Amazon_MCF_Model_Cron_Orders
 */
class Amazon_MCF_Model_Cron_Orders
{

    /**
     * This number needs to keep in mind the API throttling, currently burst
     * rate of 30 and restore 2 per second.
     *
     * http://docs.developer.amazonservices.com/en_US/fba_outbound/FBAOutbound_CreateFulfillmentOrder.html
     */
    const NUM_ORDERS_TO_RESUBMIT = 20;

    const NUM_ORDER_RESUMIT_RETRYS = 5;

    /**
     * This job checks all orders that have been submitted to Amazon but not yet
     * shipped. Once the order has shipped from Amazon, we invoice and ship the
     * Magento order.
     */
    public function orderUpdate()
    {

        /**
         * @var Amazon_MCF_Helper_Data $helper
         */
        $helper = Mage::helper('amazon_mcf');

        $stores = Mage::app()->getStores(true);
        /**
         *
         *
         * @var Amazon_MCF_Model_Service_Outbound $service
         */
        $service = Mage::getSingleton('amazon_mcf/service_outbound');


        foreach ($stores as $store) {
            if (!$helper->isEnabled($store)) {
                continue;
            }

            /**
             * @var \Mage_Sales_Model_Resource_Order_Collection $ordersToProcess
             */
            $ordersToProcess = Mage::getResourceModel(
                'sales/order_collection'
            );
            $ordersToProcess
                ->addFieldToFilter('store_id', $store->getId())
                ->addFieldToFilter(
                    'state', array(
                        'in' => array(
                            Mage_Sales_Model_Order::STATE_NEW,
                            Mage_Sales_Model_Order::STATE_PROCESSING,
                        ),
                    )
                )
                ->addFieldToFilter('fulfilled_by_amazon', true)
                ->addFieldToFilter(
                    'amazon_order_status', array(
                        'in' => array(
                            Amazon_MCF_Helper_Data::ORDER_STATUS_RECEIVED,
                            Amazon_MCF_Helper_Data::ORDER_STATUS_PLANNING,
                            Amazon_MCF_Helper_Data::ORDER_STATUS_PROCESSING,
                        ),
                    )
                );

            if ($ordersToProcess->count()) {
                $helper->logOrder(
                    'Beginning Order Update for '
                    . $ordersToProcess->count() . ' orders.'
                );
            }

            foreach ($ordersToProcess as $order) {

                $helper->logOrder(
                    'Updating order #' . $order->getIncrementId()
                );
                /**
                 * @var \FBAOutboundServiceMWS_Model_GetFulfillmentOrderResponse $result
                 */
                $result = $service->getFulfillmentOrder($order);

                if (!empty($result)) {
                    $fulfillmentOrderResult = $result
                        ->getGetFulfillmentOrderResult();

                    // Amazon Statuses: RECEIVED / INVALID / PLANNING / PROCESSING
                    // / CANCELLED / COMPLETE / COMPLETE_PARTIALLED / UNFULFILLABLE
                    $amazonStatus = $fulfillmentOrderResult->getFulfillmentOrder()
                        ->getFulfillmentOrderStatus();

                    $helper->logOrder(
                        'Status of order #'
                        . $order->getIncrementId() . ': ' . $amazonStatus
                    );

                    if (in_array(
                        $amazonStatus, array(
                            'COMPLETE',
                            'COMPLETE_PARTIALLED',
                        )
                    )
                    ) {
                        $this->magentoOrderUpdate($order, $fulfillmentOrderResult);
                    } elseif (in_array(
                        $amazonStatus, array(
                            'CANCELLED',
                            'UNFULFILLABLE',
                            'INVALID',
                        )
                    )
                    ) {
                        $this->cancelFBAShipment(
                            $order, $fulfillmentOrderResult, strtolower($amazonStatus)
                        );

                        break;
                    }
                }
            }
        }
    }

    /**
     * This processes all orders that should have been submitted to Amazon but
     * were unable to when initially placed for some reason.
     *
     * Processes up to self::NUM_ORDERS_TO_RESUBMIT orders at a time, and
     * retries each order self::NUM_ORDER_RESUMIT_RETRYS times before failing
     * permanently
     */
    public function resubmitOrdersToAmazon()
    {
        $stores = Mage::app()->getStores(true);
        /**
         * @var Amazon_MCF_Model_Service_Outbound $service
         */
        $service = Mage::getSingleton('amazon_mcf/service_outbound');
        /**
         *
         *
         * @var Amazon_MCF_Helper_Data $helper
         */
        $helper = Mage::helper('amazon_mcf');
        $mcfStores = array();

        foreach ($stores as $store) {
            if ($helper->isEnabled($store)) {
                $mcfStores[] = $store->getId();
            }
        }

        /**
         * @var \Mage_Sales_Model_Resource_Order_Collection $ordersToProcess
         */
        $ordersToProcess = Mage::getResourceModel(
            'sales/order_collection'
        );

        $ordersToProcess
            ->addFieldToFilter(
                'store_id', array(
                    'in' => $mcfStores,
                )
            )
            ->addFieldToFilter(
                'state', array(
                    'in' => array(
                        Mage_Sales_Model_Order::STATE_NEW,
                        Mage_Sales_Model_Order::STATE_PROCESSING,
                    ),
                )
            )
            ->addFieldToFilter('fulfilled_by_amazon', true)
            ->addFieldToFilter(
                'amazon_order_status', array(
                    'in' => array(
                        Amazon_MCF_Helper_Data::ORDER_STATUS_NEW,
                        Amazon_MCF_Helper_Data::ORDER_STATUS_ATTEMPTED,
                    ),
                )
            )
            ->setPageSize(self::NUM_ORDERS_TO_RESUBMIT)
            // always process the first page, order status will change once failed enough times
            ->setCurPage(1);

        foreach ($ordersToProcess as $order) {
            $helper->logOrder(
                'Retrying submission of order #' . $order->getIncrementId()
            );

            $currentAttempt = $order->getAmazonOrderSubmissionAttemptCount() + 1;
            /**
             * @var \FBAOutboundServiceMWS_Model_CreateFulfillmentOrderResponse $result
             */
            $result = $service->createFulfillmentOrder($order);
            $responseMetadata = $result->getResponseMetadata();

            if (!empty($result) && !empty($responseMetadata)) {
                $order->setAmazonOrderStatus(
                    Amazon_MCF_Helper_Data::ORDER_STATUS_RECEIVED
                );
            } elseif ($currentAttempt >= self::NUM_ORDER_RESUMIT_RETRYS) {
                $order->setAmazonOrderStatus(
                    Amazon_MCF_Helper_Data::ORDER_STATUS_FAIL
                );
                $helper->logOrder(
                    'Giving up on order #' . $order->getIncrementId()
                    . "after $currentAttempt tries."
                );
            } else {
                $order->setAmazonOrderSubmissionAttemptCount($currentAttempt);
            }

            $order->save();
        }

    }

    /**
     * This invoices and ships the order
     *
     * @param $order
     * @param $fulfillmentOrderResult
     */
    protected function magentoOrderUpdate($order, $fulfillmentOrderResult)
    {
        $this->createShipment($order, $fulfillmentOrderResult);
    }

    /**
     * @param $order
     * @param $fulfillmentOrder
     */
    protected function invoiceOrder($order, $fulfillmentOrder)
    {
        if ($order->canInvoice()) {
            $invoice = Mage::getModel('sales/service_order', $order)
                ->prepareInvoice();
            $invoice->setRequestedCaptureCase(
                Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE
            );
            $invoice->register();

            $transaction = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder());

            $transaction->save();
        }
    }

    /**
     * @param $order
     * @param $fulfillmentOrder
     */
    protected function createShipment($order, $fulfillmentOrder)
    {
        if ($order->canShip()) {
            $shipmentItems = array();
            $shipments = array();

            // group shipments by package number
            foreach ($fulfillmentOrder->getFulfillmentShipment()
                ->getmember() as $fulfillmentShipment) {

                foreach ($fulfillmentShipment->getFulfillmentShipmentItem()
                    ->getmember() as $details) {
                    if ($details) {
                        $shipments[$details->getPackageNumber()][] = array(
                            'sellerSku' => $details->getSellerSKU(),
                            'quantity' => $details->getQuantity(),
                        );
                    }
                }
            }

            if ($shipments) {

                $conversionHelper = Mage::helper('amazon_mcf/conversion');
                $packages = $this->getPackagesFromFulfillmentOrder($fulfillmentOrder);

                // match each package with tracking information
                foreach ($packages as $package) {

                    if (isset($shipments[$package->getPackageNumber()])) {
                        $shipments[$package->getPackageNumber()]['tracking'] = array(
                            'carrierCode' =>
                                $conversionHelper->getCarrierCodeFromPackage(
                                    $package
                                ),
                            'title' =>
                                $conversionHelper->getCarrierTitleFromPackage(
                                    $package
                                ),
                        );
                    }

                }

                // match order items with each shipment/package
                // so that 1 shipment can have 1..n orders based on packaging
                // information
                foreach ($order->getAllItems() as $orderItem) {
                    $product = $orderItem->getProduct();

                    foreach ($shipments as $packageNumber => $data) {

                        foreach ($data as $index => $item) {
                            if (isset($item['sellerSku'])
                                && (($item['sellerSku'] == $product->getSku())
                                || $item['sellerSku'] == $product->getAmazonMcfSku())
                            ) {
                                $id = $orderItem->getId();
                                $shipmentItems[$packageNumber][$id]
                                    = $item['quantity'];
                            }
                        }
                    }
                }

                // check to see if we have shipping item ids linked to quantities
                if ($shipmentItems) {

                    foreach ($shipmentItems as $packageNumber => $quantities) {
                        $shipment = Mage::getModel(
                            'sales/service_order',
                            $order
                        )
                            ->prepareShipment($quantities);
                        $shipment->register();

                        if (isset($shipments[$packageNumber]['tracking'])) {
                            $track = Mage::getModel(
                                'sales/order_shipment_track'
                            );
                            $track->setCarrierCode(
                                $shipments[$packageNumber]['tracking']['carrierCode']
                            );
                            $track->setTrackNumber($packageNumber);
                            $track->setTitle(
                                $shipments[$packageNumber]['tracking']['title']
                            );
                        }

                        $shipment->addTrack($track)->save();

                        // save the order, if fully invoiced and shipped
                        // will update to "complete"
                        $shipment->getOrder()->setIsInProcess(true);
                        $transactionSave = Mage::getModel(
                            'core/resource_transaction'
                        )
                            ->addObject($shipment)
                            ->addObject($shipment->getOrder())
                            ->save();

                        $shipment->sendEmail(true);
                    }
                }
            }
        }
    }

    /**
     * @param FBAOutboundServiceMWS_Model_FulfillmentOrder $order
     * @return array
     */
    protected function getPackagesFromFulfillmentOrder(
        \FBAOutboundServiceMWS_Model_FulfillmentOrder $order
    ) {
        /**
         * @var FBAOutboundServiceMWS_Model_FulfillmentShipmentList $shipments
         */
        $shipments = $order->getFulfillmentShipment();
        $packages = array();

        if (!empty($shipments)) {
            /**
             * @var FBAOutboundServiceMWS_Model_FulfillmentShipment $amazonShipment
             */
            foreach ($shipments->getmember() as $amazonShipment) {
                /**
                 * @var FBAOutboundServiceMWS_Model_FulfillmentShipmentPackageList $package
                 */
                $packages = array_merge(
                    $packages, $amazonShipment->getFulfillmentShipmentPackage()
                        ->getmember()
                );
            }
        }

        return $packages;
    }

    /**
     * Handles cancelation of items if order is canceled via seller central or
     * an item can't be fulfilled via FBA for some reason.
     *
     * @param  Mage_Sales_Model_Order                                $order
     * @param  FBAOutboundServiceMWS_Model_GetFulfillmentOrderResult $fulfillmentResult
     * @param  $amazonStatus
     * @throws Exception
     */
    protected function cancelFBAShipment(
        Mage_Sales_Model_Order $order,
        \FBAOutboundServiceMWS_Model_GetFulfillmentOrderResult
        $fulfillmentResult, $amazonStatus
    ) {
        $helper = Mage::helper('amazon_mcf');

        if ($order->canCancel()) {
            $shipment = $fulfillmentResult->getFulfillmentOrderItem();
            $skus = array();
            // Get skus from cancelled order
            foreach ($shipment->getmember() as $amazonShipment) {
                $skus[] = $amazonShipment->getSellerSKU();
            }

            // if there are skus, match them to products in the order.
            // We want to cancel specific items not the entire order.
            if ($skus) {
                $canceledSku = array();
                foreach ($order->getAllItems() as $item) {
                    $product = $item->getProduct();
                    if (in_array($product->getSku(), $skus)
                        || in_array($product->getAmazonMcfSku(), $skus)
                    ) {
                        // check to make sure the item hasn't already been canceled
                        if ($item->getQtyOrdered() != $item->getQtyCanceled()) {
                            $qty = $item->getQtyOrdered();

                            $item->setQtyCanceled($qty);
                            $item->save();
                            $canceledSku[] = $product->getSku();
                        }
                    }
                }

                // If we have canceled items, add a comment.
                if ($canceledSku) {
                    $helper->logOrder(
                        'FBA order canceled - items set to canceled with SKUs: '
                        . implode(", ", $canceledSku)
                    );
                    $order->addStatusHistoryComment(
                        "FBA items with Magento SKUs: "
                        . implode(", ", $canceledSku)
                        . " are unable to be fulfilled. 
                        Check your seller central account for more information."
                    );
                    $order->save();
                }
            }
        }
    }
}

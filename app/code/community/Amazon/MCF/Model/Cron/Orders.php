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

class Amazon_MCF_Model_Cron_Orders {

    /**
     * This number needs to keep in mind the API throttling, currently burst rate
     * of 30 and restore 2 per second.
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
    public function orderUpdate() {
        $stores = Mage::app()->getStores(TRUE);
        /** @var Amazon_MCF_Model_Service_Outbound $service */
        $service = Mage::getSingleton('amazon_mcf/service_outbound');
        /** @var Amazon_MCF_Helper_Data $helper */
        $helper = Mage::helper('amazon_mcf');

        foreach ($stores as $store) {
            if (!$helper->isEnabled($store)) {
                continue;
            }

            /** @var \Mage_Sales_Model_Resource_Order_Collection $ordersToProcess */
            $ordersToProcess = Mage::getResourceModel('sales/order_collection');
            $ordersToProcess
                ->addFieldToFilter('store_id', $store->getId())
                ->addFieldToFilter('state', array(
                    'in' => array(
                        Mage_Sales_Model_Order::STATE_NEW,
                        Mage_Sales_Model_Order::STATE_PROCESSING
                    )
                ))
                ->addFieldToFilter('fulfilled_by_amazon', TRUE)
                ->addFieldToFilter('amazon_order_status', array(
                    'in' => array(
                        Amazon_MCF_Helper_Data::ORDER_STATUS_RECEIVED,
                        Amazon_MCF_Helper_Data::ORDER_STATUS_PLANNING,
                        Amazon_MCF_Helper_Data::ORDER_STATUS_PROCESSING
                    )
                ))
            ;

            if ($ordersToProcess->count()) {
                $helper->logOrder('Beginning Order Update for ' . $ordersToProcess->count() . ' orders.');
            }

            foreach ($ordersToProcess as $order) {

                $helper->logOrder('Updating order #' . $order->getIncrementId());
                /** @var \FBAOutboundServiceMWS_Model_GetFulfillmentOrderResponse $result */
                $result = $service->getFulfillmentOrder($order);

                if (!empty($result)) {
                    $fulfillmentOrderResult = $result->getGetFulfillmentOrderResult();

                    // Amazon Statuses: RECEIVED / INVALID / PLANNING / PROCESSING / CANCELLED / COMPLETE / COMPLETE_PARTIALLED / UNFULFILLABLE
                    $amazonStatus = $fulfillmentOrderResult->getFulfillmentOrder()
                        ->getFulfillmentOrderStatus();

                    $helper->logOrder('Status of order #' . $order->getIncrementId() . ': ' . $amazonStatus);

                    if (in_array($amazonStatus, array('COMPLETE', 'COMPLETE_PARTIALLED'))) {
                        $this->magentoOrderUpdate($order, $fulfillmentOrderResult);
                    } elseif (in_array($amazonStatus, array('CANCELLED', 'UNFULFILLABLE'))) {
                        // Since cancellation came from Amazon, we don't want to sent it back to them
                        $order->setSkipAmazonCancel(true);
                        $order->cancel()->save();
                    }
                }
            }
        }
    }

    /**
     * This processes all orders that should have been submitted to Amazon but
     * were unable to when initially placed for some reason.
     *
     * Processes up to self::NUM_ORDERS_TO_RESUBMIT orders at a time, and retries
     * each order self::NUM_ORDER_RESUMIT_RETRYS times before failing permanently
     */
    public function resubmitOrdersToAmazon() {
        $stores = Mage::app()->getStores(TRUE);
        /** @var Amazon_MCF_Model_Service_Outbound $service */
        $service = Mage::getSingleton('amazon_mcf/service_outbound');
        /** @var Amazon_MCF_Helper_Data $helper */
        $helper = Mage::helper('amazon_mcf');
        $mcfStores = array();

        foreach ($stores as $store) {
            if ($helper->isEnabled($store)) {
                $mcfStores[] = $store->getId();
            }
        }

        /** @var \Mage_Sales_Model_Resource_Order_Collection $ordersToProcess */
        $ordersToProcess = Mage::getResourceModel('sales/order_collection');
        $ordersToProcess
            ->addFieldToFilter('store_id', array(
                'in' => $mcfStores
            ))
            ->addFieldToFilter('state', array(
                'in' => array(
                    Mage_Sales_Model_Order::STATE_NEW,
                    Mage_Sales_Model_Order::STATE_PROCESSING,
                )
            ))
            ->addFieldToFilter('fulfilled_by_amazon', TRUE)
            ->addFieldToFilter('amazon_order_status', array(
                'in' => array(
                    Amazon_MCF_Helper_Data::ORDER_STATUS_NEW,
                    Amazon_MCF_Helper_Data::ORDER_STATUS_ATTEMPTED,
                )
            ))
            ->setPageSize(self::NUM_ORDERS_TO_RESUBMIT)
            ->setCurPage(1) // always process the first page, order status will change once failed enough times
        ;

        foreach ($ordersToProcess as $order) {
            $helper->logOrder('Retrying submission of order #' . $order->getIncrementId());
            $currentAttempt = $order->getAmazonOrderSubmissionAttemptCount() + 1;
            /** @var \FBAOutboundServiceMWS_Model_CreateFulfillmentOrderResponse $result */
            $result = $service->createFulfillmentOrder($order);
            $responseMetadata = $result->getResponseMetadata();

            if (!empty($result) && !empty($responseMetadata)) {
                $order->setAmazonOrderStatus(Amazon_MCF_Helper_Data::ORDER_STATUS_RECEIVED);
            } elseif ($currentAttempt >= self::NUM_ORDER_RESUMIT_RETRYS) {
                $order->setAmazonOrderStatus(Amazon_MCF_Helper_Data::ORDER_STATUS_FAIL);
                $helper->logOrder('Giving up on order #' . $order->getIncrementId() . "after $currentAttempt tries.");
            } else {
                $order->setAmazonOrderSubmissionAttemptCount($currentAttempt);
            }

            $order->save();
        }

    }

    /**
     * This invoices and ships the order
     *
     * @param Mage_Sales_Model_Order $order
     * @param \FBAOutboundServiceMWS_Model_FulfillmentOrder $fulfillmentOrder
     */
    protected function magentoOrderUpdate($order, $fulfillmentOrderResult) {
        $fulfillmentOrder = $fulfillmentOrderResult->getFulfillmentOrder();
        $this->invoiceOrder($order, $fulfillmentOrder);
        $this->createShipment($order, $fulfillmentOrderResult);
    }

    protected function invoiceOrder($order, $fulfillmentOrder) {
        if ($order->canInvoice()) {
            $invoice = Mage::getModel('sales/service_order', $order)
                ->prepareInvoice();
            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
            $invoice->register();

            $transaction = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder());

            $transaction->save();
        }
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param $fulfillmentOrder
     */
    protected function createShipment($order, $fulfillmentOrder) {
        if ($order->canShip()) {
            $packages = $this->getPackagesFromFulfillmentOrder($fulfillmentOrder);

            foreach ($packages as $package) {
                /** @var Mage_Sales_Model_Order_Shipment $shipment */
                $qtys = array();
                $conversionHelper = Mage::helper('amazon_mcf/conversion');
                $shipment = Mage::getModel('sales/service_order', $order)
                    ->prepareShipment($qtys);
                $shipment->register();


                // Amazon Carrier Codes - USPS / UPS / UPSM
                /** @var Mage_Sales_Model_Order_Shipment_Track $track */
                $track = Mage::getModel('sales/order_shipment_track');
                $track->setCarrierCode($conversionHelper->getCarrierCodeFromPackage($package));
                $track->setTrackNumber($package->getTrackingNumber());
                $track->setTitle($conversionHelper->getCarrierTitleFromPackage($package));

                $shipment->addTrack($track)->save();

                // save the order, if fully invoiced and shipped will update to "complete"
                $shipment->getOrder()->setIsInProcess(TRUE);
                $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($shipment)
                    ->addObject($shipment->getOrder())
                    ->save();

                $shipment->sendEmail(true);
            }
        }
    }

    /**
     * @param \FBAOutboundServiceMWS_Model_FulfillmentOrder $order
     *
     * @return array
     */
    protected function getPackagesFromFulfillmentOrder(\FBAOutboundServiceMWS_Model_FulfillmentOrder $order) {
        /** @var FBAOutboundServiceMWS_Model_FulfillmentShipmentList $shipments */
        $shipments = $order->getFulfillmentShipment();
        $packages = array();

        if (!empty($shipments)) {
            /** @var FBAOutboundServiceMWS_Model_FulfillmentShipment $amazonShipment */
            foreach ($shipments->getmember() as $amazonShipment) {
                /** @var FBAOutboundServiceMWS_Model_FulfillmentShipmentPackageList $package */
                $packages = array_merge($packages, $amazonShipment->getFulfillmentShipmentPackage()->getmember());
            }
        }

        return $packages;
    }
}

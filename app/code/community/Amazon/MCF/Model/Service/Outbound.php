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

require_once Mage::getBaseDir('lib') . DS . 'Amazon'
    . DS . 'FBAOutboundServiceMWS' . DS . 'Client.php';
require_once Mage::getBaseDir('lib') . DS . 'Amazon'
    . DS . 'FBAOutboundServiceMWS' . DS . 'Mock.php';

/**
 * Class Amazon_MCF_Model_Service_Outbound
 */
class Amazon_MCF_Model_Service_Outbound extends Amazon_MCF_Model_Service_Abstract
{

    const SERVICE_NAME = '/FulfillmentOutboundShipment/';
    const SERVICE_CLASS = 'FBAOutboundServiceMWS';

    /**
     * @inheritdoc
     */
    protected function getServiceVersion()
    {
        return FBAOutboundServiceMWS_Client::SERVICE_VERSION;
    }

    /**
     * @param Mage_Customer_Model_Address_Abstract $address
     * @param $items
     * @return FBAOutboundServiceMWS_Model_GetFulfillmentPreviewResponse|null
     */
    public function getFulfillmentPreview(
        Mage_Customer_Model_Address_Abstract $address,
        $items
    ) {
        /**
         * @var Amazon_MCF_Helper_Data $helper
         */
        $helper = $this->helper;
        $client = $this->getClient();

        $conversionHelper = Mage::helper('amazon_mcf/conversion');
        $address = $conversionHelper->getAmazonAddressArray($address);

        $quoteDetails
            = array(
            'Address' => $address,
            'Items' => $items,
        );

        // If Amazon carrier is not enabled, will be shipped standard so only
        // get that preview
        if (!$helper->getCarrierEnabled()) {
            $quoteDetails['ShippingSpeedCategories']
                = array('member' => array('Standard'));
        }

        $request = $this->getRequest(
            $quoteDetails
        );

        try {
            $helper->logApi(
                'getFulfillmentPreview request: '
                . var_export($request, true)
            );
            $response = $client->getFulfillmentPreview($request);
            $helper->logApi(
                'getFulfillmentPreview response: '
                . $response->toXML()
            );
        } catch (\FBAOutboundServiceMWS_Exception $e) {
            $response = null;
            $helper->logApiError('getFulfillmentPreview', $e);
        }

        return $response;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return FBAOutboundServiceMWS_Model_CreateFulfillmentOrderResponse|null
     */
    public function createFulfillmentOrder(Mage_Sales_Model_Order $order)
    {
        /**
         * @var Amazon_MCF_Helper_Data $helper
         */
        $helper = $this->helper;
        $client = $this->getClient();

        $conversionHelper = Mage::helper('amazon_mcf/conversion');

        $address = $conversionHelper->getAmazonAddressArray(
            $order->getShippingAddress()
        );
        $items = $conversionHelper->getAmazonItemsArray($order->getAllItems());
        $timestamp = $conversionHelper->getIso8601Timestamp($order->getCreatedAt());
        $shipping = $conversionHelper->getShippingSpeed($order->getShippingMethod());
        $notificationEmailList = $conversionHelper->getNotificationEmailList($order);
        $orderComment = $helper->getPackingSlipComment($order->getStore());

        $request = $this->getRequest(
            array(
                'FulfillmentPolicy' => 'FillOrKill',
                'DestinationAddress' => $address,
                'SellerFulfillmentOrderId' => $order->getIncrementId(),
                'DisplayableOrderId' => $order->getIncrementId(),
                'DisplayableOrderDateTime' => $timestamp,
                'DisplayableOrderComment' => $orderComment,
                'ShippingSpeedCategory' => $shipping,
                'Items' => $items,
                'NotificationEmailList' => $notificationEmailList,
            ),
            $order->getStoreId()
        );

        try {
            $helper->logApi(
                'createFulfillmentOrder request: '
                . var_export($request, true)
            );
            $response = $client->createFulfillmentOrder($request);
            $helper->logApi(
                'createFulfillmentOrder response: ' . $response->toXML()
            );
        } catch (\FBAOutboundServiceMWS_Exception $e) {
            $response = null;
            $helper->logApiError('createFulfillmentOrder', $e);
        }
        return $response;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return FBAOutboundServiceMWS_Model_CancelFulfillmentOrderResponse|null
     */
    public function cancelFulfillmentOrder(Mage_Sales_Model_Order $order)
    {
        /**
         *
         *
         * @var Amazon_MCF_Helper_Data $helper
         */
        $helper = $this->helper;
        $client = $this->getClient();

        $request = $this->getRequest(
            array(
                'SellerFulfillmentOrderId' => $order->getIncrementId(),
            ),
            $order->getStoreId()
        );


        try {
            $helper->logApi(
                'cancelFulfillmentOrder request: '
                . var_export($request, true)
            );
            $response = $client->cancelFulfillmentOrder($request);
            $helper->logApi(
                'cancelFulfillmentOrder response: ' . $response->toXML()
            );
        } catch (\FBAOutboundServiceMWS_Exception $e) {
            $response = null;
            $helper->logApiError('cancelFulfillmentOrder', $e);
        }
        return $response;
    }

    /**
     * @param $order
     * @return FBAOutboundServiceMWS_Model_GetFulfillmentOrderResponse|null
     */
    public function getFulfillmentOrder($order)
    {
        /**
         * @var Amazon_MCF_Helper_Data $helper
         */
        $helper = $this->helper;
        $client = $this->getClient();

        $request = $this->getRequest(
            array(
                'SellerFulfillmentOrderId' => $order->getIncrementId(),
            ),
            $order->getStoreId()
        );

        try {
            $helper->logApi(
                'getFulfillmentOrder request: '
                . var_export($request, true)
            );
            $response = $client->getFulfillmentOrder($request);
            $helper->logApi(
                'getFulfillmentOrder response: ' . $response->toXML()
            );
        } catch (\FBAOutboundServiceMWS_Exception $e) {
            $response = null;
            $helper->logApiError('getFulfillmentOrder', $e);
        }

        return $response;
    }

    /**
     * @param string $productId
     * @param string $zip
     * @param int    $qty
     * @return bool|FBAOutboundServiceMWS_Model_GetFulfillmentPreviewResponse|null
     */
    public function getDeliveryEstimate($productId, $zip, $qty = 1)
    {
        /**
         * @var Amazon_MCF_Helper_Data $helper
         */
        $helper = $this->helper;
        $client = $this->getClient();
        $conversionHelper = Mage::helper('amazon_mcf/conversion');
        $product = Mage::getModel('catalog/product')->load($productId);

        if (!$product->getId() || empty($zip)) {
            return false;
        }

        $address = array(
            'Name' => 'Delivery Preview',
            'Line1' => '1 Main St',
            'Line2' => '',
            'Line3' => '',
            'DistrictOrCounty' => '',
            'City' => 'PreviewTown',
            'StateOrProvinceCode' => 'WA',
            'CountryCode' => 'US',
            'PostalCode' => $zip,
            'PhoneNumber' => '1234567890',
        );

        $items = array();
        $items[] = $conversionHelper->getAmazonItem($product, $qty);

        $quoteDetails
            = array(
            'Address' => $address,
            'Items' => array('member' => $items),
        );

        // If Amazon carrier is not enabled, will be shipped standard
        // so only get that preview
        if (!$helper->getCarrierEnabled()) {
            $quoteDetails['ShippingSpeedCategories']
                = array('member' => array('Standard'));
        }

        $request = $this->getRequest(
            $quoteDetails
        );

        try {
            $helper->logApi(
                'getFulfillmentPreview request for delivery estimate: '
                . var_export($request, true)
            );
            $response = $client->getFulfillmentPreview($request);
            $helper->logApi(
                'getFulfillmentPreview response for delivery estimate: '
                . $response->toXML()
            );
        } catch (\FBAOutboundServiceMWS_Exception $e) {
            $response = null;
            $helper->logApiError(
                'getFulfillmentPreview for delivery estimate', $e
            );
        }

        return $response;
    }
}
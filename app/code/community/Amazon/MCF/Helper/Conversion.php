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

require_once(Mage::getBaseDir('lib') . DS . 'Amazon'. DS .'FBAOutboundServiceMWS' . DS . 'Model' . DS . 'Address.php');
require_once(Mage::getBaseDir('lib') . DS . 'Amazon'. DS .'FBAOutboundServiceMWS' . DS . 'Model' . DS . 'GetFulfillmentPreviewItem.php');
require_once(Mage::getBaseDir('lib') . DS . 'Amazon'. DS .'FBAOutboundServiceMWS' . DS . 'Model' . DS . 'GetFulfillmentPreviewItemList.php');

class Amazon_MCF_Helper_Conversion extends Mage_Core_Helper_Abstract {

    const ISO8601_FORMAT = 'Y-m-d\TH:i:s.Z\Z';
    protected $carriers = array(
        'USPS' => array('carrier_code' => 'usps', 'title' => 'United States Postal Service'),
        'UPS'  => array('carrier_code' => 'ups', 'title' => 'United Parcel Service'),
        'UPSM' => array('carrier_code' => 'ups', 'title' => 'United Parcel Service')
    );

    /**
     * @param \Mage_Customer_Model_Address_Abstract $address
     *
     * @return \FBAOutboundServiceMWS_Model_Address
     */
    public function getAmazonAddress(Mage_Customer_Model_Address_Abstract $address)
    {
        $amazonAddress = new FBAOutboundServiceMWS_Model_Address($this->getAmazonAddressArray($address));
        return $amazonAddress;
    }

    /**
     * @param \Mage_Customer_Model_Address_Abstract $address
     *
     * @return array
     */
    public function getAmazonAddressArray(Mage_Customer_Model_Address_Abstract $address)
    {
        $regionCode = Mage::getModel('directory/region')->load($address->getRegionId())->getCode();
        $city = $address->getCity() ? $address->getCity() : 'Mytown';

        $addressData = array (
            'Name' => $address->getName(),
            'Line1' => $address->getStreet1(),
            'Line2' => $address->getStreet2(),
            'Line3' => $address->getStreet3(),
            'DistrictOrCounty' => $address->getCountry(),
            'City' => $city,
            'StateOrProvinceCode' => $regionCode,
            'CountryCode' => $address->getCountryId(),
            'PostalCode' => $address->getPostcode(),
            'PhoneNumber' => $address->getTelephone(),
        );
        return $addressData;
    }

    /**
     * @param \Mage_Sales_Model_Resource_Quote_Item_Collection $items
     *
     * @return \FBAOutboundServiceMWS_Model_GetFulfillmentPreviewItemList|false
     */
    public function getAmazonItems(Mage_Sales_Model_Resource_Quote_Item_Collection $items)
    {
        $amazonItemsData = $this->getAmazonItemsArray($items);
        if (!empty($amazonItemsData) && !empty($amazonItems['member'])) {
            $amazonItems = new FBAOutboundServiceMWS_Model_GetFulfillmentPreviewItemList($amazonItemsData);
            return $amazonItems;
        }

        return false;
    }

    /**
     * @param array $items
     *
     * @return \FBAOutboundServiceMWS_Model_GetFulfillmentPreviewItemList|false
     */
    public function getAmazonItemsArrayFromRateRequest(array $items)
    {
        $amazonItems = array();
        foreach($items as $item) {
            $product = $item->getProduct();
            if ($product->getAmazonMcfEnabled()) {
                $qty = $item->getQty();
                $amazonItems[] = $this->getAmazonItem($product, $qty, $item->getQuoteId() . $item->getProductId());
            }
        }

        if (!empty($amazonItems)) {
            return  array('member' => $amazonItems);
        }

        return false;
    }

    /**
     * Builds an array suitable for use with Amazon API library from quote item
     * Collection or from an array of order items.
     *
     * @param \Mage_Sales_Model_Resource_Quote_Item_Collection|array $items
     *
     * @return array
     */
    public function getAmazonItemsArray($items)
    {
        $amazonItems = array();
        foreach($items as $item) {
            $product = $item->getProduct();
            if ($product->getAmazonMcfEnabled()) {
                // qty differs for quote vs order items
                $qty = $item->getQty() ? $item->getQty() : $item->getQtyOrdered();
                $amazonItems[] = $this->getAmazonItem($product, $qty, $item->getItemId());
            }
        }

        return array('member' => $amazonItems);
    }

    /**
     * Returns the array representation of a product to be used in a fulfillment preview
     *
     * @param $product
     * @param int $qty
     * @param int $itemId
     *
     * @return array
     */
    public function getAmazonItem($product, $qty, $itemId = 1) {
        $sku = $product->getAmazonMcfSku() ? $product->getAmazonMcfSku() : $product->getSku();
        $itemData = array(
            'SellerSKU' => $sku,
            'SellerFulfillmentOrderItemId' => $itemId,
            'Quantity' => $qty,
        );

        return $itemData;
    }

    public function getIso8601Timestamp($timestamp)
    {
        $timestamp = strtotime($timestamp);
        $converted = date(self::ISO8601_FORMAT, $timestamp);
        return $converted;
    }

    public function getShippingSpeed($shippingMethod)
    {
        /**
         * Map built in Flat Rate, Table Rate, and Free Shipping to Amazon Standard shipping
         */
        $methods = array(
            'flatrate_flatrate' => 'Standard',
            'freeshipping_freeshipping' => 'Standard',
            'tablerate_bestway' => 'Standard',
            'amazon_mcf_carrier_standard' => 'Standard',
            'amazon_mcf_carrier_priority' => 'Priority',
            'amazon_mcf_carrier_expedited' => 'Expedited',
        );

        return $methods[$shippingMethod];
    }

    public function getCarrierCodeFromPackage($package) {
        return $this->carriers[$package->getCarrierCode()]['carrier_code'];
    }

    public function getCarrierTitleFromPackage($package) {
        return $this->carriers[$package->getCarrierCode()]['title'];
    }

    public function getNotificationEmailList($order) {
        $notificationEmailList = array();

        if (Mage::helper('amazon_mcf')->sendShipmentEmail($order->getStore())) {
            $email = substr($order->getCustomerEmail(), 0, 64);
            $notificationEmailList[] = $email;
        }

        return array('member' => $notificationEmailList);
    }
}

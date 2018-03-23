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
 * Class Amazon_MCF_Model_Carrier_Amazon
 */
class Amazon_MCF_Model_Carrier_Amazon
    extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{
    protected $_code = 'amazon_mcf_carrier';
    protected $_isFixed = false;
    protected $_methodTitles = array(
        'standard' => 'Standard',
        'expedited' => 'Expedited',
        'priority' => 'Priority',
    );

    /**
     * @param Mage_Shipping_Model_Rate_Request $request
     * @return bool|false|Mage_Core_Model_Abstract|Mage_Shipping_Model_Rate_Result|null
     */
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $helper = Mage::helper('amazon_mcf');
        $result = Mage::getModel('shipping/rate_result');

        $rates = $this->getShippingRates($request);

        foreach ($rates as $method => $details) {
            /**
             * @var Mage_Shipping_Model_Rate_Result_Method $rate
             */
            $rate = Mage::getModel('shipping/rate_result_method');
            $rate->setCarrier($this->_code);
            $rate->setCarrierTitle($this->getConfigData('title'));
            $rate->setMethod(strtolower($method));
            $methodTitle = $method;
            if ($helper->getDisplayEstimatedArrival()
                && array_key_exists('earliest', $details)
                && array_key_exists('latest', $details)
            ) {
                $methodTitle = $method . ' (Delivery '
                    . date('n/j', $details['earliest']) . ' - '
                    . date('n/j', $details['latest']) . ')';
            }
            $rate->setMethodTitle($methodTitle);
            $rate->setPrice($details['fee']);
            $rate->setCost(0);
            $result->append($rate);
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        return array(
            'standard' => 'Standard',
            'expedited' => 'Expedited',
            'priority' => 'Priority',
        );
    }

    /**
     * Do shipment request to carrier web service, obtain Print Shipping Labels
     * and process errors in response
     *
     * @param Varien_Object $request
     *
     * @return Varien_Object
     */
    protected function _doShipmentRequest(Varien_Object $request)
    {
        $result = new Varien_Object();

        return $result;
    }

    /**
     * @param Mage_Shipping_Model_Rate_Request $request
     * @return array
     */
    protected function getShippingRates(Mage_Shipping_Model_Rate_Request $request)
    {
        $helper = Mage::helper('amazon_mcf');
        $rates = array();
        $items = $this->getItemsFromShippingRateRequest($request);

        // No rates if no FBA items
        if (empty($items)) {
            return $rates;
        }

        /**
         * @var Amazon_MCF_Model_Service_Outbound $service
         */
        $service = Mage::getSingleton('amazon_mcf/service_outbound');
        $address = $this->getAddressFromShippingRateRequest($request);

        $fulfillmentPreview = $service->getFulfillmentPreview($address, $items);
        if (!empty($fulfillmentPreview)) {
            $rates = $this->getRatesFromFulfillmentPreview($fulfillmentPreview);
        }

        // either not configured or wasn't able to pull Amazon rates,
        // use fallback amounts
        if (empty($rates)) {
            $rates = array(
                'Standard' => array(
                    'fee' => $helper->getDefaultStandardShippingCost(
                        $request->getStoreId()
                    )
                )
            );
        } elseif (!$helper->getUseAmazonShippingFees($request->getStoreId())) {
            $defaultRates = array(
                'Standard' => array(
                    'fee' => $helper->getDefaultStandardShippingCost(
                        $request->getStoreId()
                    )
                ),
                'Expedited' => array(
                    'fee' => $helper->getDefaultExpeditedShippingCost(
                        $request->getStoreId()
                    )
                ),
                'Priority' => array(
                    'fee' => $helper->getDefaultPriorityShippingCost(
                        $request->getStoreId()
                    )
                )
            );

            // Only update rates that are returned for the destination
            foreach ($rates as $speed => $rate) {
                $rates[$speed]['fee'] = $defaultRates[$speed]['fee'];
            }
        }

        // check for non-FBA items in cart and prevent Amazon rates
        // from being offered.
        $isFBA = false;
        $nonFBA = false;

        $items = $request->getAllItems();
        if ($items) {
            foreach ($items as $item) {

                if ($item->getProduct()->getAmazonMcfEnabled()) {
                    $isFBA = true;
                } else {
                    $nonFBA = true;
                }
            }


            if (!$rates && $isFBA && !$nonFBA) {
                $rates = array(
                    'Standard' => array(
                        'price' => $helper->getDefaultStandardShippingCost(
                            $request->getStoreId()
                        )
                    )
                );
            }

            // if any non-fba items are in cart, offer no rates
            if ($nonFBA) {
                $rates = array();
            }
        }

        return $rates;
    }

    /**
     * @param Mage_Shipping_Model_Rate_Request $request
     * @return Mage_Sales_Model_Quote_Address
     */
    protected function getAddressFromShippingRateRequest(
        Mage_Shipping_Model_Rate_Request $request
    ) {
        /**
         * @var Mage_Sales_Model_Quote_Address $address
         */
        $address = Mage::getModel('sales/quote_address');
        $street = $request->getDestStreet();
        if (empty($street)) {
            $street = '1 Main St';
        }

        $address
            ->setFirstname('John')
            ->setLastname('Doe')
            ->setStreet($street)
            ->setCity($request->getDestCity())
            ->setRegionId($request->getDestRegionId())
            ->setCountryId($request->getDestCountryId())
            ->setPostcode($request->getDestPostcode())
            ->setTelephone('123456790');

        return $address;
    }

    /**
     * @param \Mage_Shipping_Model_Rate_Request $request
     *
     * @return \FBAOutboundServiceMWS_Model_GetFulfillmentPreviewItemList
     */
    protected function getItemsFromShippingRateRequest(
        Mage_Shipping_Model_Rate_Request $request
    ) {
        $items = $request->getAllItems();
        $conversionHelper = Mage::helper('amazon_mcf/conversion');

        return $conversionHelper->getAmazonItemsArrayFromRateRequest($items);

    }

    /**
     * @param $fulfillmentPreview
     * @return array
     */
    public function getRatesFromFulfillmentPreview($fulfillmentPreview)
    {
        $previews = $fulfillmentPreview->getGetFulfillmentPreviewResult()
            ->getFulfillmentPreviews()->getmember();
        $rates = array();

        /**
         * @var FBAOutboundServiceMWS_Model_FulfillmentPreview $preview
         */
        foreach ($previews as $preview) {
            if ($preview->getIsFulfillable() != 'false') {
                $title = $preview->getShippingSpeedCategory();
                $earliestDelivery = $preview->getFulfillmentPreviewShipments()
                    ->getMember()[0]->getEarliestArrivalDate();
                $latestDelivery = $preview->getFulfillmentPreviewShipments()
                    ->getMember()[0]->getLatestArrivalDate();
                $shippingFee = $this->calculateShippingFee(
                    $preview->getEstimatedFees()
                );

                $rates[$title] = array(
                    'fee' => $shippingFee,
                    'earliest' => strtotime($earliestDelivery),
                    'latest' => strtotime($latestDelivery)
                );
            }
        }

        return $rates;
    }

    /**
     * Sum the fees returned for the fulfillment preview to use as the shipping fee
     *
     * @param \FBAOutboundServiceMWS_Model_FeeList $fees
     *
     * @return float
     */
    protected function calculateShippingFee(FBAOutboundServiceMWS_Model_FeeList $fees)
    {
        $totalFee = 0.00;
        foreach ($fees->getmember() as $fee) {
            $totalFee += $fee->getAmount()->getValue();
        }

        return $totalFee;
    }
}

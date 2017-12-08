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

class Amazon_MCF_AjaxController extends Mage_Core_Controller_Front_Action
{

    /**
     * Delivery date estimate action
     */
    public function deliveryEstimateAction() {
        $zip = $this->getRequest()->getParam('zip');
        $productId = $this->getRequest()->getParam('product_id');
        $qty = $this->getRequest()->getParam('qty');

        /** @var Amazon_MCF_Model_Service_Outbound $service */
        $service = Mage::getSingleton('amazon_mcf/service_outbound');
        $fulfillmentPreview = $service->getDeliveryEstimate($productId, $zip, $qty);

        $amazonShipping = Mage::getModel('amazon_mcf/carrier_amazon');
        $deliveryData = $amazonShipping->getRatesFromFulfillmentPreview($fulfillmentPreview);

        $rates = array();
        foreach ($deliveryData as $speed => $rate) {
            $sortedRates[$rate['earliest'] . $speed] = $speed;
        }

        foreach ($sortedRates as $speed) {
            $rate = $deliveryData[$speed];
            $earliest = date(Amazon_MCF_Helper_Conversion::ISO8601_FORMAT, $rate['earliest']);
            $latest = date(Amazon_MCF_Helper_Conversion::ISO8601_FORMAT, $rate['latest']);
            $rates[] = array('type' => $speed, 'earliest' => $earliest, 'latest' => $latest, 'cost' => $rate['fee']);
        }

        $jsonData = array(
            'result' => TRUE,
            'message' => 'Rates available',
            'data' => $rates,
        );

        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($jsonData));
    }
}
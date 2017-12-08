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

require_once(Mage::getBaseDir('lib') . DS . 'Amazon'. DS .'FBAInventoryServiceMWS' . DS . 'Client.php');
require_once(Mage::getBaseDir('lib') . DS . 'Amazon'. DS .'FBAInventoryServiceMWS' . DS . 'Mock.php');

class Amazon_MCF_Model_Service_Inventory extends Amazon_MCF_Model_Service_Abstract {

    const SERVICE_NAME = '/FulfillmentInventory/';
    const SERVICE_CLASS = 'FBAInventoryServiceMWS';

    protected function getServiceVersion() {
        return FBAInventoryServiceMWS_Client::SERVICE_VERSION;
    }
    /**
     * Gets a list of inventory details based on Skus passed in an array
     *
     * @param $sellerSKUs array
     *
     * @return mixed
     */
    public function getFulfillmentInventoryList($sellerSKUs = array(), $startTime = '') {

        /** @var Amazon_MCF_Helper_Data $helper */
        $helper = $this->helper;
        $client = $this->getClient();

        $request = $this->getRequest(
            array(
                'SellerSkus' => $sellerSKUs,
            )
        );

        if ($startTime && !$sellerSKUs) {
            $request['QueryStartDateTime'] = $startTime;
        }

        try {
            $helper->logApi('listInventorySupply request: ' . var_export($request, true));
            $response = $client->listInventorySupply($request);
            $helper->logApi('listInventorySupply response: ' . $response->toXML());
        } catch (\FBAInventoryServiceMWS_Exception $e) {
            $response = NULL;
            $helper->logApiError('listInventorySupply', $e);
        }

        return $response;
    }

    public function getListInventorySupplyByNextToken($nextToken) {

        /** @var Amazon_MCF_Helper_Data $helper */
        $helper = $this->helper;
        $client = $this->getClient();

        $request = $this->getRequest(
            array(
                'NextToken' => $nextToken
            )
        );

        try {
            $helper->logApi('listInventorySupplyByNextToken request: ' . var_export($request, true));
            $response = $client->listInventorySupplyByNextToken($request);
            $helper->logApi('listInventorySupplyByNextToken response: ' . $response->toXML());
        } catch (\FBAInventoryServiceMWS_Exception $e) {
            $response = NULL;
            $helper->logApiError('listInventorySupplyByNextToken', $e);
        }

        return $response;
    }
}

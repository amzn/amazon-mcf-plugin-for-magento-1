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

require_once Mage::getBaseDir('lib') . DS . 'Amazon' . DS
    . 'MarketplaceWebServiceSellers' . DS . 'Client.php';
require_once Mage::getBaseDir('lib') . DS . 'Amazon' . DS
    . 'MarketplaceWebServiceSellers' . DS . 'Mock.php';

/**
 * Class Amazon_MCF_Model_Service_Sellers
 */
class Amazon_MCF_Model_Service_Sellers extends Amazon_MCF_Model_Service_Abstract
{
    const SERVICE_NAME = '/Sellers/';
    const SERVICE_CLASS = 'MarketplaceWebServiceSellers';

    /**
     * @inheritdoc
     */
    protected function getServiceVersion()
    {
        return MarketplaceWebServiceSellers_Client::SERVICE_VERSION;
    }

    /**
     * @param string $marketplace
     * @param string $sellerId
     * @param string $accessKey
     * @param string $secretKey
     * @return null
     */
    public function validateCredentials(
        $marketplace,
        $sellerId,
        $accessKey = '******',
        $secretKey = '******'
    ) {
        /**
         * @var Amazon_MCF_Helper_Data $helper
         */
        $helper = $this->helper;

        $endpoint = $helper->getEndpointForCountry($marketplace);
        $marketplaceId = $helper->getMarketplaceIdForCountry($marketplace);

        if ($secretKey == '******') {
            $secretKey = $helper->getSecretAccessKey();
        }

        if ($accessKey == '******') {
            $accessKey = $helper->getAccessKeyId();
        }

        $config = array(
            'ServiceURL' => $this->getServiceUrl(null, $endpoint),

        );

        $serviceClass = $this->getServiceClass();

        $client = new $serviceClass(
            $accessKey,
            $secretKey,
            $helper->getApplicationName(),
            $helper->getApplicationVersion(),
            $config
        );

        $request = array(
            'SellerId' => $sellerId,
            'MarketplaceId' => $marketplaceId,
            'ServiceURL' => $this->getServiceUrl(null, $endpoint),
        );

        try {
            $helper->logApi(
                'Validating credentials with a listMarketplaceParticipations 
                request: '
                . var_export($request, true)
            );
            $response = $client->listMarketplaceParticipations($request);
            $helper->logApi(
                'listMarketplaceParticipations response: '
                . $response->toXML()
            );
        } catch (\MarketplaceWebServiceSellers_Exception $e) {
            $response = null;
            $helper->logApiError('listMarketplaceParticipations', $e);
        }

        return $response;
    }

    /**
     * @return mixed
     */
    public function listMarketplaceParticipations()
    {
        $helper = $this->helper;

        $config = array(
            'ServiceURL' => $this->getServiceUrl(),
        );


        $serviceClass = $this->getServiceClass();

        $client = new $serviceClass(
            $helper->getAccessKeyId(),
            $helper->getSecretAccessKey(),
            $helper->getApplicationName(),
            $helper->getApplicationVersion(),
            $config
        );

        $request = array(
            'SellerId' => $helper->getSellerId()
        );

        return $client->listMarketplaceParticipations($request);
    }

}

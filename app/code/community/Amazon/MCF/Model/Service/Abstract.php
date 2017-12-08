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

class Amazon_MCF_Model_Service_Abstract
{
    protected $helper = null;

    // Each implementing class should override this with correct service name
    const SERVICE_NAME = null;

    // Each implementing class should override this with correct service class name
    const SERVICE_CLASS = null;

    public function __construct()
    {
        /** @var Amazon_MCF_Helper_Data helper */
        $this->helper= Mage::helper('amazon_mcf');
    }

    /**
     * Each implementing class should override this with correct service version
     *
     * @return string
     */
    protected function getServiceVersion()
    {
        return '2017-01-01';
    }

    protected function getServiceUrl($store = null, $endpoint = null)
    {
        if (empty($endpoint)) {
            $endpoint = $this->helper->getEndpoint($store);
        }

        return $endpoint . $this::SERVICE_NAME . $this->getServiceVersion();
    }

    protected function getServiceClass()
    {
        return $this::SERVICE_CLASS . ($this->helper->isDebug() ? '_Mock' : '_Client');
    }

    /**
     * @return FBAOutboundServiceMWS_Client
     */
    protected function getClient()
    {
        /** @var Amazon_MCF_Helper_Data $helper */
        $helper = $this->helper;

        $config = array(
            'ServiceURL' => $this->getServiceUrl(),
        );

        $serviceClass = $this->getServiceClass();

        $client = new $serviceClass(
            $helper->getAccessKeyId(),
            $helper->getSecretAccessKey(),
            $config,
            $helper->getApplicationName(),
            $helper->getApplicationVersion()
        );

        return $client;
    }

    /**
     * @return array
     */
    protected function getRequest($params = array(), $store = null)
    {
        return array_merge(
            array(
                'SellerId' => $this->helper->getSellerId($store),
                'MarketplaceId' => $this->helper->getMarketplaceId($store),
            ),
            $params
        );
    }
}

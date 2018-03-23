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
 * Class Amazon_MCF_Block_Catalog_DeliveryEstimate
 */
class Amazon_MCF_Block_Catalog_DeliveryEstimate extends Mage_Core_Block_Template
{
    protected $product;

    /**
     *
     */
    public function _construct() 
    {
        parent::_construct();

        $this->product = Mage::registry('current_product');
    }

    /**
     * Check if Amazon Carrier is enabled and the product is configured to be
     * fulfilled by Amazon
     *
     * @return bool
     */
    public function isFBAEnabled() 
    {
        $helper = Mage::helper('amazon_mcf');
        $enabled = false;
        if ($helper->getDisplayDeliveryEstimatorPdp() 
            && $this->product->getAmazonMcfEnabled()
        ) {
            $enabled = true;
        }

        return $enabled;
    }

    /**
     * @return mixed
     */
    public function getProductId() 
    {
        return $this->product->getId();
    }
}

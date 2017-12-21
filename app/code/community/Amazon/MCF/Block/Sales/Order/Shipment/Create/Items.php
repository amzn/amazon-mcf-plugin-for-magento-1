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

class Amazon_MCF_Block_Sales_Order_Shipment_Create_Items extends Mage_Adminhtml_Block_Sales_Order_Shipment_Create_Items
{

    public function FBAEnabled() {
        $helper = Mage::helper('amazon_mcf');
        return $helper->isEnabled() && $helper->getCarrierEnabled();
    }

    /**
     * Prints warning message for use with JavaScript flag.
     * @return string
     */
    public function getFBAWarningMessage() {
        if ($this->FBAEnabled()) {
            return __('This item will be updated as shipped after FBA shipping completed, are you sure you want to manually ship this item in Magento?');
        }

        return '';
    }

    /**
     * Flags a shipment item row with class indicating it is fulfilled by Amazon
     * @param $item
     *
     * @return string
     */
    public function isFBAItem($item) {

        if ($this->FBAEnabled()) {

            $product = Mage::getModel('catalog/product')->load($item->getProductId());


            if ($product->getAmazonMcfEnabled()) {
                return ' isFBA';
            }

        }

        return '';
    }
}

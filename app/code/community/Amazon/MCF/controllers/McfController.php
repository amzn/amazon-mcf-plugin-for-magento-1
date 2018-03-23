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
 * Class Amazon_MCF_McfController
 */
class Amazon_MCF_McfController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Sync page callback
     */
    public function syncAction()
    {
        /**
         * @var Amazon_MCF_Helper_Data $helper
         */
        $helper = Mage::helper('amazon_mcf');
        $startedSync = $helper->startInventorySync();

        if ($startedSync) {
            $message = 'Started full inventory sync via admin action';
            Mage::getSingleton("adminhtml/session")
                ->addSuccess($this->__($message));
        } else {
            $message = 'Inventory sync already running';
            Mage::getSingleton("adminhtml/session")
                ->addWarning($this->__($message));
        }
        $helper->logInventory($message);

        $this->_redirectReferer();
    }

    /**
     * Validates Amazon credentials
     */
    public function validateCredentialsAction()
    {
        $request = $this->getRequest();
        $marketplace = $request->getParam('marketplace');
        $sellerId = $request->getParam('seller_id');
        $accessKey = $request->getParam('access_key_id');
        $secretKey = $request->getParam('secret_access_key');

        /**
         * @var Amazon_MCF_Model_Service_Sellers $service
         */
        $service = Mage::getModel('amazon_mcf/service_sellers');
        $result = $service->validateCredentials(
            $marketplace,
            $sellerId,
            $accessKey,
            $secretKey
        );

        if ($result) {
            $jsonData = array(
                'result' => true,
                'message' => '<b style="color:green">'
                    . $this->__(
                        "Your Amazon MWS API credentials are valid, 
                    please save config to apply."
                    ) . '</b>',
            );
        } else {
            $jsonData = array(
                'result' => true,
                'message' => '<b style="color:red">'
                    . $this->__(
                        "Your Amazon MWS API credentials are not valid. 
                    Please verify keys were entered correctly, and check user guide 
                    for more details on obtaining keys."
                    ) . '</b>',
            );
        }

        $this->getResponse()->setHeader(
            'Content-type',
            'application/json'
        );
        $this->getResponse()
            ->setBody(Mage::helper('core')->jsonEncode($jsonData));
    }
}

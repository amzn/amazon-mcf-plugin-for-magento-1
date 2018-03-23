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
 * @var $installer Mage_Catalog_Model_Resource_Setup 
 */
$installer = $this;
$installer->startSetup();

$inventorySyncVar = Mage::getModel('core/variable')
    ->setCode(Amazon_MCF_Helper_Data::CORE_VAR_INVENTORY_SYNC_TOKEN)
    ->setName('Amazon MCF Inventory Sync Token')
    ->setPlainValue('')
    ->save();

$inventorySyncPageVar = Mage::getModel('core/variable')
    ->setCode(Amazon_MCF_Helper_Data::CORE_VAR_INVENTORY_SYNC_PAGE)
    ->setName('Amazon MCF Inventory Sync Page')
    ->setPlainValue('')
    ->save();

$inventorySyncRunningVar = Mage::getModel('core/variable')
    ->setCode(Amazon_MCF_Helper_Data::CORE_VAR_INVENTORY_SYNC_RUNNING)
    ->setName('Amazon MCF Inventory Sync Running')
    ->setPlainValue('')
    ->save();

$orderSyncVar = Mage::getModel('core/variable')
    ->setCode(Amazon_MCF_Helper_Data::CORE_VAR_ORDER_SYNC_TOKEN)
    ->setName('Amazon MCF Order Sync Token')
    ->setPlainValue('')
    ->save();

$orderSyncPageVar = Mage::getModel('core/variable')
    ->setCode(Amazon_MCF_Helper_Data::CORE_VAR_ORDER_SYNC_PAGE)
    ->setName('Amazon MCF Order Sync Page')
    ->setPlainValue('')
    ->save();

$orderSyncRunningVar = Mage::getModel('core/variable')
    ->setCode(Amazon_MCF_Helper_Data::CORE_VAR_ORDER_SYNC_RUNNING)
    ->setName('Amazon MCF Order Sync Running')
    ->setPlainValue('')
    ->save();

$installer->endSetup();

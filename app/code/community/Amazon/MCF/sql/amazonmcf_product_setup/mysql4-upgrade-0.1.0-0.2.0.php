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

/** @var $installer Mage_Sales_Model_Mysql4_Setup */
$installer = new Mage_Sales_Model_Mysql4_Setup();
$installer->startSetup();

$fba_attr = array (
    'label' => 'Fulfilled By Amazon',
    'type' => 'varchar',
    'user_defined' => false,
    'input' => 'select',
    'source' => 'eav/entity_attribute_source_boolean',
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    'required' => false,
    'visible' => true,
    'default' => false,

);

$installer->addAttribute('order', 'fulfilled_by_amazon', $fba_attr);

$installer->endSetup();


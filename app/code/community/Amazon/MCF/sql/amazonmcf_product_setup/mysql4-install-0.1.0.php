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

/** @var $installer Mage_Catalog_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$enabled_attr = array (
    'label' => 'Amazon Multi-Channel Fulfillment Enabled',
    'type' => 'varchar',
    'user_defined' => false,
    'input' => 'select',
    'source' => 'eav/entity_attribute_source_boolean',
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    'required' => false,
    'default' => false,
    'apply_to' => Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
);

$sku_attr = array (
    'label' => 'Amazon SKU Override',
    'type' => 'text',
    'user_defined' => false,
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    'required' => false,
    'apply_to' => Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
);

$installer->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'amazon_mcf_enabled', $enabled_attr);
$installer->addAttribute(Mage_Catalog_Model_Product::ENTITY, 'amazon_mcf_sku', $sku_attr);

$installer->endSetup();


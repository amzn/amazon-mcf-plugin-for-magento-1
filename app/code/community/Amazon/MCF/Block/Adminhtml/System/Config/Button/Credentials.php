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
 * Class Amazon_MCF_Block_Adminhtml_System_Config_Button_Credentials
 */
class Amazon_MCF_Block_Adminhtml_System_Config_Button_Credentials
    extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Constructor
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('amazon/system/config/credentials_button.phtml');
    }

    /**
     * @inheritdoc
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->_toHtml();
    }

    /**
     * @return string
     */
    public function getAjaxValidateUrl()
    {
        return $this->getUrl('adminhtml/mcf/validateCredentials');
    }

    /**
     * @return mixed
     */
    public function getButtonHtml()
    {
        $html = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setType('button')
            ->setClass('scalable')
            ->setLabel($this->helper('adminhtml')->__('Validate Credentials'))
            ->setOnClick("javascript:validateCredentials(); return false;")
            ->toHtml();

        return $html;
    }
}

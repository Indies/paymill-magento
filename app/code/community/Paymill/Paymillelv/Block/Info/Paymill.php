<?php

class Paymill_Paymillelv_Block_Info_Paymill extends Mage_Payment_Block_Info_Cc
{
    /**
     * Prepare credit card related payment info
     *
     * @param Varien_Object|array $transport
     * @return Varien_Object
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        $transport = parent::_prepareSpecificInformation($transport);
        $additionalInformation = array();
        if(Mage::app()->getStore()->isAdmin()) {
            $orderId = $this->getRequest()->getParam('order_id');
            if (!empty($orderId)) {
                $order = Mage::getSingleton('sales/order'); 
                $order->load($orderId);
                $additionalInformation = array(
                    'Transaction ID' => ' ' . $order->getPayment()
                                                ->getAdditionalInformation('paymill_transaction_id')
                );
            }
        }
        
        return $transport->setData($additionalInformation);
    }
}

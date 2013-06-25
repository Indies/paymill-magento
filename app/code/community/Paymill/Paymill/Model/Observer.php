<?php
class Paymill_Paymill_Model_Observer{
   
    /**
     * Registered for the checkout_onepage_controller_success_action event
     * Generates the invoice for the current order
     * 
     * @param Varien_Event_Observer $observer
     */
    public function generateInvoice(Varien_Event_Observer $observer)
    {
        $paymentCode = Mage::getSingleton('core/session')->getPaymentCode();
        if($paymentCode === 'paymill_creditcard' || $paymentCode === 'paymill_directdebit'){
            $orderIds = $observer->getEvent()->getOrderIds();
            if ($orderIds) {
                $orderId = current($orderIds);
                if (!$orderId) {
                    return;
                }
            }
            
            if( Mage::getModel("paymill/transaction")->getPreAuthenticatedFlagState($orderId)){ // If the transaction is not flagged as a debit (not a preAuth) transaction
                $order = Mage::getModel('sales/order')->load($orderId);
                if($order->canInvoice()) {
                    //Create the Invoice
                    Mage::helper('paymill/loggingHelper')->log(Mage::helper('paymill')->__($paymentCode), Mage::helper('paymill')->__('paymill_checkout_generating_invoice'), "Order Id: ".$orderId); 
                    $invoiceId = Mage::getModel('sales/order_invoice_api')->create($order->getIncrementId(), array());
                    Mage::getModel('sales/order_invoice_api')->capture($invoiceId);
                }
            }
        }
    }
}

<?php
/**
 * The FastCheckout Helper contains methods dealing with the fast checkout process.
 * Examples for this might be f.Ex a customers userId used for matching client data or methods to grant easier access the db information.
 */
class Paymill_Paymill_Helper_FastCheckoutHelper extends Mage_Core_Helper_Abstract
{
    /**
     * Calls the Data helper to get the state of the Fast Checkout option
     */
    public function isFastCheckoutEnabled()
    {
        return Mage::helper("paymill/optionHelper")->isFastCheckoutEnabled();
    }
    
    /**
     * Returns the clientId matched with the userId passed as an argument.
     * If no match is found, the return value will be null.
     * @param String $userId Unique identifier of the customer
     * @return String clientId matched with the userId <b>can be null if no match is found</b>
     */
    public function getClientId()
    {
        $userId = Mage::helper("paymill/customerHelper")->getUserId();
        $collection = Mage::getModel('paymill/fastcheckout')->getCollection();
        $collection->addFilter('user_id', $userId);
        $obj = $collection->getFirstItem();
        return $obj->getClientId();
    }
    
    /**
     * Returns the PaymentId associated with the current user
     * @param String $code PaymentMethodCode
     * @return String paymentId
     */
    public function getPaymentId($code)
    {
        $userId = Mage::helper("paymill/customerHelper")->getUserId();
        return Mage::getModel("paymill/fastcheckout")->getPaymentId($userId, $code);
    }
    
    /**
     * Returns a boolean describing whether there is saved fc data for the current user
     * @param String $code PaymentMethodCode
     * @return boolean
     */
    public function hasData($code){
        $userId = Mage::helper("paymill/customerHelper")->getUserId();
        if(Mage::getModel("paymill/fastcheckout")->hasFcData($userId, $code)){
            return true;
        }
        return false;
    }
    
    /**
     * Saves the dataset into the database
     * @param String $code paymentCode
     * @param String $clientId Description
     * @param String $name Description
     */
    public function saveData($code, $clientId, $paymentId)
    {
        $userId = Mage::helper("paymill/customerHelper")->getUserId();
        if(isset($userId)){
            Mage::getModel("paymill/fastcheckout")->saveFcData($code, $userId, $clientId, $paymentId);
        }
        
    }    
    
}
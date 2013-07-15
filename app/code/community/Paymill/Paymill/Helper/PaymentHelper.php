<?php 
/**
 * Magento
 * 
 * NOTICE OF LICENSE
 * 
 * This source file is subject to the Open Software License (OSL 3.0)  
 * that is bundled with this package in the file LICENSE.txt.  
 * It is also available through the world-wide-web at this URL:  
 * http://opensource.org/licenses/osl-3.0.php  
 * If you did not receive a copy of the license and are unable to  
 * obtain it through the world-wide-web, please send an email  
 * to license@magentocommerce.com so we can send you a copy immediately.  
 * 
 * @category Paymill  
 * @package Paymill_Paymill  
 * @copyright Copyright (c) 2013 PAYMILL GmbH (https://paymill.com/en-gb/)  
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)  
 */
/**
 * The Payment Helper contains methods dealing with payment relevant information.
 * Examples for this might be f.Ex customer data, formating of basket amounts or similar.
 */
class Paymill_Paymill_Helper_PaymentHelper extends Mage_Core_Helper_Abstract
{
    /**
     * Returns the order amount in the smallest possible unit (f.Ex. cent for the EUR currency)
     * <p align = "center" color = "red">At the moment, only currencies with a 1:100 conversion are supported. Special cases need to be added if necessary</p>
     * @param Mage_Sales_Model_Quote|Mage_Sales_Model_Order $object
     * @return int Amount in the smallest possible unit
     */
    public function getAmount($object = null)
    {
        if($object == null){
            $object = Mage::getSingleton('checkout/session')->getQuote();
        }
        $decimalTotal = $object->getGrandTotal();
        $amountTotal = $decimalTotal * 100;
        return $amountTotal;
    }
    
    /**
     * Returns the PreAuthAmount and sets a session var for later use
     * @param String $_code
     */
    public function getPreAuthAmount($_code)
    {
        $amount = $this->getAmount() + Mage::helper('paymill/optionHelper')->getTokenTolerance($_code);
        Mage::getSingleton('core/session')->setPreAuthAmount($amount);
        return $amount;
    }

    /**
     * Returns the currency compliant to ISO 4217 (3 char code)
     * @return string 3 Character long currency code
     */
    public function getCurrency()
    {
         $currency_code = Mage::app()->getStore()->getCurrentCurrencyCode();
         return $currency_code;
    }

    /**
     * Returns the description you want to display in the Paymill Backend.
     * The current format is [OrderId] [Email adress of the customer]
     * @param Mage_Sales_Model_Quote|Mage_Sales_Model_Order $object
     * @return string
     */
    public function getDescription($object)
    {
        $orderId = $this->getOrderId($object);
        $customerEmail = Mage::helper("paymill/customerHelper")->getCustomerEmail($object);
        $description = $orderId. ", " . $customerEmail;

        return $description;
    }

    /**
     * Returns the short tag of the Payment
     * @param String $code
     * @return string
     */
    public function getPaymentType($code)
    {
        //Creditcard
        if($code === "paymill_creditcard"){
            $type = "cc";
        }
        //Directdebit
        if($code === "paymill_directdebit"){
            $type = "elv";
        }

        return $type;
    }

    /**
     * Returns the reserved order id
     * @param Mage_Sales_Model_Quote|Mage_Sales_Model_Order $object
     * @return String OrderId
     */
    public function getOrderId($object)
    {
        $orderId = null;

        if($object instanceof Mage_Sales_Model_Order){
            $orderId = $object->getIncrementId();
        }

        if($object instanceof Mage_Sales_Model_Quote){
            $orderId = $object->getReservedOrderId();
        }


        return $orderId;
    }


    /**
     * Returns an instance of the paymentProcessor class.
     * @param String $paymentCode name of the payment
     * @param String $token Token generated by the Javascript
     * @param Integer $authorizedAmount Amount used for the Token generation
     * @return Services_Paymill_PaymentProcessor
     */
    public function createPaymentProcessor($paymentCode, $token)
    {
        $privateKey                 = Mage::helper('paymill/optionHelper')->getPrivateKey();
        $apiUrl                     = Mage::helper('paymill')->getApiUrl();
        $quote                      = Mage::getSingleton('checkout/session')->getQuote();
        $libBase                    = null;

        $params                     = array();
        $params['token']            = $token;
        $params['amount']           = (int)$this->getAmount();
        $params['currency']         = $this->getCurrency();
        $params['payment']          = $this->getPaymentType($paymentCode); // The chosen payment (cc | elv)
        $params['name']             = Mage::helper("paymill/customerHelper")->getCustomerName($quote);
        $params['email']            = Mage::helper("paymill/customerHelper")->getCustomerEmail($quote);
        $params['description']      = $this->getDescription($quote);
        $params['source']           = Mage::helper('paymill')->getSourceString();

        $paymentProcessor = new Services_Paymill_PaymentProcessor($privateKey, $apiUrl, $libBase, $params, Mage::helper('paymill/loggingHelper'));
        return $paymentProcessor;
    }

    /**
     * Creates a client object from the given data and returns the Id
     * @param String $email
     * @param String $description
     * @return String ClientId
     * @throws Exception "Invalid Result Exception: Invalid ResponseCode for Client"
     */
    public function createClient($email, $description)
    {
        $privateKey                 = Mage::helper('paymill/optionHelper')->getPrivateKey();
        $apiUrl                     = Mage::helper('paymill')->getApiUrl();
        
        if(empty($privateKey)){
            Mage::helper('paymill/loggingHelper')->log("No private Key was set.");
            Mage::throwException("No private Key was set.");
        }
        
        $clientsObject              = new Services_Paymill_Clients($privateKey, $apiUrl);

        $client = $clientsObject->create(
                array(
                    'email' => $email,
                    'description' => $description
                )
        );

        if (isset($client['data']['response_code']) && $client['data']['response_code'] !== 20000) {
            $this->_log("An Error occured: " . $client['data']['response_code'], var_export($client, true));
            throw new Exception("Invalid Result Exception: Invalid ResponseCode for Client");
        }

        $clientId = $client['id'];
        Mage::helper('paymill/loggingHelper')->log("Client created.", $clientId);
        return $clientId;
    }

    /**
     * Creates a payment object from the given data and returns the Id
     * @param String $token
     * @param String $clientId
     * @return String PaymentId
     * @throws Exception "Invalid Result Exception: Invalid ResponseCode for Payment"
     */
    public function createPayment($token, $clientId)
    {
        $privateKey                 = Mage::helper('paymill/optionHelper')->getPrivateKey();
        $apiUrl                     = Mage::helper('paymill')->getApiUrl();
                
        if(empty($privateKey)){
            Mage::helper('paymill/loggingHelper')->log("No private Key was set.");
            Mage::throwException("No private Key was set.");
        }
        
        $paymentsObject             = new Services_Paymill_Payments($privateKey, $apiUrl);
        
        $payment = $paymentsObject->create(
                    array(
                        'token' => $token,
                        'client' => $clientId
                    )
            );

        if (isset($payment['data']['response_code']) && $payment['data']['response_code'] !== 20000) {
            $this->_log("An Error occured: " . $payment['data']['response_code'], var_export($payment, true));
            throw new Exception("Invalid Result Exception: Invalid ResponseCode for Payment");
        }

        $paymentId = $payment['id'];
        Mage::helper('paymill/loggingHelper')->log("Payment created.", $paymentId);
        return $paymentId;
    }

    /**
     * Creates a preAuthorization with the given arguments
     * @param String $token
     * @param String $paymentId if given, this replaces the token to use fast checkout
     * @return mixed Response
     */
    public function createPreAuthorization($paymentId)
    {
        $privateKey                 = Mage::helper('paymill/optionHelper')->getPrivateKey();
        $apiUrl                     = Mage::helper('paymill')->getApiUrl();
        
        if(empty($privateKey)){
            Mage::helper('paymill/loggingHelper')->log("No private Key was set.");
            Mage::throwException("No private Key was set.");
        }
        
        $preAuthObject              = new Services_Paymill_Preauthorizations($privateKey, $apiUrl);

        $amount                     = (int)$this->getAmount();
        $currency                   = $this->getCurrency();

        $params                     = array( 'payment' => $paymentId, 'source' => Mage::helper('paymill')->getSourceString(), 'amount' => $amount, 'currency' => $currency );
        $preAuth                    = $preAuthObject->create($params);

        Mage::helper('paymill/loggingHelper')->log("PreAuthorization created from Payment", $preAuth['preauthorization']['id'], print_r($params, true));

        return $preAuth['preauthorization'];


    }

    /**
     * Generates a transaction from the given arguments
     * @param Mage_Sales_Model_Order $order
     * @param String $preAuthorizationId
     * @param float|double $amount
     * @return Boolean Indicator of success
     */
    public function createTransactionFromPreAuth($order, $preAuthorizationId, $amount)
    {
        $privateKey                 = Mage::helper('paymill/optionHelper')->getPrivateKey();
        $apiUrl                     = Mage::helper('paymill')->getApiUrl();
        if(empty($privateKey)){
            Mage::helper('paymill/loggingHelper')->log("No private Key was set.");
            Mage::throwException("No private Key was set.");
        }
        
        $transactionsObject         = new Services_Paymill_Transactions($privateKey, $apiUrl);
        $params                     = array(
                                                  'amount' => (int)($amount*100),
                                                'currency' => $this->getCurrency(),
                                             'description' => $this->getDescription($order),
                                                  'source' => Mage::helper('paymill')->getSourceString(),
                                         'preauthorization'=> $preAuthorizationId
                                        );

        $transaction                = $transactionsObject->create($params);
        Mage::helper('paymill/loggingHelper')->log("Creating Transaction from PreAuthorization", print_r($params, true), var_export($transaction,true));

        return $transaction;
    }
}

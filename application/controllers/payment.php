<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class payment extends CI_Controller {

    function payment() {
        parent::__construct();
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, token");
        header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
        $this->load->model('payment_model');
        $this->load->model('comman_model');
    }

    /**
     * Function to make stripe payment
     */
    function stripePayment() {

        $accessToken = $this->getHeader();
        $verifyuserID = $this->comman_model->oAuth($accessToken, '');
        if (isset($verifyuserID['errors'])) {
            $this->checkResult($verifyuserID);
        } else {
            $userId = $verifyuserID;
        }

        // Get post data.
        $paymentInfo = array('amount' => $this->input->post('amount', TRUE),
            'currency' => $this->input->post('currency', TRUE),
            'token' => $this->input->post('stripeToken', TRUE),
            'description' => $this->input->post('description', TRUE),
            'planName' => $this->input->post('planName', TRUE),
            'email' => $this->input->post('stripeEmail', TRUE),
            'userId' => $userId,
            'planId' => $this->input->post('planId', TRUE),
            'paymentType' => (int) $this->input->post('paymentType', TRUE)
        );
        $this->payment_model->makePayment($paymentInfo);
    }

    /**
     * Function to retrieve transaction
     */
    function retrieveTransaction() {
        $limit = $this->input->post('limit', TRUE);
        $lastChargeId = $this->input->post('lastChargeId', TRUE);
        $this->payment_model->retreiveTransaction($limit, $lastChargeId);
    }

    /**
     * Function to retrieve customer transaction
     */
    function retrieveCustomerTransaction() {
        $limit = $this->input->post('limit', TRUE);
        $userId = $this->input->post('userId', TRUE);
        $lastChargeId = $this->input->post('lastChargeId', TRUE);
        $this->payment_model->retreiveCustomerTransaction($limit, $userId, $lastChargeId);
    }

    /**
     * Function to retrieve payment history
     */
    function retrievePaymentHistory() {
        $accessToken = $this->getHeader();
        $verifyUserId = $this->comman_model->oAuth($accessToken, '');
        if (isset($verifyUserId['errors'])) {
            $this->checkResult($verifyUserId);
        } else {
            $limit = $this->input->post('limit', TRUE);
            //$startIndex = $this->input->post('startIndex', TRUE);
            $userId = $this->input->post('userId', TRUE);
            $result = $this->payment_model->retrieveTransactionHistory(false, $limit, $userId, false, false);
            if (isset($result['errors'])) {
                $this->checkResult($result);
            } else {
                $resultFinal['paymentList'] = $result;
                $this->checkResult($resultFinal);
            }
        }
    }

    /**
     * Function to get accesstoken from header
     */
    function getHeader() {
        $accessToken = '';
        foreach (getallheaders() as $name => $value) {
            if ($name == 'token') {
                return $accessToken = $value;
            }
        }
        return $accessToken;
    }

    /**
     * Function to verify the result
     * @param type $result
     */
    function checkResult($result) {
        echo $this->returnSuccess($result);
        return;
    }

    /**
     * Function used for return a json data
     * @param type $result
     */
    function returnSuccess($result) {
        $return_value = array();
        if (isset($result['errors'])) {
            if ($result['errors'][0]['errorCode'] == 'EE006' || $result['errors'][0]['errorCode'] == 'EE063') {
                $status = 2;
            } else {
                $status = 0;
            }
        } else {
            $status = 1;
        }
        $return_value['status'] = $status;
        $return_value['response'] = $result;
        echo json_encode($return_value);
        die();
    }

    //function used for frontend app user
    /**
     * Function used for return a json data
     * @param type $result
     */
    /* {
      "checkForChangePlan": {
      "newPlanId": "56b05782b0cce33bc5f13a2a",
      "activePlanId":"56b05782b0cce33bc5f13a2a",
      "changeFor":"upgrade/downgrade"
      }
      } */
    function checkForChangePlan() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {
                $request = (file_get_contents('php://input'));
                $getData = json_decode($request);
                $newPlanId = (isset($getData->checkForChangePlan->newPlanId)) ? $getData->checkForChangePlan->newPlanId : '';
                $activePlanId = (isset($getData->checkForChangePlan->activePlanId)) ? $getData->checkForChangePlan->activePlanId : '';
                $changeFor = (isset($getData->checkForChangePlan->changeFor)) ? $getData->checkForChangePlan->changeFor : '';
                if ($newPlanId != '' || $activePlanId != '' || $changeFor != '') {
                    $result = $this->payment_model->checkForChangePlan($verifyuserID, $newPlanId, $activePlanId, $changeFor);
                    $this->checkResult($result);
                } else {
                    $error = $this->comman_model->senderror('EE039');
                    $this->checkResult($error);
                }
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /**
     * Function used for return a json data
     * @param type $result
     */
    /* {
      "savePayment": {
      "orderId": "56b05782b0cce33bc5f13a2a",
      "purchaseToken": "23423423423423",
      "purchaseTime": "31231231",( in unix timestamp)
      "planId": "56b05782b0cce33bc5f13a2a",
      "paymentType": "itune/google/stripe",
      "paymentResponse": "the complete response which user got after payment",
      "signature":"signature which will received after payment",
      "price":"0"
      }
      } */

    //check for valid payment 
    function savePayment() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {
                $logPost = array();
                $request = (file_get_contents('php://input'));
                $getData = json_decode($request);
                $logPost['request'] = $getData;
                $postData = array();
                $postData['userId'] = $verifyuserID;
                $postData['orderId'] = (isset($getData->savePayment->orderId)) ? $getData->savePayment->orderId : '';
                $postData['purchaseTime'] = (isset($getData->savePayment->purchaseTime)) ? $getData->savePayment->purchaseTime : '';
                $postData['purchaseToken'] = (isset($getData->savePayment->purchaseToken)) ? $getData->savePayment->purchaseToken : '';
                $postData['planId'] = (isset($getData->savePayment->planId)) ? $getData->savePayment->planId : '';
                $paymentType = (isset($getData->savePayment->paymentType)) ? $getData->savePayment->paymentType : '';
                if ($paymentType == 'google') {
                    $postData['paymentType'] = 3;
                } else if ($paymentType == 'itune') {
                    $postData['paymentType'] = 2;
                } else if ($paymentType == 'stripe') {
                    $postData['paymentType'] = 1;
                } else {
                    $postData['paymentType'] = 0;
                }
                $postData['paymentResponse'] = (isset($getData->savePayment->paymentResponse)) ? $getData->savePayment->paymentResponse : '';
                $postData['signature'] = (isset($getData->savePayment->signature)) ? $getData->savePayment->signature : '';
                $postData['price'] = (isset($getData->savePayment->price)) ? $getData->savePayment->price : '';
                //check for free plan
                if ($postData['planId'] != '' && (float) $postData['price'] == 0) {
                    $result = $this->payment_model->savePaymentFreePlan($postData);
                    //log entry start
                    $logPost['response'] = $result;
                    $this->comman_model->createLog(json_encode($logPost));
                    //log entry end
                    $this->checkResult($result);
                } else if ($postData['orderId'] != '' && $postData['planId'] != '' && $postData['paymentType'] != '' && $postData['price'] != '' && $postData['paymentResponse'] != '') {
                    $result = $this->payment_model->savePayment($postData);
                    //log entry start
                    $logPost['response'] = $result;
                    $this->comman_model->createLog(json_encode($logPost));
                    //log entry end
                    $this->checkResult($result);
                } else {
                    $error = $this->comman_model->senderror('EE039');
                    //log entry start
                    $logPost['response'] = $error;
                    $this->comman_model->createLog(json_encode($logPost));
                    //log entry end
                    $this->checkResult($error);
                }
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    //Cashin to recipient bank account
    function createTransfer() {
        $accessToken = $this->getHeader();
        $verifyuserID = $this->comman_model->oAuth($accessToken, '');
        if (isset($verifyuserID['errors'])) {
            $this->checkResult($verifyuserID);
        } else {
            $userId = $verifyuserID;
            $recipientInfo = array('amount' => $this->input->post('amount', TRUE),
            'currency' => $this->input->post('currency', TRUE),
            'description' => $this->input->post('description', TRUE),
            'name' => $this->input->post('name', TRUE),
            'email' => $this->input->post('email', TRUE),
            'bankAccount' => $this->input->post('bankAccount', TRUE), //bank account token
            'type' => $this->input->post('type', TRUE)//individual or corporate
            );
            $this->payment_model->createAndTransferToRecipient($recipientInfo, $userId);
        }
    }

    function stripeEvent() {
        $this->payment_model->stripeEvent();
    }

}

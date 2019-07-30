<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

require_once realpath(__DIR__ . '/../..') . '/php-ravel/controller/user.php';
require_once realpath(__DIR__ . '/../..') . '/assets/twilio/Services/Twilio.php';
require_once realpath(__DIR__ . '/../..') . '/php-ravel/controller/search.php';
require_once realpath(__DIR__ . '/../..') . '/application/third_party/Stripe/lib/Stripe.php';
require_once realpath(__DIR__ . '/../..') . '/application/appUtil/exceptiongenerator.php';

class payment_model extends CI_Model {

    private $db, $mongo, $dbName, $loginType, $timezone, $stripeApiKey;

    function payment_model() {
        parent::__construct();
        $this->load->helper('string');
        $this->load->helper('form');
        $this->load->helper('url');
        $this->load->helper('text');
        $this->load->library('rest_client');
        $this->load->helper('date');
        $this->load->model('ravelchannel_model');
        $this->load->model('comman_model');
        $this->mongo = $this->config->config['mongo'];
        $this->openFireUrl = $this->config->config['openfire_url'];
        $this->sid = $this->config->config['sid'];
        $this->token = $this->config->config['token'];
        $this->twilioNumber = $this->config->config['twilioNumber'];
        $dbName = $this->config->config['mongoDb'];
        $this->MAX_RECORD = $this->config->config['MAX_RECORD'];
        $this->paymentType = $this->config->config['paymentType'];
        $this->playStorePublicKey = $this->config->config['playStorePublicKey'];
        $this->sandboxItunes = $this->config->config['sandboxItunes'];
        $this->db = $this->mongo->$dbName;
        $this->user = new user();
        $this->search = new search();
        $this->loginType = $this->config->config['loginType'];
        $timezone = $this->config->config['timezone'];
        $this->stripeApiKey = $this->config->config['stripeApiKey'];
        $this->paymentAppleId = $this->config->config['paymentAppleId'];
        $this->exceptiongenerator = new exceptiongenerator(); //paymentAppleId
    }

    function makePayment($paymentInfo) {
        $results['status'] = 0;
        $paymentData = $this->db->payment->findOne(array("uid" => $paymentInfo['userId']));
        try {
            $planName = $paymentInfo['planName'];

            if (!$this->isPlanExist($planName)) {
                $planName = $this->createPlan($paymentInfo['amount'], $planName, $paymentInfo['currency']);
            }
            if (!$paymentData['cid']) {
                $customerData = $this->createCustomer($paymentInfo);
            } else {
                $customer = $paymentData['cid'];
                $customerData = $this->updateCustomer($customer, $planName, $paymentInfo['token'], $paymentInfo['email']);
            }
            $paymentInfo['customerId'] = $customerData->id;
            Stripe::setApiKey($this->stripeApiKey);
            foreach ($customerData->subscriptions->data as $subscription) {
                $paymentInfo['transactionId'] = $subscription->id;
                $paymentInfo['currentPeriodEnd'] = $subscription->current_period_end;
            }
            foreach ($customerData->sources->data as $sources) {
                $paymentInfo['brand'] = $sources->brand;
                $paymentInfo['last4'] = $sources->last4;
            }
            $invoice = $this->createInvoice($paymentInfo['customerId']);
            $this->invoicePay($invoice->id);
            // Payment is success.
            $this->saveAndUpdateTransaction($paymentInfo);
            $this->ravelchannel_model->updateChannelPlan($paymentInfo['userId'], $paymentInfo['planId']);
            $results['status'] = 1;
            $results['response']['message'] = "Plan subscribed successfully.";
            echo json_encode($results);
            die();

            // Invalid card id supplied to Stripe's API
        } catch (Stripe_CardError $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();
        }

        // Invalid parameters were supplied to Stripe's API.
        catch (Stripe_InvalidRequestError $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();

            // Authentication with Stripe's API failed.
        } catch (Stripe_AuthenticationError $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();

            // Network communication with Stripe failed.
        } catch (Stripe_ApiConnectionError $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();

            // Display a very generic error to the user.
        } catch (Stripe_Error $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();
        } catch (MongoException $e) {
            $err = $e->getMessage();
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();
        } catch (Exception $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();
        }
    }

    function createPlan($amount, $name, $currency) {
        $results['status'] = 0;
        $results['event'] = "createPlan";
        try {
            Stripe::setApiKey($this->stripeApiKey);

            // Create Plan.
            Stripe_Plan::create(array(
                "amount" => $amount,
                "interval" => "month",
                "name" => $name,
                "currency" => $currency,
                "id" => $name
            ));
            return $name;
        }
        // Invalid parameters were supplied to Stripe's API.
        catch (Stripe_InvalidRequestError $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();

            // Authentication with Stripe's API failed.
        } catch (Stripe_AuthenticationError $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();

            // Network communication with Stripe failed.
        } catch (Stripe_ApiConnectionError $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();

            // Display a very generic error to the user.
        } catch (Stripe_Error $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();
        } catch (Exception $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();
        }
    }

    function createCustomer($paymentInfo) {
        $results['status'] = 0;
        $results['event'] = "createCustomer";
        try {
            //$token = $this->createToken($paymentInfo);
            $token = $paymentInfo['token'];
            Stripe::setApiKey($this->stripeApiKey);

            // Create Customer.
            $customer = Stripe_Customer::create(array(
                        "email" => $paymentInfo['email'],
                        "plan" => $paymentInfo['planName'],
                        "source" => $token,
                        "metadata" => array("userId" => $paymentInfo['userId'])
                            //"trial_end" => time() + 1200
            ));
            return $customer;
        }
        // Invalid parameters were supplied to Stripe's API.
        catch (Stripe_InvalidRequestError $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();

            // Authentication with Stripe's API failed.
        } catch (Stripe_AuthenticationError $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();

            // Network communication with Stripe failed.
        } catch (Stripe_ApiConnectionError $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();

            // Display a very generic error to the user.
        } catch (Stripe_Error $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();
        } catch (Exception $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();
        }
    }

    function createToken($paymentInfo) {
        $results['status'] = 0;
        $results['event'] = "createToken";
        try {
            Stripe::setApiKey($this->stripeApiKey);

            // Create Token.
            $token = Stripe_Token::create(array(
                        "card" => array(
                            "number" => $paymentInfo['cardNumber'],
                            "exp_month" => $paymentInfo['expMonth'],
                            "exp_year" => $paymentInfo['expYear'],
                            "cvc" => $paymentInfo['cvc']
                        ),
            ));
            $token = $token->id;
            return $token;
        }
        // Invalid parameters were supplied to Stripe's API.
        catch (Stripe_InvalidRequestError $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();

            // Authentication with Stripe's API failed.
        } catch (Stripe_AuthenticationError $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();

            // Network communication with Stripe failed.
        } catch (Stripe_ApiConnectionError $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();

            // Display a very generic error to the user.
        } catch (Stripe_Error $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();
        } catch (Exception $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();
        }
    }

    public function isPlanExist($planName) {
        Stripe::setApiKey($this->stripeApiKey);
        try {
            $plan = Stripe_Plan::retrieve($planName);
            return $plan->name;
        } catch (Exception $e) {
            return false;
        }
    }

    public function retreiveTransaction($limit, $lastChargeId = null) {
        $results['status'] = 0;
        if ($limit == 0 || $limit == '') {
            $results['response']['message'] = "Invalid Limit";
            echo json_encode($results);
            die();
        }
        try {
            Stripe::setApiKey($this->stripeApiKey);
            $results['status'] = 1;
            $charges = Stripe_Charge::all(array("limit" => 11, "starting_after" => $lastChargeId, "expand" => array("data.customer")));
            $rows = array();
            $paymentMethod = '';
            $plan = '';
            foreach ($charges->data as $charge) {
                $lastChargeId = $charge->id;
                if ($charge->customer) {
                    $paymentMethod = $charge->customer->sources->data[0]->brand . " + ****" . $charge->customer->sources->data[0]->last4;
                    $plan = $charge->customer->subscriptions->data[0]->plan->id;
                }
                $rows[] = array(
                    'Plan' => $plan,
                    'PaymentMethod' => $paymentMethod,
                    'Amount' => number_format($charge->amount / 100, 2),
                    'Date' => date('Y-m-d', $charge->created)
                );
            }
            $results['data'] = $rows;
            $results['lastChargeId'] = $lastChargeId;
            echo json_encode($results);
        } catch (Exception $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
        }
    }

    public function retreiveCustomerTransaction($limit, $userId, $lastChargeId = null) {
        $results['status'] = 0;
        $paymentData = $this->db->payment->findOne(array("uid" => $userId));
        $paymentData['cid'] = 'cus_8AhwDB5iw8jutY';
        if ($limit == 0 || $limit == '') {
            $results['response']['message'] = "Invalid Limit";
            echo json_encode($results);
            die();
        }
        if (!$paymentData['cid']) {
            $results['response']['message'] = "No such customer exist";
            echo json_encode($results);
            die();
        }
        try {
            Stripe::setApiKey($this->stripeApiKey);
            $results['status'] = 1;
            if (empty($lastChargeId)) {
                $charges = Stripe_Charge::all(array("limit" => $limit, "customer" => $paymentData['cid'], "expand" => array("data.customer")));
            } else {
                $charges = Stripe_Charge::all(array("limit" => $limit, "starting_after" => $lastChargeId, "customer" => $paymentData['cid'], "expand" => array("data.customer")));
            }
            $rows = array();
            $paymentMethod = '';
            $plan = '';
            foreach ($charges->data as $charge) {
                $lastChargeId = $charge->id;
                if ($charge->customer) {
                    $paymentMethod = $charge->customer->sources->data[0]->brand . " + ****" . $charge->customer->sources->data[0]->last4;
                    $plan = $charge->customer->subscriptions->data[0]->plan->id;
                }
                $rows[] = array(
                    'Plan' => $plan,
                    'PaymentMethod' => $paymentMethod,
                    'Amount' => number_format($charge->amount / 100, 2),
                    'Date' => date('Y-m-d', $charge->created)
                );
            }
            $results['data'] = $rows;
            $results['lastChargeId'] = $lastChargeId;
            echo json_encode($results);
        } catch (Exception $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
        }
    }

    public function retreiveCustomerInvoices($limit, $userId) {
        if ($userId != '') {
            $results['status'] = 1;
            $paymentData = $this->db->payment->findOne(array("uid" => $userId));
            if (!$paymentData['cid']) {
                $results['data'] = [];
                echo json_encode($results);
                die();
            }
        }
        $results['status'] = 0;
        if ($userId != '') {
            $users = $this->db->user->find(array("_id" => new MongoId($userId)), array("un" => true));
        } else {
            $users = $this->db->user->find(array(), array("un" => true));
        }
        if ($limit == 0 || $limit == '') {
            $results['response']['message'] = "Invalid Limit";
            echo json_encode($results);
            die();
        }
        try {
            $results['status'] = 1;
            Stripe::setApiKey($this->stripeApiKey);
            if ($userId != "") {
                $invoices = Stripe_Invoice::all(array("limit" => $limit, "customer" => $paymentData['cid'], "expand" => array("data.customer")));
            } else {
                $invoices = Stripe_Invoice::all(array("limit" => $limit, "expand" => array("data.customer")));
            }
            $rows = array();
            $paymentMethod = '';
            $plan = '';
            foreach ($invoices->data as $invoice) {
                $plan = $invoice->lines->data[0]->plan->id;
                if ($invoice->customer) {
                    //$paymentMethod = $invoice->customer->sources->data[0]->brand . " + ****" . $invoice->customer->sources->data[0]->last4;
                    $paymentMethod = @$invoice->customer->sources->data[0]->brand;
                    $paymentDetail = @$invoice->customer->sources->data[0]->last4;
                    $plan = $invoice->customer->subscriptions->data[0]->plan->id;
                }
                $userName = '';
                foreach ($users as $user) {
                    if ($invoice->customer->metadata->userId == (string) $user['_id']) {
                        $userName = $user['un'];
                    }
                }
                if (empty($userName)) {
                    $userName = 'test';
                }
                $rows[] = array(
                    'Plan' => $plan,
                    'PaymentMethod' => $paymentMethod,
                    'Amount' => number_format($invoice->total / 100, 2),
                    'Date' => date('Y-m-d', $invoice->customer->created),
                    'userName' => $userName,
                    'paymentDetail' => $paymentDetail
                );
            }
            // }
            $results['data'] = $rows;
            echo json_encode($results);
        } catch (Exception $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
        }
    }

    public function updateCustomer($customer, $planName, $token, $email) {
        $results['status'] = 0;
        try {
            $cu = Stripe_Customer::retrieve($customer);
            $cu->email = $email;
            $cu->plan = $planName;
            $cu->source = $token;
            //$cu->trial_end = time() + 1200;
            $cu->save();
            return $cu;
        } catch (Exception $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();
        }
    }

    public function createInvoice($customerId) {
        $results['status'] = 0;
        try {
            $invoice = Stripe_Invoice::create(array("customer" => $customerId));
            return $invoice;
        } catch (Exception $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();
        }
    }

    public function invoicePay($invoiceId) {
        $results['status'] = 0;
        try {
            $invoice = Stripe_Invoice::retrieve($invoiceId);
            return $invoice->pay();
        } catch (Exception $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();
        }
    }

    public function retrieveTransactionHistory($startIndex, $limit, $userId, $startDate, $endDate) {
        if (empty($startIndex)) {
            $startIndex = 0;
        }
        if (empty($limit)) {
            $limit = $this->MAX_RECORD;
        }
        $collection = $this->db->payment;
        $query = array();
        if ($userId != '') {
            $query['uid'] = $userId;
        }
        if (!empty($startDate) && !empty($endDate)) {
            $query['cat'] = array('$gte' => $startDate, '$lte' => $endDate);
        }
        $users = $this->db->user->find(array(), array("un" => true));
        $payments = $collection->find($query)->sort(array('cat' => -1))->skip($startIndex)->limit($limit);
        if (count($payments) > 0) {
            $result = array();
            foreach ($payments as $payment) {
                foreach ($users as $user) {
                    if ((string) $user['_id'] == $payment['uid']) {
                        if (isset($user['un'])) {
                            $paymentData['userName'] = $user['un'];
                        } else {
                            $paymentData['userName'] = "";
                        }
                    }
                }
                if (isset($payment['Plan'])) {
                    $paymentData['plan'] = $payment['Plan'];
                } else {
                    $paymentData['plan'] = "";
                }
                if (isset($payment['pt'])) {
                    if ($payment['pt'] == $this->paymentType['stripe']) {
                        $paymentData['paymentType'] = "Stripe";
                    } elseif ($payment['pt'] == $this->paymentType['appleStore']) {
                        $paymentData['paymentType'] = "AppleStore";
                    } elseif ($payment['pt'] == $this->paymentType['playStore']) {
                        $paymentData['paymentType'] = "PlayStore";
                    }
                } else {
                    $paymentData['paymentType'] = "";
                }
                if (isset($payment['amount'])) {
                    $paymentData['amount'] = number_format($payment['amount'], 2);
                } else {
                    $paymentData['amount'] = 0;
                }
                if (isset($payment['pm'])) {
                    $paymentData['paymentMethod'] = $payment['pm'];
                } else {
                    $paymentData['paymentMethod'] = "";
                }
                if (isset($payment['pd'])) {
                    $paymentData['paymentDetail'] = $payment['pd'];
                } else {
                    $paymentData['paymentDetail'] = "";
                }
                if (isset($payment['cpend'])) {
                    $paymentData['currentPeriodEnd'] = date("m-d-Y", $payment['cpend']);
                } else {
                    $paymentData['currentPeriodEnd'] = "";
                }
                if (isset($payment['paymentStatus'])) {
                    if ($payment['paymentStatus'] == 0) {
                        $paymentData['paymentStatus'] = "Pending";
                    } elseif ($payment['paymentStatus'] == 1) {
                        $paymentData['paymentStatus'] = "Paid";
                    } elseif ($payment['paymentStatus'] == 2) {
                        $paymentData['paymentStatus'] = "Failed";
                    }
                } else {
                    $paymentData['paymentStatus'] = "Pending";
                }
                $paymentData['date'] = date("m-d-Y", $payment['cat']);
                $result[] = $paymentData;
            }
            return $result;
        } else {
            $error = $this->exceptiongenerator->sendError('EE047');
            return $error;
        }
    }

    public function saveAndUpdateTransaction($data) {
        $result = $this->db->payment->update(array("uid" => $data['userId'], "tid" => $data["transactionId"]), array('$set' => array("status" => 0)), array('multiple' => true));
        $data = array(
            "uid" => $data['userId'],
            "cid" => $data['customerId'],
            "Plan" => $data['planName'],
            "pt" => $data["paymentType"],
            "tid" => $data["transactionId"],
            "amount" => floatval($data["amount"] / 100),
            "pm" => $data["brand"], //payment method
            "pd" => $data["last4"], //payment detail
            "cpend" => $data["currentPeriodEnd"], //current period end
            "cat" => time(),
            "mat" => time(),
            "paymentStatus" => 1, //0 for pending, 1 for paid and 2 for fail 
            "status" => 1//for active plan
        );
        $this->db->payment->insert($data);
    }

    //function used for frontend app user

    function checkForChangePlan($userID, $newPlanId, $activePlanId, $changeFor) {
        //get new plan details
        $newPlanDetails = $this->comman_model->getPlanDetails($newPlanId);
        $activePlanDetails = $this->comman_model->getPlanDetails($activePlanId);
        if ($newPlanDetails != 0 && $activePlanDetails != 0) {
            if ($changeFor == 'upgrade') {
                if ($newPlanDetails['price'] >= $activePlanDetails['price']) {
                    //get user current channel count
                    $userCurrentChannel = $this->userCurrentChannel($userID);
                    if ($userCurrentChannel <= $newPlanDetails['cnum']) {
                        return $error = array("message" => 'You can upgrade your plan.');
                    } else {
                        return $error = $this->comman_model->senderror('EE066', (int) $newPlanDetails['cnum']);
                    }
                } else {
                    return $error = $this->comman_model->senderror('EE067');
                }
            } else if ($changeFor == 'downgrade') {
                if ($newPlanDetails['price'] <= $activePlanDetails['price']) {
                    //get user current channel count
                    $userCurrentChannel = $this->userCurrentChannel($userID);
                    if ($userCurrentChannel <= $newPlanDetails['cnum']) {
                        return $error = array("message" => 'You can downgrade your plan.');
                    } else {
                        return $error = $this->comman_model->senderror('EE066', (int) $newPlanDetails['cnum']);
                    }
                } else {
                    return $error = $this->comman_model->senderror('EE068');
                }
            }
        } else {
            return $error = $this->comman_model->senderror('EE069');
        }
    }

    function userCurrentChannel($userID) {
        $countUserExistingChannel = 0;
        $collection = $this->db->channel;
        $userExistingChannel = $collection->find(array("uid" => new MongoId($userID), "isactive" => 1));
        if (count(iterator_to_array($userExistingChannel)) > 0) {
            $countUserExistingChannel = count(iterator_to_array($userExistingChannel));
        }
        return $countUserExistingChannel;
    }

    function savePaymentFreePlan($postData) {
        $getPlandetails = $this->comman_model->getPlanDetails($postData['planId']);
        //get last payment        
        $getLastPayment = $this->getLastPayment($postData['userId']);
        $msg = 'Plan has been changed successfully.';
        $success = FALSE;
        if ($getLastPayment != FALSE && $getLastPayment['amount'] != 0) {
            //cancle other plans            
            if ($getLastPayment['pt'] == 2) {
                $msg = 'Plan has been changed successfully. Please cancle your plan on Itune Store.';
            } else if ($getLastPayment['pt'] == 3) {
                //cancle plan on play store.
                $ptJson = $getLastPayment['paymentResponse'];
                if (isset($ptJson->paymentResponse) && isset($ptJson->productId) && isset($ptJson->productId)) {
                    $packageName = $ptJson->paymentResponse;
                    $productId = $ptJson->productId;
                    $purchaseToken = $ptJson->productId;
                    $success = $this->comman_model->subscriptionsRevokeGoogle($packageName, $productId, $purchaseToken);
                }
                if ($success == TRUE) {
                    $msg = 'Plan has been changed successfully.';
                } else {
                    $msg = 'Plan has been changed successfully. Please cancle your plan on Google Play Store.';
                }
            } else if ($getLastPayment['pt'] == 1) {
                //cancle plan on stripe.
                if ($success == TRUE) {
                    $msg = 'Plan has been changed successfully.';
                } else {
                    $msg = 'Plan has been changed successfully. Please cancle your plan on Stripe Account.';
                }
            }
        }
        //deactivate other plan
        $this->deactivateOtherPayment($postData['userId']);
        $dataSave = array(
            "uid" => $postData['userId'],
            "cid" => '',
            "Plan" => $getPlandetails['name'],
            "planId" => $postData['planId'],
            "orderId" => $postData['orderId'],
            "pt" => $postData['paymentType'],
            "tid" => '',
            "amount" => 0,
            "pm" => '', //payment method
            "pd" => '', //payment detail
            "cpend" => '', //current period end
            "paymentStatus" => 1, //status of active plan payment
            "status" => 1, //status of active record
            "paymentresponse" => $postData['paymentResponse'],
            "signature" => $postData['signature'],
            "cat" => time()
        );
        $paymentInsert = $this->db->payment->insert($dataSave);
        return $this->updateChannelPlan($postData['userId'], $postData['planId'], $msg);
    }

    function savePayment($postData) {
        //validate payment
        if ($postData["paymentType"] == 3) {
            $ptJson = $postData['paymentResponse'];
            if (isset($ptJson->paymentResponse) && isset($ptJson->productId) && isset($ptJson->productId)) {
                $packageName = $ptJson->paymentResponse;
                $productId = $ptJson->productId;
                $purchaseToken = $ptJson->productId;
                $validatePayment = $this->comman_model->verifyPaymentOnPlayStore($packageName, $productId, $purchaseToken);
                if ($validatePayment == FALSE) {
                    return $error = $this->comman_model->senderror('EE071');
                }
            } else {
                return $error = $this->comman_model->senderror('EE071');
            }
        } else if ($postData["paymentType"] == 2) {
            if ($postData['paymentResponse'] != '') {
                $validatePayment = $this->comman_model->verifyPaymentOnItunes($postData['paymentResponse']);
                if ($validatePayment == FALSE) {
                    return $error = $this->comman_model->senderror('EE071');
                }
            } else {
                return $error = $this->comman_model->senderror('EE071');
            }
        }

        //check order id        
        $checkOrderId = $this->checkOrderId($postData['orderId'], $postData['userId']);
        if ($checkOrderId != FALSE) {
            return $error = $this->comman_model->senderror('EE072', $checkOrderId);
        }

        $getPlandetails = $this->comman_model->getPlanDetails($postData['planId']);
        //get last payment        
        $getLastPayment = $this->getLastPayment($postData['userId']);
        //cancle other plans 
        $msg = 'Plan has been changed successfully.';
        $success = FALSE;
        if ($getLastPayment != FALSE && $getLastPayment['amount'] != 0) {
            if ($getLastPayment['pt'] == 2 && $postData["paymentType"] != $getLastPayment['pt']) {
                $msg = 'Plan has been changed successfully. Please cancle your plan on Itune Store.';
            } else if ($getLastPayment['pt'] == 3 && $postData["paymentType"] != $getLastPayment['pt']) {
                //cancle plan on play store.
                $ptJson = $getLastPayment['paymentResponse'];
                if (isset($ptJson->paymentResponse) && isset($ptJson->productId) && isset($ptJson->productId)) {
                    $packageName = $ptJson->paymentResponse;
                    $productId = $ptJson->productId;
                    $purchaseToken = $ptJson->productId;
                    $success = $this->comman_model->subscriptionsRevokeGoogle($packageName, $productId, $purchaseToken);
                }
                if ($success == TRUE) {
                    $msg = 'Plan has been changed successfully.';
                } else {
                    $msg = 'Plan has been changed successfully. Please cancle your plan on Google Play Store.';
                }
            } else if ($getLastPayment['pt'] == 1 && $postData["paymentType"] != $getLastPayment['pt']) {
                //cancle plan on stripe.
                if ($success == TRUE) {
                    $msg = 'Plan has been changed successfully.';
                } else {
                    $msg = 'Plan has been changed successfully. Please cancle your plan on Stripe Account.';
                }
            }
        }

        //deactivate other plan
        $this->deactivateOtherPayment($postData['userId']);
        $dataSave = array(
            "uid" => $postData['userId'],
            "cid" => '',
            "Plan" => $getPlandetails['name'],
            "planId" => $postData['planId'],
            "orderId" => $postData['orderId'],
            "pt" => $postData['paymentType'],
            "tid" => $postData["purchaseToken"],
            "amount" => $getPlandetails["price"],
            "pm" => '', //payment method
            "pd" => '', //payment detail
            "cpend" => time() + 30 * 86400, //current period end
            "paymentStatus" => 1, //status of active plan payment
            "status" => 1, //status of active record
            "paymentresponse" => $postData['paymentResponse'],
            "signature" => $postData['signature'],
            "cat" => time()
        );
        $paymentInsert = $this->db->payment->insert($dataSave);
        return $this->updateChannelPlan($postData['userId'], $postData['planId'], $msg);
    }

    function getLastPayment($userId) {
        $response = FALSE;
        $orderDetails = $this->db->payment->find(array("uid" => (string) $userId, "paymentStatus" => 1, "status" => 1))->sort(array("cat" => -1))->limit(1);
        if (count(iterator_to_array($orderDetails)) > 0) {
            $orderArray = iterator_to_array($orderDetails);
            $keys = array_keys($orderArray);
            return $orderArray[$keys[0]];
        }
        return $response;
    }

    function checkOrderId($orderId, $userId) {
        $response = FALSE;
        $orderDetails = $this->db->payment->findOne(array("orderId" => (string) $orderId, "paymentStatus" => 1, "status" => 1));
        if (count($orderDetails) > 0 && $userId != $orderDetails['uid']) {
            $userInfo = $this->comman_model->getNickName($orderDetails['uid']);
            //getNickName
            return $userInfo;
        }
        return $response;
    }

    function deactivateOtherPayment($userID) {
        //set other plan deactive
        $where = array('uid' => (string) $userID);
        $updateArray = array(
            "paymentStatus" => 0,
            "status" => 0,
            "mat" => time()
        );
        $this->db->payment->update($where, array('$set' => $updateArray), array('multiple' => true));
    }

    /**
     * Function to update channel plan list 
     * @param type $userId  
     * @param type $PlanId       
     */
    function updateChannelPlan($userId, $PlanId, $msg) {
        $collection = $this->db->plan;
        $checkExist = $collection->findOne(array("_id" => new MongoId($PlanId)));
        if (count($checkExist) > 0) {
            $where = array("_id" => new MongoId($userId));
            $this->db->user->update($where, array('$set' => array('pid' => $PlanId, 'savebcast' => (int) $checkExist['sbcast'], "paymentStatus" => 1)));

            return $error = array("message" => $msg);
        } else {
            return $error = $this->comman_model->senderror('EE033');
        }
    }

    function createAndTransferToRecipient($recipientInfo, $userId) {
        $results['status'] = 0;
        try {
            $availableBalance = $this->checkAvailableBalance($userId, $recipientInfo['amount'] / 100);
            if ($availableBalance == true) {
                Stripe::setApiKey($this->stripeApiKey);
                $recipient = Stripe_Recipient::create(array(
                            "name" => $recipientInfo['name'],
                            "type" => $recipientInfo['type'],
                            "bank_account" => $recipientInfo['bankAccount'],
                            "email" => $recipientInfo['email'],
                            "description" => $recipientInfo['description']
                ));
                $transfer = $this->createTransfer($recipient->id, $recipientInfo);
                $this->insertTransfer($transfer, $userId);
                $results['status'] = 1;
                $results['response']['message'] = "Transfer has been created successfully.";
                echo json_encode($results);
                die();
            } else {
                $results['status'] = 0;
                $results['response']['message'] = "You have Insufficient balance, please try again!";
                echo json_encode($results);
                die();
            }
            // Invalid card id supplied to Stripe's API
        } catch (Stripe_CardError $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();
        }

        // Invalid parameters were supplied to Stripe's API.
        catch (Stripe_InvalidRequestError $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();

            // Authentication with Stripe's API failed.
        } catch (Stripe_AuthenticationError $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();

            // Network communication with Stripe failed.
        } catch (Stripe_ApiConnectionError $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();

            // Display a very generic error to the user.
        } catch (Stripe_Error $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();
        } catch (MongoException $e) {
            $err = $e->getMessage();
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();
        } catch (Exception $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();
        }
    }

    function createTransfer($recipientId, $recipientInfo) {
        $results['status'] = 0;
        try {
            Stripe::setApiKey($this->stripeApiKey);
            $transfer = Stripe_Transfer::create(array(
                        "amount" => $recipientInfo["amount"], // amount in cents
                        "currency" => $recipientInfo["currency"],
                        "recipient" => $recipientId
                            //"bank_account" => $recipientInfo['bankAccount']
            ));
            return $transfer;
        } catch (Stripe_CardError $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();
        }

        // Invalid parameters were supplied to Stripe's API.
        catch (Stripe_InvalidRequestError $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();

            // Authentication with Stripe's API failed.
        } catch (Stripe_AuthenticationError $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();

            // Network communication with Stripe failed.
        } catch (Stripe_ApiConnectionError $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();

            // Display a very generic error to the user.
        } catch (Stripe_Error $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();
        } catch (MongoException $e) {
            $err = $e->getMessage();
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();
        } catch (Exception $e) {
            $body = $e->getJsonBody();
            $err = $body['error']['message'];
            $results['response']['message'] = $err;
            echo json_encode($results);
            die();
        }
    }

    function insertTransfer($transfer, $userId) {
        $transferInfo = array();
        $transferInfo['tid'] = $transfer->id;
        $transferInfo['amount'] = ($transfer->amount) / 100; //in $
        $transferInfo['cat'] = $transfer->created;
        $transferInfo['rid'] = $transfer->recipient; //recipient id
        $transferInfo['st'] = $transfer->status; //paid or unpaid
        $transferInfo['type'] = $transfer->type; //bank account or card
        $transferInfo['bname'] = $transfer->bank_account->bank_name;
        $transferInfo['country'] = $transfer->bank_account->country;
        $transferInfo['currency'] = $transfer->bank_account->currency;
        $transferInfo['last4'] = $transfer->bank_account->last4;
        $transferInfo['uid'] = $userId;
        $transferInfo['mat'] = $transfer->created;
        $amount = ($transfer->amount - $transfer->amount_reversed) / 100;
        $this->db->transfer->insert($transferInfo);
        $this->db->user->update(array("_id" => new MongoId($userId)), array(
            '$inc' => array('cin' => $amount))); //cashin money
        //cash in history
        $cashIn = array();
        $cashIn['cin'] = $amount;
        $cashIn['cat'] = time();
        $cashIn['mat'] = time();
        $cashIn['uid'] = new MongoId($userId);
        $this->db->cashinhistory->insert($cashIn);
    }

    function stripeEvent() {
        Stripe::setApiKey($this->stripeApiKey);
        $input = @file_get_contents("php://input");
        $eventJson = json_decode($input);
        if ($eventJson->type == 'transfer.paid' || $eventJson->type == 'transfer.failed') {
            $update = array();
            $update['mat'] = time();
            $update['st'] = $eventJson->data->object->status;
            $this->db->transfer->update(array('rid' => $eventJson->data->object->recipient), array('$set' => $update));
        }
        if ($eventJson->type == 'invoice.payment_succeeded' || $eventJson->type == 'invoice.payment_failed') {
            $update = array();
            $update['mat'] = time();
            if ($eventJson->data->object->paid == true) {
                $update['paymentStatus'] = 1;
            } else if ($eventJson->data->object->paid == false) {
                $update['paymentStatus'] = 2;
            }
            $query = array('tid' => $eventJson->data->object->subscription,
                'Plan' => $eventJson->data->object->lines->data[0]->plan->name,
                'status' => 1);
            $this->db->payment->update($query, array('$set' => $update), array("multiple" => true));
        }
    }

    function checkAvailableBalance($userId, $cashIn) {
        $users = $this->db->user->aggregateCursor(array(array('$match' => array("_id" => new MongoId($userId))),
            array('$project' => array("availableBal" => array('$subtract' => array('$uem', '$cin'))))));
        if (count($users) > 0) {
            foreach ($users as $user) {
                $availableBalance = $user['availableBal'];
            }
        }
        if ($cashIn <= $availableBalance) {
            return true;
        }
        return false;
    }

}

?>

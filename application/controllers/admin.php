<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

require_once realpath(__DIR__ . '/../..') . '/application/controllers/controllerdomain.php';
require_once realpath(__DIR__ . '/../..') . '/application/appUtil/exceptiongenerator.php';
require_once realpath(__DIR__ . '/../..') . '/application/controllers/util/controllerutil.php';
require_once realpath(__DIR__ . '/../..') . '/application/appUtil/apputil.php';

class admin extends CI_Controller {

    private $status;

    function admin() {
        parent::__construct();
        $this->load->helper('string');
        $this->load->helper('form');
        $this->load->helper('url');
        $this->load->helper('text');
        $this->load->model('admin_model');
        $this->load->model('ravelchannel_model');
        $this->load->model('payment_model');
        $this->status = $this->config->config['status'];
        $this->controllerDomain = new controllerdomain();
        $this->exceptionGenerator = new exceptiongenerator();
        $this->controllerUtil = new controllerutil();
        $this->appUtil = new apputil();
    }

    /**
     * To login with username and password
     */
    function login() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $userObj = $this->controllerUtil->getFileContentAndValidateJson();
            if (!empty($userObj)) {
                if (!$this->controllerUtil->validateAdminLoginFields($userObj)) {
                    $this->controllerDomain->checkResult($this->exceptionGenerator->sendError('EE046'));
                } else {
                    $userName = trim($userObj->adminLogin->userName);
                    $password = trim($userObj->adminLogin->password);
                    $rememberMe = (int) trim($userObj->adminLogin->rememberMe);
                    $result = $this->admin_model->login($userName, $password, $rememberMe);
                    if (!isset($result['errors'])) {
                        $response['adminLogin'] = $result;
                    } else {
                        $response = $result;
                    }
                    $this->controllerDomain->checkResult($response);
                }
            } else {
                $result = $this->exceptionGenerator->sendError('EE049');
                if (isset($result['errors'])) {
                    $this->controllerDomain->checkResult($result);
                }
            }
        } else {
            $this->controllerDomain->checkResult($this->exceptionGenerator->sendError('EE045'));
        }
    }

    /**
     * Function to get user list
     */
    function getUserList() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $userObj = $this->controllerUtil->getFileContentAndValidateJson();
            if (empty($userObj)) {
                $result = $this->exceptionGenerator->sendError('EE049');
                if (isset($result['errors'])) {
                    $this->controllerDomain->checkResult($result);
                }
            } else {
                if ($this->controllerDomain->userTokenOauth()) {
                    if (!$this->controllerUtil->validateUserListFields($userObj)) {
                        $this->controllerDomain->checkResult($this->exceptionGenerator->sendError('EE046'));
                    } else {
                        $result = $this->admin_model->getUserList($userObj->userList->startIndex, $userObj->userList->limit, false);
                        if (isset($result['errors'])) {
                            $this->controllerDomain->checkResult($result);
                        } else {
                            $resultFinal['userList'] = $result;
                            $this->controllerDomain->checkResult($resultFinal);
                        }
                    }
                }
            }
        } else {
            $this->controllerDomain->checkResult($this->exceptionGenerator->sendError('EE045'));
        }
    }

    /**
     * To activate deactivate user
     */
    function updateUser() {
        if ($this->input->server('REQUEST_METHOD') != 'POST') {
            $result = $this->exceptionGenerator->sendError('EE045');
            if (isset($result['errors'])) {
                $this->controllerDomain->checkResult($result);
            }
        } else if ($this->controllerDomain->userTokenOauth()) {
            $userObj = $this->controllerUtil->getFileContentAndValidateJson();
            if (!empty($userObj)) {
                if (!$this->controllerUtil->validateAdminUserUpdateFields($userObj)) {
                    $this->controllerDomain->checkResult($this->exceptionGenerator->sendError('EE046'));
                } else {
                    $userId = $userObj->updateUser->userId;
                    $isActive = '';
                    if (isset($userObj->updateUser->isActive)) {
                        $isActive = (int) $userObj->updateUser->isActive;
                    }
                    $result = $this->admin_model->updateUser($userId, $isActive);
                    if (isset($result['errors'])) {
                        $this->controllerDomain->checkResult($result);
                    } else {
                        $resultFinal['updateUser'] = $result;
                        $this->controllerDomain->checkResult($resultFinal);
                    }
                }
            } else {
                $result = $this->exceptionGenerator->sendError('EE049');
                if (isset($result['errors'])) {
                    $this->controllerDomain->checkResult($result);
                }
            }
        }
    }

    /**
     * Get all channel list
     */
    function getChannelList() {
        if ($this->input->server('REQUEST_METHOD') != 'POST') {
            $result = $this->exceptionGenerator->sendError('EE045');
            if (isset($result['errors'])) {
                $this->controllerDomain->checkResult($result);
            }
        } else {
            $channelObj = $this->controllerUtil->getFileContentAndValidateJson();
            if (empty($channelObj)) {
                $result = $this->exceptionGenerator->sendError('EE049');
                if (isset($result['errors'])) {
                    $this->controllerDomain->checkResult($result);
                }
            } else if ($this->controllerDomain->userTokenOauth()) {
                if (!$this->controllerUtil->validateChannelListFields($channelObj)) {
                    $this->controllerDomain->checkResult($this->exceptionGenerator->sendError('EE046'));
                } else {
                    $userId = '';
                    if (isset($channelObj->channelList->userId) && $channelObj->channelList->userId != '') {
                        $userId = $channelObj->channelList->userId;
                    }
                    $result = $this->admin_model->getChannelList($channelObj->channelList->startIndex, $channelObj->channelList->limit, $userId, false);
                    if (isset($result['errors'])) {
                        $this->controllerDomain->checkResult($result);
                    } else {
                        $resultFinal['channelList'] = $result;
                        $this->controllerDomain->checkResult($resultFinal);
                    }
                }
            }
        }
    }

    /**
     * Activate deactivate channel
     */
    function updateChannel() {
        if ($this->input->server('REQUEST_METHOD') != 'POST') {
            $result = $this->exceptionGenerator->sendError('EE045');
            if (isset($result['errors'])) {
                $this->controllerDomain->checkResult($result);
            }
        } else if ($this->controllerDomain->userTokenOauth()) {
            $channelObj = $this->controllerUtil->getFileContentAndValidateJson();
            if (!empty($channelObj)) {
                if (!$this->controllerUtil->validateUpdateChannelFields($channelObj)) {
                    $this->controllerDomain->checkResult($this->exceptionGenerator->sendError('EE046'));
                } else {
                    $moneyEarned = '';
                    if (isset($channelObj->updateChannel->moneyEarned)) {
                        $moneyEarned = floatval($channelObj->updateChannel->moneyEarned);
                    }
                    $channelId = $channelObj->updateChannel->channelId;
                    $userId = '';
                    if (isset($channelObj->updateChannel->userId)) {
                        $userId = $channelObj->updateChannel->userId;
                    }
                    $status = '';
                    if(isset($channelObj->updateChannel->isActive)) {
                       $status = (int)$channelObj->updateChannel->isActive;   
                    }
                    $desc = '';
                    if(isset($channelObj->updateChannel->desc)) {
                       $desc = $channelObj->updateChannel->desc;   
                    }
                    $result = $this->admin_model->updateChannel($channelId, $status, $moneyEarned, $userId, $desc);
                    if (isset($result['errors'])) {
                        $this->controllerDomain->checkResult($result);
                    } else {
                        $resultFinal['updateChannel'] = $result;
                        $this->controllerDomain->checkResult($resultFinal);
                    }
                }
            } else {
                $result = $this->exceptionGenerator->sendError('EE049');
                if (isset($result['errors'])) {
                    $this->controllerDomain->checkResult($result);
                }
            }
        }
    }

    /**
     * Get plans
     */
    function getPlanList() {
        if ($this->input->server('REQUEST_METHOD') != 'POST') {
            $result = $this->exceptionGenerator->sendError('EE045');
            if (isset($result['errors'])) {
                $this->controllerDomain->checkResult($result);
            }
        } else {
            $planObj = $this->controllerUtil->getFileContentAndValidateJson();
            if (empty($planObj)) {
                $result = $this->exceptionGenerator->sendError('EE049');
                if (isset($result['errors'])) {
                    $this->controllerDomain->checkResult($result);
                }
            } else if ($this->controllerDomain->userTokenOauth()) {
                if (!$this->controllerUtil->validatePlanListFields($planObj)) {
                    $this->controllerDomain->checkResult($this->exceptionGenerator->sendError('EE046'));
                } else {
                    $result = $this->admin_model->getPlanList($planObj->planList->startIndex, $planObj->planList->limit, false);
                    if (isset($result['errors'])) {
                        $this->controllerDomain->checkResult($result);
                    } else {
                        $resultFinal['planList'] = $result;
                        $this->controllerDomain->checkResult($resultFinal);
                    }
                }
            }
        }
    }

    /**
     * Activate deactivate plan
     */
    function addUpdatePlan() {
        if ($this->input->server('REQUEST_METHOD') != 'POST') {
            $result = $this->exceptionGenerator->sendError('EE045');
            if (isset($result['errors'])) {
                $this->controllerDomain->checkResult($result);
            }
        } else if ($this->controllerDomain->userTokenOauth()) {
            //$planObj = $this->controllerUtil->getFileContentAndValidateJson();
            if (!empty($_REQUEST)) {
                $updatePlan = $this->controllerUtil->getAddUpdatePlanObj($_REQUEST);
                $data = json_decode($_REQUEST['data']);
                if (!isset($data->planId) || empty($data->planId)) {
                    $updatePlan['st'] = $this->status['active'];
                    $planId = null;
                } else {
                    $planId = $data->planId;
                }
                $result = $this->admin_model->addUpdatePlan($updatePlan, $planId);
                if (isset($result['errors'])) {
                    $this->controllerDomain->checkResult($result);
                } else {
                    $resultFinal['updatePlan'] = $result;
                    $this->controllerDomain->checkResult($resultFinal);
                }
            } else {
                $result = $this->exceptionGenerator->sendError('EE049');
                if (isset($result['errors'])) {
                    $this->controllerDomain->checkResult($result);
                }
            }
        }
    }

    /**
     * Get broadcast list
     */
    function getBroadcastList() {
        if ($this->input->server('REQUEST_METHOD') != 'POST') {
            $result = $this->exceptionGenerator->sendError('EE045');
            if (isset($result['errors'])) {
                $this->controllerDomain->checkResult($result);
            }
        } else {
            $broadcastObj = $this->controllerUtil->getFileContentAndValidateJson();
            if (empty($broadcastObj)) {
                $result = $this->exceptionGenerator->sendError('EE049');
                if (isset($result['errors'])) {
                    $this->controllerDomain->checkResult($result);
                }
            } else if ($this->controllerDomain->userTokenOauth()) {
                if (!$this->controllerUtil->validateBroadcastListFields($broadcastObj)) {
                    $this->controllerDomain->checkResult($this->exceptionGenerator->sendError('EE046'));
                } else {
                    $channelId = '';
                    if (isset($broadcastObj->broadcastList->channelId) && $broadcastObj->broadcastList->channelId != '') {
                        $channelId = $broadcastObj->broadcastList->channelId;
                    }
                    $timePeriod = '';
                    if (isset($broadcastObj->broadcastList->timePeriod) && $broadcastObj->broadcastList->timePeriod != '') {
                        $timePeriod = (int) $broadcastObj->broadcastList->timePeriod;
                    }
                    $result = $this->admin_model->getBroadcastList($broadcastObj->broadcastList->startIndex, $broadcastObj->broadcastList->limit, $channelId, false, $timePeriod);
                    if (isset($result['errors'])) {
                        $this->controllerDomain->checkResult($result);
                    } else {
                        $resultFinal['broadcastList'] = $result;
                        $this->controllerDomain->checkResult($resultFinal);
                    }
                }
            }
        }
    }

    /**
     * Activate deactivate broadcast
     */
    function updateBroadcast() {
        if ($this->input->server('REQUEST_METHOD') != 'POST') {
            $result = $this->exceptionGenerator->sendError('EE045');
            if (isset($result['errors'])) {
                $this->controllerDomain->checkResult($result);
            }
        } else if ($this->controllerDomain->userTokenOauth()) {
            $broadcastObj = $this->controllerUtil->getFileContentAndValidateJson();
            if (!empty($broadcastObj)) {
                if (!$this->controllerUtil->validateUpdateBroadcastFields($broadcastObj)) {
                    $this->controllerDomain->checkResult($this->exceptionGenerator->sendError('EE046'));
                } else {
                    $broadcastId = $broadcastObj->updateBroadcast->broadcastId;
                    $status = $broadcastObj->updateBroadcast->isActive;
                    $result = $this->admin_model->updateBroadcast($broadcastId, $status);
                    if (isset($result['errors'])) {
                        $this->controllerDomain->checkResult($result);
                    } else {
                        $resultFinal['updateBroadcast'] = $result;
                        $this->controllerDomain->checkResult($resultFinal);
                    }
                }
            } else {
                $result = $this->exceptionGenerator->sendError('EE049');
                if (isset($result['errors'])) {
                    $this->controllerDomain->checkResult($result);
                }
            }
        }
    }

    /* This function is used to drop any broadcast */
    /* {
      "dropBroadcast": {
      "contentId": "56b05782b0cce33bc5f13a2a"
      }
      } */
    /* Drop broad cast */

    function dropBroadcast() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            if ($this->controllerDomain->userTokenOauth()) {
                $request = json_decode(file_get_contents('php://input'));
                if ($request && isset($request->dropBroadcast)) {
                    $getData = $request->dropBroadcast;
                    if (isset($getData->contentId) && $getData->contentId != '') {
                        $result['dropBroadcast'] = $this->admin_model->dropBroadcast($getData->contentId);
                        $this->controllerDomain->checkResult($result);
                    } else {
                        $error = $this->exceptionGenerator->sendError('EE049');
                        $this->controllerDomain->checkResult($error);
                    }
                } else {
                    $error = $this->exceptionGenerator->sendError('EE049');
                    $this->controllerDomain->checkResult($error);
                }
            }
        } else {
            $result = $this->exceptionGenerator->sendError('EE045');
            if (isset($result['errors'])) {
                $this->controllerDomain->checkResult($result);
            }
        }
    }

    /**
     * Function to initiate the search for users
     */
    function search() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->controllerDomain->getHeader();
            $verifyUserId = $this->admin_model->oAuth($accessToken, '');
            if (isset($verifyUserId['errors'])) {
                $this->controllerDomain->checkResult($verifyUserId);
            } else {
                $userId = $verifyUserId;
                $searchParams = json_decode(file_get_contents('php://input'));
                //$searchParams = $this->input->post('searchParams', TRUE);
                if ($searchParams && isset($searchParams->searchParams)) {
                    $result = $this->admin_model->search(json_encode($searchParams->searchParams), $userId, false);
                    if (isset($result['errors'])) {
                        $this->controllerDomain->checkResult($result);
                    } else {
                        $resultFinal['search'] = $result;
                        $this->controllerDomain->checkResult($resultFinal);
                    }
                } else {
                    $this->controllerDomain->checkResult($this->comman_model->senderror('EE037'));
                }
            }
        } else {
            $result = $this->exceptionGenerator->sendError('EE045');
            if (isset($result['errors'])) {
                $this->controllerDomain->checkResult($result);
            }
        }
    }

    function retrievePaymentHistory() {
        if ($this->input->server('REQUEST_METHOD') != 'POST') {
            $result = $this->exceptionGenerator->sendError('EE045');
            if (isset($result['errors'])) {
                $this->controllerDomain->checkResult($result);
            }
        } else if ($this->controllerDomain->userTokenOauth()) {
            $paymentHistory = $this->controllerUtil->getFileContentAndValidateJson();
            if (empty($paymentHistory)) {
                $result = $this->exceptionGenerator->sendError('EE049');
                if (isset($result['errors'])) {
                    $this->controllerDomain->checkResult($result);
                }
            } else {
                $userId = "";
                if (isset($paymentHistory->paymentHistory->userId) && !empty($paymentHistory->paymentHistory->userId)) {
                    $userId = $paymentHistory->paymentHistory->userId;
                }
                $startDate = "";
                $endDate = "";
                if (isset($paymentHistory->paymentHistory->startDate) && isset($paymentHistory->paymentHistory->endDate)) {
                    $startDate = (int) $paymentHistory->paymentHistory->startDate;
                    $endDate = (int) $paymentHistory->paymentHistory->endDate;
                }
                $result = $this->payment_model->retrieveTransactionHistory($paymentHistory->paymentHistory->startIndex, $paymentHistory->paymentHistory->limit, $userId, $startDate, $endDate);
                if (isset($result['errors'])) {
                    $this->controllerDomain->checkResult($result);
                } else {
                    $resultFinal['paymentList'] = $result;
                    $this->controllerDomain->checkResult($resultFinal);
                }
            }
        }
    }
    
    /**
     * Function to send a reset password link
     */
    function forgotPassword() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $user = $this->controllerUtil->getFileContentAndValidateJson();
            //$email = $this->input->post('email', TRUE);
            if ((!filter_var(trim($user->forgotPwd->email), FILTER_VALIDATE_EMAIL)) && empty(trim($user->forgotPwd->email))) {
                $error = $this->exceptionGenerator->sendError('EE013');
                $this->controllerDomain->checkResult($error);
            } else {
                $result = $this->admin_model->forgotPassword(trim($user->forgotPwd->email));
                if (isset($result['errors'])) {
                    $this->controllerDomain->checkResult($result);
                } else {
                    $resultFinal['forgotPwd'] = $result;
                    $this->controllerDomain->checkResult($resultFinal);
                }
            }
        } else {
            $this->controllerDomain->checkResult($this->exceptionGenerator->sendError('EE050'));
        }
    }

    function getRevenue() {
        if ($this->input->server('REQUEST_METHOD') != 'POST') {
            $result = $this->exceptionGenerator->sendError('EE045');
            if (isset($result['errors'])) {
                $this->controllerDomain->checkResult($result);
            }
        } else {
            $revenueObj = $this->controllerUtil->getFileContentAndValidateJson();
            if(empty($revenueObj)) {
                $result = $this->exceptionGenerator->sendError('EE049');
                if (isset($result['errors'])) {
                    $this->controllerDomain->checkResult($result);
                }
            } else if ($this->controllerDomain->userTokenOauth()) {
                $startDate = "";
                $endDate = "";
                $userId = "";
                if(isset($revenueObj->revenueHistory->userId) && !empty($revenueObj->revenueHistory->userId)) {
                    $userId = $revenueObj->revenueHistory->userId;
                }
                if(isset($revenueObj->revenueHistory->startDate) && isset($revenueObj->revenueHistory->endDate)) {
                    $startDate = (int)$revenueObj->revenueHistory->startDate;
                    $endDate = (int)$revenueObj->revenueHistory->endDate;
                }                
                $result = $this->admin_model->getRevenueHistory($revenueObj->revenueHistory->startIndex,
                    $revenueObj->revenueHistory->limit, $userId, $startDate, $endDate);
                if (isset($result['errors'])) {
                    $this->controllerDomain->checkResult($result);
                } else {
                    $resultFinal['revenueHistory'] = $result;
                    $this->controllerDomain->checkResult($resultFinal);
                }
            }
        }
    }

    /**
     * To logout user
     */
    function logout() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->controllerDomain->getHeader();
            $result['logout'] = $this->admin_model->oAuth($accessToken, 'logout');
            $this->controllerDomain->checkResult($result);
        } else {
            $this->controllerDomain->checkResult($this->exceptionGenerator->sendError('EE045'));
        }
    }

}

/* End of file welcome.php */
/* Location: ./application/controllers/welcome.php */

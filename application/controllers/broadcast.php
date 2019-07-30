<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class broadcast extends CI_Controller {

    function broadcast() {
        parent::__construct();
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, token");
        header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
        $this->load->helper('string');
        $this->load->helper('form');
        $this->load->helper('url');
        $this->load->helper('text');
        $this->load->model('comman_model');
        $this->load->model('broadcast_model');
    }

    /**
     * Function to handle broadcast
     * @param contentId $contentId
     */
    function startBroadcast() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            $request = json_decode(file_get_contents('php://input'));
            if ($request && isset($request->broadcast)) {
                $getData = $request->broadcast;
                if (isset($verifyuserID['errors'])) {
                    $this->checkResult($verifyuserID);
                } else {
                    $userId = $verifyuserID;
                    if (isset($getData->contentId) && isset($getData->action) && $getData->contentId != '' && $getData->action != '') {
                        $contentId = $getData->contentId;
                        $action = $getData->action;
                        if ($action == 'start') {
                            $result = $this->broadcast_model->startBroadcast($userId, $contentId);
                            $this->checkResult($result);
                        } else {
                            $error = $this->comman_model->senderror('EE045');
                            $this->checkResult($error);
                        }
                    } else {
                        $error = $this->comman_model->senderror('EE039');
                        $this->checkResult($error);
                    }
                }
            } else {
                $error = $this->comman_model->senderror('EE039');
                $this->checkResult($error);
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    function endBroadcast() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            $request = json_decode(file_get_contents('php://input'));
            if ($request && isset($request->broadcast)) {
                $getData = $request->broadcast;
                if (isset($verifyuserID['errors'])) {
                    $this->checkResult($verifyuserID);
                } else {
                    $userId = $verifyuserID;
                    if (isset($getData->contentId) && isset($getData->action) && $getData->contentId != '' && $getData->action != '') {
                        $contentId = $getData->contentId;
                        $action = $getData->action;
                        if ($action == 'end') {
                            $result = $this->broadcast_model->endBroadcast($userId, $contentId);
                            $this->checkResult($result);
                        } else {
                            $error = $this->comman_model->senderror('EE045');
                            $this->checkResult($error);
                        }
                    } else {
                        $error = $this->comman_model->senderror('EE039');
                        $this->checkResult($error);
                    }
                }
            } else {
                $error = $this->comman_model->senderror('EE039');
                $this->checkResult($error);
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /* This function is used to join broad cast */

    function joinBroadcast() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            $request = json_decode(file_get_contents('php://input'));
            if ($request && isset($request->broadcast)) {
                $getData = $request->broadcast;
                if (isset($verifyuserID['errors'])) {
                    $this->checkResult($verifyuserID);
                } else {
                    $userId = $verifyuserID;
                    if (isset($getData->contentId) && isset($getData->action) && $getData->contentId != '' && $getData->action != '') {
                        $contentId = $getData->contentId;
                        $action = $getData->action;
                        if ($action == 'join') {
                            $result = $this->broadcast_model->joinBroadcast($userId, $contentId);
                            $this->checkResult($result);
                        } else {
                            $error = $this->comman_model->senderror('EE045');
                            $this->checkResult($error);
                        }
                    } else {
                        $error = $this->comman_model->senderror('EE039');
                        $this->checkResult($error);
                    }
                }
            } else {
                $error = $this->comman_model->senderror('EE039');
                $this->checkResult($error);
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /* This function is used to leave broad cast */

    function leaveBroadcast() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            $request = json_decode(file_get_contents('php://input'));
            if ($request && isset($request->broadcast)) {
                $getData = $request->broadcast;
                if (isset($verifyuserID['errors'])) {
                    $this->checkResult($verifyuserID);
                } else {
                    $userId = $verifyuserID;
                    if (isset($getData->contentId) && isset($getData->action) && $getData->contentId != '' && $getData->action != '') {
                        $contentId = $getData->contentId;
                        $action = $getData->action;
                        if ($action == 'leave') {
                            $result = $this->broadcast_model->leaveBroadcast($userId, $contentId);
                            $this->checkResult($result);
                        } else {
                            $error = $this->comman_model->senderror('EE045');
                            $this->checkResult($error);
                        }
                    } else {
                        $error = $this->comman_model->senderror('EE039');
                        $this->checkResult($error);
                    }
                }
            } else {
                $error = $this->comman_model->senderror('EE039');
                $this->checkResult($error);
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /* Report broadcast, Rate Broadcast
     */

    function rateBroadcast() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            $request = json_decode(file_get_contents('php://input'));
            if ($request && isset($request->rateBroadcast)) {
                $getData = $request->rateBroadcast;
                if (isset($verifyuserID['errors'])) {
                    $this->checkResult($verifyuserID);
                } else {
                    $userId = $verifyuserID;
                    if (isset($getData->contentId) && isset($getData->action) && $getData->contentId != '' && $getData->action != '') {
                        $contentId = $getData->contentId;
                        $action = $getData->action;
                        if ($action == 'rate' && isset($getData->rate) && $getData->rate != '') {
                            $rate = $getData->rate;
                            $result = $this->broadcast_model->rateBroadcast($userId, $contentId, $rate, $accessToken);
                            $this->checkResult($result);
                        } else if ($action == 'report') {
                            $msgId = (isset($getData->msgId) && $getData->msgId != '') ? $getData->msgId : '';
                            $msg = (isset($getData->msg) && $getData->msg != '') ? $getData->msg : '';
                            $result = $this->broadcast_model->reportBroadcast($userId, $contentId, $accessToken, $msgId, $msg);
                            $this->checkResult($result);
                        } else {
                            $error = $this->comman_model->senderror('EE039');
                            $this->checkResult($error);
                        }
                    } else {
                        $error = $this->comman_model->senderror('EE039');
                        $this->checkResult($error);
                    }
                }
            } else {
                $error = $this->comman_model->senderror('EE039');
                $this->checkResult($error);
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /* This function is used in case of on connect */

    function onPublish() {
        if (isset($_REQUEST['name'])) {
            $result = $this->broadcast_model->onPublish($_REQUEST['name']);
        }
    }

    /* This function is used incase of done */

    function onPublishDone() {
        if (isset($_REQUEST['name'])) {
            $result = $this->broadcast_model->onPublishDone($_REQUEST['name']);
        }
    }

    /* This function is used incase of onPlayDone */

    function onPlayDone() {
        if (isset($_REQUEST['name'])) {
            //mail("lalittiwari.cs@gmail.com","onPlayDone",  json_encode($_REQUEST));
            //$result = $this->broadcast_model->onPublishDone($_REQUEST['name']);
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
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            $request = json_decode(file_get_contents('php://input'));
            if ($request && isset($request->dropBroadcast)) {
                $getData = $request->dropBroadcast;
                if (isset($getData->contentId) && $getData->contentId != '') {
                    $result = $this->broadcast_model->dropBroadcast($contentId);
                    $this->checkResult($result);
                } else {
                    $error = $this->comman_model->senderror('EE039');
                    $this->checkResult($error);
                }
            } else {
                $error = $this->comman_model->senderror('EE039');
                $this->checkResult($error);
            }
        }
    }

    //Get running Broadcast
    function getRunningBroadcast() {
        $getRunningBroadcast = $this->broadcast_model->getRunningBroadcast();
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
    }

}

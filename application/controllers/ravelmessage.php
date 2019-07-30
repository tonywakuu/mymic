<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class ravelmessage extends CI_Controller {

    function ravelmessage() {
        parent::__construct();
        $this->load->helper('string');
        $this->load->helper('url');
        $this->load->helper('text');
        $this->load->model('ravelmessage_model');
        $this->load->model('comman_model');
    }

    /**
     * Function to send a message   
     * 
     */
    /* {
      "sendMessage": {
      "receiverId": "56b05782b0cce33bc5f13a2a",
      "message":"text"
      }
      } */
    function sendMessage() {
        $accessToken = $this->getHeader();
        $verifyuserID = $this->comman_model->oAuth($accessToken, '');
        if (isset($verifyuserID['errors'])) {
            $this->checkResult($verifyuserID);
        } else {
            $senderId = $verifyuserID;

            $request = (file_get_contents('php://input'));
            $getData = json_decode($request);
            $receiverId = (isset($getData->sendMessage->receiverId)) ? $getData->sendMessage->receiverId : '';
            $message = (isset($getData->sendMessage->message)) ? $getData->sendMessage->message : '';

            $checkUser = $this->ravelmessage_model->checkUserInFriendList($senderId, $receiverId);

            $messageDetails = array();
            if ($senderId != '') {
                $messageDetails['senderId'] = $senderId;
            }
            if ($receiverId != '') {
                $messageDetails['receiverId'] = $receiverId;
            }
            if ($message != '') {
                $messageDetails['message'] = $message;
            }
            $result = $this->ravelmessage_model->sendMessage($messageDetails);
            $this->checkResult($result);
        }
    }

    /**
     * Function to send a message   
     */
    function listMessage() {
        $accessToken = $this->getHeader();
        $verifyuserID = $this->comman_model->oAuth($accessToken, '');
        if (isset($verifyuserID['errors'])) {
            $this->checkResult($verifyuserID);
        } else {
            $senderId = $verifyuserID;
            $result = $this->ravelmessage_model->listMessages($senderId);
            $this->checkResult($result);
        }
    }

    /**
     * Function to send a message   
     */
    function countUnreadMessage() {
        $accessToken = $this->getHeader();
        $verifyuserID = $this->comman_model->oAuth($accessToken, '');
        if (isset($verifyuserID['errors'])) {
            $this->checkResult($verifyuserID);
        } else {
            $senderId = $verifyuserID;
            $result = $this->ravelmessage_model->countUnreadMessage($senderId);
            $this->checkResult($result);
        }
    }

    /**
     * Function to send a message   
     */
    /* {
      "User": {
      "receiverId": "56b05782b0cce33bc5f13a2a"
      }
      } */
    function countUnreadMessageOfUser() {
        $accessToken = $this->getHeader();
        $verifyuserID = $this->comman_model->oAuth($accessToken, '');
        if (isset($verifyuserID['errors'])) {
            $this->checkResult($verifyuserID);
        } else {
            $senderId = $verifyuserID;

            $request = (file_get_contents('php://input'));
            $getData = json_decode($request);

            $receiverId = (isset($getData->User->receiverId)) ? $getData->User->receiverId : '';

            $result = $this->ravelmessage_model->countUnreadMessageOfUser($senderId, $receiverId);
            $this->checkResult($result);
        }
    }

    /**
     * Function to mark message as read  
     */
    /* {
      "message": {
      "userId": "56b05782b0cce33bc5f13a2a"
      }
      } */
    function markRead() {
        $accessToken = $this->getHeader();
        $verifyuserID = $this->comman_model->oAuth($accessToken, '');
        if (isset($verifyuserID['errors'])) {
            $this->checkResult($verifyuserID);
        } else {
            $senderId = $verifyuserID;

            $request = (file_get_contents('php://input'));
            $getData = json_decode($request);

            $userId = (isset($getData->message->userId)) ? $getData->message->userId : '';
            $result = $this->ravelmessage_model->markedReadMessage($senderId, $userId);
            $this->checkResult($result);
        }
    }

    /**
     * Function to change user status  
     */
    /* {
      "status": 0/1
      } */
    function userSocketConnect() {
        $accessToken = $this->getHeader();
        $verifyuserID = $this->comman_model->oAuth($accessToken, '');

        if (isset($verifyuserID['errors'])) {
            $this->checkResult($verifyuserID);
        } else {
            $senderId = $verifyuserID;
            $status = 1;
            $result = $this->ravelmessage_model->userSocketStatus($senderId, $status);
            $this->checkResult($result);
            // }
        }
    }

    function userSocketDisConnect() {
        $accessToken = $this->getHeader();
        $verifyuserID = $this->comman_model->oAuth($accessToken, '');

        if (isset($verifyuserID['errors'])) {
            $this->checkResult($verifyuserID);
        } else {
            $senderId = $verifyuserID;
            $status = 0;
            $result = $this->ravelmessage_model->userSocketStatus($senderId, $status);
            $this->checkResult($result);
            // }
        }
    }

    /**
     * Function to get conversation   
     */
    /* {
      "messages": {
      "userId": "56b05782b0cce33bc5f13a2a",
      "direction" : up/down
      "timestamp": 123423423423
      }
      } */
    function getConversation() {
        $accessToken = $this->getHeader();
        $verifyuserID = $this->comman_model->oAuth($accessToken, '');
        if (isset($verifyuserID['errors'])) {
            $this->checkResult($verifyuserID);
        } else {
            $senderId = $verifyuserID;

            $request = (file_get_contents('php://input'));
            $getData = json_decode($request);

            $userId = (isset($getData->messages->userId)) ? $getData->messages->userId : '';
            $direction = (isset($getData->messages->direction)) ? $getData->messages->direction : '';
            $timestamp = (isset($getData->messages->timestamp)) ? $getData->messages->timestamp : '';
            $pageCount = (isset($getData->messages->pageCount)) ? $getData->messages->pageCount : '';

            $result = $this->ravelmessage_model->getConversation($senderId, $userId, $direction, $timestamp, $pageCount);
            $this->checkResult($result);
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
    }

    function getLoginUserId() {
        $accessToken = $this->getHeader();
        $verifyuserID = $this->comman_model->oAuth($accessToken, '');
        if (isset($verifyuserID['errors'])) {
            echo $this->checkResult($verifyuserID);
        } else {
            echo $verifyuserID;
        }
    }

}

/* End of file welcome.php */
/* Location: ./application/controllers/welcome.php */

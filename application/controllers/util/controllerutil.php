<?php

class ControllerUtil extends CI_Controller {

    function controllerutil() {
        parent::__construct();
        $this->load->helper('string');
        $this->load->helper('form');
        $this->load->helper('url');
        $this->load->library('user_agent');
        $this->load->library('session');
        $this->load->helper('text');
        $this->loginType = $this->config->config['loginType'];
        $this->status = $this->config->config['status'];
    }

    /**
     * @param json $str
     * @return bool : return true if valid json otherwise return false
     */
    function getFileContentAndValidateJson() {
        $json = file_get_contents('php://input');
        if (is_string($json)) {
            $decodedJson = json_decode($json);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decodedJson;
            }
        }
        return false;
    }

    /**
     * @param $userObj : user data
     * @return bool : return true if not empty Fields otherwise return false
     */
    function validateAdminLoginFields($userObj) {
        if (!isset($userObj->adminLogin->userName) || empty(trim($userObj->adminLogin->userName)) ||
                !isset($userObj->adminLogin->password) || empty(trim($userObj->adminLogin->password))) {
            return false;
        }
        return true;
    }

    /**
     * @param $userObj : user data
     * @return bool : return true if not empty Fields otherwise return false 
     */
    function validateAdminUserUpdateFields($userObj) {
        if (!isset($userObj->updateUser->userId) || empty($userObj->updateUser->userId) ||
                (!isset($userObj->updateUser->isActive) ||
                $this->status['active'] != (int)$userObj->updateUser->isActive &&
                $this->status['inActive'] != (int)$userObj->updateUser->isActive)) {
            return false;
        }
        return true;
    }

    /**
     * @param $userObj : user data
     * @return bool : return true if not empty Fields otherwise return false
     */
    function validateUserListFields($userObj) {
        if (!isset($userObj->userList->startIndex) || !isset($userObj->userList->limit)) {
            return false;
        }
        return true;
    }

    /**
     * @param $userObj : user data
     * @return bool : return true if not empty Fields otherwise return false
     */
    function validateChannelListFields($channelObj) {
        if (!isset($channelObj->channelList->startIndex) || !isset($channelObj->channelList->limit)) {
            return false;
        }
        return true;
    }

    /**
     * @param $userObj : user data
     * @return bool : return true if not empty Fields otherwise return false
     */
    function validateBroadcastListFields($broadcastObj) {
        if (!isset($broadcastObj->broadcastList->startIndex) || !isset($broadcastObj->broadcastList->limit)) {
            return false;
        }
        return true;
    }

    /**
     * @param $userObj : user data
     * @return bool : return true if not empty Fields otherwise return false
     */
    function validatePlanListFields($planObj) {
        if (!isset($planObj->planList->startIndex) || !isset($planObj->planList->limit)) {
            return false;
        }
        return true;
    }

    /**
     * @param $channelObj : channel data
     * @return bool : return true if channel fields not empty otherwise return false
     */
    function validateUpdateChannelFields($channelObj) {
        if (!isset($channelObj->updateChannel->channelId) || empty($channelObj->updateChannel->channelId) ||
                (!isset($channelObj->updateChannel->isActive) || $this->status['active'] != (int)$channelObj->updateChannel->isActive &&
                $this->status['inActive'] != (int)$channelObj->updateChannel->isActive)  
                && !isset($channelObj->updateChannel->moneyEarned)) {
            return false;
        }
        return true;
    }

    /**
     * @param $planObj : plan data
     * @return array : return array of plan fields
     */
    function getAddUpdatePlanObj($planObj) {
        $data = json_decode($planObj['data']);
        $updatePlan = array();
        if (isset($data->isActive) && ($this->status['active'] == (int)$data->isActive ||
                $this->status['inActive'] == (int)$data->isActive)
        ) {
            $updatePlan['st'] = (int)$data->isActive;
        } else if(!isset($data->planId)){
            $updatePlan['st'] = $this->status['active'];
        }
        if (isset($data->name) && !empty($data->name)) {
            $updatePlan['name'] = $data->name;
        }
        if (isset($data->price)) {
            $updatePlan['price'] = (floatval($data->price));
        }
        if (isset($data->noBroadcast) && !empty($data->noBroadcast)) {
            $updatePlan['bnum'] = (int)$data->noBroadcast;
        }
        if (isset($data->noChannel) && !empty($data->noChannel)) {
            $updatePlan['cnum'] = (int)$data->noChannel;
        }
        if (isset($data->saveBroadcast)) {
            $updatePlan['sbcast'] = (int)$data->saveBroadcast;
        }
        if (isset($data->broadcastLength) && !empty($data->broadcastLength)) {
            $updatePlan['blength'] = (int)$data->broadcastLength;
        }
        if (isset($data->btype)) {
            $updatePlan['btype'] = (int)$data->btype;
        }
        if (isset($data->demoReelLength) && !empty($data->demoReelLength)) {
            $updatePlan['ldreel'] = (int)$data->demoReelLength;
        }
        if (isset($data->planDescription) && !empty($data->planDescription)) {
            $updatePlan['pdesc'] = $data->planDescription;
        }
        if (isset($data->googleItemId) && !empty($data->googleItemId)) {
            $updatePlan['googleItemId'] = $data->googleItemId;
        }
        if (isset($data->appleItemId) && !empty($data->appleItemId)) {
            $updatePlan['appleItemId'] = $data->appleItemId;
        }
        return $updatePlan;
    }

    /**
     * @param $broadcastObj : broadcast fields data
     * @return bool : return true if fields not empty otherwise return false
     */
    function validateUpdateBroadcastFields($broadcastObj) {
        if (!isset($broadcastObj->updateBroadcast->broadcastId) || empty($broadcastObj->updateBroadcast->broadcastId) ||
                (!isset($broadcastObj->updateBroadcast->isActive) || $this->status['active'] != (int)$broadcastObj->updateBroadcast->isActive &&
                $this->status['inActive'] != (int)$broadcastObj->updateBroadcast->isActive)
        ) {
            return false;
        }
        return true;
    }

    /**
     * @param $userObj : end user login fields
     * @return bool : return true if fields not empty otherwise return false
     */
    function validateEndUserEmailLoginFields($userObj) {
        if (!isset($userObj->endUserLogin->email) || !isset($userObj->endUserLogin->password) ||
                !isset($userObj->endUserLogin->loginType) || (empty($userObj->endUserLogin->email) ||
                empty($userObj->endUserLogin->password) || empty($userObj->endUserLogin->loginType)) ||
                $this->loginType['email'] != $userObj->endUserLogin->loginType) {
            return false;
        }
        return true;
    }

    function validateEndUserFBLoginFields($userObj) {
        if (!isset($userObj->endUserLogin->accessToken) || !isset($userObj->endUserLogin->loginType) ||
                (empty($userObj->endUserLogin->accessToken) || empty($userObj->endUserLogin->loginType)) ||
                $this->loginType['fb'] != $userObj->endUserLogin->loginType) {
            return false;
        }
        return true;
    }

    function validateEndUserTWLoginFields($userObj) {
        if (!isset($userObj->endUserLogin->accessToken) || !isset($userObj->endUserLogin->loginType) ||
                (empty($userObj->endUserLogin->accessToken) || empty($userObj->endUserLogin->loginType)) ||
                $this->loginType['tw'] != $userObj->endUserLogin->loginType) {
            return false;
        }
        return true;
    }

    function validateEndUserINLoginFields($userObj) {
        if (!isset($userObj->endUserLogin->accessToken) || !isset($userObj->endUserLogin->loginType) ||
                (empty($userObj->endUserLogin->accessToken) || empty($userObj->endUserLogin->loginType)) ||
                $this->loginType['in'] != $userObj->endUserLogin->loginType) {
            return false;
        }
        return true;
    }

}

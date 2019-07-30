<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class controllerdomain extends CI_Controller {

    public function controllerdomain() {
        parent::__construct();
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, token");
        header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
        $this->load->model('admin_model');
    }

    /**
     * To get access token from header
     */
    public function getHeader() {
        $accessToken = '';
        foreach (getallheaders() as $name => $value) {
            if ($name == 'token') {
                return $accessToken = $value;
            }
        }
        return $accessToken;
    }

    /**
     * @return bool: return true or token incorrect
     */
    function userTokenOauth() {
        $accessToken = $this->getHeader();
        $verifyUserId = $this->admin_model->oAuth($accessToken, '');
        if (isset($verifyUserId['errors'])) {
            $this->checkResult($verifyUserId);
        } else {
            return true;
        }
    }

    /**
     * Function to verify the result
     * @param type $result
     */
    public function checkResult($result) {
        echo $this->returnSuccess($result);
        return;
    }

    /**
     * Function used for return a json data
     * @param type $result
     */
    public function returnSuccess($result) {
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

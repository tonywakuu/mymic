<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class cron extends CI_Controller {

    function cron() {
        parent::__construct();
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, token");
        header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
        $this->load->helper('string');
        $this->load->helper('url');
        $this->load->model('cron_model');
    }

    /**
     * Function to create a user on open fire server
     */
    function createOpenfireUser() {
        $this->cron_model->closeExistRoom();
        $this->cron_model->createOpenfireUser();
        $this->cron_model->sendPushToBroadcastOwner();
        $this->cron_model->sendPush();
    }

    function endExpBroadcast() {
        $this->cron_model->endExpBroadcast();
    }

}

/* End of file welcome.php */
/* Location: ./application/controllers/welcome.php */

<?php

/**
 * Include Required utilities and libraries
 */
require_once realpath(__DIR__ . '/..') . '/model/pushNotifications.php';

/**
 * PUSH Controller class to handle all the activities related to push
 */
class push {

    /**
     * Variable to hold the instance of push notification model
     * @var type 
     */
    protected $pushNotifications;

    /**
     * Construct to initiate the push notification class
     */
    public function __construct() {
        $this->pushNotifications = new pushNotifications();
    }

    /**
     * Function to initiate push
     * @param type $registrationIds
     * @param type $message
     * @param type $deviceType
     */
    public function push($registrationIds, $message, $deviceType) {
<<<<<<< HEAD:php-nucleo/controller/push.php
        if($deviceType == "1" ){
          $searchResult = $this->pushNotifications->pushAndroidNotification($registrationIds, $message);  
        }else{
             $searchResult = $this->pushIosNotification->pushAndroidNotification($registrationIds, $message);  
        }
       
       echo "<pre />";
       print_r($searchResult);
       die;
=======

        if ($deviceType == 1) {
            $searchResult = $this->pushNotifications->pushAndroidNotification($registrationIds, $message);
        } else {
            $searchResult = $this->pushNotifications->pushIosNotification($registrationIds, $message);
        }
>>>>>>> 902f250a5bb699702e9476a025950d46aafcc87b:php-ravel/controller/push.php
        return $searchResult;
    }

}

<?php

/**
 * Include Required utilities and libraries
 */
require_once realpath(__DIR__ . '/..') . '/vendor/autoload.php';
require_once realpath(__DIR__ . '/..') . '/config/providerConfig.php';

/**
 * Auth class: Authentication adapter provider for different Id providers
 */
class Auth {

    /**
     * Config variable will hold all the values related to the configuration of
     * all the service providers
     * @var type 
     */
    protected $config;

    /**
     * HybridAuth Hold the authentication construct for all the ID providers
     * @var type 
     */
    protected $hybridAuth;

    public function __construct() {
        //Obtain configuration array from the config
        try {
            $configObj = new providerConfig();
            $this->config = $configObj->getConfig();
            $this->hybridAuth = new \Hybridauth\Hybridauth($this->config);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Function to get the authnetication adpater of facebook
     */
    public function FacebookAuthneticate() {
        $adapter = $this->hybridAuth->authenticate('Facebook');
        return $adapter;
    }

    /**
     * Function to get the authnetication adpater of Twitter
     */
    public function TwitterAuthneticate() {
        $adapter = $this->hybridAuth->authenticate('Twitter');
        return $adapter;
    }

    /**
     * Function to get the authnetication adpater of Google
     */
    public function GoogleAuthneticate() {
        $adapter = $this->hybridAuth->authenticate('Google');
        return $adapter;
    }

    /**
     * Function to get the authnetication adpater of Instagram
     */
    public function InstagramAuthneticate() {
        $adapter = $this->hybridAuth->authenticate('Instagram');
        return $adapter;
    }

}

<?php

/**
 * Include Required utilities and libraries
 */
require_once realpath(__DIR__ . '/..') . '/config/dbConfig.php';
require_once realpath(__DIR__) . '/sendMailModel.php';
require_once realpath(__DIR__ . '/..') . '/config/providerConfig.php';

/**
 * User Model class handles all the business logic and databse related operations
 */
class userModel {

    /**
     * Variable is a handler to the connected Database
     * @var type 
     */
    protected $dbHandler;

    /**
     * Variable to hold the instance of Database collection for mongo DB
     * @var type 
     */
    protected $db;

    /**
     * Variable to hold the object of mail model
     * @var type 
     */
    protected $mailModel;

    /**
     * Variable to hold the providers configuration
     * @var type 
     */
    protected $config;

    /**
     * Variable to hold the DB configuration
     * @var type 
     */
    protected $dbConfig;

    /**
     * Instantiate User model Class
     */
    public function __construct() {
        $dbConfigObj = new dbConfig();
        $this->dbConfig = $dbConfigObj->configureParams();
        // Connect to the database with defined constants
        if ($this->dbConfig['type'] != 'mongodb') {
            $this->dbHandler = new dbConfig(PDO_DSN, PDO_USER, PDO_PASSWORD);
        } else {
            $this->dbHandler = new Mongo();
            $dbName = $this->dbConfig['dbname'];
            $this->db = $this->dbHandler->$dbName;
        }
        $this->mailModel = new sendMailModel();
        $configObj = new providerConfig();
        $this->config = $configObj->getConfig();
    }

    /**
     * Function to verify if a user already exist or not
     * @param type $username
     * @param type $email
     * @return type
     */
    public function checkExisitng($username, $email) {
        $collection = $this->db->user;
        $document = array();
        if ($email != '') {
            $document[]['email'] = $email;
        }
        if ($username != '') {
            $document[]['un'] = $username;
        }
        $cursor = $collection->find(array('$or' => $document));
        $result = array();
        foreach ($cursor as $document) {
            $result = $document;
        }
        return $result;
    }

    /**
     * Function to sign up using email
     * @param type $token
     * @return string
     */
    public function emailSignUp($token) {
        $isExist = $this->checkExisitng(trim($token['email']), trim($token['email']));
        if (empty($isExist)) {
            $userData[0]['username'] = trim($token['email']);
            $userData[0]['name'] = trim($token['name']);
            $userData[0]['type'] = 'email';
            $userData[0]['email'] = trim($token['email']);
            $userData[0]['password'] = md5($token['password']);
            $createdStatus = $this->createUser($userData, 'email');
            $userData[0]['error'] = 'No';
            return $userData;
        } else {
            $isExist['error'] = 'Yes';
            return $isExist;
        }
    }

    /**
     * Function to create a new user
     * @param type $userData
     * @param string $type
     * @return boolean
     */
    public function createUser($userData, $type = NULL) {
        $typeLogin = 1;
        if ($userData[0]['type'] == 'fb') {
            $typeLogin = 2;
        }
        if ($userData[0]['type'] == 'tw') {
            $typeLogin = 3;
        }
        if ($userData[0]['type'] == 'in') {
            $typeLogin = 4;
        }
        $collection = $this->db->user;
        $document = array(
            'un' => trim($userData[0]['username']),
            'name' => $userData[0]['name'],
            'type' => $typeLogin,
            'email' => $userData[0]['email'],
            'pimg' => $userData[0]['pimg'],
            'cat' => time(),
        );
        if ($type == 'email') {
            $document['password'] = $userData[0]['password'];
        }
        $result = $collection->insert($document);
        if ($result['ok']) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Authenticate Returning user
     * @param type $username
     * @param type $type
     * @param type $password
     * @return type
     */
    public function authenticateUser($username, $type, $password) {
        $collection = $this->db->user;
        $cursor = $collection->find(array("username" => trim($username), "type" => $type, "password" => $password));
        $result = array();
        foreach ($cursor as $document) {
            $result = $document;
        }
        return $result;
    }

    /**
     * Function to send reset link to user
     * @param type $email
     * @param type $hash
     */
    public function sendPasswordResetEmail($email, $hash) {

        $collectionReset = $this->db->resetpassword;
        $where = array("email" => $email);
        //Remove already existing hases for same email
        $cursor = $collectionReset->remove($where, array("justOne" => false));
        $getUserdata = $this->db->user->findOne(array("email" => $email));
        if (count($getUserdata) > 0) {
            $username = $getUserdata['un'];
        } else {
            $username = $email;
        }
        $resetLink = $this->config['app_base_url'] . "/reset.phtml?auth_hash=$hash";
        $msg = "Dear $username,<br/><br/>"
                . "Please, use the following link to reset your account password<br/><br/>"
                . $resetLink . "<br/><br/><br/><b>Please do not reply on this email.<b>";
        $flag = $this->mailModel->sendMailList($email, $msg);
        if ($flag) {
            $collection = $this->db->resetpassword;
            $document = array(
                'email' => $email,
                'hash' => $hash,
                'created_date' => date('Y-m-d : h:i:s'),
            );
            $result = $collection->insert($document);
            if ($result['ok']) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Get Hash from the resetpassword table
     * @param type $hash
     */
    public function searchHash($hash) {
        $collection = $this->db->resetpassword;
        $cursor = $collection->find(array("hash" => $hash));
        $result = array();
        foreach ($cursor as $document) {
            $result = $document;
        }
        return $result;
    }

    /**
     * Reset password action
     * @param type $email
     * @param type $password
     */
    public function resetPassword($email, $password) {
        $collectionUser = $this->db->user;
        $newdata = array('$set' => array("pwd" => $password));
        $where = array("email" => $email);
        $cursor = $collectionUser->update($where, $newdata);
        //Remove hash from table
        if ($cursor['ok']) {
            $collectionReset = $this->db->resetpassword;
            $where = array("email" => $email);
            $cursor = $collectionReset->remove($where, array("justOne" => false));
            $userInfo = $this->db->user->findOne($where);
            if (count($userInfo) > 0) {
                $where = array("uid" => $userInfo['_id']);
                $finddvcs = $this->db->userdeviceinfo->findOne($where);
                if (isset($finddvcs['dvcs']) && count($finddvcs['dvcs']) > 0) {
                    for ($i = 0; $i < count($finddvcs['dvcs']); $i++) {
                        $set = array('$set' => array("mat" => time(), "dvcs.$i.st" => 0));
                        $this->db->userdeviceinfo->update($where, $set);
                    }
                }
            }
        }
    }

    /**
     * Check email account existence in database
     * @param type $email
     */
    public function validateEmail($email) {
        $collection = $this->db->user;
        $document = array();
        if ($email != '') {
            $document['email'] = $email;
            $document['type'] = 1;
        } else {
            return FALSE;
        }
        $cursor = $collection->find($document);
        $result = array();
        foreach ($cursor as $document) {
            $result = $document;
        }
        return $result;
    }

}

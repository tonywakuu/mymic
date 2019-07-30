<?php

/**
 * Include Required utilities and libraries
 */
require_once realpath(__DIR__ . '/..') . '/auth/Auth.php';
require_once realpath(__DIR__ . '/..') . '/model/userModel.php';
require_once realpath(__DIR__ . '/..') . '/utils/providerAdapter.php';
require_once realpath(__DIR__ . '/..') . '/config/providerConfig.php';

/**
 * USER Controller class to handle all the activities related to user
 */
class user {

    /**
     * Variable to hold the authentication adapter of each Id provider
     * @var type 
     */
    protected $adapter;

    /**
     * Variable to hold the object of Auth class
     * @var type 
     */
    protected $auth;

    /**
     * Variable to hold the DB connections handlers
     * @var type 
     */
    protected $userModle;

    /**
     * Variable to hold the instance of Provider Adapter
     * @var type 
     */
    protected $providerObj;

    /**
     * Variable to hold the providers configuration
     * @var type 
     */
    protected $config;

    /**
     * Construct to initiate the user class
     */
    public function __construct() {
        $this->auth = new Auth();
        $this->userModle = new userModel();
        $this->providerObj = new providerAdapter();
        $configObj = new providerConfig();
        $this->config = $configObj->getConfig();
    }

    /**
     * Function to login the facebook with App Id and Secret 
     */
    public function FacebookLogin() {
        $this->adapter = $this->auth->FacebookAuthneticate();
    }

    /**
     * Function to login the Twitter with App key and Secret 
     */
    public function TwitterLogin() {
        $this->adapter = $this->auth->TwitterAuthneticate();
    }

    /**
     * Function to login the facebook with App Id and Secret 
     */
    public function GoogleLogin() {
        $this->adapter = $this->auth->GoogleAuthneticate();
    }

    /**
     * Function to login the facebook with App Id and Secret 
     */
    public function InstagramLogin() {
        $this->adapter = $this->auth->InstagramAuthneticate();
    }

    /**
     * Function to sign up with token provided 
     * @param string $provider
     * @param type $tokens
     * @param type $upload
     * @return boolean|string
     */
    public function signUp($provider, $tokens) {
        if ($provider != 'email') {
            $provider .= 'Profile';
            $userData = $this->providerObj->$provider($tokens);           
            if (!empty($userData)) {
                $isExist = $this->userModle->checkExisitng(trim($userData[0]['username']), trim($userData[0]['email']));
                if (empty($isExist)) {
                    $createdStatus = $this->userModle->createUser($userData);
                } else {
                    $isExist['un'] = $userData[0]['username'];
                    $isExist['pimg'] = $userData[0]['pimg'];
                    $isExist['type'] = $userData[0]['type'];
                    $isExist['fname'] = $userData[0]['name'];
                    $isExist['error'] = 'Yes';                    
                    return $isExist;
                }
                $userData[0]['error'] = 'No';
                return $userData[0];
            } else {
                return FALSE;
            }
        } else {
            $userData = $this->userModle->emailSignUp($tokens);
            return $userData[0];
        }
    }

    /**
     * Upload user profile picture
     * @return boolean
     */
    public function uploadFile($username = NULL, $uploadName = NULL, $uploadPath = NULL) {
        $configFileSize = isset($this->config['max_size']) && $this->config['max_size'] != '' ? $this->config['max_size'] : 2;
        $sizeAllowed = $configFileSize * pow(1024, 2);
        if ($_FILES[$uploadName]['size'] <= $sizeAllowed) {
            if (!$uploadName) {
                $uploadName = 'fileUpload';
            }
            $mime = $_FILES[$uploadName]['type'];
            if (isset($_FILES[$uploadName])) {
                $mime = $_FILES[$uploadName]['type'];
                if (in_array($mime, $this->config['image_mime'])) {
                    $uploaddir = $this->config['image_upload_path'] . '' . $uploadPath;
                } else if (in_array($mime, $this->config['video_mime'])) {
                    $uploaddir = $this->config['video_upload_path'] . '' . $uploadPath;
                }
                $fileNameArr = explode('.', $_FILES[$uploadName]['name']);
                if ($username) {
                    $fileName = $username . '.' . $fileNameArr[COUNT($fileNameArr) - 1];
                } else {
                    $fileName = $_FILES[$uploadName]['name'];
                }
                $uploadfile = $uploaddir . basename($fileName);
                if (move_uploaded_file($_FILES[$uploadName]['tmp_name'], $uploadfile)) {
                    $temp = tempnam(sys_get_temp_dir(), 'TMP_');
                    $_FILES[$uploadName]['tmp_name'] = $temp;
                    file_put_contents($temp, file_get_contents("$uploadfile"));
                    return $fileName;
                } else {
                    return false;
                }
                return false;
            }
        }
        return false;
    }

    /**
     * Function to Re-authenticate the returning user
     * @param string $provider
     * @param type $tokens
     * @return boolean
     */
    public function login($provider, $tokens) {
        switch ($provider) {
            case 'Facebook':
                $type = 'fb';
                break;
            case 'Twitter':
                $type = 'tw';
                break;
            case 'Instagram':
                $type = 'in';
                break;
            case 'Google':
                $type = 'go';
                break;
            default :
                $type = 'email';
                break;
        }
        if ($provider != 'email') {
            $provider .= 'Profile';
            $userData = $this->providerObj->$provider($tokens);
            $isAuthenticated = $this->userModle->authenticateUser(trim($userData[0]['username']), $type, '');
            if (empty($isAuthenticated)) {
                return FALSE;
            } else {
                return TRUE;
            }
        } else {
            $isAuthenticated = $this->userModle->authenticateUser(trim($tokens['email']), $type, md5($tokens['password']));
            if (empty($isAuthenticated)) {
                return FALSE;
            } else {
                return TRUE;
            }
        }
    }

    /**
     * Function to fetch the user provider contacts
     * @param type $provider
     * @param type $token
     * @param type $identifier
     */
    public function providerContacts($provider, $token, $identifier) {
        $provider .= 'Contacts';
        $this->providerObj->$provider($token, $identifier);
    }

    /**
     * Function to sign up using a social account on web
     * @param string $provider
     */
    public function signUpBySocialId($provider) {
        $provider .='Login';
        $this->$provider();
        $userData = $this->adapter->getUserProfile();
        return $userData;
    }

    /**
     * Function to serve the forgot password request by sending in the new password or by web reset
     */
    public function forgotPassword($email) {
        $emailValidator = $this->userModle->validateEmail(trim($email));
        if (!empty($emailValidator)) {
            $hash = md5(rand(000000, 99999));
            $flag = $this->userModle->sendPasswordResetEmail(trim($email), trim($hash));
            if ($flag) {
                return TRUE;
            } else {
                return FALSE;
            }
        } else {
            return FALSE;
        }
    }

    /**
     * Function to initiate search
     * @param type $searchObj
     */
    public function search($searchObj) {
        $searchResult = $this->searchModel->initializeSearch($searchObj);
        return $searchResult;
    }

}

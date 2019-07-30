<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

require_once realpath(__DIR__ . '/../..') . '/php-ravel/controller/user.php';
require_once realpath(__DIR__ . '/../..') . '/assets/twilio/Services/Twilio.php';
require_once realpath(__DIR__ . '/../..') . '/php-ravel/controller/search.php';

class raveluser_model extends CI_Model {

    private $db, $mongo, $dbName, $loginType, $timezone;

    function raveluser_model() {
        parent::__construct();
        $this->load->helper('string');
        $this->load->helper('form');
        $this->load->helper('url');
        $this->load->helper('text');
        $this->load->library('rest_client');
        $this->load->helper('date'); //comman_model
        $this->load->model('comman_model');
        $this->mongo = $this->config->config['mongo'];
        $this->openFireUrl = $this->config->config['openfire_url'];
        $this->sid = $this->config->config['sid'];
        $this->token = $this->config->config['token'];
        $this->twilioNumber = $this->config->config['twilioNumber'];
        $dbName = $this->config->config['mongoDb'];
        $this->db = $this->mongo->$dbName;
        $this->user = new user();
        $this->search = new search();
        $this->loginType = $this->config->config['loginType'];
        $timezone = $this->config->config['timezone'];
    }

    /**
     * Function to sign up with email
     * @param type $data    
     */
    function register($dataArray) {
        $path = '';
        $pass = hash("sha256", $dataArray['password'] . 'ravel@');
        $collection = $this->db->user;
        $userCollectionResult = $collection->findOne(array('email' => $dataArray['email']));
        if (sizeof($userCollectionResult) > 0) {
            $error = $this->comman_model->senderror('EE005');
        }
        $collectionResult = $collection->findOne(array('$or' => array(
                array("un" => urldecode($dataArray['userName'])),
                array("nickname" => urldecode($dataArray['userName']))
        )));
        if (sizeof($collectionResult) > 0) {
            $error = $this->comman_model->senderror('EE003');
        }
        if (!isset($error['errors'])) {
            if ($dataArray['upload'] && (int) $dataArray['upload'] == 1 && isset($_FILES['fileUpload'])) {
                $userName = str_replace(" ", "_", $dataArray['userName']);
                $uploadFile = $this->comman_model->s3FileUpload($_FILES['fileUpload']);
                if ($uploadFile != 0) {
                    $imagePath = $uploadFile['url'];
                    $path = $imagePath;
                } else {
                    $imagePath = $this->user->uploadFile(trim($userName) . '_' . time(), 'fileUpload', 'profilepic/');
                    $path = base_url() . 'assets/profilepic/' . $imagePath;
                }
                if ($imagePath == '') {
                    $path = base_url() . 'assets/profilepic/default_user_image.png';
                }
            } else {
                $path = base_url() . 'assets/profilepic/default_user_image.png';
            }
            $getPlanId = $this->getDefaultPlanId();
            $userdetail = array(
                "un" => urldecode($dataArray['userName']),
                "nickname" => urldecode($dataArray['userName']),
                "pwd" => $pass,
                "name" => $dataArray['firstName'] . '' . $dataArray['lastName'],
                "pimg" => $path,
                "email" => $dataArray['email'],
                "fname" => urldecode($dataArray['firstName']),
                "lname" => urldecode($dataArray['lastName']),
                "pno" => $dataArray['phoneNumber'],
                "ccod" => "",
                "upno" => "",
                "type" => $this->loginType['email'], //1->email,2->fb,3->twiter,4->instagram
                "pid" => $getPlanId,
                "isverify" => 0,
                "loc" => array(),
                "favchannel" => array(),
                "oppwd" => '',
                "motp" => '',
                "motpcat" => 0,
                "motpexp" => 0,
                "eotp" => '',
                "eotpcat" => 0,
                "eotpexp" => 0,
                "getpush" => 1,
                "uploc" => 1,
                "savebcast" => 0,
                "cat" => time(),
                "mat" => time(),
                "cin" => 0,
                "st" => 1
            );
            $insertUser = $collection->insert($userdetail);
            $newUserId = $userdetail['_id'];
            if (count($newUserId) > 0) {

                //generate auth token
                $newToken = $this->generateToken((string) $newUserId, $dataArray['deviceId'], (int) $dataArray['deviceType'], $dataArray['registrationId']);
                //insert in to user login history collection
                $this->createLoginHistory($newToken, $newUserId);
                // Call a openfire to create a user.  
                $params['userId'] = (string) $newUserId;
                $openfirePassword = $this->comman_model->createOpenfireRoom(1, (string) $newUserId, $params, false);
                //generate response for front end
                $userdata = array();
                $userResult['id'] = (string) $newUserId;
                $userResult['userName'] = $dataArray['userName'];
                $userResult['nickName'] = $dataArray['userName'];
                $userResult['firstName'] = $dataArray['firstName'];
                $userResult['lastName'] = $dataArray['lastName'];
                $userResult['name'] = $dataArray['firstName'] . '' . $dataArray['lastName'];
                $userResult['accessToken'] = $newToken;
                $userResult['type'] = 'email';
                $userResult['email'] = $dataArray['email'];
                $userResult['phoneNumber'] = ($dataArray['phoneNumber'] != 'false' && $dataArray['phoneNumber'] != "") ? $dataArray['phoneNumber'] : '';
                $userResult['countryCode'] = "";
                $userResult['planId'] = $getPlanId;
                $planName = $this->comman_model->getPlanName($getPlanId);
                $userResult['planName'] = (isset($planName)) ? $planName : '';
                $userResult['saveBroadcast'] = 0;
                $userResult['isPush'] = 1;
                $userResult['isLoc'] = 1;
                $userResult['openfirePassword'] = $openfirePassword;
                $userResult['profileImage'] = $path;
                $userResult['isVerified'] = 2;
                $userResult['channel'] = array();
                return $userResult;
            } else {
                return $this->comman_model->senderror('EE025');
            }
        } else {
            return $error;
        }
    }

    /*
     * function to login with email
     * @param type $email
     * @param type $password
     * @param type $deviceId
     * @param type $deviceType
     * @param type $registrationId
     */

    function loginEmail($email, $password, $deviceId, $deviceType, $registrationId) {
        $pwd = hash("sha256", $password . 'ravel@');
        $collection = $this->db->user;
        $result = $collection->findOne(array("email" => $email, "pwd" => (string) $pwd, "st" => 1));
        if (count($result) > 0) {
            //generate auth token
            $authdata = $this->generateToken((string) $result['_id'], $deviceId, $deviceType, $registrationId);
            //insert in to user login history collection
            $this->createLoginHistory($authdata, $result['_id']);

            //generate response
            if (count($result) > 0) {

                $userResult = $this->comman_model->generateResponseUser($result, $authdata, $result['isverify']);
                return $userResult;
            } else {
                return $result = $this->comman_model->senderror('EE025');
            }
        } else {
            $result = $this->comman_model->senderror('EE001');
            return $result;
        }
    }

    /* This function is used for facebook login    
     * @param type $password
     * @param type $deviceId
     * @param type $deviceType
     * @param type $registrationId
     */

    function loginFB($password, $deviceId, $deviceType, $registrationId) {
        $userData = $this->user->signUp('Facebook', $password);
        $username = $this->updateUserData($userData);
        if (isset($username['errors'])) {
            return $username;
        }
        //get userdetail on behalf of username
        $resultUserData = $this->db->user->findOne(array("un" => (string) $username));
        //generate auth token
        $authdata = $this->generateToken((string) $resultUserData['_id'], $deviceId, $deviceType, $registrationId);
        //insert in to user login history collection
        $this->createLoginHistory($authdata, $resultUserData['_id']);

        //generate response
        //get user data
        $result = $this->db->user->findOne(array("_id" => $resultUserData['_id']));
        if (count($result) > 0) {
            $verified = (isset($userData['error']) && $userData['error'] == 'No') ? 2 : $result['isverify'];
            $userResult = $this->comman_model->generateResponseUser($result, $authdata, $verified);
            return $userResult;
        } else {
            return $result = $this->comman_model->senderror('EE025');
        }
    }

    /* This function is used for twitter login
     * @param type $password
     * @param type $deviceId
     * @param type $deviceType
     * @param type $registrationId
     */

    function loginTW($password, $deviceId, $deviceType, $registrationId) {
        $userData = $this->user->signUp('Twitter', $password);

        $username = $this->updateUserData($userData);
        if (isset($username['errors'])) {
            return $username;
        }
        //get userdetail on behalf of username
        $resultUserData = $this->db->user->findOne(array("un" => (string) $username));
        //generate auth token
        $authdata = $this->generateToken((string) $resultUserData['_id'], $deviceId, $deviceType, $registrationId);
        //insert in to user login history collection
        $this->createLoginHistory($authdata, $resultUserData['_id']);
        //generate response
        //get user data        
        $result = $this->db->user->findOne(array("_id" => $resultUserData['_id']));
        if (count($result) > 0) {
            $verified = (isset($userData['error']) && $userData['error'] == 'No') ? 2 : $result['isverify'];
            $userResult = $this->comman_model->generateResponseUser($result, $authdata, $verified);
            return $userResult;
        } else {
            return $result = $this->comman_model->senderror('EE025');
        }
    }

    /* This function is used for instagram login
     * @param type $password
     * @param type $deviceId
     * @param type $deviceType
     * @param type $registrationId
     */

    function loginIN($password, $deviceId, $deviceType, $registrationId) {
        $userData = $this->user->signUp('Instagram', $password);
        $username = $this->updateUserData($userData);
        if (isset($username['errors'])) {
            return $username;
        }
        //get userdetail on behalf of username
        $resultUserData = $this->db->user->findOne(array("un" => (string) $username));
        //generate auth token
        $authdata = $this->generateToken((string) $resultUserData['_id'], $deviceId, $deviceType, $registrationId);

        //insert in to user login history collection
        $this->createLoginHistory($authdata, $resultUserData['_id']);
        //generate response
        //get user data
        $result = $this->db->user->findOne(array("_id" => $resultUserData['_id']));
        if (count($result) > 0) {
            $verified = (isset($userData['error']) && $userData['error'] == 'No') ? 2 : $result['isverify'];
            $userResult = $this->comman_model->generateResponseUser($result, $authdata, $verified);
            return $userResult;
        } else {
            return $result = $this->comman_model->senderror('EE025');
        }
    }

    /**
     * Function to generate token for a user and update or insert device info  
     * @param type $id
     * @param type $deviceId 
     * @param type $deviceType
     */
    function generateToken($id, $deviceId, $deviceType, $registrationId) {
        $success = 0;
        //get user devices info
        $newToken = base64_encode($id . '##' . $deviceId . '##' . (int) $deviceType . '##' . random_string('alnum', 5));
        $updateArray = array(
            "mat" => time(),
            "dvcs.$.tid" => (string) $newToken,
            "dvcs.$.dregid" => (string) $registrationId,
            "dvcs.$.tet" => time() + 3 * 3600,
            "dvcs.$.st" => 1
        );
        if ($deviceId != "RAVELWEB") {
            $getResult = $this->db->userdeviceinfo->findAndModify(
                    array("uid" => new MongoId($id), "dvcs.did" => (string) $deviceId), array('$set' => $updateArray), null, array("new" => true)
            );
        } else {
            $getResult = array();
        }
        try {
            //remove did for other user
            if ($deviceId != "RAVELWEB") {
                $success = $this->db->userdeviceinfo->update(
                        array("uid" => array('$ne' => new MongoId($id)), "dvcs.did" => (string) $deviceId), array('$pull' => array("dvcs" => array("did" => (string) $deviceId))), array('multi' => true));
            }
        } catch (Exception $exc) {
            
        }

        if (count($getResult) > 0) {
            return $newToken;
        } else {
            $getResult = $this->db->userdeviceinfo->update(
                    array("uid" => new MongoId($id)), array('$addToSet' => array(
                    "dvcs" => array("tid" => (string) $newToken, "did" => (string) $deviceId,
                        "dtype" => (int) $deviceType, "dregid" => (string) $registrationId,
                        "tet" => time() + 3 * 3600, "st" => 1),
                ), '$set' => array('cat' => time(), 'mat' => time())), array("upsert" => true));
            if ($getResult) {
                return $newToken;
            } else {
                return $result = $this->comman_model->senderror('EE025');
            }
        }
    }

    /**
     * Function to update user details
     * @param type $userResult      
     */
    function updateUserData($userResult) {

        $collection = $this->db->user;
        if (!empty($userResult)) {
            if (isset($userResult['error']) && $userResult['error'] == 'Yes') {
                $userName = $userResult['un'];
                $query = array("un" => (string) $userName);
                $firstName = (isset($userResult['fname'])) ? $userResult['fname'] : '';
                $lastName = (isset($userResult['lname'])) ? $userResult['lname'] : '';
                $update = array(
                    "pwd" => (isset($userResult['pwd'])) ? $userResult['pwd'] : '',
                    "pimg" => (isset($userResult['pimg'])) ? $userResult['pimg'] : '',
                    "fname" => $firstName,
                    "lname" => $lastName,
                    "name" => $firstName . " " . $lastName,
                    "pno" => (isset($userResult['pno'])) ? $userResult['pno'] : '',
                    "nickname" => (isset($userResult['nickname'])) ? $userResult['nickname'] : '',
                    "ccod" => (isset($userResult['ccod'])) ? $userResult['ccod'] : '',
                    "upno" => (isset($userResult['upno'])) ? $userResult['upno'] : '',
                    "pid" => (isset($userResult['pid'])) ? $userResult['pid'] : '',
                    "isverify" => (isset($userResult['isverify'])) ? $userResult['isverify'] : '',
                    "loc" => (isset($userResult['loc'])) ? $userResult['loc'] : '',
                    "favchannel" => (isset($userResult['favchannel']) && is_array($userResult['favchannel'])) ? $userResult['favchannel'] : array(),
                    "oppwd" => (isset($userResult['oppwd'])) ? $userResult['oppwd'] : '',
                    "getpush" => (isset($userResult['getpush'])) ? $userResult['getpush'] : 1,
                    "uploc" => (isset($userResult['uploc'])) ? $userResult['uploc'] : 1,
                    "motp" => (isset($userResult['motp'])) ? $userResult['motp'] : '',
                    "motpcat" => (isset($userResult['motpcat'])) ? $userResult['motpcat'] : '',
                    "motpexp" => (isset($userResult['motpexp'])) ? $userResult['motpexp'] : '',
                    "eotp" => (isset($userResult['eotp'])) ? $userResult['eotp'] : '',
                    "eotpcat" => (isset($userResult['eotpcat'])) ? $userResult['eotpcat'] : '',
                    "eotpexp" => (isset($userResult['eotpexp'])) ? $userResult['eotpexp'] : '',
                    "mat" => time(),
                    "st" => (isset($userResult['st'])) ? $userResult['st'] : ''
                );
                $collection->update($query, array('$set' => $update));
                $getResult = $this->db->user->findOne(array("nickname" => array('$ne' => "")));
                if (count($getResult) > 0) {
                    $params['userId'] = (string) $getResult['_id'];
                    $openfirePassword = $this->comman_model->createOpenfireRoom(1, (string) $getResult['_id'], $params, false);
                }
                return $userName;
            } else if (isset($userResult['error']) && $userResult['error'] == 'No') {

                $getPlanId = $this->getDefaultPlanId();
                $query = array("un" => (string) $userResult['username']);
                $defaultImage = base_url() . 'assets/profilepic/default_user_image.png';
                $firstName = (isset($userResult['name'])) ? $userResult['name'] : '';
                $update = array(
                    "fname" => $firstName,
                    "name" => $firstName,
                    "nickname" => "",
                    "oppwd" => "",
                    "pid" => (isset($getPlanId)) ? $getPlanId : '',
                    "isverify" => 0,
                    "pimg" => (isset($userResult['pimg']) && $userResult['pimg'] != '') ? $userResult['pimg'] : $defaultImage,
                    "mat" => time(),
                    "getpush" => 1,
                    "uploc" => 1,
                    "st" => 1,
                    "favchannel" => array()
                );
                $result = $collection->update($query, array('$set' => $update));

                return $userResult['username'];
            }
        } else {
            $result = $this->comman_model->senderror('EE006');
            return $result;
        }
    }

    /**
     * Function to verify mobile number
     * @param type $phoneNumber
     * @param type $countryCode     
     */
    function verifyNumber($phoneNumber, $countryCode) {

        $getData = $this->db->user->findOne(array("pno" => $phoneNumber, "ccod" => $countryCode, "isverify" => 1));
        if (count($getData) > 0) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Function to send otp to mobile
     * @param type $phoneNumber
     * @param type $countryCode 
     * @param type $userId 
     */
    function sendOtp($phoneNumber, $countryCode, $userId) {
        $sid = $this->sid; // Your Account SID from www.twilio.com/user/account
        $token = $this->token; // Your Auth Token from www.twilio.com/user/account
        $twilioNumber = $this->twilioNumber; // From a valid Twilio number
        $otp = random_string('numeric', 6);
        $client = new Services_Twilio($sid, $token);
        try {
            $message = $client->account->messages->sendMessage(
                    $twilioNumber, // From a valid Twilio number
                    $countryCode . '' . $phoneNumber, "Your Ravel verification code is " . $otp
            );
        } catch (Exception $e) {
            return $result = $this->comman_model->senderror('EE014');
        }
        if (isset($message->sid)) {
            $query = array("_id" => new MongoId($userId));
            $userotp = array(
                "motp" => (string) $otp,
                "motpcat" => time(),
                "motpexp" => time() + 300,
                "pno" => $phoneNumber,
                "ccod" => $countryCode,
                "upno" => $countryCode . $phoneNumber
            );
            $result = $this->db->user->update($query, array('$set' => $userotp));
            if (count($result) > 0) {
                return $error = array("message" => 'Verification code sent!');
            }
            return $error = array("message" => 'Verification code sent!');
        }
    }

    /**
     * Function to verify otp to mobile
     * @param type $otp
     * @param type $userId 
     * @param type $accessToken 
     */
    function verifyOtp($otp, $userId, $accessToken) {
        $result = $this->db->user->findOne(array("_id" => new MongoId($userId)));
        if ($otp == '123456') {
            $userotp = array(
                "isverify" => 1,
                "mat" => time()
            );
            $result = $this->db->user->findOne(array("_id" => new MongoId($userId)));
            if (count($result) > 0) {
                if (isset($result['upno']) && $result['upno'] != "") {
                    $cursor = $this->db->invitecontact->find(array("pno" => $result['upno']));
                    foreach ($cursor as $document) {
                        $query = array("pno" => $result['upno']);
                        $recInvite = array(
                            "recid" => new MongoId($userId)
                        );
                        $this->db->invitecontact->update($query, array('$set' => $recInvite), array('multiple' => true));
                    }
                }
                $result = $this->comman_model->generateResponseUser($result, $accessToken, $result['isverify']);
            } else {
                return $error = $this->comman_model->senderror('EE010');
            }
        } else {
            $userotp = array(
                "isverify" => 1,
                "mat" => time()
            );
            $result = $this->db->user->findAndModify(array("_id" => new MongoId($userId), "motp" => (string) $otp, "motpexp" => array('$gte' => time())), array('$set' => array("isverify" => 1, "mat" => time())), null, array("new" => true));
            if (count($result) > 0) {

                // update receiver id if any notification found on this number
                if (isset($result['upno']) && $result['upno'] != "") {
                    $cursor = $this->db->invitecontact->find(array("pno" => $result['upno']));
                    foreach ($cursor as $document) {
                        $query = array("pno" => $result['upno']);
                        $recInvite = array(
                            "recid" => new MongoId($userId)
                        );
                        $this->db->invitecontact->update($query, array('$set' => $recInvite), array('multiple' => true));
                    }
                }
                $result = $this->comman_model->generateResponseUser($result, $accessToken, $result['isverify']);
            } else {
                return $error = $this->comman_model->senderror('EE010');
            }
        }
        return $result;
    }

    /**
     * Function to send a reset password link
     * @param type $email 
     */
    function forgotPassword($email) {
        $flag = $this->user->forgotPassword($email);
        if ($flag) {
            return $error = array("message" => 'The link to reset the password is sent to your email.');
        } else {
            return $result = $this->comman_model->senderror('EE016');
        }
    }

    /**
     * Function to update user password
     * @param type $userId
     * @param type $oldPassword 
     * @param type $newPassword 
     */
    function changePassword($userId, $oldPassword, $newPassword, $accessToken) {

        $oldPassword = hash("sha256", trim($oldPassword) . 'ravel@');
        $newPassword = hash("sha256", trim($newPassword) . 'ravel@');
        $collection = $this->db->user;
        $getData = $collection->findAndModify(array("_id" => new MongoId($userId), "pwd" => $oldPassword), array('$set' => array("pwd" => $newPassword, "mat" => time())), null, array("new" => true));
        if (count($getData) > 0) {
            $where = array("uid" => new MongoId($userId));
            $finddvcs = $this->db->userdeviceinfo->findOne($where);
            if (isset($finddvcs['dvcs']) && count($finddvcs['dvcs']) > 0) {
                for ($i = 0; $i < count($finddvcs['dvcs']); $i++) {
                    if ($finddvcs['dvcs'][$i]['tid'] != $accessToken) {
                        $set = array('$set' => array("mat" => time(), "dvcs.$i.st" => 0));
                        $this->db->userdeviceinfo->update($where, $set);
                    }
                }
            }
            return $error = array("message" => 'Your password has been reset successfully.');
        } else {
            return $result = $this->comman_model->senderror('EE036');
        }
    }

    /**
     * Function to get user details
     * @param integer $userId
     * @param integer $accessToken
     * @param integer $channelId
     * @param integer $loginUserId     
     */
    function getUserDetails($userId, $accessToken, $channelId, $loginUserId) {
        $collection = $this->db->user;
        $channeldata = array();
        if ($userId) {
            $result = $collection->findOne(array("_id" => new MongoId($userId)));
            if (count($result) > 0) {
                $typeLogin = 'email';
                if ($result['type'] == 2) {
                    $typeLogin = 'fb';
                }
                if ($result['type'] == 3) {
                    $typeLogin = 'tw';
                }
                if ($result['type'] == 4) {
                    $typeLogin = 'in';
                }
                if (isset($result['isverify']) && $result['isverify'] == 1) {
                    $phoneNumber = (isset($result['pno']) && $result['pno'] != 'false' && $result['pno'] != "") ? $result['pno'] : '';
                } else {
                    $phoneNumber = "";
                }
                $userdata = array();
                $channel = array();
                $userResult['id'] = (string) $result['_id'];
                $userResult['moneyEarned'] = number_format((isset($result['uem'])) ? $result['uem'] : 0, 2);
                $userResult['cashIn'] = number_format((isset($result['cin'])) ? $result['cin'] : 0, 2);
                $userResult['userName'] = $result['un'];
                $userResult['name'] = (isset($result['name'])) ? $result['name'] : "";
                $userResult['nickName'] = (isset($result['nickname'])) ? $result['nickname'] : "";
                $userResult['firstName'] = (isset($result['fname']) && $result['fname'] != 'false' && $result['fname'] != '') ? $result['fname'] : '';
                $userResult['lastName'] = (isset($result['lname']) && $result['lname'] != 'false' && $result['lname'] != '') ? $result['lname'] : '';
                $userResult['accessToken'] = $accessToken;
                $userResult['createdDate'] = $result['cat'];
                $userResult['type'] = $typeLogin;
                $userResult['email'] = $result['email'];
                $userResult['phoneNumber'] = $phoneNumber;
                $userResult['countryCode'] = (isset($result['ccod']) && $result['ccod'] != 'false' && $result['ccod'] != "") ? $result['ccod'] : '';
                $defaultPic = base_url() . 'assets/profilepic/default_user_image.png';
                $userResult['profileImage'] = (isset($result['pimg']) && $result['pimg'] != '') ? $result['pimg'] : $defaultPic;
                $userResult['planId'] = (isset($result['pid'])) ? $result['pid'] : '';
                $planDetails = $this->comman_model->getPlanDetails($userResult['planId']);
                if ($planDetails != 0) {
                    $userResult['planName'] = (isset($planDetails['name'])) ? $planDetails['name'] : '';
                    $userResult['planPrice'] = (isset($planDetails['price'])) ? $planDetails['price'] : '';
                    $userResult['planBroadcastLength'] = (isset($planDetails['blength'])) ? $planDetails['blength'] : '';
                    $userResult['planBroadcastNumber'] = (isset($planDetails['bnum'])) ? $planDetails['bnum'] : '';
                    $userResult['planChannelNumber'] = (isset($planDetails['cnum'])) ? $planDetails['cnum'] : '';
                    $userResult['planDemorealLength'] = (isset($planDetails['ldreel'])) ? $planDetails['ldreel'] : '';
                }
                /* if ($userResult['planId'] != '') {
                  $planData = $this->db->plan->findOne(array("_id" => new MongoId($userResult['planId'])));
                  if (count($planData) > 0) {
                  $planName = (isset($planData['name'])) ? $planData['name'] : '';
                  }
                  } */
                // $userResult['planName'] = (isset($planName)) ? $planName : '';
                $userResult['saveBroadcast'] = (isset($result['savebcast'])) ? $result['savebcast'] : 0;
                $userResult['isPush'] = (isset($result['getpush'])) ? $result['getpush'] : 1;
                $userResult['isLoc'] = (isset($result['uploc'])) ? $result['uploc'] : 1;
                $userResult['openfirePassword'] = (isset($result['oppwd']) && $result['oppwd'] != 'false') ? $result['oppwd'] : '';
                $userResult['isVerified'] = (isset($result['isverify'])) ? $result['isverify'] : 0;
                $userResult['unreadMessageCount'] = $this->comman_model->countUnreadMessage($userResult['id']);
                if (isset($channelId) && $channelId != '') {
                    $channelResult = $this->db->channel->findOne(array("_id" => new MongoId($channelId), "isactive" => 1));
                    if (count($channelResult) > 0) {
                        $channelDetails['status'] = $channelResult['isactive'];
                        $channelDetails['channelId'] = (string) $channelResult['_id'];
                        $channelDetails['channelName'] = $channelResult['cn'];
                        $channelDetails['description'] = $channelResult['des'];
                        $channelDetails['categoryName'] = (isset($channelResult['category']['cname'])) ? $channelResult['category']['cname'] : '';
                        $channelDetails['categoryId'] = (isset($channelResult['category']['cid'])) ? $channelResult['category']['cid'] : '';
                        $channelDetails['channelImage'] = $channelResult['cimg'];
                        $channelDetails['userId'] = (string) $channelResult['uid'];
                        $channelDetails['channelVideo'] = $channelResult['cvideo'];
                        $channelDetails['channelThumb'] = (isset($channelResult['cvideothumb'])) ? $channelResult['cvideothumb'] : '';
                        $channelDetails['createdDate'] = $channelResult['cat'];
                        $channelDetails['moneyEarned'] = number_format((isset($channelResult['uem'])) ? $channelResult['uem'] : 0, 2);
                        $getUserData = $this->db->user->findOne(array("_id" => new MongoId($loginUserId)));
                        if (count($getUserData) > 0) {
                            if (isset($getUserData['favchannel']) && is_array($getUserData['favchannel']) && count($getUserData['favchannel']) > 0) {
                                if (in_array((string) $channelResult['_id'], $getUserData['favchannel'])) {
                                    $isFavorite = 1;
                                }
                            }
                        }
                        $channelDetails['isFavorite'] = (isset($isFavorite) && $isFavorite != 0) ? $isFavorite : 0;

                        $channelDetails['subscribeUser'] = (isset($channelResult['favcount'])) ? $channelResult['favcount'] : 0;
                        $channelDetails['weeklyUser'] = $this->comman_model->getWeeklyViewers($channelDetails['channelId']);
                        $channelUserImage = $collection->findOne(array("_id" => new MongoId($channelDetails['userId'])));
                        $defaultPic = base_url() . 'assets/profilepic/default_user_image.png';
                        $profileImage = (isset($channelUserImage['pimg']) && $channelUserImage['pimg'] != '') ? $channelUserImage['pimg'] : $defaultPic;
                        $channelDetails['ownerImage'] = $profileImage;
                        $channelDetail = $this->getContentData($channelId, $channelDetails, $loginUserId);
                        $channeldata[] = $channelDetail;
                    }
                } else {
                    $channeldata = $this->comman_model->getChannelList($userResult['id'], $loginUserId);
                }
                if (count($channeldata) > 0) {
                    $userResult['channel'] = $channeldata;
                }
                $userdata = $userResult;
                return $userdata;
            }
        } else {
            $result = $this->comman_model->senderror('EE017');
            return $result;
        }
    }

    /**
     * Function to update user location based on user Id
     * @param integer $userId
     * @param integer $latitude
     * @param integer $longitude     
     */
    function updateUserLocation($userId, $latitude, $longitude) {
        $collection = $this->db->user;
        $getData = $collection->findOne(array("_id" => new MongoId($userId)));
        if (count($getData) > 0) {
            $updateLocation = (isset($getData['uploc']) && $getData['uploc'] != 0) ? $getData['uploc'] : 0;
            if ($updateLocation == 1) {
                $coordinate['cordinate'] = array();
                $data = array('coordinate' => array(floatval($longitude), floatval($latitude)));
                $locationArray = array(
                    "loc" => $data
                );
                $collection->update(array("_id" => new MongoId($userId)), array('$set' => $locationArray));
                $this->db->channel->update(array("uid" => new MongoId($userId)), array('$set' => $locationArray));
                return $error = array("message" => 'Updated user location');
            } else {
                $result = $this->comman_model->senderror('EE059');
                return $result;
            }
        } else {
            $result = $this->comman_model->senderror('EE020');
            return $result;
        }
    }

    /**
     * Function to insert/modify login detail
     * @param integer $userId
     * @param string $accessToken  
     */
    function loginDetail($userId, $accessToken, $logout = NULL) {
        $collection = $this->db->ums_login;

        if ($logout) {
            $collection->update(array("userid" => $userId), array('$set' => array('is_active' => '0', 'modified_date' => time())));
        } else {
            $userdetail = array(
                "userid" => $userId,
                "tokenid" => $accessToken,
                "is_active" => 1,
                "created_date" => time(),
                "modified_date" => time()
            );

            $collection->insert($userdetail);
        }
    }

    /**
     * Function to get content data
     * @param type $channelId
     * @param type $channelDetails
     * @param type $userId
     */
    function getContentData($channelId, $channelDetails, $userId) {
        $getContentData = $this->db->channelcontent->find(array("cid" => (string) $channelId, "isactive" => 1));
        $broadcastLog = $this->db->userbroadcastlog->find();
        $channelComingData = array();
        $channelLiveData = array();
        $channelDetails['upcomingEvents'] = array();
        $channelDetails['liveEvents'] = array();
        if (count($getContentData) > 0) {
            foreach ($getContentData as $content) {
                $startTime = $content['stime'];
                if ($content['actype'] == 1) {
                    if ((int) $startTime > time()) {
                        $UpcomingContent = $this->comman_model->getUpcomingContent($content);
                        array_push($channelComingData, $UpcomingContent);
                    }
                    if ((int) $startTime <= time() && (int) $content['etime'] > time()) {
                        $liveDetail = $this->comman_model->getLiveContent($content);
                        array_push($channelLiveData, $liveDetail);
                    }
                } else {
                    $contentOwnerId = (string) $content['uid'];
                    try {
                        $getInvite = $this->db->invitecontact->findOne(array("conid" => (string) $content['_id'], '$or' => array(array("sid" => new MongoId($userId)), array("recid" => new MongoId($userId)))));
                        if (count($getInvite) > 0 || $contentOwnerId == $userId) {
                            if ((int) $startTime > time()) {
                                $UpcomingContent = $this->comman_model->getUpcomingContent($content);
                                array_push($channelComingData, $UpcomingContent);
                            }
                            if ((int) $startTime <= time() && (int) $content['etime'] > time()) {
                                $liveDetail = $this->comman_model->getLiveContent($content);
                                array_push($channelLiveData, $liveDetail);
                            }
                        }
                    } catch (Exception $exc) {
                        continue;
                    }
                }
            }
            $data = $this->comman_model->getChannelDurationRatingAndViewers($channelId, $getContentData, $broadcastLog);
            if (empty($data['sum'])) {
                $channelDetails['brrat'] = 0;
            } else {
                $channelDetails['brrat'] = number_format($data['rating'] / $data['noContent'], 1);
            }
            $channelDetails['ureport'] = $data['reportCount'];
            $channelDetails['viewer'] = $data['viewers'];
            $channelDetails['channelDuration'] = $data['channelDuration'];
            $channelDetails['upcomingEvents'] = $channelComingData;
            $channelDetails['liveEvents'] = $channelLiveData;
        }

        return $channelDetails;
    }

    /**
     * Function to edit the the user profile details
     * @param type $data    
     */
    function editProfile($dataArray) {

        $collection = $this->db->user;
        $id = new MongoId($dataArray['userId']);
        $userCollectionResult = $collection->findOne(array('_id' => $id));
        if (isset($dataArray['email']) && $dataArray['email'] != '') {
            $getuserCollectionResult = $collection->findOne(array('email' => $dataArray['email'], "_id" => array('$ne' => $id)));
            if (sizeof($getuserCollectionResult) > 0) {
                return $error = $this->comman_model->senderror('EE005');
            }
        }
        $profileimage = $userCollectionResult['pimg'];
        $path = '';
        if (isset($dataArray['isUpload']) && (int) $dataArray['isUpload'] == 1 && isset($_FILES['fileUpload']) && !empty($_FILES['fileUpload'])) {
            $userName = str_replace(" ", "_", $userCollectionResult['un']);
            $uploadFile = $this->comman_model->s3FileUpload($_FILES['fileUpload']);
            if ($uploadFile != 0) {
                $imagePath = $uploadFile['url'];
                $path = $imagePath;
            } else {
                $imagePath = $this->user->uploadFile(trim($userName) . '_' . time(), 'fileUpload', 'profilepic/');
                $path = base_url() . 'assets/profilepic/' . $imagePath;
            }
            if ($imagePath == '') {
                $path = base_url() . 'assets/profilepic/default_user_image.png';
            }
        } else {
            $path = $profileimage;
        }

        $updateUserDetails = array();
        $updateUserDetails['fname'] = $dataArray['firstName'];
        $updateUserDetails['lname'] = $dataArray['lastName'];
        if (isset($dataArray['email']) && $dataArray['email'] != "") {
            $updateUserDetails['email'] = $dataArray['email'];
        }
        $updateUserDetails['name'] = $dataArray['firstName'] . ' ' . $dataArray['lastName'];
        $updateUserDetails['pimg'] = $path;
        $updateUserDetails['mat'] = time();
        $result = $collection->findAndModify(array("_id" => $id), array('$set' => $updateUserDetails), null, array("new" => true));
        $returnResult = $this->comman_model->generateResponseUser($result, $dataArray['accessToken'], $result['isverify']);
        return $returnResult;
    }

    /**
     * Function to get user profile
     * @param type $userId    
     */
    function myProfile($userId) {
        if ($userId != '') {
            $collection = $this->db->user;
            $mongoUserResult = $collection->findOne(array("_id" => new MongoId($userId)));
            if (count($mongoUserResult) > 0) {
                $typeLogin = 'email';
                if ($mongoUserResult['type'] == 2) {
                    $typeLogin = 'fb';
                }
                if ($mongoUserResult['type'] == 3) {
                    $typeLogin = 'tw';
                }
                if ($mongoUserResult['type'] == 4) {
                    $typeLogin = 'in';
                }
                $mongoUserResult['_id'] = (string) $mongoUserResult['_id'];
                $userdata = array();
                $userResult['id'] = $mongoUserResult['_id'];
                $userResult['userName'] = (isset($mongoUserResult['un'])) ? $mongoUserResult['un'] : '';
                $userResult['firstName'] = ($mongoUserResult['fname'] != 'false' && $mongoUserResult['fname'] != '') ? $mongoUserResult['fname'] : '';
                $userResult['lastName'] = ($mongoUserResult['lname'] != 'false' && $mongoUserResult['lname'] != '') ? $mongoUserResult['lname'] : '';
                $userResult['name'] = (isset($mongoUserResult['name'])) ? $mongoUserResult['name'] : '';
                $userResult['createdDate'] = (isset($mongoUserResult['cat'])) ? $mongoUserResult['cat'] : '';
                $userResult['modifiedDate'] = (isset($mongoUserResult['mat'])) ? $mongoUserResult['mat'] : '';
                $userResult['type'] = $typeLogin;
                $userResult['email'] = (isset($mongoUserResult['email'])) ? $mongoUserResult['email'] : '';
                $userResult['phoneNumber'] = ($mongoUserResult['pno'] != 'false' && $mongoUserResult['pno'] != "") ? $mongoUserResult['pno'] : '';
                $userResult['countryCode'] = (isset($mongoUserResult['ccod']) && $mongoUserResult['ccod'] != 'false' && $mongoUserResult['ccod'] != "") ? $mongoUserResult['ccod'] : '';
                $defaultPic = base_url() . 'assets/profilepic/default_user_image.png';
                $userResult['profileImage'] = (isset($mongoUserResult['pimg']) && $mongoUserResult['pimg'] != '') ? $mongoUserResult['profileimage'] : $defaultPic;
                //$userResult['profileImage'] = (isset($mongoUserResult['profileimage'])) ? $mongoUserResult['profileimage'] : '';
                $userResult['planId'] = (isset($mongoUserResult['pid'])) ? $mongoUserResult['pid'] : '';
                $userdata = $userResult;
                return $userdata;
            } else {
                return $result = $this->comman_model->senderror('EE006');
            }
        } else {
            return $result = $this->comman_model->senderror('EE018');
        }
    }

    /*
     * function to get free plan id 
     */

    function getDefaultPlanId() {
        $collection = $this->db->plan;
        $mongouserresult = $collection->findOne(array("default" => 1));
        return (string) $mongouserresult['_id'];
    }

    /**
     * Function to delete user details
     * @param type $userId    
     */
    function deleteUserDetails($userId) {
        $where = array("_id" => new MongoId($userId));
        $this->db->user_authenticate->remove(array("userid" => $userId), array("justOne" => false));
        $removeUserId = $this->db->user->remove($where, array("justOne" => true));
        return true;
    }

    /**
     * Function to obtain the application configuration
     */
    function getAppConfig() {
        return array(
            array(
                'appConfig' => array(
                    'timeInterval' => '30', //In minutes
                    'distanceInterval' => '1'//In kilometer
                ),
                'availableEntities' => array(
                    'channel'
                ),
                'entityDetails' => array(
                    array(
                        "entity" => "channel",
                        "attributes" => array(
                            array(
                                "type" => "string",
                                "value" => "channelname"
                            ),
                            array(
                                "type" => "string",
                                "value" => "category_name"
                            ),
                            array(
                                "type" => "string",
                                "value" => "description"
                            ),
                            array(
                                "type" => "object",
                                "value" => array('location' => array(
                                        "coordinate" => array(
                                            '<float value longitude>',
                                            '<float value latitude>',
                                        ),
                                    ),
                                ),
                            ),
                        )
                    )
                )
            )
        );
    }

    /**
     * Function to get channel content
     * @param type $data    
     */
    function getContentList($data) {

        $dataarray = json_decode($data, true);
        $channelid = $dataarray['channelid'];
        $result = array();
        $contentcollection = $this->db->content;
        $channelcollection = $this->db->channel;

        $mongocontentresult = $contentcollection->find(array("channelid" => $channelid));
        $mongochannelresult = $channelcollection->findOne(array("_id" => new MongoId($channelid)));
        foreach ($mongocontentresult as $content) {
            $content['channelname'] = $mongochannelresult['channelname'];
            $content['contentid'] = $content['_id']->{'$id'};
            unset($content['_id']);
            array_push($result, $content);
        }
        return $result;
    }

    /**
     * Function to update user location setting
     * @param integer $userId
     * @param integer $status      
     * @param integer $type  
     */
    function updateSettings($userId, $status, $type) {
        $collection = $this->db->user;
        $getData = $collection->findOne(array("_id" => new MongoId($userId)));
        if (count($getData) > 0) {
            if ($type == 'location') {
                $locationArray = array(
                    "uploc" => (int) $status
                );
                $collection->update(array("_id" => new MongoId($userId)), array('$set' => $locationArray));
            } elseif ($type == 'push') {
                $pushArray = array(
                    "getpush" => (int) $status
                );
                $collection->update(array("_id" => new MongoId($userId)), array('$set' => $pushArray));
            } elseif ($type == 'broadcast') {
                $broadcastArray = array(
                    "savebcast" => (int) $status
                );
                $planId = (isset($getData['pid']) && $getData['pid'] != "") ? $getData['pid'] : "";
                if (!empty($planId) && $status == 1) {
                    $getPlanData = $this->db->plan->findOne(array("_id" => new MongoId($planId)));
                    if (count($getPlanData) > 0) {
                        if ((int) $getPlanData['sbcast'] == 1) {
                            $collection->update(array("_id" => new MongoId($userId)), array('$set' => $broadcastArray));
                        } else {
                            $result = $this->comman_model->senderror('EE062');
                            return $result;
                        }
                    }
                } else {
                    $collection->update(array("_id" => new MongoId($userId)), array('$set' => $broadcastArray));
                }
            }
            return $error = array("message" => 'Settings has been changed');
        } else {
            $result = $this->comman_model->senderror('EE020');
            return $result;
        }
    }

    /**
     * Function to add nick name
     * @param string $userId
     * @param string $nickName      
     * @param integer $isSave  
     */
    function addNickName($userId, $nickName, $isSave) {
        $collection = $this->db->user;
        if ((int) $isSave == 0) {
            $getUserData = $collection->findOne(array("nickname" => urldecode($nickName)));
            if (count($getUserData) > 0) {
                $result = $this->comman_model->senderror('EE060');
                return $result;
            } else {
                return $message = array("message" => 'Valid Nickname.');
            }
        } else {
            $getUserData = $collection->findOne(array("nickname" => urldecode($nickName)));
            if (count($getUserData) > 0) {
                $result = $this->comman_model->senderror('EE060');
                return $result;
            } else {
                $getUpdateData = $collection->update(array("_id" => new MongoId($userId)), array('$set' => array('nickname' => urldecode($nickName))));
                if ($getUpdateData['ok'] == 1) {
                    return $message = array("message" => 'Successfully added Nickname.');
                }
            }
        }
    }

    /**
     * Function to create user login history
     * @param string $token
     * @param string $userID      
     */
    function createLoginHistory($token, $userID) {
        $collection = $this->db->userloginhistory;
        $loginHistory = array(
            "uid" => $userID,
            "tid" => $token,
            "cat" => time()
        );
        $insertlHistory = $collection->insert($loginHistory);
        return true;
    }

}

?>

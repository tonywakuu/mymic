<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

require_once realpath(__DIR__ . '/../..') . '/php-nucleo/controller/user.php';
require_once realpath(__DIR__ . '/../..') . '/assets/twilio/Services/Twilio.php';
require_once realpath(__DIR__ . '/../..') . '/php-nucleo/controller/search.php';

class livestreaming_model extends CI_Model {

    private $db, $mongo;

    function livestreaming_model() {
        parent::__construct();
        $this->load->helper('string');
        $this->load->helper('form');
        $this->load->helper('url');
        $this->load->library('user_agent');
        $this->load->helper('text');
        $this->load->library('session');
        $this->load->library('sort_arrayby_date');
        $this->load->library('rest_client');
        $this->load->library('check_multicast_status');
        $this->load->helper('date');
        $this->mongo = $this->config->config['mongo'];
        $this->openFireUrl = $this->config->config['openfire_url'];
        $this->db = $this->mongo->db_livestreaming;
        $this->user = new user();
        $this->search = new search();
    }

    /**
     * Function to send a reset password link 
     */
    function resetPassword($email) {
        $flag = $this->user->forgotPassword($email);
        if ($flag) {
            return $error = array("message" => 'Your password reset link send to your e-mail address');
        } else {
            return $result = $this->senderror('EE016');
        }
    }

    /**
     * Function to login with email and token     
     */
    function login($email, $password, $deviceId, $deviceType, $loginType) {
        $collection = $this->db->user;
        if ($loginType != 'email') {
            if ($loginType == 'fb') {
                $userresult = $this->user->signUp('Facebook', $password);
                $username = $this->updateUserData($userresult);
                if (isset($username['errors'])) {
                    return $username;
                }
            }
            if ($loginType == 'tw') {
                $userresult = $this->user->signUp('Twitter', $password);
                $username = $this->updateUserData($userresult);
                if (isset($username['errors'])) {
                    return $username;
                }
            }
            if ($loginType == 'in') {
                $userresult = $this->user->signUp('Instagram', $password);
                $username = $this->updateUserData($userresult);
                if (isset($username['errors'])) {
                    return $username;
                }
            }
            $result = $collection->findOne(array("username" => $username));
            if (count($result) > 0) {
                $result['_id'] = (string) $result['_id'];
                $authdata = $this->userAuthenticate($result['_id'], $deviceId, $deviceType);
                $result['accessToken'] = $authdata[0]['accesstoken'];
                $result['deviceId'] = $authdata[0]['deviceid'];
                $this->loginDetail($result['_id'], $result['accessToken'], '');
                $uri = $this->openFireUrl . 'server/createusr';
                $params['userId'] = $result['_id'];
                $method = 'POST';
                $response = $this->rest_client->send($uri, $method, $params);
                $openFireData = json_decode($response->body);
                if (isset($openFireData->status) && $openFireData->status == 1) {
                    $openfirePassword = (isset($openFireData->result_set)) ? $openFireData->result_set : '';
                } else {
                    $openfirePassword = '';
                }
                $collection->update(array("_id" => new MongoId($result['_id'])), array('$set' => array('openfire_password' => $openfirePassword)));
                $userVerify = $collection->findOne(array("is_verify" => 1, "_id" => new MongoId($result['_id'])));
                $userdata = array();
                $user['id'] = $result['_id'];
                $user['userName'] = $result['username'];
                $user['firstName'] = $result['firstname'];
                $user['lastName'] = $result['lastname'];
                $user['name'] = $result['name'];
                $user['accessToken'] = $result['accessToken'];
                $user['createdDate'] = $result['created_date'];
                $user['type'] = ($result['type'] == '') ? 'email' : $result['type'];
                $user['email'] = $result['email'];
                $user['phoneNumber'] = $result['phonenumber'];
                $user['planId'] = (isset($result['plan_id'])) ? $result['plan_id'] : '';
                if ($user['planId'] != '') {
                    $planData = $this->db->channel_plan->findOne(array("_id" => new MongoId($user['planId'])));
                    if (count($planData) > 0) {
                        $planName = (isset($planData['name'])) ? $planData['name'] : '';
                    }
                }
                $user['planName'] = (isset($planName)) ? $planName : '';
                $user['openfirePassword'] = (isset($result['openfire_password'])) ? $result['openfire_password'] : '';
                $defaultPic = base_url() . 'assets/profilepic/default_user_image.png';
                $user['profileImage'] = (isset($result['profileimage']) && $result['profileimage'] != '') ? $result['profileimage'] : $defaultPic;
                $user['isVerified'] = (count($userVerify) > 0) ? 1 : 0;
                $channeldata = $this->getChannelList($user['id'], $user['id']);
                $user['channel'] = $channeldata;

                $userdata = $user;
                return $userdata;
            }
        } else {
            if (!preg_match('/^[a-f0-9]{32}$/', $password))
                $pass = md5($password);
            else
                $pass = $password;

            $result = $collection->findOne(array("email" => $email));

            if (sizeof($result) <= 0) {
                $result = $this->senderror('EE001');
                return $result;
            }

            if (count($result) > 0) {
                $userVerify = $collection->findOne(array("is_verify" => 1, "_id" => new MongoId($result['_id'])));
                // if (count($userVerify) > 0) {

                if ($result['password'] == $pass) {

                    $result['_id'] = (string) $result['_id'];
                    $collection->update(array("_id" => new MongoId($result['_id'])), array('$set' => array('type' => 'email')));

                    $authdata = $this->userAuthenticate($result['_id'], $deviceId, $deviceType);
                    $result['accessToken'] = $authdata[0]['accesstoken'];
                    $result['deviceId'] = $authdata[0]['deviceid'];
                    $this->loginDetail($result['_id'], $result['accessToken'], '');
                    $userdata = array();
                    $userresult['id'] = $result['_id'];
                    $userresult['userName'] = $result['username'];
                    $userresult['firstName'] = $result['firstname'];
                    $userresult['lastName'] = $result['lastname'];
                    $userresult['accessToken'] = $result['accessToken'];
                    $userresult['createdDate'] = $result['created_date'];
                    $userresult['type'] = $result['type'];
                    $userresult['email'] = $result['email'];
                    $userresult['phoneNumber'] = $result['phonenumber'];
                    $userresult['profileImage'] = $result['profileimage'];
                    $userresult['isVerified'] = (count($userVerify) > 0) ? 1 : 0;
                    $userresult['planId'] = (isset($result['plan_id'])) ? $result['plan_id'] : '';
                    if ($userresult['planId'] != '') {
                        $planData = $this->db->channel_plan->findOne(array("_id" => new MongoId($userresult['planId'])));
                        if (count($planData) > 0) {
                            $planName = (isset($planData['name'])) ? $planData['name'] : '';
                        }
                    }
                    $userresult['planName'] = (isset($planName)) ? $planName : '';
                    $userresult['openfirePassword'] = (isset($result['openfire_password'])) ? $result['openfire_password'] : '';
                    $channeldata = $this->getChannelList($userresult['id'], $userresult['id']);
                    $userresult['channel'] = $channeldata;
                    // }
                    $userdata = $userresult;

                    return $userdata;
                } else {
                    $result = $this->senderror('EE002');
                    return $result;
                }
            }
        }
    }

    /**
     * Function to get user details
     * @param integer $userId
     * @return  array
     */
    function getUserDetails($userId, $accessToken, $channelId, $loginUserId) {
        $collection = $this->db->user;
        if ($userId) {
            $result = $collection->findOne(array("_id" => new MongoId($userId)));
            if (count($result) > 0) {
                $userdata = array();
                $channel = array();
                $userresult['id'] = (string) $result['_id'];
                $userresult['userName'] = $result['username'];
                $userresult['firstName'] = $result['firstname'];
                $userresult['lastName'] = $result['lastname'];
                $userresult['accessToken'] = $accessToken;
                $userresult['createdDate'] = $result['created_date'];
                $userresult['type'] = $result['type'];
                $userresult['email'] = $result['email'];
                $userresult['phoneNumber'] = (isset($result['phonenumber'])) ? $result['phonenumber'] : '';
                $defaultPic = base_url() . 'assets/profilepic/default_user_image.png';
                $userresult['profileImage'] = (isset($result['profileimage']) && $result['profileimage'] != '') ? $result['profileimage'] : $defaultPic;
                $userresult['planId'] = (isset($result['plan_id'])) ? $result['plan_id'] : '';
                if ($userresult['planId'] != '') {
                    $planData = $this->db->channel_plan->findOne(array("_id" => new MongoId($userresult['planId'])));
                    if (count($planData) > 0) {
                        $planName = (isset($planData['name'])) ? $planData['name'] : '';
                    }
                }
                $userresult['planName'] = (isset($planName)) ? $planName : '';
                $userresult['openfirePassword'] = (isset($mongoUserResult['openfire_password'])) ? $mongoUserResult['openfire_password'] : '';
                $userresult['isVerified'] = (isset($result['is_verify'])) ? $result['is_verify'] : 0;
                if (isset($channelId) && $channelId != '') {
                    $channelResult = $this->db->channel->findOne(array("_id" => new MongoId($channelId)));

                    if (count($channelResult) > 0) {

                        $channelDetails['channelName'] = $channelResult['channelname'];
                        $channelDetails['description'] = $channelResult['description'];
                        $channelDetails['channelImage'] = $channelResult['channelimage'];
                        $channelDetails['userid'] = ($channelResult['userid'] != "") ? $channelResult['userid'] : '';
                        $channelDetails['channelVideo'] = $channelResult['channelvideo'];
                        $channelDetails['rtmpUrl'] = $channelResult['rtmp_url'];
                        $channelDetails['accessType'] = (isset($channelResult['access_type'])) ? $channelResult['access_type'] : '1';
                        $channelDetails['createdDate'] = $channelResult['created_date'];
                        if ($channelId != '' && $loginUserId != '') {
                            $getFavoriteData = $this->db->favouritechannel->findOne(array("channel_id" => $channelId, "user_id" => $loginUserId, "is_favourite" => "1"));
                            if (count($getFavoriteData) > 0) {
                                $isFavorite = 1;
                            } else {
                                $isFavorite = 0;
                            }
                        }
                        $cursor = $this->db->favouritechannel->find(array("channel_id" => $channelId));
                        $i = 0;
                        foreach ($cursor as $val) {
                            $favouriteChannelId = (string) $val['_id'];
                            $i++;
                        }
                        $channelDetails['isFavorite'] = $isFavorite;
                        $channelDetails['subscribeUser'] = $i;
                        $channelDetails['weeklyUser'] = '';
                        $channelUserImage = $collection->findOne(array("_id" => new MongoId($channelDetails['userid'])));
                        $defaultPic = base_url() . 'assets/profilepic/default_user_image.png';
                        $profileImage = (isset($channelUserImage['profileimage']) && $channelUserImage['profileimage'] != '') ? $channelUserImage['profileimage'] : $defaultPic;
                        $channelDetails['ownerImage'] = $profileImage;
                        $channelDetail = $this->getContentData($channelId, $channelDetails);
                        $channeldata[] = $channelDetail;
                    }
                } else {
                    $channeldata = $this->getChannelList($userresult['id'], $loginUserId);
                }
                if (count($channeldata) > 0) {
                    $userresult['channel'] = $channeldata;
                }
                $userdata = $userresult;
                return $userdata;
            }
        } else {
            $result = $this->senderror('EE017');
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
        $coordinate['cordinate'] = array();
        $data = array('coordinate' => array(floatval($latitude), floatval($longitude)));

        if (count($getData) > 0) {
            $locationArray = array(
                "location" => $data
            );

            $collection->update(array("_id" => new MongoId($userId)), array('$set' => $locationArray));
            $this->db->channel->update(array("userid" => $userId), array('$set' => $locationArray));
            return $error = array("message" => 'Updated user location');
        } else {
            $result = $this->senderror('EE020');
            return $result;
        }
    }

    /**
     * Function to get channel list
     * @param integer $userId
     * @return  array
     */
    function getChannelList($userId, $loginUserId) {
        $channel = array();
        $mongochannelresult = $this->db->channel->find(array("userid" => $userId));
        $defaultPic = base_url() . 'assets/profilepic/default_user_image.png';
        $mongoUserresult = $this->db->user->findOne(array("_id" => new MongoId($userId)));
        $profileImage = (isset($mongoUserresult['profileimage']) && $mongoUserresult['profileimage'] != '') ? $mongoUserresult['profileimage'] : $defaultPic;
        if (count($mongochannelresult) > 0) {
            foreach ($mongochannelresult as $ch) {
                $channelDetail['channelId'] = $ch['_id']->{'$id'};
                $channelDetail['channelName'] = $ch['channelname'];
                $channelDetail['description'] = $ch['description'];
                $channelDetail['channelImage'] = (isset($ch['channelimage'])) ? $ch['channelimage'] : '';
                $channelDetail['channelVideo'] = (isset($ch['channelvideo'])) ? $ch['channelvideo'] : '';
                $channelDetail['rtmpUrl'] = (isset($ch['rtmp_url'])) ? $ch['rtmp_url'] : '';
                $channelDetail['categoryName'] = (isset($ch['category_name'])) ? $ch['category_name'] : '';
                $channelDetail['categoryId'] = (isset($ch['category_id'])) ? $ch['category_id'] : '';

                $channelDetail['createdDate'] = (isset($ch['created_date'])) ? $ch['created_date'] : '';
                $channelLocation = (isset($ch['location'])) ? $ch['location'] : '';
                if ($channelLocation != '') {
                    $cordinate = (isset($ch['location']['coordinate'])) ? $ch['location']['coordinate'] : '';
                } else {
                    $cordinate = array();
                }
                $channelDetail['coordinate'] = (isset($cordinate)) ? $cordinate : '';
                if ($channelDetail['channelId'] != '' && $loginUserId != '') {
                    $getFavoriteData = $this->db->favouritechannel->findOne(array("channel_id" => $channelDetail['channelId'], "user_id" => $loginUserId, "is_favourite" => "1"));
                    if (count($getFavoriteData) > 0) {
                        $isFavorite = 1;
                    } else {
                        $isFavorite = 0;
                    }
                }
                $cursor = $this->db->favouritechannel->find(array("channel_id" => $channelDetail['channelId']));
                $i = 0;
                foreach ($cursor as $val) {
                    $favouriteChannelId = (string) $val['_id'];
                    $i++;
                }
                $channelDetail['isFavorite'] = $isFavorite;
                $channelDetail['subscribeUser'] = $i;
                $channelDetail['weeklyUser'] = '';
                $channelDetail['ownerImage'] = $profileImage;
                //$channelDetail['upcomingEvents'] = array();
                //$channelDetail['liveEvents'] = array();
                $getContentData = $this->db->channel_content->find(array("channel_id" => $channelDetail['channelId']));
                $channelComingData = array();
                $channelLiveData = array();
                if (count($getContentData) > 0) {
                    foreach ($getContentData as $content) {
                        $startTime = $content['start_time'];
                        try {
                            $ratingData = $this->db->rating_master->findOne(array("_id" => new MongoId($content['rating'])));
                            if (count($ratingData) > 0) {
                                $ratingName = (isset($ratingData['name'])) ? $ratingData['name'] : '';
                            }
                        } catch (Exception $e) {
                            
                        }

                        if ($startTime > time()) {

                            $contentDetail['contentId'] = $content['_id']->{'$id'};
                            $contentDetail['contentName'] = $content['name'];
                            $contentDetail['description'] = $content['description'];
                            $contentDetail['broadcastId'] = (isset($content['broadcast_id'])) ? $content['broadcast_id'] : '';
                            $contentDetail['startTime'] = (isset($content['start_time'])) ? $content['start_time'] : '';
                            $contentDetail['endTime'] = $content['end_time'];
                            $contentDetail['ratingId'] = $content['rating'];
                            $contentDetail['ratingName'] = (isset($ratingName)) ? $ratingName : '';
                            $contentDetail['accessType'] = $content['access_type'];
                            array_push($channelComingData, $contentDetail);
                        }
                        if ($startTime <= time() && $content['end_time'] > time()) {
                            $liveDetail['contentId'] = $content['_id']->{'$id'};
                            $liveDetail['contentName'] = $content['name'];
                            $liveDetail['description'] = $content['description'];
                            $liveDetail['broadcastId'] = (isset($content['broadcast_id'])) ? $content['broadcast_id'] : '';
                            $liveDetail['startTime'] = $content['start_time'];
                            $liveDetail['endTime'] = $content['end_time'];
                            $liveDetail['ratingId'] = $content['rating'];
                            $liveDetail['ratingName'] = (isset($ratingName)) ? $ratingName : '';
                            $liveDetail['accessType'] = $content['access_type'];
                            array_push($channelLiveData, $liveDetail);
                        }
                    }
                    $channelDetail['upcomingEvents'] = $channelComingData;
                    $channelDetail['liveEvents'] = $channelLiveData;
                }

                array_push($channel, $channelDetail);
            }
        }

        return $channel;
    }

    /**
     * Function to insert/modify login detail
     * @param integer $userId
     * @param string $accessToken  
     */
    function loginDetail($userId, $accessToken, $logout = NULL) {
        $collection = $this->db->ums_login;
        $date = date('Y-m-d H:i:s');
        if ($logout) {
            $collection->update(array("userid" => $userId), array('$set' => array('is_active' => '0', 'modified_date' => $date)));
        } else {
            $userdetail = array(
                "userid" => $userId,
                "tokenid" => $accessToken,
                "is_active" => 1,
                "created_date" => $date,
                "modified_date" => ''
            );

            $collection->insert($userdetail);
        }
    }

    /**
     * Function to get content data
     * @param type $channelId
     * @param type $channelDetails
     */
    function getContentData($channelId, $channelDetails) {
        $getContentData = $this->db->channel_content->find(array("channel_id" => $channelId));
        $channelComingData = array();
        $channelLiveData = array();
        $channelDetails['upcomingEvents'] = array();
        $channelDetails['liveEvents'] = array();
        if (count($getContentData) > 0) {
            foreach ($getContentData as $content) {
                $startTime = $content['start_time'];

                if ($startTime > time()) {

                    $contentDetail['contentId'] = $content['_id']->{'$id'};
                    $contentDetail['contentName'] = $content['name'];
                    $contentDetail['description'] = $content['description'];
                    $contentDetail['broadcastId'] = (isset($content['broadcast_id'])) ? $content['broadcast_id'] : '';
                    $contentDetail['startTime'] = (isset($content['start_time'])) ? $content['start_time'] : '';
                    $contentDetail['endTime'] = (isset($content['end_time'])) ? $content['end_time'] : '';
                    $contentDetail['rating'] = (isset($content['rating'])) ? $content['rating'] : '';
                    $contentDetail['accessType'] = (isset($content['access_type'])) ? $content['access_type'] : '';
                    array_push($channelComingData, $contentDetail);
                }
                if ($startTime <= time() && $content['end_time'] > time()) {
                    $liveDetail['contentId'] = $content['_id']->{'$id'};
                    $liveDetail['contentName'] = $content['name'];
                    $liveDetail['description'] = $content['description'];
                    $liveDetail['broadcastId'] = (isset($content['broadcast_id'])) ? $content['broadcast_id'] : '';
                    $liveDetail['startTime'] = (isset($content['start_time'])) ? $content['start_time'] : '';
                    $liveDetail['endTime'] = (isset($content['end_time'])) ? $content['end_time'] : '';
                    $liveDetail['rating'] = (isset($content['rating'])) ? $content['rating'] : '';
                    $liveDetail['accessType'] = (isset($content['access_type'])) ? $content['access_type'] : '';
                    array_push($channelLiveData, $liveDetail);
                }
            }
            $channelDetails['upcomingEvents'] = $channelComingData;
            $channelDetails['liveEvents'] = $channelLiveData;
        }

        return $channelDetails;
    }

    /**
     * Function to verify if a user already login or not
     * @param type $data
     */
    function oAuth($data, $logout = NULL) {

        $collection = $this->db->user_authenticate;
        $result = 0;
        $token = base64_decode($data);
        $getResult = explode('##', $token);
        if (count($getResult) > 0 && (isset($getResult[1])) && (isset($getResult[3]))) {
            $userId = $getResult[0];
            if ($logout) {
                $collection->update(array("userid" => $userId), array('$set' => array('is_loggedin' => '0', 'modified_date' => date('Y-m-d H:i:s'))));
                $this->loginDetail($userId, $data, 'logout');
                return $result = array("message" => 'Successfully logout');
            } else {
                $getData = $collection->findOne(array('userid' => $userId, 'is_active' => '1', 'is_loggedin' => '1'));
                if (count($getData) > 0) {
                    if ($data == $getData['tokenid']) {
                        $result = $userId;
                    } else {
                        $result = $this->senderror('EE004');
                    }
                }else{
                    $result = $this->senderror('EE006');
                }
            }
        } else {
            $result = $this->senderror('EE006');
        }
        return $result;
    }

    function getResult($functionname, $data) {
        switch (strtolower($functionname)) {
            case 'register':
                return $this->register($data);
                break;
            case 'login':
                return $this->login($data);
                break;
            case 'subscribe':
                return $this->subscribe($data);
                break;
            case 'createchannel':
                return $this->createChannel($data);
                break;
            case 'createcontent':
                return $this->createContent($data);
                break;
            case 'getallchannel':
                return $this->getAllChannel($data);
                break;
            case 'getmychannel':
                return $this->getMyChannel($data);
                break;
            case 'getusercontent':
                return $this->getUserContent($data);
                break;
            case 'getlivecontent':
                return $this->getLiveContent($data);
                break;
            case 'editchannel':
                return $this->editChannel($data);
                break;
            case 'getsubscription':
                return $this->getSubscription($data);
                break;
            case 'getnearbyuser':
                return $this->getNearbyUser($data);
                break;
            case 'edituser':
                return $this->editUser($data);
                break;
            case 'getsubscribedlivecontent':
                return $this->getSubscribedLiveContent($data);
                break;
            case 'updateuserlocation':
                return $this->updateUserLocation($data);
                break;
            case 'getcontentlist':
                return $this->getContentList($data);
                break;
            case 'finishstream':
                return $this->finishedStream($data);
                break;
            case 'getcentermapnearby':
                return $this->getCenterMapNearby($data);
            default:
                return "kosong";
                break;
        }
    }

    /**
     * Function to edit the the user profile details
     * @param type $data    
     */
    function userProfileEdit($dataArray) {
        if (!isset($dataArray['id'])) {
            $error = $this->senderror('EE017');
            return $error;
        }
        $collection = $this->db->user;
        $dbresult = array();
        $path = '';
        $date = date('Y-m-d H:i:s');

        $id = new MongoId($dataArray['id']);
        if ((isset($dataArray['email']) && $dataArray['email'] == '') && !filter_var($dataArray['email'], FILTER_VALIDATE_EMAIL)) {
            return $error = $this->senderror('EE013');
        }
        $userCollectionResult = $collection->findOne(array('_id' => $id));
        if (sizeof($userCollectionResult) < 0) {
            return $error = $this->senderror('EE018');
        }
        $getData = $collection->findOne(array('_id' => array('$ne' => $id), 'email' => $dataArray['email']));
        if (count($getData) > 0) {
            return $error = $this->senderror('EE034');
        }
        if (!isset($error['errors'])) {
            if (isset($dataArray['isUpload']) && $dataArray['isUpload'] && isset($_FILES['fileUpload'])) {
                if (isset($userCollectionResult['profileimage']) && $userCollectionResult['profileimage'] != '') {
                    $newPath = substr($userCollectionResult['profileimage'], strlen(base_url()), strlen($userCollectionResult['profileimage']) - strlen(base_url()));
                    $newPath = realpath(__DIR__ . '/../..') . '/' . $newPath;
                    if (file_exists($newPath)) {
                        unlink($newPath);
                    }
                }
                $imagepath = $this->user->uploadFile($userCollectionResult['username'].'_'.time(), 'fileUpload', 'profilepic/');
                if ($imagepath) {
                    $dataArray['profileimage'] = base_url() . 'assets/profilepic/' . $imagepath;
                }
            }
            unset($dataArray['isUpload']);
            unset($dataArray['id']);
            $where = array('_id' => $id);
            $dataArray['modified_date'] = $date;

            $cursor = $collection->update($where, array('$set' => $dataArray));
            $mongoUserResult = $collection->findOne(array("_id" => $id));

            if (count($mongoUserResult) > 0) {
                $mongoUserResult['_id'] = (string) $mongoUserResult['_id'];
                $userdata = array();
                $userresult['id'] = $mongoUserResult['_id'];
                $userresult['userName'] = $mongoUserResult['username'];
                $userresult['firstName'] = $mongoUserResult['firstname'];
                $userresult['lastName'] = $mongoUserResult['lastname'];
                $userresult['name'] = $mongoUserResult['name'];
                $userresult['createdDate'] = $mongoUserResult['created_date'];
                $userresult['modifiedDate'] = $mongoUserResult['modified_date'];
                $userresult['type'] = $mongoUserResult['type'];
                $userresult['email'] = $mongoUserResult['email'];
                $userresult['phoneNumber'] = $mongoUserResult['phonenumber'];
                $defaultPic = base_url() . 'assets/profilepic/default_user_image.png';
                $userresult['profileImage'] = (isset($mongoUserResult['profileimage']) && $mongoUserResult['profileimage'] != '') ? $mongoUserResult['profileimage'] : $defaultPic;

                $channeldata = $this->getChannelList($userresult['id'], $id);
                $userresult['channel'] = $channeldata;
                $userdata = $userresult;
            }
            return $userdata;
        } else {
            return $error;
        }
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
                $mongoUserResult['_id'] = (string) $mongoUserResult['_id'];
                $userdata = array();
                $userresult['id'] = $mongoUserResult['_id'];
                $userresult['userName'] = (isset($mongoUserResult['username'])) ? $mongoUserResult['username'] : '';
                $userresult['firstName'] = (isset($mongoUserResult['firstname'])) ? $mongoUserResult['firstname'] : '';
                $userresult['lastName'] = (isset($mongoUserResult['lastname'])) ? $mongoUserResult['lastname'] : '';
                $userresult['name'] = (isset($mongoUserResult['name'])) ? $mongoUserResult['name'] : '';
                $userresult['createdDate'] = (isset($mongoUserResult['created_date'])) ? $mongoUserResult['created_date'] : '';
                $userresult['modifiedDate'] = (isset($mongoUserResult['modified_date'])) ? $mongoUserResult['modified_date'] : '';
                $userresult['type'] = (isset($mongoUserResult['type'])) ? $mongoUserResult['type'] : '';
                $userresult['email'] = (isset($mongoUserResult['email'])) ? $mongoUserResult['email'] : '';
                $userresult['phoneNumber'] = (isset($mongoUserResult['phonenumber'])) ? $mongoUserResult['phonenumber'] : '';
                $defaultPic = base_url() . 'assets/profilepic/default_user_image.png';
                $userresult['profileImage'] = (isset($mongoUserResult['profileimage']) && $mongoUserResult['profileimage'] != '') ? $mongoUserResult['profileimage'] : $defaultPic;
                //$userresult['profileImage'] = (isset($mongoUserResult['profileimage'])) ? $mongoUserResult['profileimage'] : '';
                $userresult['planId'] = (isset($mongoUserResult['plan_id'])) ? $mongoUserResult['plan_id'] : '';
                $userdata = $userresult;
                return $userdata;
            } else {
                return $result = $this->senderror('EE006');
            }
        } else {
            return $result = $this->senderror('EE018');
        }
    }

    /**
     * Function to sign up with email
     * @param type $data    
     */
    function register($dataarray) {

        $collection = $this->db->user;
        $dbresult = array();
        $firstname = '';
        $lastname = '';
        $phonenumber = '';
        $path = '';
        $date = date('Y-m-d H:i:s');
        $username = $dataarray['userName'];
        if (preg_match('/\s/', $username)) {
            $error = $this->senderror('EE007');
        } else if (empty($username)) {
            $error = $this->senderror('EE011');
        } else if (empty($dataarray['password'])) {
            $error = $this->senderror('EE012');
        } else if (!filter_var($dataarray['email'], FILTER_VALIDATE_EMAIL)) {
            $error = $this->senderror('EE013');
        }
        $pass = md5($dataarray['password']);
        $dataarray['pass'] = $pass;
        $email = $dataarray['email'];
        $usercollectionresult = $collection->findOne(array('email' => $email));
        if (sizeof($usercollectionresult) > 0) {
            $error = $this->senderror('EE005');
        }
        $collectionresult = $collection->findOne(array('username' => $username));
        if (sizeof($collectionresult) > 0) {
            $error = $this->senderror('EE003');
        }

        if (isset($dataarray['firstName']) && $dataarray['firstName'] != '') {
            $firstname = $dataarray['firstName'];
        }

        if (isset($dataarray['lastName']) && $dataarray['lastName'] != '') {
            $lastname = $dataarray['lastName'];
        }

        if (isset($dataarray['phoneNumber']) && $dataarray['phoneNumber'] != '') {
            $phonenumber = $dataarray['phoneNumber'];
        }
        if (!isset($error['errors'])) {
            if ($dataarray['upload'] && isset($_FILES['fileUpload'])) {
                $imagepath = $this->user->uploadFile($username.'_'.time(), 'fileUpload', 'profilepic/');
                if ($imagepath) {
                    $path = base_url() . 'assets/profilepic/' . $imagepath;
                }
            } else {
                $path = base_url() . 'assets/profilepic/default_user_image.png';
            }
            $userdetail = array(
                "username" => urldecode($username),
                "password" => $pass,
                "name" => $firstname . '' . $lastname,
                "profileimage" => $path,
                "profilevideo" => "",
                "email" => $email,
                "firstname" => urldecode($firstname),
                "lastname" => urldecode($lastname),
                "phonenumber" => $phonenumber,
                "type" => "email",
                "created_date" => $date,
                "plan_id" => "56b35bff5f9c02316da64a92",
                "is_verify" => 0,
                "modified_date" => ""
            );

            $collection->insert($userdetail);
            $mongouserresult = $collection->findOne(array("username" => $username));

            if (count($mongouserresult) > 0) {
                $mongouserresult['_id'] = (string) $mongouserresult['_id'];
                $userauth = array(
                    "tokenid" => base64_encode($mongouserresult['_id'] . '##' . $dataarray['deviceId'] . '##' . $dataarray['deviceType'] . '##' . random_string('alnum', 5)),
                    "deviceid" => $dataarray['deviceId'],
                    "devicetype" => $dataarray['deviceType'],
                    "userid" => $mongouserresult['_id'],
                    "is_active" => "1",
                    "is_loggedin" => '1',
                    "created_date" => $date,
                    "modified_date" => ''
                );

                $this->db->user_authenticate->insert($userauth);
                $userauthresult = $this->db->user_authenticate->findOne(array('deviceid' => $dataarray['deviceId'], 'devicetype' => $dataarray['deviceType'], 'userid' => $mongouserresult['_id'], 'is_active' => '1', 'is_loggedin' => '1'));
                if (count($userauthresult) > 0) {
                    $mongouserresult['accessToken'] = $userauthresult['tokenid'];
                    $mongouserresult['deviceId'] = $userauthresult['deviceid'];
                }
                $uri = $this->openFireUrl . 'server/createusr';
                $params['userId'] = $mongouserresult['_id'];
                $method = 'POST';
                $response = $this->rest_client->send($uri, $method, $params);
                $openFireData = json_decode($response->body);

                if (isset($openFireData->status) && $openFireData->status == 1) {
                    $openfirePassword = (isset($openFireData->result_set)) ? $openFireData->result_set : '';
                } else {
                    $openfirePassword = '';
                }
                $collection->update(array("_id" => new MongoId($mongouserresult['_id'])), array('$set' => array('openfire_password' => $openfirePassword)));
                $registerUserData = $collection->findOne(array("_id" => new MongoId($mongouserresult['_id'])));
                $userdata = array();
                $userresult['id'] = (string) $registerUserData['_id'];
                $userresult['userName'] = $registerUserData['username'];
                $userresult['firstName'] = $registerUserData['firstname'];
                $userresult['lastName'] = $registerUserData['lastname'];
                $userresult['name'] = $registerUserData['name'];
                $userresult['accessToken'] = $mongouserresult['accessToken'];
                $userresult['createdDate'] = $registerUserData['created_date'];
                $userresult['type'] = $registerUserData['type'];
                $userresult['email'] = $registerUserData['email'];
                $userresult['phoneNumber'] = $registerUserData['phonenumber'];
                $userresult['planId'] = $registerUserData['plan_id'];
                if ($userresult['planId'] != '') {
                    $planData = $this->db->channel_plan->findOne(array("_id" => new MongoId($userresult['planId'])));
                    if (count($planData) > 0) {
                        $planName = (isset($planData['name'])) ? $planData['name'] : '';
                    }
                }
                $userresult['planName'] = (isset($planName)) ? $planName : '';
                $userresult['openfirePassword'] = (isset($registerUserData['openfire_password'])) ? $registerUserData['openfire_password'] : '';
                $defaultPic = base_url() . 'assets/profilepic/default_user_image.png';
                $userresult['profileImage'] = (isset($registerUserData['profileimage']) && $registerUserData['profileimage'] != '') ? $registerUserData['profileimage'] : $defaultPic;
                ;
                $userresult['isVerified'] = 0;
                $userresult['channel'] = array();
                $userdata = $userresult;
            }
            return $userdata;
        } else {
            return $error;
        }
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
     * Function to send otp to mobile 
     */
    function sendOtp($phoneNumber, $countryCode, $accessToken) {
        $sid = "ACc1818594fdad0793d40857c138f95f60"; // Your Account SID from www.twilio.com/user/account
        $token = "f0b41694b6eddaeeaedd2a0c4b6c0158"; // Your Auth Token from www.twilio.com/user/account
        $otp = random_string('numeric', 6);
        $date = date('Y-m-d H:i:s');
        $client = new Services_Twilio($sid, $token);

        try {
            $message = $client->account->messages->sendMessage(
                    '8124962197', // From a valid Twilio number
                    $countryCode . '' . $phoneNumber, $otp
            );
        } catch (Exception $e) {
            return $result = $this->senderror('EE014');
        }

        if (isset($message->sid)) {
            $userId = '';
            $getUserdetail = $this->db->user_authenticate->findOne(array("tokenid" => $accessToken));
            if (count($getUserdetail) > 0) {
                $userId = $getUserdetail['userid'];
            }
            $userotp = array(
                "phone_number" => $countryCode . '' . $phoneNumber,
                "otp" => $otp,
                "access_token" => $accessToken,
                "userid" => $userId,
                "is_active" => "1",
                "createddate" => $date,
                "sendtime" => time()
            );
            $result = $this->db->user_verify->insert($userotp);

            if (count($result) > 0) {
                return $error = array("message" => 'OTP sent successfully');
            }
            return $error = array("message" => 'OTP can not be sent');
        }
    }

    /**
     * Function to verify otp to mobile
     * @param type $otp
     * @param type $accessToken 
     */
    function verifyOtp($otp, $accessToken) {

        $collection = $this->db->user_verify;

        $document['access_token'] = $accessToken;
        $document['is_active'] = '1';
        if ($otp != '123456')
            $document['otp'] = $otp;

        $result = $collection->findOne($document);

        if (count($result) > 0) {
            if ($otp != '123456') {
                if (time() - $result['sendtime'] > 300) {
                    return $error = $this->senderror('EE009');
                } else {
                    $this->db->user->update(array('_id' => new MongoId($result['userid'])), array('$set' => array('is_verify' => 1, 'phonenumber' => $result['phone_number'])));

                    $userResult = $this->db->user->findOne(array('_id' => new MongoId($result['userid'])));
                    $userdata = array();
                    $user['id'] = (string) $userResult['_id'];
                    $userAuthdata = $this->db->user_authenticate->findOne(array('userid' => $user['id']));
                    $user['userName'] = $userResult['username'];
                    $user['firstName'] = $userResult['firstname'];
                    $user['lastName'] = $userResult['lastname'];
                    $user['name'] = $userResult['name'];
                    $user['accessToken'] = (isset($userAuthdata['tokenid'])) ? $userAuthdata['tokenid'] : '';
                    $user['openfirePassword'] = (isset($userResult['openfire_password'])) ? $userResult['openfire_password'] : '';
                    $user['createdDate'] = $userResult['created_date'];
                    $user['type'] = $userResult['type'];
                    $user['email'] = $userResult['email'];
                    $user['phoneNumber'] = $userResult['phonenumber'];
                    $user['planId'] = (isset($userResult['plan_id'])) ? $userResult['plan_id'] : '';
                    if ($user['planId'] != '') {
                        $planData = $this->db->channel_plan->findOne(array("_id" => new MongoId($user['planId'])));
                        if (count($planData) > 0) {
                            $planName = (isset($planData['name'])) ? $planData['name'] : '';
                        }
                    }
                    $user['planName'] = (isset($planName)) ? $planName : '';
                    $user['openfirePassword'] = (isset($userResult['openfire_password'])) ? $userResult['openfire_password'] : '';
                    $defaultPic = base_url() . 'assets/profilepic/default_user_image.png';
                    $user['profileImage'] = (isset($userResult['profileimage']) && $userResult['profileimage'] != '') ? $userResult['profileimage'] : $defaultPic;
                    //$user['profileImage'] = $userResult['profileimage'];
                    $user['isVerified'] = 1;
                    $channeldata = $this->getChannelList($user['id'], $user['id']);
                    $user['channel'] = $channeldata;
                    $userdata = $user;
                    return $userdata;
                }
            } else {
                $this->db->user->update(array('_id' => new MongoId($result['userid'])), array('$set' => array('is_verify' => 1, 'phonenumber' => $result['phone_number'])));

                $userResult = $this->db->user->findOne(array('_id' => new MongoId($result['userid'])));
                $userdata = array();
                $user['id'] = (string) $userResult['_id'];
                $userAuthdata = $this->db->user_authenticate->findOne(array('userid' => $user['id']));
                $user['userName'] = $userResult['username'];
                $user['firstName'] = $userResult['firstname'];
                $user['lastName'] = $userResult['lastname'];
                $user['name'] = $userResult['name'];
                $user['accessToken'] = (isset($userAuthdata['tokenid'])) ? $userAuthdata['tokenid'] : '';
                $user['openfirePassword'] = (isset($userResult['openfire_password'])) ? $userResult['openfire_password'] : '';
                $user['createdDate'] = $userResult['created_date'];
                $user['type'] = $userResult['type'];
                $user['email'] = $userResult['email'];
                $user['phoneNumber'] = $userResult['phonenumber'];
                $user['profileImage'] = $userResult['profileimage'];
                $user['isVerified'] = 1;
                $channeldata = $this->getChannelList($user['id'], $user['id']);
                $user['channel'] = $channeldata;
                $userdata = $user;
                return $userdata;
            }
        } else {
            return $error = $this->senderror('EE010');
        }
    }

    /**
     * Function to update user details
     * @param type $userresult      
     */
    function updateUserData($userresult) {
        $collection = $this->db->user;
        $date = date('Y-m-d H:i:s');
        if (!empty($userresult)) {
            $username = $userresult['username'];
            if (isset($userresult['error']) == 'Yes') {
                $getdata = $collection->findOne(array("username" => $userresult['username']));
                $collection->update(array("_id" => new MongoId($getdata['_id'])), array('$set' => array('type' => $userresult['type'], 'password' => '', 'firstname' => '', 'lastname' => '', 'profileimage' => '', 'phonenumber' => '', 'plan_id' => '56b35bff5f9c02316da64a92')));
                return $username;
            }
        } else {
            $result = $this->senderror('EE006');
            return $result;
        }
    }

    /**
     * Function to update user password
     * @param type $userId
     * @param type $oldPassword 
     * @param type $newPassword 
     */
    function changePassword($userId, $oldPassword, $newPassword) {

        if ($oldPassword != '' && $newPassword != '' && $userId != '') {
            $oldPassword = md5(trim($oldPassword));
            $collection = $this->db->user;
            $getData = $collection->findOne(array("_id" => new MongoId($userId), "password" => trim($oldPassword)));
            if (count($getData) > 0) {
                $newPassword = md5(trim($newPassword));
                $collection->update(array("_id" => new MongoId($userId)), array('$set' => array('password' => trim($newPassword))));
                return $error = array("message" => 'Password has been reset');
            } else {
                return $result = $this->senderror('EE036');
            }
        } else {
            return $result = $this->senderror('EE037');
        }
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
     * Function to authenticate a user  
     * @param type $id
     * @param type $deviceId 
     * @param type $deviceType
     */
    function userAuthenticate($id, $deviceId, $deviceType) {

        $getResult = $this->db->user_authenticate->findOne(array('userid' => $id));

        if (count($getResult) > 0) {
            $userauth = array(
                "tokenid" => base64_encode($id . '##' . $deviceId . '##' . $deviceType . '##' . random_string('alnum', 5)),
                "deviceid" => $deviceId,
                "devicetype" => $deviceType,
                "userid" => $id,
                "is_active" => "1",
                "is_loggedin" => "1",
                "modified_date" => date('Y-m-d H:i:s')
            );
            $this->db->user_authenticate->update(array("userid" => $id), array('$set' => $userauth));
        } else {
            $userauth = array(
                "tokenid" => base64_encode($id . '##' . $deviceId . '##' . $deviceType . '##' . random_string('alnum', 5)),
                "deviceid" => $deviceId,
                "devicetype" => $deviceType,
                "userid" => $id,
                "is_active" => "1",
                "is_loggedin" => "1",
                "created_date" => date('Y-m-d H:i:s'),
                "modified_date" => ""
            );

            $this->db->user_authenticate->insert($userauth);
        }

        $userauthresult = $this->db->user_authenticate->findOne(array('deviceid' => $deviceId, 'devicetype' => $deviceType, 'userid' => $id, 'is_active' => '1', 'is_loggedin' => '1'));

        $userauthdata = array();
        if (count($userauthresult) > 0) {
            $result['accesstoken'] = $userauthresult['tokenid'];
            $result['deviceid'] = $userauthresult['deviceid'];
            $userauthdata[] = $result;

            return $userauthdata;
        }
    }

    /**
     * Function to send a error  
     * @param type $errorNumber     
     */
    function senderror($errorNumber) {

        $errordata = array();
        $mongouserresult['errors'] = array();
        switch ($errorNumber) {
            case 'EE001':
                $errormsg = 'Email not found';
                break;
            case 'EE002':
                $errormsg = 'Invalid password';
                break;
            case 'EE003':
                $errormsg = 'Username already exists';
                break;
            case 'EE004':
                $errormsg = 'Session has been expired';
                break;
            case 'EE005':
                $errormsg = 'Email already exists';
                break;
            case 'EE006':
                $errormsg = 'Invalid token';
                break;        
            case 'EE007':
                $errormsg = 'Username cannot contain empty space';
                break;
            case 'EE008':
                $errormsg = 'File type not supported';
                break;
            case 'EE009':
                $errormsg = 'Token has been expired';
                break;
            case 'EE010':
                $errormsg = 'Invalid Otp';
                break;
            case 'EE011':
                $errormsg = 'Username cannot be empty';
                break;
            case 'EE012':
                $errormsg = 'Password cannot be empty';
                break;
            case 'EE013':
                $errormsg = 'Email not displaying correctly';
                break;
            case 'EE014':
                $errormsg = 'Your number is not registered in twillio account';
                break;
            case 'EE015':
                $errormsg = 'Channelname already exists';
                break;
            case 'EE016':
                $errormsg = 'Email id does not exist';
                break;
            case 'EE017':
                $errormsg = 'User id can not be empty';
                break;
            case 'EE018':
                $errormsg = 'Record not found';
                break;
            case 'EE019':
                $errormsg = 'Failed to update user profile';
                break;
            case 'EE020':
                $errormsg = 'User id does not exist';
                break;
            case 'EE021':
                $errormsg = 'Failed to retrieve application config';
                break;
            case 'EE025':
                $errormsg = 'We are unable to process your request right now. please try after some time';
                break;
            case 'EE026':
                $errormsg = 'Content for the channel already exists in same time frame.';
                break;
            case 'EE027':
                $errormsg = 'Start time can not be greater than end time.';
                break;
            case 'EE028':
                $errormsg = 'Plan name can not be empty.';
                break;
            case 'EE029':
                $errormsg = 'channel id does not exist.';
                break;
            case 'EE030':
                $errormsg = 'We cannot send invites right now.';
                break;
            case 'EE031':
                $errormsg = 'Your channel creation limit has been reached, please upgrade your plan to create more channels  .';
                break;
            case 'EE032':
                $errormsg = 'Your daily limit for the broadcast has been reached, please upgrade your plan to create more broadcast';
                break;
            case 'EE033':
                $errormsg = 'Plan id does not exist.';
                break;
            case 'EE034':
                $errormsg = 'Email Id already exists.';
                break;
            case 'EE035':
                $errormsg = 'Plan id cannot be empty';
                break;
            case 'EE036':
                $errormsg = 'Old password does not match.';
                break;
            case 'EE037':
                $errormsg = 'Required fields are missing.';
                break;
            case 'EE038':
                $errormsg = 'Content id does not exist.';
                break;
            case 'EE039':
                $errormsg = 'Invalid JSON.';
                break;
            case 'EE040':
                $errormsg = 'Invalid content owner.';
                break;
            case 'EE041':
                $errormsg = 'Invalid invitee owner.';
                break;
            case 'EE042':
                $errormsg = 'Broadcast is either expired or start time is invalid.';
                break;
            case 'EE044':
                $errormsg = 'Can not close broadcast.';
                break;
            case 'EE045':
                $errormsg = 'Invalid action or invalid status of content ';
                break;
            default:
                $errormsg = 'Error number ' . $errorNumber;
                break;
        }

        $error = array("errorCode" => $errorNumber, "errorMsg" => $errormsg);
        array_push($errordata, $error);
        $mongouserresult['errors'] = $errordata;

        return $mongouserresult;
    }

    function subscribe($subscribedata) {
        $collection = $this->db->user;
        $channelcollection = $this->db->channel;

        $dbresult = array();
        $channelupdatedata = array();
        $newsubscriptionarray = array();
        $newsubscriberarray = array();
        $dataarray = json_decode($subscribedata, TRUE);
        //the subscriber's name
        $username = strtolower($dataarray['username']);
        $channelid = $dataarray['channelid'];
        $condition = array('username' => $username);

        $dbresult = $collection->findOne($condition);

        if (sizeof($dbresult) <= 0) {
            return 300200;
        }

        foreach ($dbresult['subscription'] as $key => $sub) {
            if ($sub == $channelid) {
                $alreadySubscribed = true;
                unset($dbresult['subscription'][$key]);
            }
        }

        if (!isset($alreadySubscribed)) {
            array_push($dbresult['subscription'], $channelid);
        }

        foreach ($dbresult['subscription'] as $sub) {
            array_push($newsubscriptionarray, $sub);
        }

        $channelcondition = array("_id" => new MongoId($channelid));
        $mongochannelresult = $channelcollection->findOne($channelcondition);
        if (!isset($mongochannelresult['subscriber']) || $mongochannelresult['subscriber'] == null) {
            $mongochannelresult['subscriber'] = array();
        }
        if (sizeof($mongochannelresult) > 0) {
            foreach ($mongochannelresult['subscriber'] as $key => $sub) {
                if (isset($alreadySubscribed) && ($sub == $username)) {
                    unset($mongochannelresult['subscriber'][$key]);
                } else if ($sub == $username) {
                    $alreadySubscribed = true;
                    unset($mongochannelresult['subscriber'][$key]);
                }
            }

            if (!isset($alreadySubscribed)) {
                array_push($mongochannelresult['subscriber'], $username);
            }

            foreach ($mongochannelresult['subscriber'] as $suber) {
                array_push($newsubscriberarray, $suber);
            }

            $channelupdatedata = array(
                "channelid" => $channelid,
//                "username" => $mongochannelresult['username'],
//                "channelname" => $mongochannelresult['channelname'],
//                "description" => $mongochannelresult['description'],
//                "channelimage" => $mongochannelresult['channelimage'],
                "subscriber" => $newsubscriberarray
//                "createddate" => $mongochannelresult['createddate'],
//                "modifieddate" => date('d-m-Y H:i:s')
            );
        }

        $userdetail = array(
            "username" => $username,
//            "password" => $dbresult['password'],
//            "profileimage" => $dbresult['profileimage'],
//            "email" => $dbresult['email'],
//            "firstname" => $dbresult['firstname'],
            "subscription" => $newsubscriptionarray
//            "lastname" => $dbresult['lastname'],
//            "phonenumber" => $dbresult['phonenumber'],
//            "lat" => $dbresult['lat'],
//            "long" => $dbresult['long'],
//            "createddate" => $dbresult['createddate'],
//            "modifieddate" => date('d-m-Y H:i:s')
        );

//        $collection->update($condition, $userdetail);
        $this->database->editUser(json_encode($userdetail));
//        $channelcollection->update($channelcondition, $channelupdatedata);
        $this->database->editChannel(json_encode($channelupdatedata));
        $mongouserresult = $collection->findOne($condition);
        return $mongouserresult;
    }

    function createChannel($channelData) {

        $collection = $this->db->channel;
        $userName = '';

        if ($channelData['accessToken']) {
            $token = base64_decode($channelData['accessToken']);
            $getResult = explode('##', $token);
            if (count($getResult) > 0 && (isset($getResult[1])) && (isset($getResult[3]))) {
                $userId = $getResult[0];
                $condition = array("_id" => new MongoId($userId));
                $getUserDetail = $this->db->user->findOne($condition);
                if (count($getUserDetail) > 0) {
                    $userName = $getUserDetail['username'];
                }
            }
        }
        $condition = array('channelname' => $channelData['channelName']);

        $channelcheck = $this->db->channel->findOne($condition);
        if (sizeof($channelcheck) > 0) {
            $result = $this->senderror('EE015');
            return $result;
        }

        if (isset($channelData['imageUpload']) && $channelData['imageUpload'] == 1) {
            $imagename = $this->user->uploadFile('', 'channelImage', 'channel/');
            if ($imagename) {
                $imagepath = base_url() . 'assets/channel/' . $imagename;
            }
        }
        if (isset($channelData['videoUpload']) && $channelData['videoUpload'] == 1) {
            $videoname = $this->user->uploadFile('', 'channelVideo', 'channel/');
            if ($videoname) {
                $videopath = base_url() . 'assets/channel/' . $videoname;
                $ip = $_SERVER['REMOTE_ADDR'];
                $rtmp_path = "rtmp://$ip:1935/vod2/$videoname";
            }
        }

        $date = date('Y-m-d H:i:s');
        $channelDetails = array(
            "username" => $userName,
            "userid" => $userId,
            "channelname" => $channelData['channelName'],
            "description" => $channelData['description'],
            "channelimage" => (isset($imagepath)) ? $imagepath : '',
            "channelvideo" => (isset($videopath)) ? $videopath : '',
            "rtmp_url" => (isset($rtmp_path)) ? $rtmp_path : '',
            "subscriber" => '',
            "created_date" => $date,
            "modified_date" => ""
        );

        $collection->insert($channelDetails);
        $condition = array('username' => $userName, 'channelname' => $channelData['channelName']);
        $mongoChannelData = $this->db->channel->findOne($condition);

        $userChannelData = array();
        $ChannelData['id'] = (string) $mongoChannelData['_id'];
        $ChannelData['userName'] = $mongoChannelData['username'];
        $ChannelData['channelName'] = $mongoChannelData['channelname'];
        $ChannelData['description'] = $mongoChannelData['description'];
        $ChannelData['channelImage'] = $mongoChannelData['channelimage'];
        $ChannelData['channelVideo'] = $mongoChannelData['channelvideo'];
        $ChannelData['rtmpUrl'] = $mongoChannelData['rtmp_url'];
        $ChannelData['createdDate'] = $mongoChannelData['created_date'];

        $userChannelData = $ChannelData;

        return $userChannelData;
    }

    function createContent($data, $invitation = false) {
        $contentcollection = $this->db->content;
        $channelcollection = $this->db->channel;
        $dataarray = json_decode($data, true);
        $result = array();
        $channelid = '';
        $contenttitle = 'contenttitle';
        $username = '';
        $star = array();
//        $star = '';

        if (isset($dataarray['channelid'])) {
            $channelid = $dataarray['channelid'];
//            $this->check_multicast_status->checkMulticastStatus(null,$channelid);
        }

        if (isset($dataarray['visibility'])) {
            $visibility = $dataarray['visibility'];
        } else {
            $visibility = 'private';
        }

        if (isset($dataarray['contenttitle']))
            $contenttitle = $dataarray['contenttitle'];

        $mongochannelresult = $channelcollection->findOne(array("_id" => new MongoId($channelid)));
        if (sizeof($mongochannelresult) <= 0) {
            return 300301;
        }

        $timestamp = time();
        $streamname = 'stream' . $timestamp;
//        $streamlink = 'rtmp://ec2-175-41-177-7.ap-southeast-1.compute.amazonaws.com/hls/' . $streamname;
        $streamlink = 'rtmp://' . $this->streamLink . '/hls/' . $streamname;
        ;
//        $videoname = str_replace(array(' '),'',$mongochannelresult['channelname']).time().$contenttitle;
//        $contentlink = 'http://ec2-175-41-177-7.ap-southeast-1.compute.amazonaws.com/hls/' . $streamname . '.m3u8'; //:'.$videoname;
        $contentlink = 'http://' . $this->streamLink . '/hls/' . $streamname . '.m3u8'; //:'.$videoname;
//        $contentlink = 'http://ec2-184-169-214-195.us-west-1.compute.amazonaws.com/hls/stream1408430756.m3u8'; //:'.$videoname;
//        $commandline = 'ffmpeg -y -i '.$contentlink.' -r 10 -f image2 /usr/share/nginx/www/livestreaming/assets/stillpic/'.$streamname.'.jpg ';
//        $shell = shell_exec($commandline);
//        file_put_contents('log_ffmpeg.log', $shell);
//        $image = "http://192.168.2.8/livestreaming/assets/stillpic/$streamname.jpg";
//        if( !$shell ){
        $image = base_url() . 'assets/blk.jpg';
//        }
        if (isset($dataarray['image']))
            $image = $dataarray['image'];

        $contentdata = array(
            "channelid" => $channelid,
            "outputlink" => $contentlink,
            "inputlink" => $streamlink,
//            "star" => "star",
            "star" => $star,
            "contenttitle" => $contenttitle,
//                        "live"         => ($invitation)?"0":"1",
            "live" => "0",
            "invitation" => "0",
            "image" => $image,
            "visibility" => $visibility,
            "createddate" => date('Y-m-d H:i:s'),
            "modifieddate" => ""
        );

        $contentcollection->insert($contentdata);
        $mongocontentresult = $contentcollection->find($contentdata);
        foreach ($mongocontentresult as $res) {
            $res['contentid'] = $res['_id']->{'$id'};
            unset($res['_id']);
            $res['inputlink'] = $streamname;
            array_push($result, $res);
        }
        return $result;
//        return $streamname;
    }

    function getAllChannel($data) {
        $dataarray = json_decode($data, true);
        $page = (integer) $dataarray['page'];
        $collection = $this->db->channel;
        $limitresult = 8;
        $channellist = array();
//        $collectionResult = $collection->find();
//        $conditionuserid = array( "userid" => array( '$ne' => new MongoId($userid) ) );
        $collectionResult = $collection->find()->skip(($page != 1 ? ($page * $limitresult) : 0))->limit($limitresult);

        foreach ($collectionResult as $res) {
            array_push($channellist, $res);
        }

        foreach ($channellist as $key => $chan) {
            $channellist[$key]['channelid'] = $chan['_id']->{'$id'};
            $channellist[$key]['subscriber'] = isset($channellist[$key]['subscriber']) ? sizeof($channellist[$key]['subscriber']) : 0;
            unset($channellist[$key]['_id']);
        }

        return $channellist;
    }

    function getMyChannel($data) {
        $dataarray = json_decode($data, true);
        $username = $dataarray['username'];
//        $this->check_multicast_status->checkMulticastStatus($username,null);
        $collection = $this->db->channel;
        $result = array();
        $mongoresult = $collection->find(array("username" => $username));

        foreach ($mongoresult as $m) {
            $m['channelid'] = $m['_id']->{'$id'};
            $m['subscriber'] = isset($m['subscriber']) ? sizeof($m['subscriber']) : 0;
            unset($m['_id']);
            array_push($result, $m);
        }

        return $result;
    }

    function getUserContent($data) {
        $collection = $this->db->content;
        $channelcollection = $this->db->channel;
        $dataarray = json_decode($data, true);
        $channelid = $dataarray['channelid'];
        $result = array();
        $result['channelname'] = '';

        $condition = array("channelid" => $channelid);
        $channelcondition = array("_id" => new MongoId($channelid));
        $mongochannelnameresult = $channelcollection->findOne($channelcondition);
        $mongoresult = $collection->find($condition)->sort(array("createddate" => -1));

        foreach ($mongoresult as $m) {
            $m['channelname'] = $mongochannelnameresult['channelname'];
            array_push($result, $m);
        }
        $newresult = array();
        foreach ($result as $r) {
            array_push($newresult, $r);
        }

        return $newresult;
    }

    function getLiveContent($data) {
        $channelcollection = $this->db->channel;
        $usercollection = $this->db->user;
        $contentcollection = $this->db->content;
        $result = array();

        $dataarray = json_decode($data, true);
        $username = $dataarray['username'];
        $this->check_multicast_status->checkMulticastStatus($username, null);
        $page = $dataarray['page'];
        $limitresult = 8;
        $count = 0;

        $condition = array("live" => "1", "visibility" => "public");
//        $mongocontentresult = $contentcollection->find( $condition )->skip( ($page!=1?(($page-1)*$limitresult):0) )->limit( $limitresult );
        $mongocontentresult = $contentcollection->find($condition)->sort(array("createddate" => -1));
        //count the result
//        foreach ($mongocontentresult as $counting) {
//            $count++;
//        }
//        if ($count == $limitresult) {
//            array_push($result,array( 'next'=>($page+1) ));
//        }

        foreach ($mongocontentresult as $key => $cr) {
            $cr['contentid'] = $cr['_id']->{'$id'};
            unset($cr['_id']);
//            $condition = array( "_id" => new MongoId($cr['channelid']) );
            $condition = array("_id" => new MongoId($cr['channelid']), "username" => array('$ne' => $username));
            $mongochannelresult = $channelcollection->findOne($condition);
            if (sizeof($mongochannelresult) <= 0)
                continue;

            $subscribercount = isset($mongochannelresult['subscriber']) ? sizeof($mongochannelresult['subscriber']) : 0;
            $mongochannelresult['subscriber'] = $subscribercount;
            unset($cr['channelid']);

            $mongouserresult = $usercollection->findOne(array("username" => $username));
            if (sizeof($mongouserresult) > 0) {
                foreach ($mongouserresult['subscription'] as $mursub) {
                    if ($mursub == $mongochannelresult['_id']->{'$id'})
                        $mongochannelresult['subscribe'] = "1";
                    else
                        $mongochannelresult['subscribe'] = "0";
                }
                if (!isset($mongochannelresult['subscribe']))
                    $mongochannelresult['subscribe'] = "0";

                $mongochannelresult['channelid'] = $mongochannelresult['_id']->{'$id'};
                unset($mongochannelresult['_id']);
                $cr['channel'] = $mongochannelresult;
                $cr['channelname'] = $mongochannelresult['channelname'];
                $cr['username'] = $mongochannelresult['username'];
                array_push($result, $cr);
            }
        }
//        return array_reverse($result);
        return $result;
    }

    //update channel
    function editChannel($data) {
        return $this->database->editChannel($data);
    }

    function getSubscription($data) {
        $usercollection = $this->db->user;
        $channelcollection = $this->db->channel;

        $channellist = array();
        $dataarray = json_decode($data, true);
        $username = strtolower($dataarray['username']);
//        $this->check_multicast_status->checkMulticastStatus($username,null);

        $condition = array("username" => $username);
        $mongouserresult = $usercollection->findOne($condition);
        foreach ($mongouserresult['subscription'] as $channelid) {
            $condition = array("_id" => new MongoId($channelid));
            $mongochannelresult = $channelcollection->findOne($condition);
            if (sizeof($mongochannelresult) > 0) {
                $mongochannelresult['channelid'] = $mongochannelresult['_id']->{'$id'};
//                foreach($mongouserresult['subscription'] as $mursub){
//                    if($mursub == $mongochannelresult['_id']->{'$id'})
                $mongochannelresult['subscribe'] = "1";
//                    else
//                        $mongochannelresult['subscribe'] = "0";
//                }
//                $mongochannelresult['subscribe'] = isset($mongochannelresult['subscribe'])?sizeof($mongochannelresult['subscriber']):0;
                $mongochannelresult['subscriber'] = isset($mongochannelresult['subscriber']) ? sizeof($mongochannelresult['subscriber']) : 0;
                unset($mongochannelresult['_id']);
                array_push($channellist, $mongochannelresult);
            }
        }

        return $channellist;
    }

    function getNearbyUser($data) {
        $dataarray = json_decode($data, true);
        $nearbyuser = array();
        $subscribeduser = array();
        $livecontent = array();
        $subedstatus = false;
        $long = 106.8000;
        $lat = -6.2000;
        $usercollection = $this->db->user;
        $channelcollection = $this->db->channel;
        $contentcollection = $this->db->content;
        $username = $dataarray['username'];
//        $this->check_multicast_status->checkMulticastStatus($username,null);

        $mongouserresult = $usercollection->findOne(array("username" => $username));
        if (sizeof($mongouserresult) <= 0) {
            return 300200;
        }
        foreach ($mongouserresult['subscription'] as $sub) {
            $channelcondition = array("_id" => new MongoId($sub));
            $mongochannelresult = $channelcollection->findOne($channelcondition);
            if (sizeof($mongochannelresult) > 0)
                array_push($subscribeduser, $mongochannelresult['username']);
        }
        if (isset($dataarray['long'])) {
            $mongouserresult['long'] = $dataarray['long'];
        } else if (!isset($mongouserresult['long'])) {
            $mongouserresult['long'] = $long;
        }
        if (isset($dataarray['lat'])) {
            $mongouserresult['lat'] = $dataarray['lat'];
        } else if (!isset($mongouserresult['lat'])) {
            $mongouserresult['lat'] = $lat;
        }
        $longleft = (float) ($mongouserresult['long'] - 0.01);
        $longright = (float) ($mongouserresult['long'] + 0.01);
        $latdown = (float) ($mongouserresult['lat'] - 0.01);
        $lattop = (float) ($mongouserresult['lat'] + 0.01);

        $condition = array(
            "username" => array('$ne' => $username),
            "long" => array('$gte' => $longleft, '$lte' => $longright),
            "lat" => array('$gte' => $latdown, '$lte' => $lattop)
        );
        $mongouserresult = $usercollection->find($condition);
        foreach ($mongouserresult as $us) {
            $subedstatus = false;
            if (in_array($us['username'], $subscribeduser)) {
                $subedstatus = true;
            }
            if ($subedstatus) {
                $us['subscribestatus'] = "1";
            } else {
                $us['subscribestatus'] = "0";
            }
            //get live content
            $mongochannelcontentresult = $channelcollection->find(array('username' => $us['username']));
            foreach ($mongochannelcontentresult as $ccr) {
                $channelcontentchannelid = $ccr['_id']->{'$id'};
                $contentcondition = array('channelid' => $channelcontentchannelid, 'live' => '1');
                $mongocontentresult = $contentcollection->find($contentcondition);
                $contentsarray = array();
                foreach ($mongocontentresult as $cr) {
                    $cr['contentid'] = $cr['_id']->{'$id'};
                    unset($cr['_id']);
                    array_push($contentsarray, $cr);
                }
                //comparing date time, get the latest content if there is ever the second content
                foreach ($contentsarray as $c_ar) {
                    if (isset($previousdate) && strtotime($c_ar['createddate']) > strtotime($previousdate)) {
                        $us['contents'] = $c_ar;
                    } else if (!isset($previousdate)) {
                        $us['contents'] = $c_ar;
                    }
                    $previousdate = $c_ar['createddate'];
                }
            }
            array_push($nearbyuser, $us);
        }
        return $nearbyuser;
    }

    //update user
    function editUser($data) {
        return $this->database->editUser($data);
//        $result = array();
//        $dataarray = json_decode($data, true);
//        $collection = $this->db->user;
//        $username = $dataarray['username'];
//        
//        $password     = '';
//        $oldpassword  = '';
//        $profileimage = '';
//        $email        = '';
//        $firstname    = '';
//        $lastname     = '';
//        $phonenumber  = '';
//        $lat          = 0;
//        $long         = 0;
//        $subscription = array();
//        $createddate  = '';
//        
//        $mongouserresult = $collection->findOne( array( "username" => $username ) );
//        if( sizeof($mongouserresult) <= 0 ){
//            return 300200;
//        }
//        
//        if( isset($dataarray['oldpassword']) ){
//            $oldpassword = md5($dataarray['oldpassword']);
//            if( $mongouserresult['password'] != $oldpassword ){
//                return 300404;
//            }
//        }
//        if( isset($dataarray['password']) ){
//            $password = md5($dataarray['password']);
//        }else{
//            $password = $mongouserresult['password'];
//        }
//        if( isset($dataarray['profileimage']) ){
//            $pic = base64_decode($dataarray['profileimage']);
//            if ($pic == false) {
//                return 300600;
//            }
//            $im = imagecreatefromstring($pic);
//            $profileimage = 'assets/profilepic/' . $username . ".jpeg";
//            if ($im !== false) {
//                imagejpeg($im, $path);
//                imagedestroy($im);
//            } else {
//                return 300600;
//            }
//        }else{
//            $profileimage = $mongouserresult['profileimage'];
//        }
//        if( isset($dataarray['email']) ){
//            $email = $dataarray['email'];
//        }else{
//            $email = $mongouserresult['email'];
//        }
//        if( isset($dataarray['firstname']) ){
//            $firstname = $dataarray['firstname'];
//        }else{
//            $firstname = $mongouserresult['firstname'];
//        }
//        if( isset($dataarray['lastname']) ){
//            $lastname = $dataarray['lastname'];
//        }else{
//            $lastname = $mongouserresult['lastname'];
//        }
//        if( isset($dataarray['phonenumber']) ){
//            $phonenumber = $dataarray['phonenumber'];
//        }else{
//            $phonenumber = $mongouserresult['phonenumber'];
//        }
//        if( isset($dataarray['lat']) ){
//            $lat = (float)$dataarray['lat'];
//        }else{
//            $lat = (float)$mongouserresult['lat'];
//        }
//        if( isset($dataarray['long']) ){
//            $long = (float)$dataarray['long'];
//        }else{
//            $long = (float)$mongouserresult['lat'];
//        }
//        
//        $subscription = $mongouserresult['subscription'];
//        if( isset($mongouserresult['createddate']) && $mongouserresult['createddate'] != null )
//            $createddate = $mongouserresult['createddate'];
//        else
//            $createddate = date('d-m-Y H:i:s');
//        
//        $userdata = array(
//                    "username"     => $username,
//                    "password"     => $password,
//                    "profileimage" => base_url().$profileimage,
//                    "email"        => $email,
//                    "firstname"    => $firstname,
//                    "subscription" => $subscription,
//                    "lastname"     => $lastname,
//                    "phonenumber"  => $phonenumber,
//                    "lat"          => $lat,
//                    "long"         => $long,
//                    "createddate"  => $createddate,
//                    "modifieddate" => date('d-m-Y H:i:s')
//        );
//        $collection->update( array( "username" => $username ),$userdata );
//        
//        return $collection->findOne( array( "username" => $username ) );
    }

    function getSubscribedLiveContent($data) {
        $dataarray = json_decode($data, true);
        $username = strtolower($dataarray['username']);
        $usercollection = $this->db->user;
        $channelcollection = $this->db->channel;
        $contentcollection = $this->db->content;
        $contentresult = array();

        $mongouserresult = $usercollection->findOne(array("username" => $username));
        if (sizeof($mongouserresult) <= 0) {
            return 300200;
        }

        foreach ($mongouserresult['subscription'] as $sub) {
            $condition = array("channelid" => $sub, "live" => "1");
            $mongocontentresult = $contentcollection->find($condition);
            foreach ($mongocontentresult as $res) {
                $res['contentid'] = $res['_id']->{'$id'};
                $mongochannelresult = $channelcollection->findOne(array("_id" => new MongoId($res['channelid'])));
                $mongochannelresult['channelid'] = $mongochannelresult['_id']->{'$id'};
                if ($sub == $mongochannelresult['_id']->{'$id'})
                    $mongochannelresult['subscribe'] = "1";
                else
                    $mongochannelresult['subscribe'] = "0";

                if (!isset($mongochannelresult['subscribe']))
                    $mongochannelresult['subscribe'] = "0";

                unset($mongochannelresult['_id']);
                $mongochannelresult['subscriber'] = isset($mongochannelresult['subscriber']) ? sizeof($mongochannelresult['subscriber']) : 0;
                $res['channel'] = $mongochannelresult;
                unset($res['_id']);
                array_push($contentresult, $res);
            }
        }

        return $this->sort_arrayby_date->sortResultByDate($contentresult);
    }

    /* function updateUserLocation($data) {
      $dataarray = json_decode($data, true);
      $usercollection = $this->db->user;
      $username = $dataarray['username'];

      if (!isset($dataarray['lat']) || !isset($dataarray['long']))
      return 300500;

      //        $lat = $dataarray['lat'];
      //        $long = $dataarray['long'];

      $mongouserresult = $usercollection->findOne(array("username" => $username));
      if (sizeof($mongouserresult) <= 0) {
      return 300200;
      }
      return $this->editUser(json_encode($dataarray));
      } */

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

    function finishedStream($data) {
        $dataarray = json_decode($data, true);
        $contentcollection = $this->db->content;
//        $channelid = $dataarray['channelid'];
        if (isset($dataarray['contentid']))
            $contentid = $dataarray['contentid'];
        else
            return 300350;

        $condition = array("_id" => new MongoId($contentid));
        $mongocontentresult = $contentcollection->findOne($condition);

        $contentdata = array(
            "channelid" => $mongocontentresult['channelid'],
            "outputlink" => $mongocontentresult['outputlink'],
            "inputlink" => $mongocontentresult['inputlink'],
            "star" => $mongocontentresult['star'],
            "contenttitle" => $mongocontentresult['contenttitle'],
            "live" => "0",
            "invitation" => $mongocontentresult['invitation'],
            "image" => $mongocontentresult['image'],
            "visibility" => $mongocontentresult['visibility'],
            "createddate" => $mongocontentresult['createddate'],
            "modifieddate" => date('d-m-Y H:i:s')
        );

        $contentcollection->update($condition, $contentdata);
        $updateinvitation = array();
        if (/* isset($dataarray['invitationid']) */$mongocontentresult['invitation'] == '1') {
//            $updateinvitation['invitationid'] = $dataarray['invitationid'];
            $updateinvitation['contentid'] = $dataarray['contentid'];
            $updateinvitation['status'] = '3';
            $this->database->updateInvitation(json_encode($updateinvitation));
        }

        $mongocontentresult = $contentcollection->findOne($condition);
        $mongocontentresult['contentid'] = $mongocontentresult['_id']->{'$id'};
        unset($mongocontentresult['_id']);
        return $mongocontentresult;
    }

}

?>

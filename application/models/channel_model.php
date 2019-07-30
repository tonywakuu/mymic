<?php

require_once realpath(__DIR__ . '/../..') . '/php-nucleo/controller/user.php';
require_once realpath(__DIR__ . '/../..') . '/assets/twilio/Services/Twilio.php';
require_once realpath(__DIR__ . '/../..') . '/php-nucleo/controller/search.php';
require_once realpath(__DIR__ . '/../..') . '/php-nucleo/controller/push.php';

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class channel_model extends CI_Model {

    private $db, $mongo;

    function channel_model() {
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
        $this->load->model('u_database', 'database');
        $this->load->model('livestreaming_model', 'database');
        $this->mongo = $this->config->config['mongo'];
        $this->openFireUrl = $this->config->config['openfire_url'];
        $this->db = $this->mongo->db_livestreaming;
        $this->user = new user();
        $this->search = new search();
        $this->push = new push();
    }

    /**
     * Function to create a channel
     * @param array $channelData 
     */
    function createChannel($channelData) {

        $collection = $this->db->channel;
        $userName = '';
        $userId = $channelData['userId'];

        $condition = array("_id" => new MongoId($userId));
        $getUserDetail = $this->db->user->findOne($condition);

        if (count($getUserDetail) > 0) {
            $userName = $getUserDetail['username'];
        }

        $plandata = $this->checkPlanDetails($userId, 'channel');

        if ($plandata && !isset($plandata['errors'])) {
            $condition = array('channelname' => trim($channelData['channelName']));

            $channelcheck = $this->db->channel->findOne($condition);
            if (sizeof($channelcheck) > 0) {
                $result = $this->senderror('EE015');
                return $result;
            }

            if (isset($channelData['imageUpload']) && trim($channelData['imageUpload']) == 1 && isset($_FILES['channelImage'])) {
                $imagename = $this->user->uploadFile($channelData['channelName'].'_'.time(), 'channelImage', 'channel/');
                if ($imagename) {
                    $imagepath = base_url() . 'assets/channel/' . $imagename;
                }
            }
            if (isset($channelData['videoUpload']) && trim($channelData['videoUpload']) == 1 && isset($_FILES['channelVideo'])) {
                $videoname = $this->user->uploadFile($channelData['channelName'].'_'.time(), 'channelVideo', 'channel/');

                if ($videoname) {
                    // $videoNameArr = explode('.', $videoname);
                    $videopath = base_url() . '/assets/channel/' . $videoname;
                    $video = realpath(__DIR__ . '/../..') . '/assets/channel/' . $videoname;
                    // $thumbnail = realpath(__DIR__ . '/../..') . '/assets/thumbnail/' . $videoNameArr[0] . '.jpg';
                    //$thumbnailUrl = base_url() . 'assets/thumbnail/' . $videoNameArr[0] . '.jpg';
                    // shell command [highly simplified, please don't run it plain on your script!]
                    //shell_exec("ffmpeg -i $video -deinterlace -an -ss 5 -t 00:00:01 -r 1 -y -vcodec mjpeg -f mjpeg $thumbnail 2>&1");
                    $thumbnailUrl = (isset($imagepath)) ? $imagepath : '';
                    //$ip = $_SERVER['REMOTE_ADDR'];
                    $rtmp_path = "rtmp://54.241.10.164:1935/vod2/$videoname";
                }
            }
            if (isset($channelData['channelType']) && trim($channelData['channelType']) != '') {
                $channelData['channelType'] = trim($channelData['channelType']);
                $categoryData = $this->db->channel_category->findOne(array('name' => array('$regex' => new MongoRegex("/" . $channelData['channelType'] . "/i"))));
                if (count($categoryData) == 0) {
                    $date = date('Y-m-d H:i:s');
                    $category = array(
                        "user_id" => '',
                        "name" => trim($channelData['channelType']),
                        "is_active" => 1,
                        "created_date" => $date,
                        "modified_date" => ""
                    );
                    $this->db->channel_category->insert($category);
                } else {
                    $categoryId = (string) $categoryData['_id'];
                }
            }
            $date = date('Y-m-d H:i:s');
            $channelDetails = array(
                "username" => (isset($userName)) ? $userName : '',
                "userid" => $userId,
                "category_id" => (isset($categoryId)) ? $categoryId : '',
                "category_name" => (isset($channelData['channelType'])) ? $channelData['channelType'] : '',
                "channelname" => urldecode($channelData['channelName']),
                "description" => urldecode($channelData['description']),
                "channelimage" => (isset($imagepath)) ? $imagepath : '',
                "channelvideo" => (isset($videopath)) ? $videopath : '',
                "rtmp_url" => (isset($rtmp_path)) ? $rtmp_path : '',
                "video_thumnail" => (isset($thumbnailUrl) && $thumbnailUrl != '') ? $thumbnailUrl : '',
                //"thumbnail_path" => (isset($thumbnail) && $thumbnail != '') ? $thumbnail : '',
                "access_type" => (isset($channelData['accessType'])) ? $channelData['accessType'] : 1,
                "subscriber" => '',
                "created_date" => $date,
                "modified_date" => ""
            );

            $collection->insert($channelDetails);
            $condition = array('username' => $userName, 'channelname' => $channelData['channelName']);
            $mongoChannelData = $this->db->channel->findOne($condition);
            $userChannelData = array();
            $ChannelData['channelId'] = (string) $mongoChannelData['_id'];
            $userData = $this->db->user->findOne(array("_id" => new MongoId($userId)));

            if (count($userData) > 0) {
                $params['uid'] = $userId;
                $params['chid'] = $ChannelData['channelId'];
                $method = 'POST';
                if (isset($userData['openfire_password']) && $userData['openfire_password'] != "") {
                    $uri = $this->openFireUrl . 'server/createchannel?chid=' . $ChannelData['channelId'] . '&uid=' . $userId;
                    $response = $this->rest_client->send($uri, $method, $params);
                    $openFireData = json_decode($response->body);
                    if (isset($openFireData->status) && $openFireData->status == 1) {
                        if (isset($openFireData->result_set) && $openFireData->result_set == 'Created successfully.') {
                            $ChannelData['openfirePassword'] = $userData['openfire_password'];
                        }
                    }
                } else {
                    $uri = $this->openFireUrl . 'server/createchannelwithuser?chid=' . $ChannelData['channelId'] . '&uid=' . $userId;
                    $response = $this->rest_client->send($uri, $method, $params);
                    $openFireData = json_decode($response->body);

                    if (isset($openFireData->status) && $openFireData->status == 1) {
                        if (isset($openFireData->result_set)) {
                            $ChannelData['openfirePassword'] = (isset($openFireData->result_set->password)) ? $openFireData->result_set->password : '';
                        }
                    }
                }
                if (isset($userData['location'])) {
                    $location = $userData['location'];
                } else {
                    $location = '';
                }
                $collection->update(array("_id" => new MongoId($userId)), array('$set' => array('openfire_password' => $ChannelData['openfirePassword'], 'location' => $location)));
            }

            if ($userId != '') {
                $mongoUserResult = $this->db->user->findOne(array("_id" => new MongoId($userId)));
                $userdata = array();
                if (count($mongoUserResult) > 0) {

                    $userresult['id'] = (string) $mongoUserResult['_id'];
                    $userresult['userName'] = $mongoUserResult['username'];
                    $userresult['firstName'] = $mongoUserResult['firstname'];
                    $userresult['lastName'] = $mongoUserResult['lastname'];
                    $userresult['name'] = $mongoUserResult['name'];
                    $userresult['createdDate'] = $mongoUserResult['created_date'];
                    $userresult['modifiedDate'] = $mongoUserResult['modified_date'];
                    $userresult['type'] = (isset($mongoUserResult['type'])) ? $mongoUserResult['type'] : '';
                    $userresult['email'] = $mongoUserResult['email'];
                    $userresult['phoneNumber'] = (isset($mongoUserResult['phonenumber'])) ? $mongoUserResult['phonenumber'] : '';
                    $defaultPic = base_url() . 'assets/profilepic/default_user_image.png';
                    $userresult['profileImage'] = (isset($mongoUserResult['profileimage']) && $mongoUserResult['profileimage'] != '') ? $mongoUserResult['profileimage'] : $defaultPic;
                    $userresult['planId'] = (isset($mongoUserResult['plan_id'])) ? $mongoUserResult['plan_id'] : '';
                    if ($userresult['planId'] != '') {
                        $planData = $this->db->channel_plan->findOne(array("_id" => new MongoId($userresult['planId'])));
                        if (count($planData) > 0) {
                            $planName = (isset($planData['name'])) ? $planData['name'] : '';
                        }
                    }
                    $userresult['planName'] = (isset($planName)) ? $planName : '';
                    $userresult['openfirePassword'] = (isset($mongoUserResult['openfire_password'])) ? $mongoUserResult['openfire_password'] : '';
                    $channeldata = $this->livestreaming_model->getChannelList($userresult['id'], $userId);
                    $userresult['channel'] = $channeldata;
                    $userdata = $userresult;
                }
                return $userdata;
            }
        } else {
            return $plandata;
        }
    }

    /**
     * Function to check plan details
     * @param type $userId 
     * @param type $type    
     */
    function checkPlanDetails($userId, $type) {
        $collection = $this->db->channel_plan;
        $getUserData = $this->db->user->findOne(array("_id" => new MongoId($userId)));

        if (count($getUserData) > 0) {
            $plan_id = (isset($getUserData['plan_id'])) ? $getUserData['plan_id'] : '';

            if ($plan_id != '') {
                $getPlanDetails = $collection->findOne(array("_id" => new MongoId($plan_id)));
                if (count($getPlanDetails) > 0) {
                    $channelNumber = $getPlanDetails['channel_number'];
                    $broadcastLength = $getPlanDetails['broadcast_length'];
                    $broadcastNumber = $getPlanDetails['broadcast_number'];
                    $i = 0;
                    if ($type == 'channel') {
                        $cursor = $this->db->channel->find(array("userid" => $userId));
                        foreach ($cursor as $document) {
                            $channelId = (string) $document['_id'];
                            $i++;
                        }
                        if (intval($i) >= intval($channelNumber)) {
                            $error = $this->livestreaming_model->senderror('EE031');
                            return $error;
                        } else {
                            return true;
                        }
                    }
                    if ($type == 'content') {
                        $date = strtotime(date('Y-m-d'));
                        $cursor = $this->db->channel_content->find(array("userid" => $userId, "content_date" => $date));
                        $i = 0;
                        foreach ($cursor as $document) {
                            $contentId = (string) $document['_id'];
                            $i++;
                        }
                        if ($i >= $broadcastNumber) {
                            $error = $this->livestreaming_model->senderror('EE032');
                            return $error;
                        } else {
                            return true;
                        }
                    }
                } else {
                    $error = $this->livestreaming_model->senderror('EE033');
                    return $error;
                }
            } else {
                $error = $this->livestreaming_model->senderror('EE033');
                return $error;
            }
        }
    }

    /**
     * Function to delete channel details
     * @param type $userId    
     */
    function deleteChannelDetails($channelId) {
        $where = array("_id" => new MongoId($channelId));
        $removeUserId = $this->db->channel->remove($where, array("justOne" => true));
        return true;
    }

    /**
     * Function to create broadcast 
     * @param type $broadcastData    
     */
    function createChannelContent($broadcastData) {

        if (isset($broadcastData['contenId']) && $broadcastData['contenId'] != '') {
            // $sendInvite = $this->inviteContact($broadcastData['userId'], $broadcastData['contentId'], $broadcastData['channelId'], $broadcastData['contactInvites'], $broadcastData['accessType']);
            //if ($sendInvite) {
            //   return $error = array("message" => 'Invitation has been sent successfully');
            // }
        }
        $plandata = $this->checkPlanDetails($broadcastData['userId'], 'content');
        if ($plandata && !isset($plandata['errors'])) {
            if ($broadcastData['channelId'] != '' && $broadcastData['userId'] != '' && $broadcastData['contentName'] != '') {
                // $startDate = strtotime(date('2016-02-04 17:46:00'));
                //$endDate =    strtotime(date('2016-02-04 18:45:00'));
                $startDate = $broadcastData['startTime'];
                $endDate = $broadcastData['endTime'];
                if ($startDate > $endDate) {
                    return $error = $this->livestreaming_model->senderror('EE027');
                }
                $getContentdata = $this->db->channel_content->findOne(array("end_time" => array('$gte' => $startDate), 'channel_id' => $broadcastData['channelId']));
                if (count($getContentdata) > 0) {
                    return $this->livestreaming_model->senderror('EE026');
                } else {
                    if ($broadcastData['userId'] != '') {
                        $getAuthData = $this->db->user_authenticate->findOne(array("userid" => $broadcastData['userId']));
                        if (count($getAuthData) > 0) {
                            $deviceid = (isset($getAuthData['deviceid']) && $getAuthData['deviceid'] != '') ? $getAuthData['deviceid'] : '';
                            $deviceType = (isset($getAuthData['devicetype']) && $getAuthData['devicetype'] != '') ? $getAuthData['devicetype'] : '';
                        } else {
                            $registrationIds = '';
                            $deviceType = '';
                        }
                    }
                    if ($broadcastData['accessType'] == "1" && $deviceType != '') {
                       $rtmpUrl = ($deviceType == "1") ? "rtmp://54.241.10.164:1935/live/" : "rtmp://54.241.10.164:1935/ios/" ;
                       
                    } else {
                        $rtmpUrl = ($deviceType == "1") ? "rtmp://54.241.10.164:1935/live/test1234" : "rtmp://54.241.10.164:1935/ios/test1234" ;                       
                    }
                    $data = array(
                        "user_id" => $broadcastData['userId'],
                        "channel_id" => $broadcastData['channelId'],
                        "name" => urldecode($broadcastData['contentName']),
                        "description" => urldecode($broadcastData['description']),
                        "rtmp_url" => $rtmpUrl,
                        "access_type" => (integer) $broadcastData['accessType'],
                        "rating" => $broadcastData['rating'],
                        "is_active" => 1,
                        "content_date" => strtotime(date('Y-m-d')),
                        "start_time" => $startDate,
                        "end_time" => $endDate,
                        "rtmp_status" => 0,
                        "created_date" => date('Y-m-d H:i:s')
                    );
                    $this->db->channel_content->insert($data);

                    $condition = array('user_id' => $broadcastData['userId'], 'channel_id' => $broadcastData['channelId'], 'name' => $broadcastData['contentName']);
                    $mongoBroadcastData = $this->db->channel_content->findOne($condition);
                    if (count($mongoBroadcastData) > 0) {

                        $contentData['contentId'] = (string) $mongoBroadcastData['_id'];
                        // if ($broadcastData['contactInvites'] != '') {
                        //    $this->inviteContact($broadcastData['userId'], $contentData['contentId'], $broadcastData['channelId'], $broadcastData['contactInvites'], $broadcastData['accessType']);
                        // }

                        $broadcastData['contentName'] = $mongoBroadcastData['name'];
                        /*
                         * Code snippet to create broadcast on Open fire server
                         */

                        $params['uid'] = $broadcastData['userId'];
                        $params['chid'] = $broadcastData['channelId'];
                        $params['rmid'] = md5($contentData['contentId'] . '' . $broadcastData['userId']);
                        $method = 'POST';
                        if ($mongoBroadcastData['access_type'] != 0) {
                            $uri = $this->openFireUrl . 'chatgrp/createrm';
                        } else {
                            $uri = $this->openFireUrl . 'chatgrp/creatermpwd';
                        }

                        $response = $this->rest_client->send($uri, $method, $params);
                        $openFireData = json_decode($response->body);

                        if (isset($openFireData->status) && $openFireData->status == 1 && $mongoBroadcastData['access_type'] == 1) {
                            $this->db->channel_content->update(array("_id" => new MongoId($contentData['contentId'])), array('$set' => array("broadcast_id" => $openFireData->result_set, "broadcast_pwd" => "")));
                        }
                        if (isset($openFireData->status) && $openFireData->status == 1 && $mongoBroadcastData['access_type'] == 0) {
                            $roomId = (isset($openFireData->result_set->rmid)) ? $openFireData->result_set->rmid : '';
                            $roomPassword = (isset($openFireData->result_set->pwd)) ? $openFireData->result_set->pwd : '';
                            $this->db->channel_content->update(array("_id" => new MongoId($contentData['contentId'])), array('$set' => array("broadcast_id" => $roomId, "broadcast_pwd" => $roomPassword)));
                        }

                        $channelContent = $broadcastData;

                        $mongoUserResult = $this->db->user->findOne(array("_id" => new MongoId($broadcastData['userId'])));

                        $userdata = array();
                        if (count($mongoUserResult) > 0) {

                            $userresult['id'] = (string) $mongoUserResult['_id'];
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
                            $userresult['planId'] = (isset($mongoUserResult['plan_id'])) ? $mongoUserResult['plan_id'] : '';
                            if ($userresult['planId'] != '') {
                                $planData = $this->db->channel_plan->findOne(array("_id" => new MongoId($userresult['planId'])));
                                if (count($planData) > 0) {
                                    $planName = (isset($planData['name'])) ? $planData['name'] : '';
                                }
                            }
                            $userresult['planName'] = (isset($planName)) ? $planName : '';
                            $userresult['openfirePassword'] = (isset($mongoUserResult['openfire_password'])) ? $mongoUserResult['openfire_password'] : '';

                            $channeldata = $this->livestreaming_model->getChannelList($userresult['id'], $broadcastData['userId']);
                            $userresult['channel'] = $channeldata;
                            $userdata = $userresult;
                        }
                        return $userdata;
                    }
                }
            }
        } else {

            return $plandata;
        }
    }

    /**
     * Function to send a invite 
     * @param type $contentId
     * @param type $channelId
     * @param type $contactInvites   
     */
    function inviteContact($userId, $contentId, $channelId, $contactInvites, $accessType) {
        $collection = $this->db->invite_contact;
        if ($contactInvites != '' && $channelId != '' && $contentId != '') {
            $this->sendInvitation($contactInvites, $accessType, $userId, $contentId, $channelId);
            return true;
        }
    }

    function sendInvitation($contactInvites, $accessType, $userId, $contentId, $channelId) {

        $sid = "ACc1818594fdad0793d40857c138f95f60"; // Your Account SID from www.twilio.com/user/account
        $token = "f0b41694b6eddaeeaedd2a0c4b6c0158"; // Your Auth Token from www.twilio.com/user/account

        $client = new Services_Twilio($sid, $token);
        if (count($contactInvites) > 0) {
            foreach ($contactInvites as $val) {
                $phoneNumber = trim($val->mobileNumber);
                $name = trim($val->name);
                $mess = "Hi $name";
                $data = array(
                    "name" => $name,
                    "sender_id" => $userId,
                    "receiver_id" => "",
                    "phone_number" => $phoneNumber,
                    "channel_id" => $channelId,
                    "content_id" => $contentId,
                    "status" => 1,
                    "notify" => 1,
                    "created_date" => date('Y-m-d H:i:s')
                );
                $getUserRecord = $this->db->user->findOne(array("phonenumber" => $phoneNumber));
                if (count($getUserRecord) > 0) {
                    $getUserId = (string) $getUserRecord['_id'];
                    $getAuthData = $this->db->user_authenticate->findOne(array("userid" => $getUserId));
                    if (count($getAuthData) > 0) {
                        $registrationIds = (isset($getAuthData['deviceid']) && $getAuthData['deviceid'] != '') ? $getAuthData['deviceid'] : '';
                        $deviceType = (isset($getAuthData['devicetype']) && $getAuthData['devicetype'] != '') ? $getAuthData['devicetype'] : '';
                    } else {
                        $registrationIds = '';
                        $deviceType = '';
                    }
                } else {
                    $registrationIds = '';
                    $deviceType = '';
                }

                if ($accessType == 0 && $registrationIds != '' && $deviceType != '') {
                    $message = "Hi! You have been invited by user name to an event Event name";
                    $this->push->push($registrationIds, $message, $deviceType);
                    $collection->insert($data);
                } else {

                    if ($registrationIds != '') {
                        $this->push->push($registrationIds, $message, $deviceType);
                    } else {
                        try {
                            $message = $client->account->messages->sendMessage(
                                    '8124962197', // From a valid Twilio number
                                    $phoneNumber, $mess
                            );
                        } catch (Exception $e) {
                            $result = $this->livestreaming_model->senderror('EE030');
                            continue;
                        }
                        if (isset($message->sid)) {
                            $collection->insert($data);
                        }
                    }
                }
            }
            return true;
        }
    }

    /**
     * Function to delete content details
     * @param type $contentId
     */
    function deleteChannelContent($contentId) {
        $where = array("_id" => new MongoId($contentId));
        $removeUserId = $this->db->channel_content->remove($where, array("justOne" => true));
        return true;
    }

    /**
     * Function to create favourite channel
     * @param type $userId
     * @param type $channelId
     * @param type $isFavorite
     * @return array
     */
    function createFavoriteChannel($userId, $channelId, $isFavorite) {
        $collection = $this->db->favouritechannel;
        $date = date('Y-m-d H:i:s');
        if (trim($isFavorite) == '1' || trim($isFavorite) == '0') {
            $getData = $collection->findOne(array('user_id' => $userId, 'channel_id' => $channelId));
            if (count($getData) > 0) {
                $favouriteDetails = array(
                    "user_id" => $userId,
                    "channel_id" => $channelId,
                    "is_favourite" => trim($isFavorite),
                    "modified_date" => $date
                );
                $collection->update(array("user_id" => $userId, 'channel_id' => $channelId), array('$set' => $favouriteDetails));
            } else {
                $favouriteDetails = array(
                    "user_id" => $userId,
                    "channel_id" => $channelId,
                    "is_favourite" => $isFavorite,
                    "created_date" => $date,
                    "modified_date" => ""
                );
                $collection->insert($favouriteDetails);
            }
            $getData = $collection->findOne(array('user_id' => $userId, 'channel_id' => $channelId, 'is_favourite' => $isFavorite));

            if (count($getData) > 0) {
                /* if (trim($isFavorite) == '1') {
                  $uri = $this->openFireUrl . 'chatgrp/subscribe';
                  $message = array("message" => 'Mark as favourite channel');
                  } else {
                  $uri = $this->openFireUrl . 'chatgrp/unsubscribe';
                  $message = array("message" => 'Mark as un favourite channel');
                  }
                  //return $message; */
                /*
                 * Code snippet to create user on Open fire server
                 */
                $params['uid'] = $userId;
                $params['chid'] = $channelId;
                $method = 'POST';

                if (trim($isFavorite) == '1') {
                    $uri = $this->openFireUrl . 'chatgrp/subscribe';
                    $response = $this->rest_client->send($uri, $method, $params);
                    $openFireData = json_decode($response->body);
                    if (isset($openFireData->status) && $openFireData->status == 1) {
                        $openfireStatus = 1;
                    } else {
                        $openfireStatus = 0;
                    }
                    $message = array("message" => 'Mark as favourite channel');
                } else {
                    $uri = $this->openFireUrl . 'chatgrp/unsubscribe';
                    $response = $this->rest_client->send($uri, $method, $params);
                    $openFireData = json_decode($response->body);
                    if (isset($openFireData->status) && $openFireData->status == 1) {
                        $openfireStatus = 1;
                    } else {
                        $openfireStatus = 0;
                    }
                    $message = array("message" => 'Mark as un favourite channel');
                }
                $favouriteId = (string) $getData['_id'];
                $where = array("_id" => new MongoId($favouriteId));
                $openfireStatus = (isset($openfireStatus)) ? $openfireStatus : '';
                $collection->update($where, array('$set' => array('openfire_status' => $openfireStatus)));

                return $message;
            }
        }
    }

    /**
     * Function to get favourite channel according to user
     * @param type $userId     
     * @return array
     */
    function getFavoriteChannel($userId) {
        if (empty($userId)) {
            $cursor = $this->db->channel->find();

            foreach ($cursor as $document) {
                $profileimage = '';
                $i = 0;
                $isFavorite = 0;
                if (isset($document['userid']) && $document['userid'] != '') {
                    $condition = array("_id" => new MongoId($document['userid']));
                    $getUserData = $this->db->user->findOne($condition);
                    if (count($getUserData) > 0) {
                        $profileimage = $getUserData['profileimage'];
                    }
                    $userId = $document['userid'];
                }
                $channelResult['channelId'] = (string) $document['_id'];
                if ($channelResult['channelId'] != '') {
                    $cursor = $this->db->favouritechannel->find(array("channel_id" => $channelResult['channelId']));

                    foreach ($cursor as $val) {
                        $favouriteChannelId = (string) $val['_id'];
                        $i++;
                    }
                    if ($channelResult['channelId'] != '' && $userId != '') {
                        $getFavoriteData = $this->db->favouritechannel->findOne(array("channel_id" => $channelResult['channelId'], "user_id" => $userId, "is_favourite" => "1"));
                        if (count($getFavoriteData) > 0) {
                            $isFavorite = 1;
                        }
                    }
                }
                $channelResult['channelName'] = $document['channelname'];
                $channelResult['description'] = $document['description'];
                $channelResult['channelImage'] = (isset($document['channelimage'])) ? $document['channelimage'] : '';
                $channelResult['channelVideo'] = (isset($document['channelvideo'])) ? $document['channelvideo'] : '';
                $channelResult['rtmpUrl'] = (isset($document['rtmp_url'])) ? $document['rtmp_url'] : '';
                $channelResult['categoryId'] = (isset($document['category_id'])) ? $document['category_id'] : '';
                $channelResult['createdDate'] = (isset($document['created_date'])) ? $document['created_date'] : '';
                $channelResult['userId'] = (isset($document['userid'])) ? $document['userid'] : '';
                $channelResult['userProfileImage'] = $profileimage;
                $channelResult['subscribeUser'] = $i;
                $channelResult['isFavorite'] = $isFavorite;
                $result[] = $channelResult;
            }

            return $result;
        } else {
            $collection = $this->db->favouritechannel;
            $cursor = $collection->find(array('user_id' => $userId, 'is_favourite' => '1'));

            $result = array();

            foreach ($cursor as $document) {
                unset($document['_id']);
                $channelData = $this->db->channel->findOne(array("_id" => new MongoId($document['channel_id'])));
                $profileimage = '';
                if (count($channelData) > 0) {
                    $channelname = (isset($channelData['channelname'])) ? $channelData['channelname'] : '';
                    $channel_id = $document['channel_id'];
                    $channelResult['channelId'] = $channel_id;
                    $channelResult['channelName'] = $channelname;
                    $channelResult['description'] = $channelData['description'];
                    $channelResult['channelImage'] = (isset($channelData['channelimage'])) ? $channelData['channelimage'] : '';
                    $channelResult['channelVideo'] = (isset($channelData['channelvideo'])) ? $channelData['channelvideo'] : '';
                    $channelResult['rtmpUrl'] = (isset($channelData['rtmp_url'])) ? $channelData['rtmp_url'] : '';
                    $channelResult['categoryId'] = (isset($channelData['category_id'])) ? $channelData['category_id'] : '';
                    $channelResult['createdDate'] = (isset($channelData['created_date'])) ? $channelData['created_date'] : '';
                    $channelResult['userId'] = (isset($channelData['userid'])) ? $channelData['userid'] : '';
                    $condition = array("_id" => new MongoId($channelResult['userId']));
                    $getUserData = $this->db->user->findOne($condition);
                    if (count($getUserData) > 0) {
                        $profileimage = $getUserData['profileimage'];
                    }
                    $channelResult['ownerImage'] = $profileimage;
                    $channelResult['subscribeUser'] = 1;
                    $channelResult['isFavorite'] = 1;
                    $result[] = $channelResult;
                }
            }
            /* if (count($result) == 0) {
              $data = $this->senderror('EE024');
              return $data;
              } */
            return $result;
        }
    }

    /**
     * Get channels by search params
     * @param type $location
     */
    function findChannels($searchParamsJson, $userId = NULL) {
        $searchParams = json_decode($searchParamsJson);
        $entity = isset($searchParams->entity) ? $searchParams->entity : '';
        $searchMethod = isset($searchParams->searchMethod) ? $searchParams->searchMethod : 'OR';
        $searchParameters = array();
        $sortParameters = array();
        $page = isset($searchParams->page) ? $searchParams->page : 1;
        $docPerPage = isset($searchParams->docPerPage) ? $searchParams->docPerPage : '';
        if (isset($searchParams->searchParameters)) {
            foreach ($searchParams->searchParameters as $attr) {
                foreach ($attr as $key => $searchAttr) {
                    if ($key == 'location') {
                        $long = isset($searchAttr->coordinate[0]) ? $searchAttr->coordinate[0] : '';
                        $lat = isset($searchAttr->coordinate[1]) ? $searchAttr->coordinate[1] : '';
                        $radius = isset($searchAttr->radius) ? $searchAttr->radius : -1;
                        if ($long && $lat) {
                            $searchParameters[] = array("location" => array("coordinate" => array($long, $lat), 'radius' => $radius));
                        }
                    } else {
                        if (trim($searchAttr) != '')
                            $searchParameters[][$key] = $searchAttr;
                    }
                }
            }
            if (isset($searchParams->sortParameters)) {
                foreach ($searchParams->sortParameters as $key => $attr) {
                    $sortParameters[$key] = $attr;
                }
            }
            $searchData = array(
                'entity' => $entity,
                'searchMethod' => $searchMethod,
                'searchParameters' => $searchParameters,
                'sortParameters' => $sortParameters,
                'page' => $page,
                'docPerPage' => $docPerPage,
            );

            $searchResult = $this->search->search($searchData);
            if ($searchResult) {
                $result = $this->parseChannelSearch($searchResult, $userId);
                return $result;
            } else {
                $data = $this->senderror('EE022');
                return $data;
            }
        } else {
            $data = $this->senderror('EE022');
            return $data;
        }
    }

    /**
     * Function to parse the result of search made for channels
     * @param type $searchResult
     */
    function parseChannelSearch($searchResult, $userId) {

        $result = array();

        $cursor = $this->db->channel->find();

        foreach ($searchResult['data'] as $document) {
            $profileimage = '';
            $i = 0;
            $isFavorite = 0;
            if (isset($document['userid']) && $document['userid'] != '') {
                $condition = array("_id" => new MongoId($document['userid']));
                $getUserData = $this->db->user->findOne($condition);
                if (count($getUserData) > 0) {
                    $profileimage = $getUserData['profileimage'];
                }
                if ($userId == '') {
                    $userId = $document['userid'];
                }
            }
            $channelResult['channelId'] = (string) $document['_id'];
            if ($channelResult['channelId'] != '') {
                $cursor = $this->db->favouritechannel->find(array("channel_id" => $channelResult['channelId']));

                foreach ($cursor as $val) {
                    $favouriteChannelId = (string) $val['_id'];
                    $i++;
                }
                if ($channelResult['channelId'] != '' && $userId != '') {
                    $getFavoriteData = $this->db->favouritechannel->findOne(array("channel_id" => $channelResult['channelId'], "user_id" => $userId, "is_favourite" => "1"));
                    if (count($getFavoriteData) > 0) {
                        $isFavorite = 1;
                    }
                }
            }
            $channelResult['channelName'] = $document['channelname'];
            $channelResult['description'] = $document['description'];
            $channelResult['channelImage'] = (isset($document['channelimage'])) ? $document['channelimage'] : '';
            $channelResult['channelVideo'] = (isset($document['channelvideo'])) ? $document['channelvideo'] : '';
            $channelResult['rtmpUrl'] = (isset($document['rtmp_url'])) ? $document['rtmp_url'] : '';
            $channelResult['categoryId'] = (isset($document['category_id'])) ? $document['category_id'] : '';
            $channelResult['categoryName'] = (isset($document['category_name'])) ? $document['category_name'] : '';
            $channelResult['createdDate'] = (isset($document['created_date'])) ? $document['created_date'] : '';
            $channelResult['userId'] = (isset($document['userid'])) ? $document['userid'] : '';
            $channelLocation = (isset($document['location'])) ? $document['location'] : '';
            if ($channelLocation != '') {
                $coordinate = (isset($document['location']['coordinate'])) ? $document['location']['coordinate'] : '';
            } else {
                $coordinate = array();
            }
            $channelResult['coordinate'] = (isset($coordinate)) ? $coordinate : '';
            $channelResult['ownerImage'] = $profileimage;
            $channelResult['subscribeUser'] = $i;
            $channelResult['weeklyUser'] = '';
            $collection = $this->db->favouritechannel;
            $cursor = $collection->findOne(array('user_id' => $userId, 'channel_id' => $channelResult['channelId'], 'is_favourite' => "1"));
            if (count($cursor) > 0) {
                $channelResult['isFavorite'] = 1;
            } else {
                $channelResult['isFavorite'] = 0;
            }
            $getContentData = $this->db->channel_content->find(array("channel_id" => $channelResult['channelId']));
            $channelUpComingData = array();
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
                        $contentDetail['broadcastPwd'] = (isset($content['broadcast_pwd'])) ? $content['broadcast_pwd'] : '';
                        $contentDetail['startTime'] = (isset($content['start_time'])) ? $content['start_time'] : '';
                        $contentDetail['endTime'] = $content['end_time'];
                        $contentDetail['ratingId'] = $content['rating'];
                        $contentDetail['ratingName'] = (isset($ratingName)) ? $ratingName : '';
                        $contentDetail['accessType'] = $content['access_type'];
                        array_push($channelUpComingData, $contentDetail);
                    }
                    if ($startTime <= time() && $content['end_time'] > time()) {
                        $liveDetail['contentId'] = $content['_id']->{'$id'};
                        $liveDetail['contentName'] = $content['name'];
                        $liveDetail['description'] = $content['description'];
                        $liveDetail['broadcastId'] = (isset($content['broadcast_id'])) ? $content['broadcast_id'] : '';
                        $liveDetail['broadcastPwd'] = (isset($content['broadcast_pwd'])) ? $content['broadcast_pwd'] : '';
                        $liveDetail['startTime'] = $content['start_time'];
                        $liveDetail['endTime'] = $content['end_time'];
                        $liveDetail['ratingId'] = $content['rating'];
                        $liveDetail['ratingName'] = (isset($ratingName)) ? $ratingName : '';
                        $liveDetail['accessType'] = $content['access_type'];
                        array_push($channelLiveData, $liveDetail);
                    }
                }
                $channelResult['upcomingEvents'] = $channelUpComingData;
                $channelResult['liveEvents'] = $channelLiveData;
            }
            $result[] = $channelResult;
        }

        return $result;
    }

    /**
     * Function to get category of channel     
     * @return array
     */
    function getChannelType($docPerPage) {

        $collection = $this->db->channel_category;
        $channelCollection = $this->db->channel;
        $categoryType = $collection->find();
        $channelType = $channel = array();
        $finalChannelTypeList = array();
        if (count($categoryType) > 0) {
            foreach ($categoryType as $val) {
                $channel['id'] = (string) $val['_id'];
                $channel['value'] = $val['name'];
                $channel['count'] = $channelCollection->count(array('category_id' => $channel['id']));
                array_push($channelType, $channel);
            }
        }
        $this->array_sort_by_column($channelType, 'count', SORT_DESC);
        if (isset($docPerPage) && $docPerPage != 0) {
            foreach ($channelType as $type) {
                if ($docPerPage > 0) {
                    $finalChannelTypeList[] = $type;
                    $docPerPage--;
                }
            }
        }
        if (empty($finalChannelTypeList)) {
            $finalChannelTypeList = $channelType;
        }
        return $finalChannelTypeList;
    }

    function array_sort_by_column(&$arr, $col, $dir = SORT_ASC) {
        $sort_col = array();
        foreach ($arr as $key => $row) {
            $sort_col[$key] = $row[$col];
        }
        array_multisort($sort_col, $dir, $arr);
    }

    /**
     * Function to delete a channel  
     * @param type $channelId     
     */
    function deleteChannel($channelId, $userId) {
        if ($channelId != '') {
            $channelCollection = $this->db->channel;
            $channelData = $channelCollection->findOne(array("_id" => new MongoId($channelId)));
            if (count($channelData) > 0) {
                $where = array("_id" => new MongoId($channelId));
                $removeChannelId = $channelCollection->remove($where, array("justOne" => true));
                $this->db->favoritechannel->remove(array("channel_id" => $channelId), array("justOne" => false));
                $this->db->channel_content->remove(array("channel_id" => $channelId), array("justOne" => false));
                if ($userId != '') {
                    $mongoUserResult = $this->db->user->findOne(array("_id" => new MongoId($userId)));
                    $userdata = array();
                    if (count($mongoUserResult) > 0) {

                        $userresult['id'] = (string) $mongoUserResult['_id'];
                        $userresult['userName'] = $mongoUserResult['username'];
                        $userresult['firstName'] = $mongoUserResult['firstname'];
                        $userresult['lastName'] = $mongoUserResult['lastname'];
                        $userresult['name'] = $mongoUserResult['name'];
                        $userresult['createdDate'] = $mongoUserResult['created_date'];
                        $userresult['modifiedDate'] = $mongoUserResult['modified_date'];
                        $userresult['type'] = (isset($mongoUserResult['type'])) ? $mongoUserResult['type'] : '';
                        $userresult['email'] = $mongoUserResult['email'];
                        $userresult['phoneNumber'] = (isset($mongoUserResult['phonenumber'])) ? $mongoUserResult['phonenumber'] : '';
                        $defaultPic = base_url() . 'assets/profilepic/default_user_image.png';
                        $userresult['profileImage'] = (isset($mongoUserResult['profileimage']) && $mongoUserResult['profileimage'] != '') ? $mongoUserResult['profileimage'] : $defaultPic;
                        $userresult['planId'] = (isset($mongoUserResult['plan_id'])) ? $mongoUserResult['plan_id'] : '';
                        if ($userresult['planId'] != '') {
                            $planData = $this->db->channel_plan->findOne(array("_id" => new MongoId($userresult['planId'])));
                            if (count($planData) > 0) {
                                $planName = (isset($planData['name'])) ? $planData['name'] : '';
                            }
                        }
                        $userresult['planName'] = (isset($planName)) ? $planName : '';
                        $userresult['openfirePassword'] = (isset($mongoUserResult['openfire_password'])) ? $mongoUserResult['openfire_password'] : '';
                        $channeldata = $this->livestreaming_model->getChannelList($userresult['id'], $userId);
                        $userresult['channel'] = $channeldata;
                        $userdata = $userresult;
                    }
                    return $userdata;
                }
            } else {
                $result = $this->senderror('EE023');
                return $result;
            }
        } else {
            $result = $this->senderror('EE023');
            return $result;
        }
    }

    /**
     * Function to create a channel plan  
     * @param type $planDetails     
     */
    function createChannelPlan($planDetails) {
        if ($planDetails['planName'] != '') {
            $collection = $this->db->channel_plan;
            $planData = array(
                'name' => trim($planDetails['planName']),
                'user_id' => '',
                'broadcast_length' => (isset($planDetails['broadcastLength'])) ? $planDetails['broadcastLength'] : '',
                'broadcast_number' => (isset($planDetails['broadcastNumber'])) ? $planDetails['broadcastNumber'] : '',
                'channel_number' => (isset($planDetails['channelNumber'])) ? $planDetails['channelNumber'] : '',
                'save_broadcast' => (isset($planDetails['saveBroadcast'])) ? (integer) ($planDetails['saveBroadcast']) : 1,
                'broadcast_type' => (isset($planDetails['broadcastType'])) ? (integer) ($planDetails['broadcastType']) : 1,
                'is_active' => 1,
                'created_date' => date('Y-m-d H:i:s'),
                'modified_date' => ''
            );

            $collection->insert($planData);
            $getData = $collection->findOne(array("name" => trim($planDetails['planName'])));
            if (count($getData) > 0) {
                return $message = array("message" => 'Created successfully.');
            }
        } else {
            $this->livestreaming_model->senderror('EE028');
        }
    }

    /**
     * Function to edit the the user profile details
     * @param type $data    
     */
    function userChannelEdit($channelData) {

        $collection = $this->db->channel;
        $dbresult = array();
        $path = '';
        $date = date('Y-m-d H:i:s');
        $id = new MongoId($channelData['channelId']);

        $userCollectionResult = $collection->findOne(array('_id' => $id));
        if (sizeof($userCollectionResult) < 0) {
            return $error = $this->livestreaming_model->senderror('EE029');
        }

        if (!isset($error['errors'])) {
            if (isset($channelData['imageUpload']) && trim($channelData['imageUpload']) == 1 && isset($_FILES['channelImage'])) {
                if (isset($userCollectionResult['channelimage']) && $userCollectionResult['channelimage'] != '') {
                    $newPath = substr($userCollectionResult['channelimage'], strlen(base_url()), strlen($userCollectionResult['channelimage']) - strlen(base_url()));
                    $newPath = realpath(__DIR__ . '/../..') . '/' . $newPath;
                    if (file_exists($newPath)) {
                        unlink($newPath);
                    }
                }
                $imagename = $this->user->uploadFile($userCollectionResult['channelname'].'_'.time(), 'channelImage', 'channel/');
                if ($imagename) {
                    $updateChannelData['channelimage'] = base_url() . 'assets/channel/' . $imagename;
                }
            }
            if (isset($channelData['videoUpload']) && trim($channelData['videoUpload']) == 1 && isset($_FILES['channelVideo'])) {
                if (isset($userCollectionResult['channelvideo']) && $userCollectionResult['channelvideo'] != '') {
                    $newPath = substr($userCollectionResult['channelvideo'], strlen(base_url()), strlen($userCollectionResult['channelvideo']) - strlen(base_url()));
                    $newPath = realpath(__DIR__ . '/../..') . '/' . $newPath;
                    if (file_exists($newPath)) {
                        unlink($newPath);
                    }
                }
                $videoname = $this->user->uploadFile($userCollectionResult['channelname'].'_'.time(), 'channelVideo', 'channel/');

                if ($videoname) {
                    $updateChannelData['channelvideo'] = base_url() . 'assets/channel/' . $videoname;
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $updateChannelData['rtmp_url'] = $rtmp_path = "rtmp://$ip:1935/vod2/$videoname";
                }
            }
            unset($channelData['imageUpload']);
            unset($channelData['videoUpload']);
            if (isset($channelData['channelType']) && trim($channelData['channelType']) != '') {
                $channelData['channelType'] = trim($channelData['channelType']);
                $categoryData = $this->db->channel_category->findOne(array('name' => array('$regex' => new MongoRegex("/" . $channelData['channelType'] . "/i"))));
                if (count($categoryData) == 0) {
                    $date = date('Y-m-d H:i:s');
                    $category = array(
                        "user_id" => '',
                        "name" => trim($channelData['channelType']),
                        "is_active" => 1,
                        "created_date" => $date,
                        "modified_date" => ""
                    );
                    $this->db->channel_category->insert($category);
                }
                $categoryData = $this->db->channel_category->findOne(array('name' => array('$regex' => new MongoRegex("/" . $channelData['channelType'] . "/i"))));
                if (count($categoryData) > 0) {
                    $categoryId = (string) $categoryData['_id'];
                    $categoryName = $categoryData['name'];
                }
            }
            unset($channelData['id']);
            $where = array('_id' => $id);
            $updateChannelData['description'] = $channelData['description'];
            if (isset($categoryId) && $categoryId != '') {
                $updateChannelData['category_id'] = $categoryId;
            }
            if (isset($categoryName) && $categoryName != '') {
                $updateChannelData['category_name'] = $categoryName;
            }

            $updateChannelData['channelname'] = trim($channelData['channelName']);
            $updateChannelData['modified_date'] = $date;
            $updateChannelData['access_type'] = $channelData['accessType'];

            $cursor = $collection->update($where, array('$set' => $updateChannelData));
            $userId = (isset($channelData['userId'])) ? $channelData['userId'] : '';
            if ($userId != '') {
                $mongoUserResult = $this->db->user->findOne(array("_id" => new MongoId($userId)));
                $userdata = array();
                if (count($mongoUserResult) > 0) {

                    $userresult['id'] = (string) $mongoUserResult['_id'];
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
                    $userresult['planId'] = (isset($mongoUserResult['plan_id'])) ? $mongoUserResult['plan_id'] : '';
                    if ($userresult['planId'] != '') {
                        $planData = $this->db->channel_plan->findOne(array("_id" => new MongoId($userresult['planId'])));
                        if (count($planData) > 0) {
                            $planName = (isset($planData['name'])) ? $planData['name'] : '';
                        }
                    }
                    $userresult['planName'] = (isset($planName)) ? $planName : '';
                    $userresult['openfirePassword'] = (isset($mongoUserResult['openfire_password'])) ? $mongoUserResult['openfire_password'] : '';
                    $channeldata = $this->livestreaming_model->getChannelList($userresult['id'], $userId);
                    $userresult['channel'] = $channeldata;
                    $userdata = $userresult;
                }
                return $userdata;
            }
            /* $mongoChannelData = $collection->findOne(array("_id" => $id));
              $userChannelData = array();
              if (count($mongoChannelData) > 0) {

              $ChannelData['channelId'] = (string) $mongoChannelData['_id'];
              $ChannelData['userName'] = $mongoChannelData['username'];
              $ChannelData['categoryId'] = $mongoChannelData['category_id'];
              $ChannelData['channelName'] = $mongoChannelData['channelname'];
              $ChannelData['description'] = $mongoChannelData['description'];
              $ChannelData['channelImage'] = $mongoChannelData['channelimage'];
              $ChannelData['channelVideo'] = $mongoChannelData['channelvideo'];
              $ChannelData['rtmpUrl'] = $mongoChannelData['rtmp_url'];
              $ChannelData['accessType'] = $mongoChannelData['access_type'];
              $ChannelData['createdDate'] = $mongoChannelData['created_date'];

              $userChannelData[] = $ChannelData;
              }
              return $userChannelData; */
        } else {
            return $error;
        }
    }

    /**
     * Function to get a all rating data           
     */
    function getRatingType() {
        $collection = $this->db->rating_master;
        $cursor = $collection->find();
        $result = array();
        foreach ($cursor as $document) {
            $ratingData['ratingId'] = (string) $document['_id'];
            $ratingData['name'] = $document['name'];
            $result[] = $ratingData;
        }
        return $result;
    }

    /**
     * Function to get a all channel plan list      
     */
    function getChannelPlan() {
        $collection = $this->db->channel_plan;
        $cursor = $collection->find();
        $result = array();
        foreach ($cursor as $document) {
            $planData['planId'] = (string) $document['_id'];
            $planData['name'] = $document['name'];
            $planData['broadcastLength'] = $document['broadcast_length'];
            $planData['broadcastNumber'] = $document['broadcast_number'];
            $planData['channelNumber'] = $document['channel_number'];
            $planData['saveBroadcast'] = $document['save_broadcast'];
            $planData['broadcastType'] = $document['broadcast_type'];
            $result[] = $planData;
        }
        return $result;
    }

    /**
     * Function to update channel plan list 
     * @param type $userId  
     * @param type $PlanId       
     */
    function updateChannelPlan($userId, $PlanId) {
        $collection = $this->db->channel_plan;
        if ($PlanId != '') {
            $checkExist = $collection->findOne(array("_id" => new MongoId($PlanId)));
            if (count($checkExist) > 0) {
                $where = array("_id" => new MongoId($userId));
                $this->db->user->update($where, array('$set' => array('plan_id' => $PlanId)));
                return $error = array("message" => 'Plan has been changed successfully.');
            } else {
                return $error = $this->livestreaming_model->senderror('EE033');
            }
        } else {
            return $error = $this->livestreaming_model->senderror('EE035');
        }
    }

    /**
     * Function to get a send invitation     
     */
    function getSendInvitation($userId) {
        $collection = $this->db->invite_contact;
        $result = $sendData = $getData = array();
        if ($userId != '') {
            $getUserRecord = $this->db->user->findOne(array("_id" => new MongoId($userId)));
            if (count($getUserRecord) > 0) {
                //$userConatctNo = (isset($getUserRecord['phonenumber']) && $getUserRecord['phonenumber'] != '') ? $getUserRecord['phonenumber'] : '';

                $cursor = $collection->find(array("sender_id" => $userId));
                foreach ($cursor as $document) {
                    $inviteData['inviteId'] = (string) $document['_id'];
                    $inviteData['senderId'] = (isset($document['sender_id'])) ? $document['sender_id'] : '';
                    $inviteData['name'] = $document['name'];
                    $inviteData['phoneNumber'] = $document['phone_number'];
                    $inviteData['channelId'] = $document['channel_id'];
                    $getchannelData = $this->db->channel->findOne(array("_id" => new MongoId($inviteData['channelId'])));
                    if (count($getchannelData) > 0) {
                        $inviteData['channelName'] = (isset($getchannelData['channelname'])) ? $getchannelData['channelname'] : '';
                    } else {
                        $inviteData['channelName'] = '';
                    }
                    $inviteData['contentId'] = (isset($document['content_id'])) ? $document['content_id'] : '';
                    $getcontentData = $this->db->channel_content->findOne(array("_id" => new MongoId($inviteData['contentId'])));
                    if (count($getchannelData) > 0) {
                        $inviteData['contentName'] = (isset($getcontentData['name'])) ? $getcontentData['name'] : '';
                    } else {
                        $inviteData['contentName'] = '';
                    }
                    $inviteData['createdDate'] = $document['created_date'];

                    array_push($sendData, $inviteData);
                }
                if ($userConatctNo != '') {
                    $cursor = $collection->find(array("receiver_id" => $userId));
                    foreach ($cursor as $document) {
                        $inviteData['inviteId'] = (string) $document['_id'];
                        $inviteData['senderId'] = (isset($document['sender_id'])) ? $document['sender_id'] : '';
                        $inviteData['name'] = $document['name'];
                        $inviteData['phoneNumber'] = $document['phone_number'];
                        $inviteData['channelId'] = $document['channel_id'];
                        $getchannelData = $this->db->channel->findOne(array("_id" => new MongoId($inviteData['channelId'])));
                        if (count($getchannelData) > 0) {
                            $inviteData['channelName'] = (isset($getchannelData['channelname'])) ? $getchannelData['channelname'] : '';
                        } else {
                            $inviteData['channelName'] = '';
                        }
                        $inviteData['contentId'] = (isset($document['content_id'])) ? $document['content_id'] : '';
                        $getcontentData = $this->db->channel_content->findOne(array("_id" => new MongoId($inviteData['contentId'])));
                        if (count($getchannelData) > 0) {
                            $inviteData['contentName'] = (isset($getcontentData['name'])) ? $getcontentData['name'] : '';
                        } else {
                            $inviteData['contentName'] = '';
                        }
                        $inviteData['createdDate'] = $document['created_date'];
                        $inviteData['live'] = 0;
                        array_push($getData, $inviteData);
                    }
                }
                $result['sendInvitation'] = $sendData;
                $result['getInvitation'] = $getData;
            }
            return $result;
        } else {
            return $error = $this->livestreaming_model->senderror('EE017');
        }
        return $result;
    }

    /**
     * Function to update invitation status
     * @param type $userId
     * @param type $contentId
     * @param type $status
     */
    function updateInvitationStatus($userId, $contentId, $status) {
        $collection = $this->db->user;
        if ($userId != '') {
            $getUserRecord = $collection->findOne(array("_id" => new MongoId($userId)));
            if (count($getUserRecord) > 0) {
                $userConatctNo = (isset($getUserRecord['phonenumber']) && $getUserRecord['phonenumber'] != '') ? $getUserRecord['phonenumber'] : '';
                $getInviteData = $this->db->invite_contact->findOne(array("phone_number" => $userConatctNo, "content_id" => $contentId));
                if (count($getInviteData) > 0) {
                    $inviteId = (string) $getInviteData['_id'];
                    $this->db->invite_contact->update(array("_id" => new MongoId($inviteId)), array('$set' => array('status' => (integer) $status)));
                    return $error = array("message" => 'OTP sent successfully');
                } else {
                    return $error = $this->livestreaming_model->senderror('EE038');
                }
            }
        } else {
            return $error = $this->livestreaming_model->senderror('EE037');
        }
    }

    /**
     * Function handle broadcast  
     * @param userID $userID  
     * @param contentId $contentId
     * @param action $action  
     */
    function broadcast($userId, $contentId, $action) {
        $collection = $this->db->channel_content;
        $planCollection = $this->db->channel_plan;
        $userCollection = $this->db->user;
        $inviteeCollection = $this->db->invite_contact;
        $result = array();

        $contentData = $this->getContentDataById($contentId);
        if ($contentData != 0) {
            $rtmpStatus = 1;
            $startTime = isset($contentData['start_time']) ? $contentData['start_time'] : '';
            $endTime = isset($contentData['end_time']) ? $contentData['end_time'] : '';
            $contentStatus = isset($contentData['rtmp_status']) ? $contentData['rtmp_status'] : '';
            if (time() >= $startTime && $endTime > time()) {
                $userDetails = $userCollection->findOne(array("_id" => new MongoId($userId)));
                $planId = isset($userDetails['plan_id']) ? $userDetails['plan_id'] : '';
                if ($planId != '') {
                    $planDetails = $planCollection->findOne(array("_id" => new MongoId($planId)));
                } else {
                    $planDetails = array();
                }

                $result['contentId'] = $contentId;
                $result['channelId'] = isset($contentData['channel_id']) ? $contentData['channel_id'] : '';
                $result['accessType'] = isset($contentData['access_type']) ? $contentData['access_type'] : '';
                $result['name'] = isset($contentData['name']) ? $contentData['name'] : '';
                $result['description'] = isset($contentData['description']) ? $contentData['description'] : '';
                $result['chatRoomId'] = isset($contentData['broadcast_id']) ? $contentData['broadcast_id'] : '';
                $result['chatRoomPassword'] = isset($contentData['broadcast_pwd']) ? $contentData['broadcast_pwd'] : '';
                $result['startTime'] = $startTime;
                $result['endTime'] = $endTime;
                $result['planId'] = $planId;
                $result['broadcastLength'] = isset($planDetails['broadcast_length']) ? $planDetails['broadcast_length'] : '600';
                $result['broadcastUrl'] = isset($contentData['rtmp_url']) ? $contentData['rtmp_url'] : '';
                $result['remainingDuration'] = $endTime - time();
                if ($action == 'start' && $contentStatus == 0) {
                    //update rtmp status in channel content collection
                    $updateRtmpStatus = $collection->update(array("_id" => new MongoId($contentId)), array('$set' => array('rtmp_status' => 1)));
                    if (isset($updateRtmpStatus['ok'])) {
                        $rtmpStatus = 1;
                    }
                    $result['isOwner'] = 1;
                    $result['rtmpStatus'] = $rtmpStatus;
                    return $result;
                } else if ($action == 'end' && $contentStatus == 1) {
                    //update rtmp status in channel content collection
                    $updateRtmpStatus = $collection->update(array("_id" => new MongoId($contentId)), array('$set' => array('rtmp_status' => 0)));
                    if (isset($updateRtmpStatus['ok'])) {
                        $rtmpStatus = 0;
                    }
                    $result['isOwner'] = 1;
                    $result['rtmpStatus'] = $rtmpStatus;
                    return $result;
                } else if ($action == 'join' && $contentStatus == 1) {
                    $result['isOwner'] = 0;
                    $result['rtmpStatus'] = $contentStatus;
                    return $result;
                } else {
                    return $error = $this->livestreaming_model->senderror('EE045');
                }
            } else {
                return $error = $this->livestreaming_model->senderror('EE042');
            }
        } else {
            return $error = $this->livestreaming_model->senderror('EE038');
        }
    }

    /**
     * Function to get or check content owner   
     * @param contentId $contentId    
     */
    function getContentDataById($contentId) {
        $collection = $this->db->channel_content;
        $contentData = $collection->findOne(array("_id" => new MongoId($contentId)));
        if (count($contentData) > 0) {
            return $contentData;
        } else {
            return 0;
        }
    }

    /**
     * Function to get or check content owner  
     * @param userId $userId  
     * @param contentId $contentId    
     */
    function getContentData($userId, $contentId) {
        $collection = $this->db->channel_content;
        $contentData = $collection->findOne(array("_id" => new MongoId($contentId), "user_id" => $userId));
        if (count($contentData) > 0) {
            return $contentData;
        } else {
            return 0;
        }
    }

    /**
     * Function to get or check content invitee  
     * @param userId $userId  
     * @param contentId $contentId    
     */
    function getInviteeData($userId, $contentId) {
        $inviteeCollection = $this->db->invite_contact;
        $inviteeCollection->findOne(array("receiver_id" => $userId, "content_id" => $contentId));
        if (count($inviteeCollection) > 0) {
            return $inviteeCollection;
        } else {
            return 0;
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
            case 'EE015':
                $errormsg = 'Channelname already exists';
                break;
            case 'EE024':
                $errormsg = 'Failed to retrieve favourite channel list.';
                break;
            case 'EE023':
                $errormsg = 'Channel Id does not exist in db.';
                break;
            case 'EE022':
                $errormsg = 'Failed to retrieve map channel list.';
                break;
        }

        $error = array("errorCode" => $errorNumber, "errorMsg" => $errormsg);
        array_push($errordata, $error);
        $mongouserresult['errors'] = $errordata;

        return $mongouserresult;
    }

    function testPush() {
        $result = $this->push->push(array('fnEy2oH3_34:APA91bHMTsg0EqKiGxUzKouUrKK1KFBeA63LwO7ytR1IkNCHS8UqsZcbZUHS8N3KaTNduB0aBeUF7pbrgmwgvYzS553c-HgTCn3ytQ7Jn1Dl59RV21o6QaFoKgAK3OusU_CY_qBr8Y1Z'), 'hi how are you', 1);
        print_r($result);
        exit;
    }

}

<?php

require_once realpath(__DIR__ . '/../..') . '/php-ravel/controller/user.php';
require_once realpath(__DIR__ . '/../..') . '/assets/twilio/Services/Twilio.php';
require_once realpath(__DIR__ . '/../..') . '/php-ravel/controller/search.php';
require_once realpath(__DIR__ . '/../..') . '/php-ravel/controller/push.php';
require_once realpath(__DIR__ . '/../..') . '/application/third_party/aws/aws-autoloader.php';

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class ravelchannel_model extends CI_Model {

    private $db, $mongo, $timezone;

    function ravelchannel_model() {
        parent::__construct();
        $this->load->helper('string');
        $this->load->helper('form');
        $this->load->helper('url');
        $this->load->helper('text');
        $this->load->library('rest_client');
        $this->load->helper('date');
        $this->load->model('comman_model');
        $this->load->model('pushnotification_model');
        $this->mongo = $this->config->config['mongo'];
        $this->openFireUrl = $this->config->config['openfire_url'];
        $this->sid = $this->config->config['sid'];
        $this->token = $this->config->config['token'];
        $this->twilioNumber = $this->config->config['twilioNumber'];
        $dbName = $this->config->config['mongoDb'];
        $this->rtmpAppUrl = $this->config->config['rtmpAppUrl'];
        $this->db = $this->mongo->$dbName;
        $this->user = new user();
        $this->search = new search();
        $this->push = new push();
        $timezone = $this->config->config['timezone'];
        $this->awsS3 = $this->config->config['awsS3'];
        $this->invitationMessage = $this->config->config['invitationMessage'];
    }

    /**
     * Function to get category of channel
     * @param type $docPerPage
     * @return array
     */
    function getCategory($docPerPage) {
        $collection = $this->db->channelcategory;
        $categoryChannel = $collection->find();
        if (count(iterator_to_array($categoryChannel)) > 0) {
            $finalChannelTypeList = $channelList = array();
            foreach ($categoryChannel as $cat) {
                $channel = array();
                $channel['id'] = (string) $cat['_id'];
                $channel['value'] = $cat['name'];
                $channel['count'] = count($this->getChannelByCategory((string) $cat['_id']));
                array_push($channelList, $channel);
            }
            $this->array_sort_by_column($channelList, 'count', SORT_DESC);
            if (isset($docPerPage) && $docPerPage != 0) {
                $finalChannelTypeList = array_slice($channelList, 0, $docPerPage);
            } else {
                $finalChannelTypeList = $channelList;
            }
            return $finalChannelTypeList;
        } else {
            return $this->comman_model->senderror('EE018');
        }
    }

    /* Get channel By category id to get all the list of channels under any category
     * @param type $catId
     */

    function getChannelByCategory($catId) {
        $collectionChannel = $this->db->channel;
        $categoryChannel = $collectionChannel->find(array("category.cid" => (string) $catId, "isactive" => 1));
        return iterator_to_array($categoryChannel);
    }

    /**
     * Function to create a channel
     * @param array $channelData
     */
    function createChannel($channelData) {
        $collection = $this->db->channel;
//find is the same channal name exit
        $condition = array('cn' => urldecode($channelData['channelName']), "isactive" => 1);
        $channelcheck = $collection->findOne($condition);
        if (count($channelcheck) > 0) {
            $result = $this->comman_model->senderror('EE015');
            return $result;
        } else {
//check user plan
            $userPlan = $this->getUserCurrentPlan($channelData['userId']);
//get count of user existing channel
            $userExistingChannel = $collection->find(array("uid" => new MongoId($channelData['userId']), "isactive" => 1));
            $countUserExistingChannel = count(iterator_to_array($userExistingChannel));
            if ((int) $countUserExistingChannel < (int) $userPlan['cnum']) {
                $channelName = str_replace(" ", "_", $channelData['channelName']);
                if (isset($channelData['imageUpload']) && trim($channelData['imageUpload']) == 1 && isset($_FILES['channelImage'])) {
                    $uploadFile = $this->comman_model->s3FileUpload($_FILES['channelImage']);
                    if ($uploadFile != 0) {
                        $imagepath = $uploadFile['url'];
                    } else {
                        $imagename = $this->user->uploadFile(trim($channelName) . '_' . time(), 'channelImage', 'channel/');
                        $imagepath = base_url() . 'assets/channel/' . $imagename;
                    }
                }
                if (isset($channelData['videoUpload']) && trim($channelData['videoUpload']) == 1 && isset($_FILES['channelVideo'])) {
                    $uploadFile = $this->comman_model->s3FileUpload($_FILES['channelVideo'], $image = NULL, $channelData['angle']);
                    if ($uploadFile != 0) {
                        $videopath = $uploadFile['url'];
                        $thumbnailUrl = $uploadFile['thumbnail'];
                    } else {
                        $videoname = $this->user->uploadFile(trim($channelName) . '_' . time(), 'channelVideo', 'channel/');
                        $videopath = base_url() . '/assets/channel/' . $videoname;
                        $thumbnailUrl = (isset($imagepath)) ? $imagepath : '';
                    }
                }
                $userData = $this->db->user->findOne(array("_id" => new MongoId($channelData['userId'])));
                if (isset($userData['loc']) && $userData['loc'] != '') {
                    $location = $userData['loc'];
                } else {
                    $location = array();
                }
                $catInfo = $this->getCategoryInfo($channelData['channelCategoryId']);
                $ChannelInsertArray = array(
                    "uid" => new MongoId($channelData['userId']),
                    "category" => array("cid" => $channelData['channelCategoryId'], "cname" => $catInfo['name']),
                    "cn" => urldecode($channelData['channelName']),
                    "des" => urldecode($channelData['description']),
                    "cimg" => (isset($imagepath)) ? $imagepath : '',
                    "cvideo" => (isset($videopath)) ? $videopath : '',
                    "loc" => $location,
                    "cvideothumb" => (isset($thumbnailUrl) && $thumbnailUrl != '') ? $thumbnailUrl : '',
                    "favcount" => 0,
                    "favuid" => array(),
                    "cat" => time(),
                    "mat" => time(),
                    "isactive" => 1
                );
                $collection->insert($ChannelInsertArray);
                $newChannelID = $ChannelInsertArray['_id'];
                if (count($userData) > 0) {
//generate channel on open fire server
                    $params['uid'] = (string) $channelData['userId'];
                    $params['chid'] = (string) $newChannelID;
                    $method = 'POST';
                    if (isset($userData['oppwd']) && $userData['oppwd'] != "") {
                        $this->comman_model->createOpenfireRoom(2, (string) $channelData['userId'], $params, true);
                    } else {
                        $this->comman_model->createOpenfireRoom(2, (string) $channelData['userId'], $params, false);
                    }
                    $userData = $this->db->user->findOne(array("_id" => new MongoId($channelData['userId'])));
                    $userResult = $this->comman_model->generateResponseUser($userData, $channelData['accessToken'], $userData['isverify']);
                    return $userResult;
                }
            } else {
                return $this->comman_model->senderror('EE031');
            }
        }
    }

    /* Function to get cat info
     * @param type $catId
     */

    function getCategoryInfo($catId) {
        $collection = $this->db->channelcategory;
        $result = $collection->findOne(array("_id" => new MongoId($catId)));
        return $result;
    }

    /**
     * Function to edit the the user profile details
     * @param type $channelData
     */
    function editChannel($channelData) {
        if (isset($_FILES['channelVideo']) && isset($channelData['planId']) && $channelData['planId'] != '' && $_FILES['channelVideo']['size'] > 0) {
            $planData = $this->db->plan->findOne(array("_id" => new MongoId($channelData['planId'])));
            $imagePath = $this->user->uploadFile(trim("test") . '_' . time(), 'channelVideo', 'profilepic/');
            $path = base_url() . 'assets/profilepic/' . $imagePath;
            ob_start();
            $ffmpeg = '/usr/bin/ffmpeg';
            passthru("$ffmpeg -i $path  2>&1");
            $duration = ob_get_contents();
            ob_end_clean();
            $regex_duration = "/Duration: ([0-9]{1,2}):([0-9]{1,2}):([0-9]{1,2}).([0-9]{1,2})/";
            $time = 0;
            if (preg_match($regex_duration, $duration, $regs)) {
                $hours = $regs [1] ? $regs [1] : null;
                $mins = $regs [2] ? $regs [2] : null;
                $secs = $regs [3] ? $regs [3] : null;
                $ms = $regs [4] ? $regs [4] : null;
                $time = $hours * 3600 + $mins * 60 + $secs + $ms / 1000;
            }
            unlink('assets/profilepic/' . $imagePath);
            if ($time > $planData['ldreel']) {
                return $result = $this->comman_model->senderror('EE070', $planData['ldreel']);
            }
        }
        $collection = $this->db->channel;
        $condition = array('cn' => $channelData['channelName'], "isactive" => 1);
        $channelcheck = $collection->findOne($condition);
        if (count($channelcheck) > 0 && (string) $channelcheck['uid'] != $channelData['userId']) {
            $result = $this->comman_model->senderror('EE015');
            return $result;
        } else {
            $id = new MongoId($channelData['channelId']);
            $ChannelCollectionResult = $collection->findOne(array('_id' => $id));
            if (count($ChannelCollectionResult) > 0) {
                $thumbnailUrl = $ChannelCollectionResult['cvideothumb'];
                $channelName = str_replace(" ", "_", $ChannelCollectionResult['cn']);
                //if user wants to remove image
                if (isset($channelData['imageUpload']) && trim($channelData['imageUpload']) == 1) {
                    $updateChannelData['cimg'] = '';
                }

                if (isset($channelData['imageUpload']) && trim($channelData['imageUpload']) == 1 && isset($_FILES['channelImage']) && $_FILES['channelImage']['size'] > 0) {
                    $uploadFile = $this->comman_model->s3FileUpload($_FILES['channelImage']);
                    if ($uploadFile != 0) {
                        $imagepath = $uploadFile['url'];
                        $updateChannelData['cimg'] = $imagepath;
                    } else {
                        $imagename = $this->user->uploadFile(trim($channelName) . '_' . time(), 'channelImage', 'channel/');
                        $updateChannelData['cimg'] = base_url() . 'assets/channel/' . $imagename;
                    }
                }

                //if user wants to remove video
                if (isset($channelData['videoUpload']) && trim($channelData['videoUpload']) == 1) {
                    $updateChannelData['cvideo'] = '';
                    $thumbnailUrl = '';
                }

                if (isset($channelData['videoUpload']) && trim($channelData['videoUpload']) == 1 && isset($_FILES['channelVideo']) && $_FILES['channelVideo']['size'] > 0) {
                    $uploadFile = $this->comman_model->s3FileUpload($_FILES['channelVideo'], $image = NULL, $channelData['angle']);
                    if ($uploadFile != 0) {
                        $videopath = $uploadFile['url'];
                        $updateChannelData['cvideo'] = $videopath;
                        $thumbnailUrl = $uploadFile['thumbnail'];
                    } else {
                        $videoname = $this->user->uploadFile(trim($channelName) . '_' . time(), 'channelVideo', 'channel/');
                        $videopath = base_url() . '/assets/channel/' . $videoname;
                        $thumbnailUrl = (isset($updateChannelData['cimg'])) ? $updateChannelData['cimg'] : '';
                        $updateChannelData['cvideo'] = base_url() . 'assets/channel/' . $videoname;
                    }
                }

                $userData = $this->db->user->findOne(array("_id" => new MongoId($channelData['userId'])));
                if (isset($userData['location']) && $userData['location'] != '') {
                    $location = $userData['location'];
                } else {
                    $location = array();
                }
                $catInfo = $this->getCategoryInfo($channelData['channelCategoryId']);
                $ChannelUpdateArray = array(
                    "cn" => $channelData['channelName'],
                    "category" => array("cid" => $channelData['channelCategoryId'], "cname" => $catInfo['name']),
                    "cn" => urldecode($channelData['channelName']),
                    "des" => urldecode($channelData['description']),
                    "cimg" => (isset($updateChannelData['cimg'])) ? $updateChannelData['cimg'] : $ChannelCollectionResult['cimg'],
                    "cvideo" => (isset($updateChannelData['cvideo'])) ? $updateChannelData['cvideo'] : $ChannelCollectionResult['cvideo'],
                    "loc" => $location,
                    "cvideothumb" => $thumbnailUrl,
                    "mat" => time()
                );
                $where = array('_id' => $id);
                $cursor = $collection->update($where, array('$set' => $ChannelUpdateArray));
                $userResult = $this->comman_model->generateResponseUser($userData, $channelData['accessToken'], $userData['isverify']);
                return $userResult;
            } else {
                return $error = $this->comman_model->senderror('EE029');
            }
        }
    }

    /**
     * Function to delete a channel  
     * @param type $channelId  
     * @param type $userId
     * @param type $accessToken   
     */
    function deleteChannel($channelId, $userId, $accessToken) {

        $channelCollection = $this->db->channel;
        $channelData = $channelCollection->findOne(array("_id" => new MongoId($channelId), "uid" => new MongoId($userId)));
        if (count($channelData) > 0) {
            $where = array('_id' => new MongoId($channelId));
            $ChannelUpdateArray = array(
                "mat" => time(),
                "isactive" => 0
            );
            $cursor = $channelCollection->update($where, array('$set' => $ChannelUpdateArray));
            if ($cursor['ok'] == 1) {
//remove all content related to this channel
                $contentCollection = $this->db->channelcontent;
                $where = array("uid" => new MongoId($userId), 'cid' => (string) $channelId);
                $contentUpdateArray = array(
                    "mat" => time(),
                    "isactive" => 0
                );
                $contentData = $contentCollection->update($where, array('$set' => $contentUpdateArray), array('multiple' => true));
//remove from user favoutrate channel list
                $getUserData = $this->db->user->find();
                foreach ($getUserData as $val) {
                    if (isset($val['favchannel']) && is_array($val['favchannel']) && count($val['favchannel']) > 0) {
                        if (in_array($channelId, $val['favchannel'])) {
                            if (($key = array_search($channelId, $val['favchannel'])) !== false) {
                                unset($val['favchannel'][$key]);
                                $result = $this->db->user->update(array("_id" => new MongoId($val['_id'])), array('$set' => array('favchannel' => $val['favchannel'], "mat" => time())));
                            }
                        }
                    }
                }
                $userData = $this->db->user->findOne(array("_id" => new MongoId($userId)));
                return $userResult = $this->comman_model->generateResponseUser($userData, $accessToken, $userData['isverify']);
            }
        } else {
            $result = $this->comman_model->senderror('EE023');
            return $result;
        }
    }

    /**
     * Function to delete channel details
     * @param type $channelId    
     */
    function deleteChannelDetails($channelId) {
        $where = array("_id" => new MongoId($channelId));
        $removeUserId = $this->db->channel->remove($where, array("justOne" => true));
        return true;
    }

    /**
     * Function to create (content)broadcast 
     * @param type $broadcastData   
     * @param type $accessToken  
     */
    function createContent($broadcastData, $accessToken) {
//check for channel id is active or not       
        $getchanneldata = $this->db->channel->findOne(array("_id" => new MongoId($broadcastData['channelId']), "isactive" => 1));
        if (count($getchanneldata) > 0) {
            $success = 0;
//check start time for creating content
            if ($broadcastData['startTime'] > time()) {
//get user plan
                $plandata = $this->getUserCurrentPlan($broadcastData['userId']);
                if ($plandata != 0) {
                    if (!isset($broadcastData['endTime']) || $broadcastData['endTime'] == '' || $broadcastData['endTime'] < time()) {
                        if ($plandata['blength'] != 0) {
                            $broadcastData['endTime'] = $broadcastData['startTime'] + $plandata['blength'];
                        } else {
                            $broadcastData['endTime'] = $broadcastData['startTime'] + 30 * 86400;
                        }
                    }
//check for broadcast length
                    $checkBrodcastLength = $this->checkBrodcastLength($plandata, $broadcastData['startTime'], $broadcastData['endTime']);
                    if ($checkBrodcastLength == 1) {
//check for current day upcomming
                        $currentDayUpcommingBroadcast = $this->currentDayUpcommingBroadcast($broadcastData, $plandata);
                        if ($currentDayUpcommingBroadcast == 1) {
//check for other upcomming broad cast time frame
                            $otherUpcommingBroadcast = $this->otherUpcommingBroadcast($broadcastData, $plandata);
                            if ($otherUpcommingBroadcast == 1) {
//generate stream key and store in to content db in place of rtmp url
                                $rtmpUrl = md5(uniqid() . '#' . $broadcastData['channelId'] . '#' . time());
                                $data = array(
                                    "uid" => new MongoId($broadcastData['userId']),
                                    "cid" => $broadcastData['channelId'],
                                    "name" => urldecode($broadcastData['contentName']),
                                    "des" => urldecode($broadcastData['description']),
                                    "rtmpurl" => $rtmpUrl,
                                    "actype" => (int) $broadcastData['accessType'],
                                    "cratid" => $broadcastData['rating'],
                                    "rtmpst" => 0,
                                    "stime" => (int) $broadcastData['startTime'],
                                    "etime" => (int) $broadcastData['endTime'],
                                    "isactive" => 1,
                                    "brrat" => array(),
                                    "cat" => time()
                                );
                                $this->db->channelcontent->insert($data);
                                $newContentID = $data['_id'];
                                if (count($newContentID) > 0) {
                                    $mongoBroadcastData = $this->db->channelcontent->findOne(array("_id" => new MongoId($newContentID)));
                                    $contentData['contentId'] = (string) $mongoBroadcastData['_id'];
// Code snippet to create broadcast on Open fire server
                                    $params['uid'] = (string) $broadcastData['userId'];
                                    $params['chid'] = $broadcastData['channelId'];
                                    $params['rmid'] = (string) $contentData['contentId'] . $params['uid'];
                                    $method = 'POST';
                                    $this->comman_model->createOpenfireRoom(3, (string) $broadcastData['userId'], $params, false, (int) $mongoBroadcastData['actype'], $contentData['contentId']);

//generate response
                                    $mongoUserResult = $this->db->user->findOne(array("_id" => new MongoId($broadcastData['userId'])));
                                    if (count($mongoUserResult) > 0) {
                                        $inviteContect = $this->inviteContact($broadcastData['userId'], (string) $contentData['contentId'], $broadcastData['channelId'], $broadcastData['contactInvites'], (int) $broadcastData['accessType']);
                                        $userResult = $this->comman_model->generateResponseUser($mongoUserResult, $accessToken, $mongoUserResult['isverify']);
                                        return $userResult;
                                    } else {
                                        return $error = $this->comman_model->senderror('EE025');
                                    }
                                } else {
                                    return $error = $this->comman_model->senderror('EE025');
                                }
                            } else {
                                return $otherUpcommingBroadcast;
                            }
                        } else {
                            return $currentDayUpcommingBroadcast;
                        }
                    } else {
                        return $error = $this->comman_model->senderror('EE053', $plandata['blength']);
                    }
                } else {
                    return $error = $this->comman_model->senderror('EE046');
                }
            } else {
                return $error = $this->comman_model->senderror('EE047');
            }
        } else {
            return $error = $this->comman_model->senderror('EE029');
        }
    }

    /**
     * Function to create (content)broadcast and start it
     * @param type $broadcastData   
     * @param type $accessToken  
     */
    function createContentStartNow($broadcastData, $accessToken) {
        $getchanneldata = $this->db->channel->findOne(array("_id" => new MongoId($broadcastData['channelId']), "isactive" => 1));
        if (count($getchanneldata) > 0) {
//get user plan
            $plandata = $this->getUserCurrentPlan($broadcastData['userId']);
            if ($plandata != 0) {
                $broadcastData['startTime'] = time();
                if (!isset($broadcastData['endTime']) || $broadcastData['endTime'] == '' || $broadcastData['endTime'] < time()) {
                    if ($plandata['blength'] != 0) {
                        $broadcastData['endTime'] = $broadcastData['startTime'] + $plandata['blength'];
                    } else {
                        $broadcastData['endTime'] = $broadcastData['startTime'] + 30 * 86400;
                    }
                }
//check for broadcast length
                $checkBrodcastLength = $this->checkBrodcastLength($plandata, $broadcastData['startTime'], $broadcastData['endTime']);
                if ($checkBrodcastLength == 1) {
//check for current day upcomming
                    $currentDayUpcommingBroadcast = $this->currentDayUpcommingBroadcast($broadcastData, $plandata);
                    if ($currentDayUpcommingBroadcast == 1) {
//check for other upcomming broad cast time frame
                        $otherUpcommingBroadcast = $this->otherUpcommingBroadcast($broadcastData, $plandata);
                        if ($otherUpcommingBroadcast == 1) {
//generate stream key and store in to content db in place of rtmp url
                            $rtmpUrl = md5(uniqid() . '#' . $broadcastData['channelId'] . '#' . time());
                            $data = array(
                                "uid" => new MongoId($broadcastData['userId']),
                                "cid" => $broadcastData['channelId'],
                                "name" => urldecode($broadcastData['contentName']),
                                "des" => urldecode($broadcastData['description']),
                                "rtmpurl" => $rtmpUrl,
                                "actype" => (int) $broadcastData['accessType'],
                                "cratid" => $broadcastData['rating'],
                                "rtmpst" => 0,
                                "stime" => (int) $broadcastData['startTime'],
                                "etime" => (int) $broadcastData['endTime'],
                                "isactive" => 1,
                                "brrat" => array(),
                                "cat" => time()
                            );
                            $this->db->channelcontent->insert($data);
                            $newContentID = $data['_id'];
                            if (count($newContentID) > 0) {
                                $mongoBroadcastData = $this->db->channelcontent->findOne(array("_id" => new MongoId($newContentID)));
                                $contentData['contentId'] = (string) $mongoBroadcastData['_id'];
// Code snippet to create broadcast on Open fire server
                                $params['uid'] = (string) $broadcastData['userId'];
                                $params['chid'] = $broadcastData['channelId'];
                                $params['rmid'] = (string) $contentData['contentId'] . $params['uid'];
                                $method = 'POST';
                                $this->comman_model->createOpenfireRoom(3, (string) $broadcastData['userId'], $params, false, (int) $mongoBroadcastData['actype'], $contentData['contentId']);
//generate response
                                $mongoUserResult = $this->db->user->findOne(array("_id" => new MongoId($broadcastData['userId'])));
                                if (count($mongoUserResult) > 0) {
                                    $inviteContect = $this->inviteContact($broadcastData['userId'], (string) $contentData['contentId'], $broadcastData['channelId'], $broadcastData['contactInvites'], (int) $broadcastData['accessType']);
                                    $userResult = $this->startBroadcast($broadcastData['userId'], (string) $newContentID);
                                    return $userResult;
                                } else {
                                    return $error = $this->comman_model->senderror('EE025');
                                }
                            } else {
                                return $error = $this->comman_model->senderror('EE025');
                            }
                        } else {
                            return $otherUpcommingBroadcast;
                        }
                    } else {
                        return $currentDayUpcommingBroadcast;
                    }
                } else {
                    return $error = $this->comman_model->senderror('EE053', $plandata['blength']);
                }
            } else {
                return $error = $this->comman_model->senderror('EE046');
            }
        } else {
            return $error = $this->comman_model->senderror('EE029');
        }
    }

    /* This function is used to start broad cast
     *      * @param userID $userID  
     * @param contentId $contentId */

    function startBroadcast($userId, $contentId) {
        $planCollection = $this->db->plan;
        $result = array();
        $contentData = $this->getContentData($userId, $contentId);
        if ($contentData != 0) {
            if (isset($contentData['rtmpst']) && $contentData['rtmpst'] == 0) {
//cratid
                $rtmpStatus = 0;
                $startTime = isset($contentData['stime']) ? $contentData['stime'] : '';
                $endTime = isset($contentData['etime']) ? $contentData['etime'] : '';
                if (time() >= $startTime && $endTime > time()) {
                    $userDetails = $this->getUser((string) $userId);
                    $planId = isset($userDetails['pid']) ? $userDetails['pid'] : '';
                    if ($planId != '') {
                        $planDetails = $planCollection->findOne(array("_id" => new MongoId($planId)));
                    } else {
                        $planDetails = array();
                    }
//get owner details
                    $ownerName = $userDetails['name'];
                    $ownerId = (string) $userDetails['_id'];
                    $ownerNickName = (isset($userDetails['nickname'])) ? $userDetails['nickname'] : "";
//generate play url
                    $playUrl = $this->generatePlayUrl($contentData['rtmpurl'], $contentData['etime']);
                    $isOwner = 1;
                    $remainingDuration = $endTime - time();
//update rtmp status in channel content collection
//$updateRtmpStatus = $channelCollection->update(array("_id" => new MongoId($contentId)), array('$set' => array('rtmpst' => 1)));
//if (isset($updateRtmpStatus['ok'])) {
// $rtmpStatus = 1;
//}
//generate broadcast response 
                    $result = $this->broadcastResponse($contentData, $ownerName, $ownerId, $ownerNickName, $playUrl, $rtmpStatus, $isOwner, $remainingDuration);
                    /* Function to send push notification invite user */
                    $this->getBroadcastInviteData($contentId, $contentData['cid'], $userId);
                    return $result;
                } else {
                    return $error = $this->comman_model->senderror('EE042');
                }
            } else {
                return $error = $this->comman_model->senderror('EE045');
            }
        } else {
            return $error = $this->comman_model->senderror('EE040');
        }
    }

    /* This function is used for generate play url */

    function generatePlayUrl($rtmpUrl, $endtime) {
//rtmp://52.37.145.41/myapp/mystream?e=1456826182&st=5b589ff34cbd3bf0055379b44716bf9f
        $st = shell_exec("echo -n 'ravel@ravelApp/" . $rtmpUrl . $endtime . "' | openssl dgst -md5 -binary | openssl enc -base64 | tr '+/' '-_' | tr -d '='");
        $playUrl = $this->rtmpAppUrl . $rtmpUrl . "?e=" . $endtime . "&st=" . trim($st);
        return $playUrl;
    }

    function broadcastResponse($contentData, $ownerName, $ownerId, $ownerNickName, $playUrl, $rtmpStatus, $isOwner, $remainingDuration) {
        $ratingDetails = $this->comman_model->getratingDetail($contentData['cratid']);
        $channelDetail = $this->getChannel(isset($contentData['cid']) ? $contentData['cid'] : '');
        $result = array();
        $result['contentId'] = (string) $contentData['_id'];
        $result['ownerName'] = $ownerName;
        $result['ownerId'] = $ownerId;
        $result['ownerNickName'] = $ownerNickName;
        $result['channelId'] = isset($contentData['cid']) ? $contentData['cid'] : '';
        $result['channelName'] = isset($channelDetail['cn']) ? $channelDetail['cn'] : '';
        $result['accessType'] = isset($contentData['actype']) ? $contentData['actype'] : '';
        $result['broadcastName'] = isset($contentData['name']) ? $contentData['name'] : '';
        $result['description'] = isset($contentData['des']) ? $contentData['des'] : '';
        $result['chatRoomId'] = isset($contentData['ofrmid']) ? $contentData['ofrmid'] : '';
        $result['chatRoomPassword'] = isset($contentData['ofpwd']) ? $contentData['ofpwd'] : '';
        $result['startTime'] = isset($contentData['stime']) ? $contentData['stime'] : '';
        $result['endTime'] = isset($contentData['etime']) ? $contentData['etime'] : '';
        $result['broadcastRating'] = isset($contentData['cratid']) ? $contentData['cratid'] : '';
        $result['broadcastRatingName'] = isset($ratingDetails['name']) ? $ratingDetails['name'] : '';
        $result['broadcastUrl'] = $playUrl;
        $result['remainingDuration'] = $remainingDuration;
        $result['isOwner'] = $isOwner;
        $result['rtmpStatus'] = $rtmpStatus;
        return $result;
    }

    /**
     * Function to send push notification invite user
     * @param type $contentId    
     */
    function getBroadcastInviteData($contentId, $channelId, $userId) {
        $collection = $this->db->invitecontact;
        $getInviteData = $collection->find(array("recid" => array('$ne' => ""), "conid" => $contentId));
        $gcmData = $iosData = $twilioData = array();
        if (count(iterator_to_array($getInviteData)) > 0) {
            foreach ($getInviteData as $val) {
                $userId = (isset($val["recid"]) && $val["recid"] != "") ? $val["recid"] : "";
                if (!empty($userId)) {
                    $getUserData = $this->db->user->findOne(array("_id" => new MongoId($userId)));
                    if (count($getUserData) > 0) {
                        $sendPush = (isset($getUserData['getpush']) && $getUserData['getpush'] != 0) ? $getUserData['getpush'] : 0;
                        if ($sendPush == 1) {
                            $userName = $getUserData['name'];
                            $contentName = $this->comman_model->getContentName($contentId);
                            $channelName = $this->comman_model->getChannelName($contentId);
                            $message = str_replace("<con>", $contentName, $this->invitationMessage['start']);
                            $sendMessage = str_replace("<cn>", $channelName, $message);
                            $getDeviceInfo = $getResult = $this->db->userdeviceinfo->findOne(array("uid" => new MongoId($userId)));
                            if (count($getDeviceInfo) > 0) {
                                $deviceCollection = $getDeviceInfo['dvcs'];
                                if (count($deviceCollection) > 0) {
                                    foreach ($deviceCollection as $val) {
                                        if ($val['st'] == 1 && !empty($val['dregid'])) {
                                            if ((int) $val['dtype'] == 1) {
                                                $gcmData[] = $val['dregid'];
                                            }
                                            if ((int) $val['dtype'] == 2) {
                                                $iosData[] = $val['dregid'];
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } else {
            return true;
        }
        if (count($gcmData) > 0) {
            $this->pushnotification_model->pushAndroidNotification($gcmData, $sendMessage, "broadcastStart", 0, $channelId, (string) $userId);
        }
        if (count($iosData) > 0) {
            $this->pushnotification_model->pushIosNotification($iosData, $sendMessage, "broadcastStart", 0, $channelId, (string) $userId);
        }
        return true;
    }

    /**
     * Function to get or check content owner   
     * @param contentId $contentId    
     */
    function getChannel($channelId) {
        $collection = $this->db->channel;
        $channel = $collection->findOne(array("_id" => new MongoId($channelId)));
        if (count($channel) > 0) {
            return $channel;
        } else {
            return 0;
        }
    }

    /**
     * Function to check for broadcast length
     * @param type $plandata   
     * @param type $startTime 
     *  @param type $endTime   
     */
    function checkBrodcastLength($plandata, $startTime, $endTime) {
        $success = 0;
        $userBroadcastlength = (int) $endTime - (int) $startTime;
        if ((int) $plandata['blength'] == 0) {
            $success = 1;
        } else if ($userBroadcastlength <= (int) $plandata['blength']) {
            $success = 1;
        }
        return $success;
    }

    /**
     * Function to check for current day upcomming
     * @param type $broadcastData   
     * @param type $plandata     
     */
    function currentDayUpcommingBroadcast($broadcastData, $plandata) {
        $success = 0;
        $upcommingBroadCastuserCurrentDay = $this->getUpcommingBroadCastUserCurrentDay($broadcastData['userId']);
//get plan broad cast count of
        $planBroadCastCount = $plandata['bnum'];
//check for the current day upcomming content
        if (isset($plandata['checkforcurrentday']) && $plandata['checkforcurrentday'] == 1) {
            if (count($upcommingBroadCastuserCurrentDay) < $planBroadCastCount) {
// check any content availabe for same time frame
                /* if (count($upcommingBroadCastuserCurrentDay)) {
                  foreach ($upcommingBroadCastuserCurrentDay as $upcommingBroad) {
                  if ((int) $broadcastData['startTime'] >= (int) $upcommingBroad['stime'] && (int) $broadcastData['startTime'] <= (int) $upcommingBroad['etime'] || (int) $broadcastData['endTime'] >= (int) $upcommingBroad['stime'] && (int) $broadcastData['endTime'] <= (int) $upcommingBroad['etime'] || (int) $broadcastData['startTime'] <= (int) $upcommingBroad['stime'] && (int) $broadcastData['endTime'] >= (int) $upcommingBroad['etime']) {
                  return $error = $this->comman_model->senderror('EE026');
                  exit;
                  } else {
                  $success = 1;
                  }
                  }
                  } else {
                  $success = 1;
                  } */
                $success = 1;
            } else {
                return $error = $this->comman_model->senderror('EE032');
            }
        } else {
            $success = 1;
        }
        return $success;
    }

    /**
     * Function to check for other upcomming broad cast time frame   
     * @param type $broadcastData   
     * @param type $plandata     
     */
    function otherUpcommingBroadcast($broadcastData, $plandata) {
        $success = 0;
        $upcommingBroadCastuserAll = $this->getUpcommingBroadCastUserAll($broadcastData['userId']);
//get plan broad cast count of
        $planBroadCastCount = $plandata['bnum'];
        if (count($upcommingBroadCastuserAll) < $planBroadCastCount) {
//check for other upcomming broad cast time frame
            /* if (count($upcommingBroadCastuserAll)) {
              foreach ($upcommingBroadCastuserAll as $upcommingBroad) {
              if ((int) $broadcastData['startTime'] >= (int) $upcommingBroad['stime'] && (int) $broadcastData['startTime'] <= (int) $upcommingBroad['etime'] || (int) $broadcastData['endTime'] >= (int) $upcommingBroad['stime'] && (int) $broadcastData['endTime'] <= (int) $upcommingBroad['etime'] || (int) $broadcastData['startTime'] <= (int) $upcommingBroad['stime'] && (int) $broadcastData['endTime'] >= (int) $upcommingBroad['etime']) {
              return $error = $this->comman_model->senderror('EE026');
              exit;
              } else {
              $success = 1;
              }
              }
              } else {
              $success = 1;
              } */
            $success = 1;
        } else {
            return $error = $this->comman_model->senderror('EE032');
        }
        return $success;
    }

    /**
     * Function to get user colection
     * @param type $userId 
     */
    function getUser($userId) {
        $getUserData = $this->db->user->findOne(array("_id" => new MongoId($userId)));
        if (count($getUserData) > 0) {
            return $getUserData;
        } else {
            return 0;
        }
    }

    /*
     * Get Upcomming broadcast for current day of user 
     * @param $userId     
     */

    function getUpcommingBroadCastUserCurrentDay($userId) {
        $getContentdata = $this->db->channelcontent;
        $endOfDay = (strtotime('tomorrow') - 1);
        $curentTime = time();
        $upcommingBroadcat = $getContentdata->find(array("stime" => array('$gte' => $curentTime, '$lte' => $endOfDay), 'uid' => new MongoId($userId), "isactive" => 1));
        return iterator_to_array($upcommingBroadcat);
    }

    /*
     * Get Upcomming broadcast for current day of user 
     * @param $userId     
     */

    function getUpcommingBroadCastUserAll($userId) {
        $getContentdata = $this->db->channelcontent;
        $curentTime = time();
        $upcommingBroadcat = $getContentdata->find(array("etime" => array('$gte' => $curentTime), 'uid' => new MongoId($userId), "isactive" => 1));
        return iterator_to_array($upcommingBroadcat);
    }

    /*
     * Get user current plan 
     * @param userid $userid     
     */

    function getUserCurrentPlan($userId) {
        $getChannelPlan = $this->db->plan;
        $getUserData = $this->db->user->findOne(array("_id" => new MongoId($userId)));
        if (count($getUserData) > 0) {
            $plan_id = (isset($getUserData['pid'])) ? $getUserData['pid'] : '';
            $getPlanDetails = $getChannelPlan->findOne(array("_id" => new MongoId($plan_id)));
            return $getPlanDetails;
        } else {
            return 0;
        }
    }

    /**
     * Function to get or check content owner   
     * @param contentId $contentId    
     */
    function getContentDataById($contentId) {
        $collection = $this->db->channelcontent;
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
        $collection = $this->db->channelcontent;
        $contentData = $collection->findOne(array("_id" => new MongoId($contentId), "uid" => new MongoId($userId)));
        if (count($contentData) > 0) {
            return $contentData;
        } else {
            return 0;
        }
    }

    /**
     * Function to send a invite 
     * @param type $contentId
     * @param type $channelId
     * @param type $contactInvites   
     */
    function inviteContact($userId, $contentId, $channelId, $contactInvites, $accessType) {
        if ($contactInvites != '' && $channelId != '' && $contentId != '') {
            $getContentData = $this->db->channelcontent->findOne(array("_id" => new MongoId($contentId)));
            if (count($getContentData) > 0) {
                $this->sendInvitation($contactInvites, $accessType, $userId, $contentId, $channelId);
                return true;
            } else {
                return false;
            }
        } else {
            
        }
    }

    /**
     * Function to send a content invitation
     * @param type $contactInvites
     * @param type $accessType
     * @param type $userId 
     * @param type $contentId 
     * @param type $channelId   
     */
    function sendInvitation($contactInvites, $accessType, $userId, $contentId, $channelId) {
        if (count($contactInvites) > 0) {
// $gcmData = $IosData = $twilioData = array();
            $getUserDetails = $this->db->user->findOne(array("_id" => new MongoId($userId)));
            if (count($getUserDetails) > 0) {
                $userName = $getUserDetails['nickname'];
            } else {
                $userName = "";
            }
            $contentData = $this->getContentDataById($contentId);
            $channelName = $this->comman_model->getChannelName($contentId);
            $message = str_replace("<un>", $userName, $this->invitationMessage['sendinvitation']);
            $message = str_replace("<con>", $contentData['name'], $message);
            $message = str_replace("<cn>", $channelName, $message);
            $sendMessage = str_replace("<dt>", date("m/d/Y h:i A", $contentData['stime']), $message);

            foreach ($contactInvites as $val) {
                $sendPush = 0;
                $phoneNumber = trim($val->mobileNumber);
                //$contentName = $this->comman_model->getContentName($contentId);                
                $name = trim($val->name);
                $getUserId = "";
                $getUserRecord = $this->db->user->findOne(array("upno" => $phoneNumber, "isverify" => 1));
                $inviteData = array(
                    "name" => $name,
                    "sid" => new MongoId($userId),
                    "recid" => "",
                    "pno" => $phoneNumber,
                    "cid" => $channelId,
                    "conid" => $contentId,
                    "st" => 1,
                    "notify" => 1,
                    "cat" => time(),
                    "mat" => time()
                );
                $collection = $this->db->invitecontact;
                if (count($getUserRecord) > 0) {
                    $getUserId = (string) $getUserRecord['_id'];
                    $inviteData['recid'] = new MongoId($getUserId);
                } else {
                    if (strlen($phoneNumber) <= 10 && (substr($phoneNumber, 0, 1) != '+')) {
                        if (isset($getUserDetails['ccod']) && !empty($getUserDetails['ccod']) && $getUserDetails['ccod'] != false) {
                            $phoneNumber = $getUserDetails['ccod'] . $phoneNumber;
                        } else {
                            $getCountryCode = $this->comman_model->getCountryCode();
                            if ($getCountryCode) {
                                $phoneNumber = $getCountryCode . $phoneNumber;
                            }
                        }
                    }
                }

                $getUserInviteData = $collection->find(array("pno" => trim($phoneNumber), "conid" => $contentId));
                if (count(iterator_to_array($getUserInviteData)) > 0) {
                    foreach ($getUserInviteData as $val) {
                        if ($val['st'] == 0) {
                            $collection->update(array("pno" => trim($phoneNumber), "conid" => $contentId), array('$set' => array('st' => 1, "mat" => time())));
                            $sendPush = 1;
                        }
                    }
                } else {
                    $insertInvites = $collection->insert($inviteData);
                    $sendPush = 1;
                }

                if ($sendPush == 1) {
                    $pushData = array(
                        "name" => $name,
                        "sid" => new MongoId($userId),
                        "recid" => (isset($getUserId) && !empty($getUserId)) ? new MongoId($getUserId) : "",
                        "pno" => $phoneNumber,
                        "cid" => $channelId,
                        "conid" => $contentId,
                        "type" => "contentInvitation",
                        "message" => $sendMessage,
                        "cat" => time()
                    );
                    $this->db->pushdata->insert($pushData);
                }
            }

            /* if (count($gcmData) > 0) {
              $this->pushnotification_model->pushAndroidNotification($gcmData, $sendMessage, "contentInvitation");
              }
              if (count($IosData) > 0) {
              $this->pushnotification_model->pushIosNotification($IosData, $sendMessage, "contentInvitation");
              }
              if (count($twilioData) > 0) {
              $this->pushnotification_model->sendTwilioMessage($twilioData, $sendMessage);
              } */
            return true;
        } else {
            return false;
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

        $collection = $this->db->user;
        $getUserData = $collection->findOne(array("_id" => new MongoId($userId)));
        if ((int) ($isFavorite) == 1) {
            if (is_array($getUserData['favchannel']) && in_array($channelId, $getUserData['favchannel'])) {
                return $message = array("message" => 'You have already Mark as favorite channel');
            } else {
                array_push($getUserData['favchannel'], $channelId);
                $result = $collection->update(array("_id" => new MongoId($userId)), array('$set' => array('favchannel' => $getUserData['favchannel'], "mat" => time())));
                if ($result['ok'] == 1) {
                    $getChannelData = $this->db->channel->findOne(array("_id" => new MongoId($channelId)));
                    array_push($getChannelData['favuid'], $userId);
                    $channelFav = array(
                        "favcount" => (isset($getChannelData['favcount'])) ? (int) $getChannelData['favcount'] + 1 : 1,
                        "favuid" => $getChannelData['favuid'],
                        "mat" => time()
                    );
                    $this->db->channel->update(array("_id" => new MongoId($channelId)), array('$set' => $channelFav));
//$this->comman_model->sendPushToFavorite((string) $getChannelData['uid'], $getUserData['un'], $getChannelData['cn']);
                    $this->sendPushToFavorite((string) $getChannelData['uid'], $channelId, $getUserData['un'], $getChannelData['cn']);
                }
            }
        }
        if ((int) ($isFavorite) == 0) {
            if (is_array($getUserData['favchannel']) && in_array($channelId, $getUserData['favchannel'])) {
                if (($key = array_search($channelId, $getUserData['favchannel'])) !== false) {
                    unset($getUserData['favchannel'][$key]);
                    $result = $collection->update(array("_id" => new MongoId($userId)), array('$set' => array('favchannel' => $getUserData['favchannel'], "mat" => time())));
                    if ($result['ok'] == 1) {
                        $getChannelData = $this->db->channel->findOne(array("_id" => new MongoId($channelId)));
                        if (($key = array_search($userId, $getChannelData['favuid'])) !== false) {
                            unset($getChannelData['favuid'][$key]);
                            $channelFav = array(
                                "favcount" => (isset($getChannelData['favcount']) && ($getChannelData['favcount'] > 0)) ? (int) $getChannelData['favcount'] - 1 : 0,
                                "favuid" => $getChannelData['favuid'],
                                "mat" => time()
                            );
                            $this->db->channel->update(array("_id" => new MongoId($channelId)), array('$set' => $channelFav));
                        }
                    }
                }
            } else {
                return $message = array("message" => 'You have already Mark as un favorite channel');
            }
        }
        /*
         * Code snippet to create user on Open fire server
         */
        $params['uid'] = $userId;
        $params['chid'] = $channelId;
        $method = 'POST';

        if ((int) ($isFavorite) == 1) {
            $uri = $this->openFireUrl . 'chatgrp/subscribe';
            $response = $this->rest_client->send($uri, $method, $params);
            $openFireData = json_decode($response->body);
            if (isset($openFireData->status) && $openFireData->status == 1) {
                $openfireStatus = 1;
            } else {
                $openfireStatus = 0;
            }
            $message = array("message" => 'Mark as favorite channel');
        }
        if ((int) ($isFavorite) == 0) {
            $uri = $this->openFireUrl . 'chatgrp/unsubscribe';
            $response = $this->rest_client->send($uri, $method, $params);
            $openFireData = json_decode($response->body);
            if (isset($openFireData->status) && $openFireData->status == 1) {
                $openfireStatus = 1;
            } else {
                $openfireStatus = 0;
            }
            $message = array("message" => 'Mark as un favorite channel');
        }
        return $message;
    }

    function sendPushToFavorite($receiverID, $channelId, $userName, $channelName) {
        $message = str_replace("<un>", $userName, $this->invitationMessage['favorite']);
        $sendMessage = str_replace("<cn>", $channelName, $message);
        $pushData = array(
            "recid" => new MongoId($receiverID),
            "cid" => $channelId,
            "type" => "favorite",
            "message" => $sendMessage,
            "cat" => time()
        );
        $this->db->pushdata->insert($pushData);
    }

    /**
     * Function to get favourite channel according to user
     * @param type $userId     
     * @return array
     */
    function getFavoriteChannel($userId) {
        $result = array();
        $getUserData = $this->db->user->findOne(array("_id" => new MongoId($userId)));
        if (count($getUserData) > 0) {
            if (isset($getUserData['favchannel']) && is_array($getUserData['favchannel']) && count($getUserData['favchannel']) > 0) {
                foreach ($getUserData['favchannel'] as $document) {

                    $channelData = $this->db->channel->findOne(array("_id" => new MongoId($document), "isactive" => 1));
                    if (count($channelData) > 0) {
                        $channelDetail['channelId'] = $channelData['_id']->{'$id'};
                        $channelDetail['channelName'] = $channelData['cn'];
                        $channelDetail['description'] = $channelData['des'];
                        $channelDetail['channelImage'] = (isset($channelData['cimg'])) ? $channelData['cimg'] : '';
                        $channelDetail['channelVideo'] = (isset($channelData['cvideo'])) ? $channelData['cvideo'] : '';
                        $channelDetail['channelThumb'] = (isset($channelData['cvideothumb'])) ? $channelData['cvideothumb'] : '';
                        $channelDetail['categoryName'] = (isset($channelData['category']['cname'])) ? $channelData['category']['cname'] : '';
                        $channelDetail['categoryId'] = (isset($channelData['category']['cid'])) ? $channelData['category']['cid'] : '';
                        $channelDetail['createdDate'] = (isset($channelData['cat'])) ? $channelData['cat'] : '';
                        $channelLocation = (isset($channelData['loc']['coordinate'])) ? $channelData['loc']['coordinate'] : array();
                        $channelDetail['coordinate'] = $channelLocation;
                        $channelDetail['isFavorite'] = 1;
                        $channelDetail['subscribeUser'] = (isset($channelData['favcount'])) ? $channelData['favcount'] : 0;
                        $channelDetail['weeklyUser'] = $this->comman_model->getWeeklyViewers($channelDetail['channelId']);
                        ;
                        $channelDetail['userId'] = (string) $channelData['uid'];
                        $defaultPic = base_url() . 'assets/profilepic/default_user_image.png';
                        $mongoUserresult = $this->db->user->findOne(array("_id" => new MongoId($channelData['uid'])));
                        $profileImage = (isset($mongoUserresult['pimg']) && $mongoUserresult['pimg'] != '') ? $mongoUserresult['pimg'] : $defaultPic;
                        $channelDetail['ownerImage'] = $profileImage;
                        $result[] = $channelDetail;
                    }
                }
                return $result;
            } else {
                return $result;
            }
        }
    }

    function mapMarkerUser($userId, $radius, $lat, $long) {
        //db.user.find({ "loc.coordinate" : { $near : [ 28.622293, 77.375198 ], $maxDistance: 0.10 } }).pretty()
        //loc: { $exists: true, $not: {$size: 0} }
        //long=>x axis
        //lat=>y axis
        $userResult = array();
        $response = array();
        $response['mapUser'] = array();
        $collection = $this->db->user;
        $collection->ensureIndex(array('loc.coordinate' => '2d'));
        //$collection->createIndex( array('loc.coordinate' => '2dsphere') );
        $radiusOfEarth = 3963.2; //avg radius of earth in miles
        //db.user.find({"loc.coordinate":{$geoWithin:{$centerSphere:[[-118.243683,34.0522],10000/6378.1]}}}).pretty()
        $locArray = array((float) ($long), (float) ($lat));
        $searchResult = $collection->find(array(
            "loc.coordinate" => array('$geoWithin' =>
                array('$centerSphere' => array($locArray, $radius / $radiusOfEarth)))
        ));
        if (count(iterator_to_array($searchResult)) > 0) {
            //get logged in user details
            $logedInUser = $this->getUser((string) $userId);
            //get all users info
            $userResult = iterator_to_array($searchResult);
            $favchannels = '';
            if (isset($logedInUser['favchannel'])) {
                $favchannels = $logedInUser['favchannel'];
            }
            foreach ($userResult as $nearUser) {
                $useRes = array();
                $useRes['id'] = (string) $nearUser['_id'];
                $useRes['name'] = $nearUser['name'];
                $useRes['nickName'] = $nearUser['nickname'];
                $useRes['userName'] = $nearUser['un'];
                $useRes['profileImage'] = $nearUser['pimg'];
                $useRes['location'] = $nearUser['loc'];
                if ($useRes['id'] == (string) $userId) {
                    $useRes['isCurrentUser'] = 1;
                } else {
                    $useRes['isCurrentUser'] = 0;
                }
                $useRes['isLive'] = 0;
                $useRes['isFav'] = 0;
                $getUserAllChannels = $this->getUserAllChannels($useRes['id']);
                if ($getUserAllChannels != 0) {
                    $getUserAllChannelsKeys = array_keys($getUserAllChannels);
                    if (is_array($favchannels) && count(array_intersect($favchannels, $getUserAllChannelsKeys)) > 0) {
                        $useRes['isFav'] = 1;
                    }
                    $getliveContentOfChannelIds = $this->getliveContentOfChannelIdsDESC($getUserAllChannelsKeys);
                    if ($getliveContentOfChannelIds != 0) {
                        $chinfo = array();
                        $getliveContentOfChannelIdsKey = array_keys($getliveContentOfChannelIds);
                        $chinfo['id'] = $getliveContentOfChannelIds[$getliveContentOfChannelIdsKey[0]]['cid'];
                        $chinfo['name'] = $getUserAllChannels[$chinfo['id']]['cn'];
                        $chinfo['channelImage'] = $getUserAllChannels[$chinfo['id']]['cimg'];
                        $chinfo['channelVideo'] = $getUserAllChannels[$chinfo['id']]['cvideo'];
                        $chinfo['channelThumb'] = $getUserAllChannels[$chinfo['id']]['cvideothumb'];
                        $useRes['isLive'] = 1;
                        $chinfo['contentInfo']['id'] = (string) $getliveContentOfChannelIds[$getliveContentOfChannelIdsKey[0]]['_id'];
                        $contentrating = (string) $getliveContentOfChannelIds[$getliveContentOfChannelIdsKey[0]]['cratid'];
                        $ratingDetails = $this->comman_model->getratingDetail($contentrating);
                        $chinfo['contentInfo']['broadcastRating'] = isset($contentrating) ? $contentrating : '';
                        $chinfo['contentInfo']['broadcastRatingName'] = isset($ratingDetails['name']) ? $ratingDetails['name'] : '';
                        $useRes['channelInfo'] = $chinfo;
                    }
                    $response['mapUser'][] = $useRes;
                }
            }
            return $response;
        } else {
            return $response;
        }
    }

    function getliveContentOfChannelIdsDESC($channelIds) {
        $getContentData = $this->db->channelcontent->find(array(
                    "cid" => array('$in' => $channelIds),
                    "isactive" => 1,
                    "actype" => 1,
                    "stime" => array('$lte' => time()),
                    "etime" => array('$gte' => time())))->sort(array("stime" => -1))->limit(1);
        if (count(iterator_to_array($getContentData)) > 0) {
            return iterator_to_array($getContentData);
        } else {
            return 0;
        }
    }

    function getUserAllChannels($userId) {
        $channelresult = $this->db->channel->find(array("uid" => new MongoId($userId), 'isactive' => 1));
        if (count(iterator_to_array($channelresult)) > 0) {
            return iterator_to_array($channelresult);
        } else {
            return 0;
        }
    }

    /**
     * Get channels by search params
     * @param type $location
     */
    function findChannels($searchParamsJson, $userId = NULL, $map = false) {
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
                    if ($key == 'loc') {
                        $long = isset($searchAttr->coordinate[0]) ? $searchAttr->coordinate[0] : '';
                        $lat = isset($searchAttr->coordinate[1]) ? $searchAttr->coordinate[1] : '';
                        $radius = isset($searchAttr->radius) ? $searchAttr->radius : -1;
                        if ($long && $lat) {
                            $searchParameters[] = array("loc" => array("coordinate" => array($long, $lat), 'radius' => $radius));
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
            if (count($sortParameters) == 0) {
                $sortParameters['cat'] = 1;
            }
            $searchData = array(
                'entity' => $entity,
                'searchMethod' => $searchMethod,
                'searchParameters' => $searchParameters,
                'sortParameters' => $sortParameters,
                'page' => $page,
                'docPerPage' => $docPerPage,
            );

            $searchResult = $this->search->search($searchData, $map);
            if ($searchResult) {
//$result = $this->parseChannelSearch($searchResult, $userId);
                $result = $this->channelSearch($searchResult, $userId);
                return $result;
            } else {
                $data = $this->comman_model->senderror('EE022');
                return $data;
            }
        } else {
            $data = $this->comman_model->senderror('EE022');
            return $data;
        }
    }

    function channelSearch($searchResult, $userId) {
        $result = array();
        $channelArray = array();
        $responsechannel = array();
        $channelContent = $this->db->channelcontent->find(array(), array('cid' => true, 'etime' => true, 'stime' => true));
        $broadcastLog = $this->db->userbroadcastlog->find();
        foreach ($searchResult as $document) {
            $channelArray[] = (string) $document['_id'];
            $channelArrayKey[(string) $document['_id']] = $this->generateChannelDetail($document, $userId, $channelContent, $broadcastLog);
        }
        if (count($channelArray) > 0) {
            $getliveContent = $this->getliveContentOfChannelIds($channelArray);
            $getUpcommingContent = $this->getUpcommingContent($channelArray);
            if ($getliveContent != 0) {
                foreach ($getliveContent as $contentData) {
                    if (!isset($responsechannel[$contentData['cid']])) {
                        $responsechannel[$contentData['cid']] = $channelArrayKey[$contentData['cid']];
                    }
                    if ($contentData['actype'] == 1) {
                        $responsechannel[$contentData['cid']]['liveEvents'][] = $this->comman_model->getUpcomingContent($contentData);
                    } else {
                        try {
                            $getInvite = $this->db->invitecontact->findOne(array(
                                "conid" => (string) $contentData['_id'],
                                '$or' => array(array("sid" => new MongoId($userId)),
                                    array("recid" => new MongoId($userId)))));
                            if (count($getInvite) > 0 || (string) $userId == (string) $contentData['uid']) {
                                $responsechannel[$contentData['cid']]['liveEvents'][] = $this->comman_model->getUpcomingContent($contentData);
                            }
                        } catch (Exception $exc) {
                            continue;
                        }
                    }
                }
            }
            if ($getUpcommingContent != 0) {
                foreach ($getUpcommingContent as $contentData) {
                    if (isset($responsechannel[$contentData['cid']]) && count($responsechannel[$contentData['cid']]) > 0) {
                        if ($contentData['actype'] == 1) {
                            $responsechannel[$contentData['cid']]['upcomingEvents'][] = $this->comman_model->getUpcomingContent($contentData);
                        } else {
                            try {
                                $getInvite = $this->db->invitecontact->findOne(array(
                                    "conid" => (string) $contentData['_id'],
                                    '$or' => array(array("sid" => new MongoId($userId)),
                                        array("recid" => new MongoId($userId)))));
                                if (count($getInvite) > 0 || (string) $userId == (string) $contentData['uid']) {
                                    $responsechannel[$contentData['cid']]['upcomingEvents'][] = $this->comman_model->getUpcomingContent($contentData);
                                }
                            } catch (Exception $exc) {
                                continue;
                            }
                        }
                    } else {
                        $responsechannel[$contentData['cid']] = $channelArrayKey[$contentData['cid']];
                        if ($contentData['actype'] == 1) {
                            $responsechannel[$contentData['cid']]['upcomingEvents'][] = $this->comman_model->getUpcomingContent($contentData);
                        } else {
                            try {
                                $getInvite = $this->db->invitecontact->findOne(array(
                                    "conid" => (string) $contentData['_id'],
                                    '$or' => array(array("sid" => new MongoId($userId)),
                                        array("recid" => new MongoId($userId)))));
                                if (count($getInvite) > 0 || (string) $userId == (string) $contentData['uid']) {
                                    $responsechannel[$contentData['cid']]['upcomingEvents'][] = $this->comman_model->getUpcomingContent($contentData);
                                }
                            } catch (Exception $exc) {
                                continue;
                            }
                        }
                    }
                }
            }
            foreach ($channelArrayKey as $key => $remainchannel) {
                if (!isset($responsechannel[$key])) {
                    $responsechannel[$key] = $remainchannel;
                }
                if (!isset($responsechannel[$key]['liveEvents'])) {
                    $responsechannel[$key]['liveEvents'] = array();
                }
                if (!isset($responsechannel[$key]['upcomingEvents'])) {
                    $responsechannel[$key]['upcomingEvents'] = array();
                }
            }
            foreach ($responsechannel as $value) {
                $result[] = $value;
            }
            return $result;
        } else {
            return $result;
        }
    }

    function getliveContentOfChannelIds($channelIds) {
        $getContentData = $this->db->channelcontent->find(array(
                    "cid" => array('$in' => $channelIds),
                    "isactive" => 1,
                    "stime" => array('$lte' => time()),
                    "etime" => array('$gte' => time())))->sort(array("stime" => 1));
        if (count(iterator_to_array($getContentData)) > 0) {
            return iterator_to_array($getContentData);
        } else {
            return 0;
        }
    }

    function getUpcommingContent($channelIds) {
        $getContentData = $this->db->channelcontent->find(array(
                    "cid" => array('$in' => $channelIds),
                    "isactive" => 1,
                    "stime" => array('$gt' => time())))->sort(array("stime" => 1));
        if (count(iterator_to_array($getContentData)) > 0) {
            return iterator_to_array($getContentData);
        } else {
            return 0;
        }
    }

    function generateChannelDetail($document, $userId, $channelContent, $broadcastLog) {
        $profileimage = base_url() . 'assets/profilepic/default_user_image.png';
        if (isset($document['uid']) && $document['uid'] != '') {
            $condition = array("_id" => new MongoId($document['uid']));
            $getUserData = $this->db->user->findOne($condition);
            if (count($getUserData) > 0) {
                $profileimage = $getUserData['pimg'];
            }
            if ($userId == '') {
                $userId = $document['uid'];
            }
        }
        $data = $this->comman_model->getChannelDurationRatingAndViewers($document, $channelContent, $broadcastLog);
        if (empty($data['sum'])) {
            $channelResult['brrat'] = 0;
        } else {
            $channelResult['brrat'] = number_format($data['rating'] / $data['noContent'], 1);
        }
        $channelResult['ureport'] = $data['reportCount'];
        $channelResult['viewer'] = $data['viewers'];
        $channelResult['moneyEarned'] = number_format((isset($document['uem'])) ? $document['uem'] : 0, 2);
        $channelResult['channelDuration'] = $data['channelDuration'];
        $channelResult['channelId'] = (string) $document['_id'];
        $channelResult['channelName'] = (isset($document['cn'])) ? $document['cn'] : '';
        $channelResult['description'] = (isset($document['des'])) ? $document['des'] : '';
        $channelResult['channelImage'] = (isset($document['cimg'])) ? $document['cimg'] : '';
        $channelResult['channelVideo'] = (isset($document['cvideo'])) ? $document['cvideo'] : '';
        $channelResult['channelThumb'] = (isset($document['cvideothumb'])) ? $document['cvideothumb'] : '';
        $channelResult['categoryName'] = (isset($document['category']['cname'])) ? $document['category']['cname'] : '';
        $channelResult['categoryId'] = (isset($document['category']['cid'])) ? $document['category']['cid'] : '';
        $channelResult['createdDate'] = (isset($document['cat'])) ? $document['cat'] : '';
        $channelResult['userId'] = (isset($document['uid'])) ? (string) $document['uid'] : '';
        $channelLocation = (isset($document['loc']['coordinate'])) ? $document['loc']['coordinate'] : array();
        $channelResult['coordinate'] = $channelLocation;
        $channelResult['userProfileImage'] = $profileimage;
        $channelResult['subscribeUser'] = (isset($document['favcount'])) ? $document['favcount'] : 0;
        if (!empty($document['channelId'])) {
            $channelResult['weeklyUser'] = $this->comman_model->getWeeklyViewers($document['channelId']);
        } else {
            $channelResult['weeklyUser'] = 0;
        }
        $channelResult['ownerImage'] = $profileimage;
        $getUserData = $this->db->user->findOne(array("_id" => new MongoId($userId)));
        if (count($getUserData) > 0) {
            if (isset($getUserData['favchannel']) && is_array($getUserData['favchannel']) && count($getUserData['favchannel']) > 0 && isset($channelResult['channelId'])) {
                if (in_array($channelResult['channelId'], $getUserData['favchannel'])) {
                    $isFavorite = 1;
                }
            }
        }
        $channelResult['isFavorite'] = (isset($isFavorite) && $isFavorite != 0) ? $isFavorite : 0;
        $channelResult['status'] = $document['isactive'];
        return $channelResult;
    }

    /**
     * Function to parse the result of search made for channels
     * @param type $searchResult
     * @param type $userId
     */
    function parseChannelSearch($searchResult, $userId) {
//return $this->channelSearch($searchResult, $userId);
        $result = array();
        $cursor = $this->db->channel->find();
        foreach ($searchResult['data'] as $document) {
            $islive = 0;
            $profileimage = base_url() . 'assets/profilepic/default_user_image.png';
            $i = 0;
            $isFavorite = 0;
            if (isset($document['uid']) && $document['uid'] != '') {
                $condition = array("_id" => new MongoId($document['uid']));
                $getUserData = $this->db->user->findOne($condition);
                if (count($getUserData) > 0) {
                    $profileimage = $getUserData['pimg'];
                }
                if ($userId == '') {
                    $userId = $document['uid'];
                }
            }
            if ($document['isactive'] == 1) {
                $channelResult['channelId'] = (string) $document['_id'];

                $channelResult['channelName'] = (isset($document['cn'])) ? $document['cn'] : '';
                $channelResult['description'] = (isset($document['des'])) ? $document['des'] : '';
                $channelResult['channelImage'] = (isset($document['cimg'])) ? $document['cimg'] : '';
                $channelResult['channelVideo'] = (isset($document['cvideo'])) ? $document['cvideo'] : '';
                $channelResult['channelThumb'] = (isset($document['cvideothumb'])) ? $document['cvideothumb'] : '';
                $channelResult['categoryName'] = (isset($document['category']['cname'])) ? $document['category']['cname'] : '';
                $channelResult['categoryId'] = (isset($document['category']['cid'])) ? $document['category']['cid'] : '';
                $channelResult['createdDate'] = (isset($document['cat'])) ? $document['cat'] : '';
                $channelResult['userId'] = (isset($document['uid'])) ? (string) $document['uid'] : '';
                $channelLocation = (isset($document['loc']['coordinate'])) ? $document['loc']['coordinate'] : array();
                $channelResult['coordinate'] = $channelLocation;
                $channelResult['userProfileImage'] = $profileimage;
                $channelResult['subscribeUser'] = (isset($document['favcount'])) ? $document['favcount'] : 0;
                $channelResult['weeklyUser'] = $this->comman_model->getWeeklyViewers($channelResult['channelId']);

                $channelResult['ownerImage'] = $profileimage;
                $getUserData = $this->db->user->findOne(array("_id" => new MongoId($userId)));
                if (count($getUserData) > 0) {
                    if (isset($getUserData['favchannel']) && is_array($getUserData['favchannel']) && count($getUserData['favchannel']) > 0 && isset($channelResult['channelId'])) {
                        if (in_array($channelResult['channelId'], $getUserData['favchannel'])) {
                            $isFavorite = 1;
                        }
                    }
                }
                $channelResult['isFavorite'] = (isset($isFavorite) && $isFavorite != 0) ? $isFavorite : 0;

                $getContentData = $this->db->channelcontent->find(array("cid" => $channelResult['channelId']));
                $channelUpComingData = array();
                $channelLiveData = array();
                if (count($getContentData) > 0) {
                    foreach ($getContentData as $content) {
                        $startTime = $content['stime'];
                        $ratingName = (isset($content['rating']['crname'])) ? $content['rating']['crname'] : '';
                        $ratingId = (isset($content['rating']['cratid'])) ? $content['rating']['cratid'] : '';
                        if ($content['actype'] == 1) {
                            if ($startTime > time()) {
                                $contentDetail = $this->comman_model->getUpcomingContent($content);
                                array_push($channelUpComingData, $contentDetail);
                            }
                            if ($startTime <= time() && $content['etime'] > time()) {
                                $liveDetail = $this->comman_model->getLiveContent($content);
                                array_push($channelLiveData, $liveDetail);
                                $islive = 1;
                            }
                        } else {
                            try {
                                $getInvite = $this->db->invitecontact->findOne(array("conid" => (string) $content['_id'], '$or' => array(array("sid" => new MongoId($userId)), array("recid" => new MongoId($userId)))));
                                if (count($getInvite) > 0) {
                                    if ($startTime > time()) {
                                        $contentDetail = $this->comman_model->getUpcomingContent($content);
                                        array_push($channelUpComingData, $contentDetail);
                                    }
                                    if ($startTime <= time() && $content['etime'] > time()) {
                                        $liveDetail = $this->comman_model->getLiveContent($content);
                                        array_push($channelLiveData, $liveDetail);
                                        $islive = 1;
                                    }
                                }
                            } catch (Exception $exc) {
                                continue;
                            }
                        }
                    }
                    $channelResult['upcomingEvents'] = $channelUpComingData;
                    $channelResult['liveEvents'] = $channelLiveData;
                }
                if ($islive == 1) {
                    array_unshift($result, $channelResult);
                } else {
                    array_push($result, $channelResult);
                }
            }
        }

        return $result;
    }

    /**
     * Function to sort the result
     * @param type $arr
     * @param type $col
     */
    function array_sort_by_column(&$arr, $col, $dir = SORT_ASC) {
        $sort_col = array();
        foreach ($arr as $key => $row) {
            $sort_col[$key] = $row[$col];
        }
        array_multisort($sort_col, $dir, $arr);
    }

    /**
     * Function to get a all rating data           
     */
    function getRatingType() {
        $collection = $this->db->ratingmaster;
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
     * Function to get a all report msg           
     */
    function getReportMsg() {
        $collection = $this->db->reportmsg;
        $cursor = $collection->find();
        $result = array();
        foreach ($cursor as $document) {
            $report['msgId'] = (string) $document['_id'];
            $report['msg'] = $document['msg'];
            $result[] = $report;
        }
        return $result;
    }

    /**
     * Function to get a all channel plan list      
     */
    function getChannelPlan($userId) {
        $collection = $this->db->plan;
        $cursor = $collection->find()->sort(array("price" => 1));
        $result = array();
        $resultFinal = array();
        $userPlan = $this->getUserCurrentPlan($userId);
        /* if (count($userPlan) > 0) {
          $planData['status'] = $userPlan['st'];
          $planData['planId'] = (string) $userPlan['_id'];
          $planData['name'] = $userPlan['name'];
          $planData['price'] = $userPlan['price'];
          $planData['broadcastLength'] = $userPlan['blength'];
          $planData['broadcastNumber'] = $userPlan['bnum'];
          $planData['channelNumber'] = $userPlan['cnum'];
          $planData['saveBroadcast'] = $userPlan['sbcast'];
          $planData['broadcastType'] = $userPlan['btype'];
          $planData['pimg'] = (isset($userPlan['pimg'])) ? $userPlan['pimg'] : '';
          $planData['pdesc'] = (isset($userPlan['pdesc'])) ? $userPlan['pdesc'] : '';
          $planData['googleItemId'] = (isset($userPlan['googleItemId'])) ? $userPlan['googleItemId'] : '';
          $planData['appleItemId'] = (isset($userPlan['appleItemId'])) ? $userPlan['appleItemId'] : '';
          $resultFinal['activeUserPlan'] = $planData;
          } */
        foreach ($cursor as $document) {
            if ($document['st'] == 1) {
                $planData['status'] = $document['st'];
                $planData['planId'] = (string) $document['_id'];
                $planData['name'] = $document['name'];
                $planData['price'] = $document['price'];
                $planData['demoReelLength'] = (isset($document['ldreel'])) ? $document['ldreel'] : '';
                $planData['broadcastLength'] = $document['blength'];
                $planData['broadcastNumber'] = $document['bnum'];
                $planData['channelNumber'] = $document['cnum'];
                $planData['saveBroadcast'] = $document['sbcast'];
                $planData['broadcastType'] = $document['btype'];
                $planData['pimg'] = (isset($document['pimg'])) ? $document['pimg'] : '';
                $planData['pdesc'] = (isset($document['pdesc'])) ? $document['pdesc'] : '';
                $planData['googleItemId'] = (isset($document['googleItemId'])) ? $document['googleItemId'] : '';
                $planData['appleItemId'] = (isset($document['appleItemId'])) ? $document['appleItemId'] : '';
                if (isset($userPlan['price']) && $planData['price'] < $userPlan['price']) {
                    $planData['planState'] = "Downgrade";
                } else if (isset($userPlan['price']) && $userPlan['price'] == $planData['price']) {
                    $planData['planState'] = "Current";
                } else {
                    $planData['planState'] = "Upgrade";
                }
                $result[] = $planData;
            }
        }
        if (count($result) > 0) {
            $resultFinal['plans'] = $result;
        }
        return $resultFinal;
    }

    /**
     * Function to update channel plan list 
     * @param type $userId  
     * @param type $PlanId       
     */
    function updateChannelPlan($userId, $PlanId) {
        $collection = $this->db->plan;
        $checkExist = $collection->findOne(array("_id" => new MongoId($PlanId)));
        if (count($checkExist) > 0) {
            $where = array("_id" => new MongoId($userId));
            $this->db->user->update($where, array('$set' => array('pid' => $PlanId, 'savebcast' => (int) $checkExist['sbcast'])));

            return $error = array("message" => 'Plan has been changed successfully.');
        } else {
            return $error = $this->comman_model->senderror('EE033');
        }
    }

    /**
     * Function to get a send invitation
     * @param type $userId       
     */
    function getSendInvitation($userId) {

        $result = $sendData = $getData = array();
        $getUserRecord = $this->db->user->findOne(array("_id" => new MongoId($userId)));
        if (count($getUserRecord) > 0) {
            $userName = $getUserRecord['un'];
            $result = $this->getInvitationData($getUserRecord, $userId, $userName);
            return $result;
        }
    }

    /**
     * Function to get a send/invite conetnt
     * @param type $getUserRecord 
     * @param type $userId 
     * @param type $userName       
     */
    function getInvitationData($getUserRecord, $userId, $userName) {
        $collection = $this->db->invitecontact;
        $result = $sendData = $getData = array();
        if (count($getUserRecord) > 0) {
            $userConatctNo = (isset($getUserRecord['upno']) && $getUserRecord['upno'] != '') ? $getUserRecord['upno'] : '';
            $cursor = $collection->find(array('$or' => array(array("sid" => new MongoId($userId)), array("recid" => new MongoId($userId)))))->sort(array("cat" => -1));
            if (count(iterator_to_array($cursor)) > 0) {
                foreach ($cursor as $document) {
                    $inviteData['inviteId'] = (string) $document['_id'];
                    $inviteData['senderId'] = (isset($document['sid'])) ? (string) $document['sid'] : '';
                    $senderName = $this->getSenderName($inviteData['senderId']);
                    $inviteData['receiverId'] = (isset($document['recid'])) ? (string) $document['recid'] : '';
                    $receiverName = $this->getReceiverName($inviteData['receiverId']);
                    $inviteData['name'] = $document['name'];
                    $inviteData['senderName'] = (isset($senderName)) ? $senderName : "";
                    $inviteData['receiverName'] = (isset($receiverName) && $receiverName != "") ? $receiverName : $document['name'];
                    $inviteData['userName'] = $userName;
                    $inviteData['phoneNumber'] = $document['pno'];
                    $inviteData['channelId'] = $document['cid'];
                    $getchannelData = $this->db->channel->findOne(array("_id" => new MongoId($inviteData['channelId'])));
                    if (count($getchannelData) > 0) {
                        $inviteData['channelName'] = (isset($getchannelData['cn'])) ? $getchannelData['cn'] : '';
                        $inviteData['channelImage'] = (isset($getchannelData['cimg']) && $getchannelData['cimg'] != '') ? $getchannelData['cimg'] : '';
                    } else {
                        $inviteData['channelName'] = '';
                        $inviteData['channelImage'] = '';
                    }
                    $inviteData['contentId'] = (isset($document['conid'])) ? $document['conid'] : '';
                    $getcontentData = $this->db->channelcontent->findOne(array("_id" => new MongoId($inviteData['contentId'])));
                    if (count($getchannelData) > 0) {
                        $inviteData['contentName'] = (isset($getcontentData['name'])) ? $getcontentData['name'] : '';
                        if ($getcontentData['stime'] <= time() && $getcontentData['etime'] > time()) {
                            $inviteData['live'] = 1;
                        } else {
                            $inviteData['live'] = 0;
                        }
                    } else {
                        $inviteData['contentName'] = '';
                    }
                    $inviteData['createdDate'] = $document['cat'];
                    $inviteData['status'] = $document['st'];

                    if ($inviteData['senderId'] == $userId) {
                        array_push($sendData, $inviteData);
                    }
                    if ($inviteData['receiverId'] == $userId && ($document['st'] == 1 || $document['st'] == 2)) {
                        array_push($getData, $inviteData);
                    }
                    $result['sendInvitation'] = $sendData;
                    $result['getInvitation'] = $getData;
                }
            } else {
                $result['sendInvitation'] = $sendData;
                $result['getInvitation'] = $getData;
            }
            return $result;
        }
    }

    /**
     * Function to get a sender name of conetnt
     * @param type $senderId             
     */
    function getSenderName($senderId) {
        $getSenderData = $this->db->user->findOne(array("_id" => new MongoId($senderId)));
        $senderName = "";
        if (count($getSenderData) > 0) {
            $senderName = (isset($getSenderData['nickname']) && $getSenderData['nickname'] != "") ? $getSenderData['nickname'] : "";
        }
        return $senderName;
    }

    /**
     * Function to get a receiver name of conetnt
     * @param type $receiverId           
     */
    function getReceiverName($receiverId) {
        $reciverName = "";
        if ($receiverId != "") {
            $getReceiverData = $this->db->user->findOne(array("_id" => new MongoId($receiverId)));
            if (count($getReceiverData) > 0) {
                $reciverName = (isset($getReceiverData['nickname']) && $getReceiverData['nickname'] != "") ? $getReceiverData['nickname'] : "";
            }
        }
        return $reciverName;
    }

    /**
     * Function to update invitation status
     * @param type $userId
     * @param type $contentId
     * @param type $status
     */
    function updateInvitationStatus($userId, $contentId, $status) {
        $collection = $this->db->user;
        $getUserRecord = $collection->findOne(array("_id" => new MongoId($userId)));
        if (count($getUserRecord) > 0) {
            $userConatctNo = (isset($getUserRecord['upno']) && $getUserRecord['upno'] != '' ) ? $getUserRecord['upno'] : '';
            $userName = $getUserRecord['un'];
            if ($userConatctNo != "") {
                $getInviteData = $this->db->invitecontact->findOne(array("pno" => $userConatctNo, "conid" => $contentId));
                if (count($getInviteData) > 0) {
                    $inviteId = (string) $getInviteData['_id'];
                    $status = ((integer) $status == 1) ? 2 : 0;
                    if ((integer) $status == 2) {
                        $sendPushNotifcation = $this->sendPushToAccept($contentId, $getUserRecord['nickname']);
                    }
                    $this->db->invitecontact->update(array("_id" => new MongoId($inviteId)), array('$set' => array('st' => (integer) $status)));
                    $result = $this->getInvitationData($getUserRecord, $userId, $userName);
                    return $result;
                } else {
                    return $error = $this->comman_model->senderror('EE051');
                }
            } else {
                return $error = $this->comman_model->senderror('EE051');
            }
        }
    }

    /**
     * Function to send a push notification to content owner
     * @param type $contentId
     * @param type $userName
     */
    function sendPushToAccept($contentId, $userName) {
        $getConetntData = $this->db->channelcontent->findOne(array("_id" => new MongoId($contentId)));
        $message = str_replace("<un>", $userName, $this->invitationMessage['accept']);
        $sendMessage = str_replace("<cn>", $getConetntData['name'], $message);
        if (count($getConetntData) > 0) {
            $contentownerId = (isset($getConetntData['uid'])) ? (string) $getConetntData['uid'] : "";
            $pushData = array(
                "recid" => new MongoId($contentownerId),
                "cid" => $getConetntData['cid'],
                "conid" => $contentId,
                "type" => "contentAccept",
                "message" => $sendMessage,
                "cat" => time()
            );
            $this->db->pushdata->insert($pushData);
            return true;
            /* if (!empty($contentownerId)) {
              $getUserData = $this->db->user->findOne(array("_id" => new MongoId($contentownerId)));
              if (count($getUserData) > 0 && isset($getUserData['getpush']) && $getUserData['getpush'] == 1) {
              $getDeviceInfo = $this->db->userdeviceinfo->findOne(array("uid" => new MongoId($contentownerId)));
              if (count($getDeviceInfo) > 0) {
              $deviceCollection = $getDeviceInfo['dvcs'];
              if (count($deviceCollection) > 0) {
              foreach ($deviceCollection as $val) {
              if ($val['st'] == 1 && !empty($val['dregid'])) {
              if ((int) $val['dtype'] == 1) {
              $gcmData[] = $val['dregid'];
              }
              if ((int) $val['dtype'] == 2) {
              $iosData[] = $val['dregid'];
              }
              }
              }
              }
              }
              }
              }
              if (count($gcmData) > 0) {
              $this->pushnotification_model->pushAndroidNotification($gcmData, $sendMessage, "contentAccept");
              }
              if (count($iosData) > 0) {
              $this->pushnotification_model->pushIosNotification($iosData, $sendMessage, "contentAccept");
              }
              return true; */
        }
        return true;
    }

    /**
     * Function to create weekly viewers
     * @param type $userId
     * @param type $channelId
     */
    function createWeeklyViewers($userId, $channelId) {
        $getChannelData = $this->db->channel->findOne(array("_id" => new MongoId($channelId)));
        if (count($getChannelData) > 0) {
            $getOwnerID = (isset($getChannelData['uid']) && $getChannelData['uid'] != "") ? (string) $getChannelData['uid'] : "";
            if ($getOwnerID != $userId) {
                $collection = $this->db->weeklyviewer;
                $weeklyData = array(
                    'cid' => $channelId,
                    'uid' => new MongoId($userId),
                    'cat' => time()
                );
                $collection->insert($weeklyData);
            }
            return $result = array("message" => 'Successfully');
        }
    }

}

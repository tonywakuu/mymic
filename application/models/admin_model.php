<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

require_once realpath(__DIR__ . '/../..') . '/application/appUtil/exceptiongenerator.php';
require_once realpath(__DIR__ . '/../..') . '/application/appUtil/apputil.php';
require_once realpath(__DIR__ . '/../..') . '/php-ravel/controller/user.php';
require_once realpath(__DIR__ . '/../..') . '/php-ravel/controller/search.php';
require_once realpath(__DIR__ . '/../..') . '/application/models/comman_model.php';
require_once realpath(__DIR__ . '/../..') . '/php-ravel/model/sendMailModel.php';

class admin_model extends CI_Model {

    private $db, $mongo, $timezone, $mailModel;

    function admin_model() {
        parent::__construct();
        $this->load->helper('string');
        $this->load->helper('form');
        $this->load->helper('url');
        $this->load->library('user_agent');
        $this->load->helper('text');
        $this->load->library('session');
        $this->load->library('rest_client');
        $this->load->helper('date');
        $this->mongo = $this->config->config['mongo'];
        $this->openFireUrl = $this->config->config['openfire_url'];
        $dbName = $this->config->config['mongoDb'];
        $this->db = $this->mongo->$dbName;
        $this->isLiveBroadcast = $this->config->config['isLiveBroadcast'];
        $this->status = $this->config->config['status'];
        $this->timePeriod = $this->config->config['timePeriod'];
        $this->MAX_RECORD = $this->config->config['MAX_RECORD'];
        $this->user = new user();
        $this->search = new search();
        $this->comman_model = new comman_model();
        $this->exceptiongenerator = new exceptiongenerator();
        $this->appUtil = new appUtil();
        $this->mailModel = new sendMailModel();
        $timezone = $this->config->config['timezone'];
        $this->adminTokenExpTime = $this->config->config['adminTokenExpTime'];
        $this->rememberMe = $this->config->config['rememberMe'];
        $this->endBroadcastPublisher = $this->config->config['endBroadcastPublisher'];
        $this->endBroadcastClient = $this->config->config['endBroadcastClient'];
    }

    /**
     * @param $userName : username
     * @param $password : password
     * @return array : return array of user
     */
    function login($userName, $password, $rememberMe) {
        $pwd = hash("sha256", $password . 'ravel@');
        $collection = $this->db->adminuser;
        $user = $collection->findOne(array("un" => $userName, "pwd" => $pwd, "st" => $this->status['active']));
        if (sizeof($user) <= 0) {
            $result = $this->exceptiongenerator->sendError('EE001');
            return $result;
        }
        if (count($user) > 0) {
            if ($user['pwd'] == $pwd) {
                $auth = $this->generateToken($user['_id'], $rememberMe);
                $result = array();
                $result['id'] = (string) $user['_id'];
                $result['userName'] = $user['un'];
                $result['accessToken'] = $auth['accessToken'];
                $result['createdOn'] = $user['cat'];
                return $result;
            } else {
                $result = $this->exceptiongenerator->sendError('EE002');
                return $result;
            }
        }
    }

    /**
     * To get all user list
     */
    function getUserList($startIndex, $limit, $data = NULL) {
        if (empty($startIndex)) {
            $startIndex = 0;
        }
        if (empty($limit)) {
            $limit = $this->MAX_RECORD;
        }
        if (!empty($data)) {
            $cursor = $data;
        } else {
            $collection = $this->db->user;
            $cursor = $collection->find()->sort(array("cat" => -1))->skip($startIndex)->limit($limit);
        }
        $channelCollection = $this->db->channel;
        $channels = $channelCollection->find(array('isactive' => 1));
        $planCollection = $this->db->plan;
        $plans = $planCollection->find(array(), array("name" => true));
        if (count($cursor) > 0) {
            $result = array();
            foreach ($cursor as $document) {
                $noOfChannel = 0;
                foreach ($channels as $channel) {
                    if (isset($document['_id']) && (string) $document['_id'] == (string) $channel['uid']) {
                        $noOfChannel++;
                    }
                }
                foreach ($plans as $plan) {
                    if (isset($document['pid']) && (string) $document['pid'] == (string) $plan['_id']) {
                        $userData['planName'] = $plan['name'];
                    }
                }
                if (isset($document['_id'])) {
                    $userData['noChannel'] = $noOfChannel;
                    $userData['userid'] = (string) $document['_id'];
                    $userData['created_date'] = $document['cat'];
                }
                if (isset($document['un'])) {
                    $userData['username'] = $document['un'];
                }
                if (isset($document['name'])) {
                    $userData['name'] = $document['name'];
                }
                if (isset($document['pimg'])) {
                    $userData['profileimage'] = $document['pimg'];
                }
                if (isset($document['email']) && !empty($document['email'])) {
                    $userData['email'] = $document['email'];
                } else {
                    $userData['email'] = '';
                }
                if (isset($document['fname'])) {
                    $userData['firstname'] = $document['fname'];
                }
                if (isset($document['lname'])) {
                    $userData['lastname'] = $document['lname'];
                }
                if (isset($document['pno']) && !empty($document['pno'])) {
                    $phoneNumber = str_repeat('*', (strlen($document['pno']) - 4)) . substr($document['pno'], - 4);
                    $userData['phonenumber'] = $phoneNumber;
                } else {
                    $userData['phonenumber'] = "Not verified";
                }
                if (isset($document['pid'])) {
                    $userData['plan_id'] = $document['pid'];
                }
                if (isset($document['st'])) {
                    $userData['status'] = $document['st'];
                }
                if (isset($document['type'])) {
                    if ($document['type'] == 1) {
                        $userData['type'] = "Email";
                    }
                    if ($document['type'] == 2) {
                        $userData['type'] = "Facebook";
                    }
                    if ($document['type'] == 3) {
                        $userData['type'] = "Twitter";
                    }
                    if ($document['type'] == 4) {
                        $userData['type'] = "Instagram";
                    }
                }
                $userData['moneyEarned'] = number_format((isset($document['uem'])) ? $document['uem'] : 0, 2);
                $userData['cashIn'] = number_format((isset($document['cin'])) ? $document['cin'] : 0, 2);
                $result[] = $userData;
            }
            return $result;
        } else {
            $error = $this->exceptiongenerator->sendError('EE047');
            return $error;
        }
    }

    /**
     * @param $userId : user id
     * @param $isActive : status
     * @return array : return response status updated successfully
     */
    function updateUser($userId, $isActive) {
        $id = $this->validateMongoId($userId);
        if (!empty($id)) {
            $userQuery['_id'] = $id;
            $updateUser = array();
            $updateUser['st'] = $isActive;
            $updateUser['mat'] = $this->appUtil->getCurrentTimeStamp();
            $userResult = $this->db->user->update($userQuery, array('$set' => $updateUser));
            $channelQuery['uid'] = $id;
            $updateChannel = array();
            $updateChannel['isactive'] = $isActive;
            $updateChannel['mat'] = $this->appUtil->getCurrentTimeStamp();
            $channelResult = $this->db->channel->update($channelQuery, array('$set' => $updateChannel), array('multiple' => true));
            $contentQuery['uid'] = $id;
            $updateContent = array();
            $updateContent['isactive'] = $isActive;
            $updateContent['mat'] = $this->appUtil->getCurrentTimeStamp();
            $contentResult = $this->db->channelcontent->update($contentQuery, array('$set' => $updateContent), array('multiple' => true));
            if (count($userResult) > 0) {
                $userResult = array();
                if ($isActive == $this->status['active']) {
                    $userResult['message'] = $this->exceptiongenerator->sendSuccess('RR005');
                }
                if ($isActive == $this->status['inActive']) {
                    $userResult['message'] = $this->exceptiongenerator->sendSuccess('RR006');
                }
                return $userResult;
            } else {
                $error = $this->exceptiongenerator->sendError('EE020');
                return $error;
            }
        } else {
            $error = $this->exceptiongenerator->sendError('EE048');
            return $error;
        }
    }

    /**
     * Get channel list
     */
    function getChannelList($startIndex, $limit, $userId, $data = NULL) {
        if (empty($startIndex)) {
            $startIndex = 0;
        }
        if (empty($limit)) {
            $limit = $this->MAX_RECORD;
        }
        $query = array();
        if ($userId != '') {
            $query['uid'] = new MongoId($userId);
            $query['isactive'] = 1;
        }
        if (!empty($data)) {
            $channels = $data;
        } else {
            $collection = $this->db->channel;
            $channels = $collection->find($query)->sort(array("cat" => -1))->skip($startIndex)->limit($limit);
        }
        $userQuery = array();
        if (!empty($userId)) {
            $userQuery['_id'] = new MongoId($userId);
        }
        $content = $this->db->channelcontent->find();
        $userCollection = $this->db->user;
        $users = $userCollection->find($userQuery, array('_id' => true, 'un' => true));
        $broadcastLog = $this->db->userbroadcastlog->find();
        if (count($channels) > 0) {
            $channelList = array();
            foreach ($channels as $channel) {
                foreach ($users as $user) {
                    if ($user['_id'] == $channel['uid']) {
                        $channelData['channelOwnerName'] = (string) $user['un'];
                        $channelData['userId'] = (string) $user['_id'];
                    }
                }
                $sum = 0;
                $count = 0;
                $reportCount = 0;
                $noContent = 0;
                $rating = 0;
                $viewers = 0;
                $channelDuration = 0;
                $noOfLiveBroadcast = 0;
                foreach ($content as $channelContent) {
                    if ($channelContent['cid'] == (string) $channel['_id']) {
                        $noContent++;
                        if (isset($channelContent['brrat']) && count($channelContent['brrat']) > 0) {
                            foreach ($channelContent['brrat'] as $brRating) {
                                if (isset($brRating['urat'])) {
                                    $count = $count + 1;
                                    $sum = $sum + $brRating['urat'];
                                }
                                if (isset($brRating['ureport'])) {
                                    $reportCount = $reportCount + $brRating['ureport'];
                                }
                            }
                            if (!empty($sum)) {
                                $rating = $rating + ($sum / $count);
                            }
                        }
                        $duration = $channelContent['etime'] - $channelContent['stime'];
                        $channelDuration = $channelDuration + $duration;
                        if ($broadcastLog && count($broadcastLog) > 0) {
                            foreach ($broadcastLog as $joiners) {
                                if ($joiners['cid'] == (string) $channelContent['_id']) {
                                    $viewers = $viewers + 1;
                                }
                            }
                        }
                        if ($channelContent['stime'] <= $this->appUtil->getCurrentTimeStamp() && $channelContent['etime'] > $this->appUtil->getCurrentTimeStamp()) {
                            $noOfLiveBroadcast++;
                        }
                    }
                }
                if (empty($sum)) {
                    $channelData['brrat'] = 0;
                } else {
                    $channelData['brrat'] = number_format($rating / $noContent, 1);
                }
                $channelData['channelId'] = (string) $channel['_id'];
                if (isset($channel['favcount'])) {
                    $channelData['favCount'] = $channel['favcount'];
                } else {
                    $channelData['favCount'] = 0;
                }
                $channelData['channelDuration'] = $channelDuration;
                $channelData['userId'] = (string) $channel['uid'];
                $channelData['channelName'] = $channel['cn'];
                $channelData['description'] = $channel['des'];
                $channelData['channelImg'] = $channel['cimg'];
                $channelData['createdOn'] = $channel['cat'];
                $channelData['status'] = $channel['isactive'];
                $channelData['categoryName'] = $channel['category']['cname'];
                $channelData['rating'] = 0;
                $channelData['ureport'] = $reportCount;
                $channelData['viewer'] = $viewers;
                $channelData['noOfLiveBroadcast'] = $noOfLiveBroadcast;
                $channelData['moneyEarned'] = number_format((isset($channel['uem'])) ? $channel['uem'] : 0, 2);
                $channelList[] = $channelData;
            }
            return $channelList;
        } else {
            $error = $this->exceptiongenerator->sendError('EE047');
            return $error;
        }
    }

    /**
     * @param $channelId : channel id
     * @param $status : status of channel
     * @return array : return response channel updated successfully
     */
    function updateChannel($channelId, $status, $moneyEarned, $userId, $desc) {
        $id = $this->validateMongoId($channelId);
        if (!empty($id)) {
            $query['_id'] = $id;
            $update = array();
            $update['mat'] = $this->appUtil->getCurrentTimeStamp();
            $collection = $this->db->channel;
            if ($moneyEarned != '' && $userId != '') {
                $update['desc']  = $desc;
                $this->db->user->update(array('_id' => new MongoId($userId)), array('$inc' => array('uem' => $moneyEarned),'$set' => $update));
                $result = $collection->update($query, array('$inc' => array('uem' => $moneyEarned),
                    '$set' => $update));
                $update['cat'] = $this->appUtil->getCurrentTimeStamp();
                $update['uem'] = $moneyEarned;
                $update['uid'] = new MongoId($userId);
                $update['cid'] = $id;
                $this->db->usermoneyearnhistory->insert($update);
            } else {
                if (empty($status)) {
                    $update['isactive'] = 0;
                } else {
                    $update['isactive'] = 1;
                }
                $result = $collection->update($query, array('$set' => $update));
            }
            if (!empty($result)) {
                $channelResult = array();
                if ($status == $this->status['active']) {
                    $channelResult['message'] = $this->exceptiongenerator->sendSuccess('RR003');
                }
                if ($status == $this->status['inActive']) {
                    $channelResult['message'] = $this->exceptiongenerator->sendSuccess('RR004');
                }
                if ($moneyEarned != '') {
                    $channelResult['message'] = $this->exceptiongenerator->sendSuccess('RR0011');
                }
                return $channelResult;
            } else {
                $error = $this->exceptiongenerator->sendError('EE020');
                return $error;
            }
        } else {
            $error = $this->exceptiongenerator->sendError('EE048');
            return $error;
        }
    }

    /*
     * get plan list
     * return list of plans
     */

    function getPlanList($startIndex, $limit, $data = NULL) {
        if (empty($startIndex)) {
            $startIndex = 0;
        }
        if (empty($limit)) {
            $limit = $this->MAX_RECORD;
        }
        if (!empty($data)) {
            $plans = $data;
        } else {
            $collection = $this->db->plan;
            $plans = $collection->find()->sort(array("cat" => -1))->skip($startIndex)->limit($limit);
        }
        if (count($plans) > 0) {
            $planList = array();
            foreach ($plans as $plan) {
                $planData['planId'] = (string) $plan['_id'];
                $planData['name'] = $plan['name'];
                $planData['price'] = number_format(ltrim($plan['price'], "$"), 2);
                $planData['noBroadcast'] = $plan['bnum'];
                $planData['noChannel'] = $plan['cnum'];
                $planData['saveBroadcast'] = $plan['sbcast'];
                if (isset($plan['btype'])) {
                    $planData['btype'] = $plan['btype'];
                } else {
                    $planData['btype'] = 0;
                }
                if (isset($plan['ldreel'])) {
                    $planData['demoReelLength'] = $plan['ldreel'];
                } else {
                    $planData['demoReelLength'] = 0;
                }
                $planData['broadcastLength'] = $plan['blength'];
                if (isset($plan['pdesc']) && !empty($plan['pdesc'])) {
                    $planData['planDescription'] = $plan['pdesc'];
                } else {
                    $planData['planDescription'] = '';
                }
                if (isset($plan['pimg']) && !empty($plan['pimg'])) {
                    $planData['planImg'] = $plan['pimg'];
                } else {
                    $planData['planImg'] = '';
                }
                if (isset($plan['googleItemId']) && !empty($plan['googleItemId'])) {
                    $planData['googleItemId'] = $plan['googleItemId'];
                } else {
                    $planData['googleItemId'] = "";
                }
                if (isset($plan['appleItemId']) && !empty($plan['appleItemId'])) {
                    $planData['appleItemId'] = $plan['appleItemId'];
                } else {
                    $planData['appleItemId'] = "";
                }
                $planData['status'] = $plan['st'];
                $planList[] = $planData;
            }
            return $planList;
        } else {
            $error = $this->exceptiongenerator->sendError('EE047');
            return $error;
        }
    }

    /**
     * @param $planId : plan id
     * @return array : return plan added and updated successfully
     */
    function addUpdatePlan($plan, $planId) {
        $query = array();
        $collection = $this->db->plan;
        if (!empty($planId)) {
            $id = $this->validateMongoId($planId);
            $query['_id'] = $id;
            if (isset($plan['name']) && !empty($plan['name'])) {
                $planName = str_replace(" ", "_", $plan['name']);
            } else {
                $plans = $collection->findOne($query, array('name' => true));
                $planName = str_replace(" ", "_", $plans['name']);
            }
        } else {
            $query['_id'] = new MongoId();
            $plan['cat'] = $this->appUtil->getCurrentTimeStamp();
            if (isset($plan['name']) && !empty($plan['name'])) {
                $planName = str_replace(" ", "_", $plan['name']);
            }
        }
        if (isset($_FILES['fileUpload']) && count($_FILES) > 0) {
            $uploadFile = $this->comman_model->s3FileUpload($_FILES['fileUpload']);
            if ($uploadFile != 0) {
                $imagePath = $uploadFile['url'];
                $path = $imagePath;
            } else {
                $imagePath = $this->user->uploadFile(trim($planName) . '_' . $this->appUtil->getCurrentTimeStamp(), 'fileUpload', 'plans/');
                $path = base_url() . 'assets/plans/' . $imagePath;
            }
            $plan['pimg'] = $path;
        } else if (empty($planId)) {
            $plan['pimg'] = '';
        }
        $plan['mat'] = $this->appUtil->getCurrentTimeStamp();
        $result = $collection->update($query, array('$set' => $plan), array('upsert' => true));
        if (!empty($result)) {
            $planResult = array();
            if (!empty($planId)) {
                $planResult['message'] = $this->exceptiongenerator->sendSuccess('RR001');
            } else {
                $planResult['message'] = $this->exceptiongenerator->sendSuccess('RR002');
            }
            return $planResult;
        } else {
            $error = $this->exceptiongenerator->sendError('EE020');
            return $error;
        }
    }

    /**
     * return broadcast list
     */
    function getBroadcastList($startIndex, $limit, $channelId, $data = NULL, $timePeriod = NULL) {
        if (empty($startIndex)) {
            $startIndex = 0;
        }
        if (empty($limit)) {
            $limit = $this->MAX_RECORD;
        }
        if (!empty($data)) {
            $broadcasts = $data;
        } else {
            $collection = $this->db->channelcontent;
            $query = array();
            if ($channelId != '') {
                $query['cid'] = $channelId;
            }
            if ($timePeriod != "") {
                if ($timePeriod == $this->timePeriod['currentWeek']) {
                    $dateRange = $this->comman_model->currentWeek();
                } elseif ($timePeriod == $this->timePeriod['previousWeek']) {
                    $dateRange = $this->comman_model->previousWeek();
                } elseif ($timePeriod == $this->timePeriod['currentMonth']) {
                    $dateRange = $this->comman_model->currentMonth();
                } elseif ($timePeriod == $this->timePeriod['previousMonth']) {
                    $dateRange = $this->comman_model->previousMonth();
                }
                $query['cat'] = array('$gte' => $dateRange['startDate'], '$lte' => $dateRange['endDate']);
            }
            $broadcasts = $collection->find($query)->sort(array("cat" => -1))->skip($startIndex)->limit($limit);
        }
        $channelQuery = array();
        if ($channelId != '') {
            $channelQuery['_id'] = new MongoId($channelId);
        }
        $channelCollection = $this->db->channel;
        $channels = $channelCollection->find($channelQuery, array("_id" => true, "cn" => true));
        $broadcastLog = $this->db->userbroadcastlog->find(array());
        $users = $this->db->user->find(array(), array("_id" => true, "un" => true));
        if (count($broadcasts) > 0) {
            $broadcastList = array();
            foreach ($channels as $channel) {
                foreach ($broadcasts as $broadcast) {
                    if ((string) $channel['_id'] == $broadcast['cid']) {
                        foreach ($users as $user) {
                            if ($broadcast['uid'] == $user['_id']) {
                                $broadcastData['ownerName'] = $user['un'];
                            }
                        }
                        $broadcastData['broadcastId'] = (string) $broadcast['_id'];
                        $broadcastData['actype'] = $broadcast['actype'];
                        $sum = 0;
                        $count = 0;
                        $reportCount = 0;
                        if (isset($broadcast['brrat']) && count($broadcast['brrat']) > 0) {
                            foreach ($broadcast['brrat'] as $brRating) {
                                if (isset($brRating['urat'])) {
                                    $count = $count + 1;
                                    $sum = $sum + $brRating['urat'];
                                }
                                if (isset($brRating['ureport'])) {
                                    $reportCount = $reportCount + $brRating['ureport'];
                                }
                            }
                        }
                        if (empty($sum)) {
                            $broadcastData['brrat'] = 0;
                        } else {
                            $broadcastData['brrat'] = number_format($sum / $count, 1);
                        }
                        $viewers = 0;
                        if ($broadcastLog && count($broadcastLog) > 0) {
                            foreach ($broadcastLog as $joiners) {
                                if ($joiners['cid'] == (string) $broadcast['_id']) {
                                    $viewers = $viewers + 1;
                                }
                            }
                        }
                        $broadcastData['viewer'] = $viewers;
                        $broadcastData['ureport'] = $reportCount;
                        $broadcastData['cid'] = $broadcast['cid'];
                        $broadcastData['cratid'] = $broadcast['cratid'];
                        $broadcastData['des'] = $broadcast['des'];
                        $broadcastData['etime'] = $broadcast['etime'];
                        $broadcastData['name'] = $broadcast['name'];
                        if ($broadcast['stime'] >= $this->appUtil->getCurrentTimeStamp()) {
                            $broadcastData['rtmpst'] = "Upcoming";
                        } else if ($this->appUtil->getCurrentTimeStamp() > $broadcast['stime'] && $this->appUtil->getCurrentTimeStamp() <= $broadcast['etime']) {
                            if ($broadcast['rtmpst'] == $this->status['active']) {
                                $broadcastData['rtmpst'] = "Live and broadcasting";
                            } else {
                                $broadcastData['rtmpst'] = "Live";
                            }
                        } else if ($this->appUtil->getCurrentTimeStamp() > $broadcast['etime']) {
                            $broadcastData['rtmpst'] = "Completed";
                        }
                        $broadcastData['stime'] = $broadcast['stime'];
                        $broadcastData['uid'] = (string) $broadcast['uid'];
                        $broadcastData['status'] = $broadcast['isactive'];
                        $broadcastData['channelName'] = $channel['cn'];
                        $broadcastData['cat'] = $broadcast['cat'];
                        $broadcastData['duration'] = $broadcast['etime'] - $broadcast['stime'];
                        $broadcastList[] = $broadcastData;
                    }
                }
            }
            return $broadcastList;
        } else {
            $error = $this->exceptiongenerator->sendError('EE047');
            return $error;
        }
    }

    /**
     * @param $broadcastId : broadcast id
     * @param $status : status of broadcast
     * @return array : return response broadcast updated successfully
     */
    function updateBroadcast($broadcastId, $status) {
        $id = $this->validateMongoId($broadcastId);
        if (!empty($id)) {
            $query['_id'] = $id;
            $update = array();
            $update['isactive'] = $status;
            $update['mat'] = $this->appUtil->getCurrentTimeStamp();
            $collection = $this->db->channelcontent;
            $result = $collection->update($query, array('$set' => $update));
            if (!empty($result)) {
                $broadcastResult = array();
                if ($status == $this->status['active']) {
                    $broadcastResult['message'] = $this->exceptiongenerator->sendSuccess('RR007');
                }
                if ($status == $this->status['inActive']) {
                    $broadcastResult['message'] = $this->exceptiongenerator->sendSuccess('RR008');
                }
                return $broadcastResult;
            } else {
                $error = $this->exceptiongenerator->sendError('EE020');
                return $error;
            }
        } else {
            $error = $this->exceptiongenerator->sendError('EE048');
            return $error;
        }
    }

    /**
     * @param $broadcastId : broadcast id
     * @param $status : drop broadcast
     * @return array : return response Broadcast has successfully dropped
     */
    function dropBroadcast($contentId) {
        $contentData = $this->getContentDataById($contentId);
        if (count($contentData) > 0) {
            $endBroadcastPublisher = file_get_contents($this->endBroadcastPublisher . $contentData['rtmpurl']);
            $endBroadcastClient = file_get_contents($this->endBroadcastClient . $contentData['rtmpurl']);
            if ($endBroadcastPublisher > 1 || $endBroadcastClient > 1) {
                $result = array();
                $result['message'] = 'Broadcast has been successfully dropped';
                return $result;
            } else {
                $result = array();
                $result['message'] = 'Broadcast has been successfully dropped';
                return $result;
            }
        } else {
            return $error = $this->exceptiongenerator->sendError('EE038');
        }
    }

    function search($searchParamsJson, $userId = NULL, $map = false) {
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
            $searchData = array(
                'entity' => $entity,
                'searchMethod' => $searchMethod,
                'searchParameters' => $searchParameters,
                'sortParameters' => $sortParameters,
                'page' => $page,
                'docPerPage' => $docPerPage,
            );

            $searchResult = $this->search->search($searchData, $map = 'admin');
            if ($searchResult && count($searchResult) > 0) {
                if ($searchParams->entity == 'user') {
                    $result = $this->getUserList(0, 0, $searchResult);
                } else if ($searchParams->entity == 'channel') {
                    $result = $this->getChannelList(0, 0, 0, $searchResult);
                } else if ($searchParams->entity == 'channelcontent') {
                    $result = $this->getBroadcastList(0, 0, 0, $searchResult, 0);
                } else if ($searchParams->entity == 'plan') {
                    $result = $this->getPlanList(0, 0, $searchResult);
                }
                //$result = $this->parseChannelSearch($searchResult, $userId);
                //$result = $this->channelSearch($searchResult, $userId);
                return $result;
            } else {
                return $searchResult;
            }
        } else {
            $data = $this->comman_model->senderror('EE022');
            return $data;
        }
    }

    /**
     * Function to get or check content owner   
     * @param contentId $contentId    
     */
    function getContentDataById($contentId) {
        $collection = $this->db->channelcontent;
        $contentData = $collection->findAndModify(array("_id" => new MongoId($contentId)), array('$set' => array("rtmpst" => 0)), null, array('new' => true));
        if (count($contentData) > 0) {
            return $contentData;
        } else {
            return 0;
        }
    }
    
    function getRevenueHistory($startIndex, $limit, $userId, $startDate, $endDate) {
        if (empty($startIndex)) {
            $startIndex = 0;
        }
        if (empty($limit)) {
            $limit = $this->MAX_RECORD;
        }
        $query = array();
        if ($userId != '') {
            $query['uid'] = new MongoId($userId);
        }
        if (!empty($startDate) && !empty($endDate)) {
            $query['cat'] = array('$gte' => $startDate, '$lte' => $endDate);
        }
        $channels = $this->db->channel->find(array(), array("cn" => true));
        $earnMoneyHistory = $this->db->usermoneyearnhistory->find($query)->sort(array('cat' => -1))->skip($startIndex)->limit($limit);
        if (count($earnMoneyHistory) > 0) {
            $result = array();
            foreach ($earnMoneyHistory as $earnMoney) {
                foreach ($channels as $channel) {
                    if ((string)$channel['_id'] == (string)$earnMoney['cid']) {
                        if (isset($channel['cn'])) {
                            $earnMoneyData['channelName'] = $channel['cn'];
                        } else {
                            $earnMoneyData['channelName'] = "";
                        }
                    }
                }
                if (isset($earnMoney['uem'])) {
                    $earnMoneyData['amount'] = number_format($earnMoney['uem'], 2);
                } else {
                    $earnMoneyData['amount'] = 0;
                }
                $earnMoneyData['cid'] = (string)$earnMoney['cid'];
                $earnMoneyData['desc'] = (isset($earnMoney['desc'])) ? $earnMoney['desc'] : '';
                $earnMoneyData['date'] = date("m-d-Y", $earnMoney['cat']);
                $result[] = $earnMoneyData;
            }
            return $result;
        } else {
            $error = $this->exceptiongenerator->sendError('EE047');
            return $error;
        }
    }
    /**
     * Function to send a reset password link
     */
    function forgotPassword($email) {
        $collection = $this->db->adminuser;
        $password = random_string('alnum', 5);
        $newPassword = hash("sha256", $password . 'ravel@');
        $update = array('pwd' => $newPassword);
        $user = $collection->findAndModify(array("email" => $email), array('$set' => $update), null, array('new' => true));
        if (count($user) > 0) {
            $userName = $user['un'];
            $msg = "Dear $userName,<br/><br/>"
                    . "Please use the below password: <br/><br/>"
                    . $password;
            $flag = $this->mailModel->sendMailList($email, $msg);
            $response = array('message' => $this->exceptiongenerator->sendSuccess('RR0010'));
            return $response;
        } else {
            return $result = $this->exceptiongenerator->sendError('EE016');
        }
    }

    /**
     * @param $id : mongoId
     * @return bool|MongoId
     */
    function validateMongoId($id) {
        try {
            $id = new MongoId($id);
            return $id;
        } catch (MongoException $exp) {
            $id = new MongoId();
            return false;
        }
    }

    /**
     * @param $data : user token
     * @param null $logout
     * @return array|int
     */
    function oAuth($data, $logout = NULL) {
        $result = 0;
        $token = base64_decode($data);
        $getResult = explode('##', $token);
        if (count($getResult) > 0 && (isset($getResult[0])) && (isset($getResult[1]))) {
            $collection = $this->db->adminauth;
            $userId = $getResult[0];
            if ($logout) {
                $collection->update(array("uid" => new MongoId($userId)), array('$set' => array('st' => $this->status['inActive'],
                        'mat' => $this->appUtil->getCurrentTimeStamp())));
                $user = array();
                $user['message'] = $this->exceptiongenerator->sendSuccess('RR009');
                return $user;
            } else {
                $arrFind = array(
                    'uid' => new MongoId($userId),
                    'st' => $this->status['active'],
                    '$or' => array(
                        array(
                            'tet' => array('$gte' => $this->appUtil->getCurrentTimeStamp())
                        ),
                        array(
                            'rm' => 1
                        )
                    )
                );
                $getData = $collection->findOne($arrFind);
                if (count($getData) > 0) {
                    if ($data == $getData['tid']) {
                        $result = $userId;
                    } else {
                        $result = $this->exceptiongenerator->sendError('EE006');
                    }
                    return $result;
                } else {
                    $result = $this->exceptiongenerator->sendError('EE006');
                }
            }
        } else {
            $result = $this->exceptiongenerator->sendError('EE006');
        }
        return $result;
    }

    /**
     * @param $id : user id
     * @return array : return user token
     */
    function generateToken($id, $rememberMe) {
        if ($rememberMe == $this->rememberMe['keepMeLoggedIn']) {
            $update = array(
                "tid" => base64_encode($id . '##' . random_string('alnum', 5)),
                "rm" => $rememberMe,
                "uid" => new MongoId($id),
                'st' => $this->status['active'],
                "mat" => $this->appUtil->getCurrentTimeStamp()
            );
        } else {
            $update = array(
                "tid" => base64_encode($id . '##' . random_string('alnum', 5)),
                "tet" => $this->adminTokenExpTime,
                "rm" => $rememberMe,
                "uid" => new MongoId($id),
                'st' => $this->status['active'],
                "mat" => $this->appUtil->getCurrentTimeStamp()
            );
        }
        $user = $this->db->adminauth->findAndModify(array("uid" => new MongoId($id)), array('$set' => $update), null, array('new' => true));
        if (count($user) <= 0) {
            $this->db->adminauth->insert($update);
            $user = $this->db->adminauth->findOne(array('uid' => new MongoId($id), 'st' => $this->status['active']));
        }
        if (count($user) > 0) {
            $result = array();
            $result['accessToken'] = $user['tid'];
            return $result;
        }
    }

}

?>
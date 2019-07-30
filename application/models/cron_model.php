<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/**
 * Class cron to create a user on openfire server
 */
class cron_model extends CI_Model {

    function cron_model() {
        parent::__construct();
        $this->load->helper('string');
        $this->load->helper('url');
        $this->load->helper('text');
        $this->openFireUrl = $this->config->config['openfire_url'];
        $this->load->library('rest_client');
        $this->mongo = $this->config->config['mongo'];
        $dbName = $this->config->config['mongoDb'];
        $this->load->model('comman_model');
        $this->db = $this->mongo->$dbName;
        $this->load->model('pushnotification_model');
        $this->invitationMessage = $this->config->config['invitationMessage'];
        $this->endBroadcastPublisher = $this->config->config['endBroadcastPublisher'];
        $this->endBroadcastClient = $this->config->config['endBroadcastClient'];
    }

    /**
     * Function to create a user on open fire server.   
     */
    function createOpenfireUser() {
        $collection = $this->db->user;
        $getUserData = $collection->find();
        if (count(iterator_to_array($getUserData)) > 0) {
            foreach ($getUserData as $userval) {
                try {
                    if (isset($userval['oppwd']) && $userval['oppwd'] == "") {
                        $params['userId'] = (string) $userval['_id'];
                        //$userId = (string) $userval['_id'];
                        if (!empty($params['userId'])) {
                            $uri = $this->openFireUrl . 'server/createusr';
                            $response = $this->rest_client->send($uri, 'POST', $params);
                            $openFireData = json_decode($response->body);
                            if (isset($openFireData->status) && $openFireData->status == 1) {
                                $openfirePassword = (isset($openFireData->result_set)) ? $openFireData->result_set : '';
                                $this->db->user->update(array("_id" => new MongoId($params['userId'])), array('$set' => array('oppwd' => $openfirePassword)));
                                // check any channel exist or not
                                $this->channelExist($params['userId'], $userval['favchannel']);
                                // check any channel conetnt exist or not
                                $this->channelContentExist($params['userId']);
                            } else {
                                continue;
                            }
                        } else {
                            continue;
                        }
                    } else {
                        if (isset($userval['oppwd']) && isset($userval['nickname']) && $userval['nickname'] != "") {
                            $this->channelExist((string) $userval['_id'], $userval['favchannel']);
                            // check any channel conetnt exist or not
                            $this->channelContentExist((string) $userval['_id']);
                        } else {
                            continue;
                        }
                    }
                } catch (Exception $ex) {
                    continue;
                }
            }
            echo "Successfully Done.";
        }
    }

    /**
     * Function to check any channel exist or not
     * @param type $userId  
     * @param type $nickName    
     */
    function channelExist($userId, $favChannel = NULL) {
        $getChannelData = $this->db->channel->find(array("uid" => new MongoId($userId)));
        if (count(iterator_to_array($getChannelData)) > 0) {
            foreach ($getChannelData as $val) {
                try {
                    $params['chid'] = (string) $val['_id'];
                    $params['uid'] = $userId;
                    $uri = $this->openFireUrl . 'server/createchannel?chid=' . $params['chid'] . '&uid=' . $userId;
                    $response = $this->rest_client->send($uri, 'POST', $params);
                    if (isset($response->status) && $response->status == 1) {
                        if (isset($favChannel) && is_array($favChannel) && count($favChannel) > 0) {
                            if (in_array($params['chid'], $favChannel)) {
                                $uri = $this->openFireUrl . 'chatgrp/subscribe';
                                $response = $this->rest_client->send($uri, 'POST', $params);
                            }
                        } else {
                            continue;
                        }
                    } else {
                        continue;
                    }
                } catch (Exception $ex) {
                    continue;
                }
            }
        }
        return true;
    }

    /**
     * Function to check any conetnt exist or not
     * @param type $userId
     * @param type $nickName     
     */
    function channelContentExist($userId) {
        // close exist room on open fire server
        // $this->closeExistRoom();

        $getChannelContentData = $this->db->channelcontent->find(array("uid" => new MongoId($userId), "stime" => array('$gt' => time()), "ofrmid" => array('$ne' => "")));
        if (count(iterator_to_array($getChannelContentData)) > 0) {
            foreach ($getChannelContentData as $val) {
                try {
                    $params['uid'] = $userId;
                    $params['chid'] = $val['cid'];
                    $params['rmid'] = (string) $val['_id'] . $params['uid'];
                    $uri = ((int) $val['actype'] == 1) ? $this->openFireUrl . 'chatgrp/createrm' : $this->openFireUrl . 'chatgrp/creatermpwd';
                    $response = $this->rest_client->send($uri, 'POST', $params);
                    $openFireData = json_decode($response->body);
                    if (isset($openFireData->status) && $openFireData->status == 1 && (int) $val['actype'] == 1) {
                        $this->db->channelcontent->update(array("_id" => new MongoId((string) $val['_id'])), array('$set' => array("ofrmid" => $openFireData->result_set, "ofpwd" => "")));
                    }
                    if (isset($openFireData->status) && $openFireData->status == 1 && (int) $val['actype'] == 0) {
                        $roomId = (isset($openFireData->result_set->rmid)) ? $openFireData->result_set->rmid : '';
                        $roomPassword = (isset($openFireData->result_set->pwd)) ? $openFireData->result_set->pwd : '';
                        $this->db->channelcontent->update(array("_id" => new MongoId((string) $val['_id'])), array('$set' => array("ofrmid" => $roomId, "ofpwd" => $roomPassword)));
                    }
                } catch (Exception $ex) {
                    continue;
                }
            }
        }
        return true;
    }

    /**
     * Function to close exist room on open fire server.        
     */
    function closeExistRoom() {
        $getContentData = $this->db->channelcontent->find(array("etime" => array('$lt' => time()), 'isactive' => 1));
        $uri = $this->openFireUrl . 'chatgrp/closeroom';
        if (count(iterator_to_array($getContentData)) > 0) {
            foreach ($getContentData as $contentData) {
                $this->db->channelcontent->update(array("_id" => new MongoId((string) $contentData['_id'])), array('$set' => array("isactive" => 0)));
                $params['uid'] = (string) $contentData['uid'];
                $params['chid'] = $contentData['cid'];
                $params['rmid'] = (string) $contentData['_id'] . $params['uid'];
                try {
                    $response = $this->rest_client->send($uri, 'POST', $params);
                    if (isset($response->status) && $response->status == 1) {
                        $this->db->channelcontent->update(array("_id" => new MongoId($contentData['_id'])), array('$set' => array('isactive' => 0)));
                    }
                } catch (Exception $ex) {
                    continue;
                }
            }
            return true;
        }
        return true;
    }

    /**
     * Function to send push notification for broadcast owner       
     */
    function sendPushToBroadcastOwner() {
        $collection = $this->db->channelcontent;
        $startTime = time() + 900;
        $getResult = $collection->find(array("isactive" => 1,
            "stime" => array('$lte' => $startTime),
            "etime" => array('$gte' => time())));
        $gcmData = $iosData = array();
        if (count(iterator_to_array($getResult)) > 0) {
            foreach ($getResult as $contentData) {
                $contentOwnerId = (string) $contentData['_id'];
                $getUserData = $this->db->user->findOne(array("_id" => new MongoId($contentOwnerId)));
                if (count($getUserData) > 0) {
                    $sendPush = (isset($getUserData['getpush']) && $getUserData['getpush'] != 0) ? $getUserData['getpush'] : 0;
                    if ($sendPush == 1) {
                        $message = str_replace("<bn>", $contentData['name'], $this->invitationMessage['broadcastOwner']);
                        $sendMessage = str_replace("<rtime>", $contentData['stime'], $message);
                        $getDeviceInfo = $this->db->userdeviceinfo->findOne(array("uid" => new MongoId($contentOwnerId)));
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
        } else {
            return false;
        }
        if (count($gcmData) > 0) {
            $this->pushnotification_model->pushAndroidNotification($gcmData, $sendMessage, "broadcastOwner");
        }
        if (count($iosData) > 0) {
            $this->pushnotification_model->pushIosNotification($iosData, $sendMessage, "broadcastOwner");
        }
        echo "Successfully Done.";
    }

    /**
     * Function to send push notification.
     */
    function sendPush() {
        $collection = $this->db->pushdata;
        $getData = $collection->find();
        if (count(iterator_to_array($getData)) > 0) {
            foreach ($getData as $val) {
                $gcmData = $IosData = $twilioData = array();
                if (isset($val['recid']) && !empty($val['recid'])) {
                    $getUserRecord = $this->db->user->findOne(array("_id" => new MongoId($val['recid'])));
                    $sendPush = (isset($getUserRecord['getpush'])) ? (int) $getUserRecord['getpush'] : 0;
                    if (count($getUserRecord) > 0 && $sendPush == 1) {
                        $getDeviceInfo = $this->db->userdeviceinfo->findOne(array("uid" => new MongoId($val['recid'])));
                        if (count($getDeviceInfo['dvcs']) > 0) {
                            foreach ($getDeviceInfo['dvcs'] as $deviceData) {
                                if ($deviceData['st'] == 1) {
                                    if (!empty($deviceData['dregid']) && (int) $deviceData['dtype'] == 1) {
                                        $gcmData[] = $deviceData['dregid'];
                                    }
                                    if (!empty($deviceData['dregid']) && (int) $deviceData['dtype'] == 2) {
                                        $IosData[] = $deviceData['dregid'];
                                    }
                                }
                            }
                            if (count($gcmData) > 0) {
                                $this->pushnotification_model->pushAndroidNotification($gcmData, $val['message'], $val['type']);
                            }
                            if (count($IosData) > 0) {
                                $this->pushnotification_model->pushIosNotification($IosData, $val['message'], $val['type']);
                            }

                            //if (count($gcmData) > 0 || count($IosData) > 0) {
                            $where = array("recid" => new MongoId($val['recid']), 'type' => $val['type'], 'message' => $val['message']);
                            $this->db->pushdata->remove($where, array("justOne" => true));
                            //}
                        } else {
                            $where = array("recid" => new MongoId($val['recid']), 'type' => $val['type'], 'message' => $val['message']);
                            $this->db->pushdata->remove($where, array("justOne" => true));
                        }
                    }
                } else {
                    $twilioData[] = (isset($val['pno']) && !empty($val['pno'])) ? $val['pno'] : "";
                    if (count($twilioData) > 0) {
                        $this->pushnotification_model->sendTwilioMessage($twilioData, $val['message']);
                        $where = array("pno" => $val['pno'], 'type' => $val['type'], 'message' => $val['message']);
                        $this->db->pushdata->remove($where, array("justOne" => true));
                    }
                }
            }
        }
        echo "Successfully Done.";
    }

    /* end all broad cast whose time is ended */

    function endExpBroadcast() {
        $collection = $this->db->channelcontent;
        $getData = $collection->find(array(
            "isactive" => 1,
            "rtmpst" => 1,
            "etime" => array('$lte' => time())));
        if (count(iterator_to_array($getData)) > 0) {
            foreach ($getData as $val) {
                $endBroadcastPublisher = file_get_contents($this->endBroadcastPublisher . $val['rtmpurl']);
                $endBroadcastClient = file_get_contents($this->endBroadcastClient . $val['rtmpurl']);
            }
        }
    }

}

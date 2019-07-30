<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class broadcast_model extends CI_Controller {

    private $db, $mongo, $timezone;

    function broadcast_model() {
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
        $dbName = $this->config->config['mongoDb'];
        $this->rtmpAppUrl = $this->config->config['rtmpAppUrl'];
        $this->db = $this->mongo->$dbName;
        $timezone = $this->config->config['timezone'];
        $this->stictlyEndOnTime = $this->config->config['stictlyEndOnTime'];
        $this->invitationMessage = $this->config->config['invitationMessage'];
        $this->endBroadcastPublisher = $this->config->config['endBroadcastPublisher'];
        $this->endBroadcastClient = $this->config->config['endBroadcastClient'];
        $this->broadcastState = $this->config->config['broadcastState'];
    }

    /* This function is used to start broad cast
     *      * @param userID $userID  
     * @param contentId $contentId */

    function startBroadcast($userId, $contentId) {
        $channelCollection = $this->db->channelcontent;
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

    /* This function is used to end broad cast 
     * @param userID $userID  
     * @param contentId $contentId     */

    function endBroadcast($userId, $contentId) {
        $channelCollection = $this->db->channelcontent;
        $planCollection = $this->db->plan;
        $result = array();
        $contentData = $this->getContentData($userId, $contentId);
        if ($contentData != 0) {
            //if (isset($contentData['rtmpst']) && $contentData['rtmpst'] == 1) {
            //cratid
            $rtmpStatus = 1;
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
                //$playUrl = $this->generatePlayUrl($contentData['rtmpurl'], $contentData['etime']);
                $playUrl = '';
                $isOwner = 1;
                $remainingDuration = $endTime - time();
                $endBroadcastPublisher = file_get_contents($this->endBroadcastPublisher . $contentData['rtmpurl']);
                $endBroadcastClient = file_get_contents($this->endBroadcastClient . $contentData['rtmpurl']);
                $updateRtmpStatus = "";
                //update rtmp status in channel content collection
                $updateRtmpStatus = $channelCollection->update(array("_id" => new MongoId($contentId)), array('$set' => array('rtmpst' => 0, 'isactive' => 0)));
                if (isset($updateRtmpStatus['ok'])) {
                    $rtmpStatus = 0;
                }
                //generate broadcast response
                $result = $this->broadcastResponse($contentData, $ownerName, $ownerId, $ownerNickName, $playUrl, $rtmpStatus, $isOwner, $remainingDuration);
                return $result;
            } else {
                return $error = $this->comman_model->senderror('EE042');
            }
            //}// else {
            //     return $error = $this->comman_model->senderror('EE045');
            //}
        } else {
            return $error = $this->comman_model->senderror('EE040');
        }
    }

    /* This function is used to join broad cast
     *      * @param userID $userID  
     * @param contentId $contentId */

    function joinBroadcast($userId, $contentId) {
        $result = array();
        $contentData = $this->getContentDataById($contentId);
        if ($contentData != 0) {
            if (isset($contentData['rtmpst']) && $contentData['rtmpst'] == 1) {
                //cratid
                $startTime = isset($contentData['stime']) ? $contentData['stime'] : '';
                $endTime = isset($contentData['etime']) ? $contentData['etime'] : '';
                if (time() >= $startTime && $endTime > time()) {
                    //get owner details
                    $ownerdetail = $this->getUser((string) $contentData['uid']);
                    $ownerName = '';
                    $ownerId = '';
                    $ownerNickName = '';
                    if ($ownerdetail != 0) {
                        $ownerName = $ownerdetail['name'];
                        $ownerId = (string) $ownerdetail['_id'];
                        $ownerNickName = (isset($ownerdetail['nickname'])) ? $ownerdetail['nickname'] : "";
                    }
                    //generate broadcast response
                    //generate play url
                    $playUrl = $this->generatePlayUrl($contentData['rtmpurl'], $contentData['etime']);
                    if ((string) $userId == (string) $contentData['uid']) {
                        $isOwner = 1;
                    } else {
                        $isOwner = 0;
                    }
                    $remainingDuration = $endTime - time();
                    $result = $this->broadcastResponse($contentData, $ownerName, $ownerId, $ownerNickName, $playUrl, $contentData['rtmpst'], $isOwner, $remainingDuration);
                    $userlog = $this->userBroadcastLog($userId, $contentId, 'join');
                    return $result;
                } else {
                    return $error = $this->comman_model->senderror('EE042');
                }
            } else {
                return $error = $this->comman_model->senderror('EE058');
            }
        } else {
            return $error = $this->comman_model->senderror('EE038');
        }
    }

    /* This function is used to leave broad cast
     *      * @param userID $userID  
     * @param contentId $contentId */

    function leaveBroadcast($userId, $contentId) {
        $userlog = $this->userBroadcastLog($userId, $contentId, 'leave');
        return $error = array("message" => 'Successfully leave Broadcast');
    }

    function userBroadcastLog($userId, $contentId, $action) {
        //userbroadcastlog
        if ($userId != '' && $contentId != '' && $action != '') {
            $userbroadcastlog = $this->db->userbroadcastlog->update(
                    array("uid" => new MongoId($userId), "cid" => $contentId), array('$set' => array("uid" => new MongoId($userId), "cid" => $contentId, "action" => $action, "cat" => time())), array("upsert" => true));
        }
        return true;
    }

    function onPublish($rtmpUrl) {
        $channelCollection = $this->db->channelcontent;
        $updateRtmpStatus = $channelCollection->update(array("rtmpurl" => (string) $rtmpUrl), array('$set' => array('rtmpst' => 1)));
    }

    function onPublishDone($rtmpUrl) {
        $channelCollection = $this->db->channelcontent;
        $updateRtmpStatus = $channelCollection->update(array("rtmpurl" => (string) $rtmpUrl), array('$set' => array('rtmpst' => 0)));
    }

    function dropBroadcast($contentId) {
        $contentData = $this->getContentDataById($contentId);
        if ($contentData != 0) {
            $endBroadcastPublisher = file_get_contents($this->endBroadcastPublisher . $contentData['rtmpurl']);
            $endBroadcastClient = file_get_contents($this->endBroadcastClient . $contentData['rtmpurl']);
            if ($endBroadcastPublisher > 1 || $endBroadcastClient > 1) {
                return $error = array("message" => 'Broadcast has successfully droped');
            }
        } else {
            return $error = $this->comman_model->senderror('EE038');
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

    function rateBroadcast($userId, $contentId, $rate, $accessToken) {
        $channelCollection = $this->db->channelcontent;
        $updateArray = array(
            "brrat.$.uid" => new MongoId($userId),
            "brrat.$.urat" => (int) $rate
        );
        $pushArray = array(
            "uid" => new MongoId($userId),
            "urat" => (int) $rate
        );
        try {
            $getResult = $channelCollection->findAndModify(
                    array("_id" => new MongoId($contentId),
                "brrat.uid" => new MongoId($userId)), array('$set' => $updateArray), null, array("new" => true));
            if (count($getResult) <= 0) {
                $getResult = $channelCollection->update(
                        array("_id" => new MongoId($contentId)), array('$push' => array('brrat' => $pushArray))
                );
            }
        } catch (Exception $exc) {
            $getResult = $channelCollection->update(
                    array("_id" => new MongoId($contentId)), array('$push' => array('brrat' => $pushArray))
            );
        }
        if (count($getResult) > 0) {
            return $error = array("message" => 'You have successfully rated this broadcast');
        } else {
            return $error = $this->comman_model->senderror('EE025');
        }
    }

    function reportBroadcast($userId, $contentId, $accessToken, $msgId, $msg) {
        $channelCollection = $this->db->channelcontent;
        $updateArray = array(
            "brrat.$.uid" => new MongoId($userId),
            "brrat.$.ureport" => 1
        );
        $pushArray = array(
            "uid" => new MongoId($userId),
            "ureport" => 1,
            "msgId" => $msgId,
            "msg" => $msg
        );
        try {
            $getResult = $channelCollection->findAndModify(
                    array("_id" => new MongoId($contentId),
                "brrat.uid" => new MongoId($userId)), array('$set' => $updateArray), null, array("new" => true));
            if (count($getResult) <= 0) {
                $getResult = $channelCollection->update(
                        array("_id" => new MongoId($contentId)), array('$push' => array('brrat' => $pushArray))
                );
            }
        } catch (Exception $exc) {
            $getResult = $channelCollection->update(
                    array("_id" => new MongoId($contentId)), array('$push' => array('brrat' => $pushArray))
            );
        }

        if (count($getResult) > 0) {
            return $error = array("message" => 'You have successfully reported this broadcast');
        } else {
            return $error = $this->comman_model->senderror('EE025');
        }
    }

    function getRunningBroadcast() {
        $xml = simplexml_load_file(urlencode($this->broadcastState), null, true);
        $application = $xml->server->application;
        $table = "<h1>" . $application->name . "<h1/>";
        $table = $table . "<table>
                          <thead>
                            <tr>
                              <th>Name</th>
                              <th>client</th>
                            </tr>
                          </thead>
                          <tbody>";
        foreach ($application->live->stream as $live) {
            $contentInfo = $this->getContentByRtmpurl($live->name);
            $table = $table . "<tr>
                <td>" . $contentInfo['name'] . "</td>
                <td>" . $live->nclients . "</td>"
                    . "</tr>";
        }
        $table = $table . " </tbody>
                        </table>";
        echo $table;
        exit;
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
                            $sendMessage = str_replace("<cn>", $contentName, $this->invitationMessage['start']);
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
     * Function to get content detail by rtmpurl
     * @param type $rtmpurl    
     */
    function getContentByRtmpurl($rtmpurl) {
        $contentCollection = $this->db->channelcontent;
        $contentData = $contentCollection->findOne(array("rtmpurl" => (string) $rtmpurl));
        if (count($contentData) > 0) {
            return $contentData;
        } else {
            return 0;
        }
    }

    /* Function to get user colection */

    function getUser($userId) {
        $getUserData = $this->db->user->findOne(array("_id" => new MongoId($userId)));
        if (count($getUserData) > 0) {
            return $getUserData;
        } else {
            return 0;
        }
    }

}

/* End of file ravelchannel.php */
    /* Location: ./application/controllers/ravelchannel.php */

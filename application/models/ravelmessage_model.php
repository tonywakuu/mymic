<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class ravelmessage_model extends CI_Model {

    private $db, $mongo, $timezone, $socketurl;

    function ravelmessage_model() {
        parent::__construct();
        $this->load->helper('string');
        $this->load->helper('url');
        $this->load->helper('text');
        $this->load->library('session');
        $this->load->helper('date');
        $this->load->model('comman_model');
        $this->load->model('pushnotification_model');
        $this->mongo = $this->config->config['mongo'];
        $dbName = $this->config->config['mongoDb'];
        $this->db = $this->mongo->$dbName;
        $timezone = $this->config->config['timezone'];
        $this->socketurl = $this->config->config['socketurl'];
        $this->invitationMessage = $this->config->config['invitationMessage'];
    }

    /**
     * Function to send a message
     * @param type $messageDetails
     */
    function sendMessage($messageDetails) {
        $collection = $this->db->message;
        $socket_url = $this->socketurl . 'emit';
        if (isset($messageDetails['senderId']) && isset($messageDetails['receiverId']) && isset($messageDetails['message'])) {
            $data = array(
                "sender_id" => new MongoId(trim($messageDetails['senderId'])),
                "receiver_id" => new MongoId(trim($messageDetails['receiverId'])),
                "message" => $messageDetails['message'],
                "is_active" => 1,
                "is_read" => 0,
                "created_date" => time(),
                "modified_date" => time()
            );

            $senderId = $messageDetails['senderId'];
            $receiverId = $messageDetails['receiverId'];
            $collection->insert($data);
            $messagesCount = $collection->count(array('sender_id' => new MongoId($senderId), 'receiver_id' => new MongoId($receiverId),
                'is_read' => 0));
            $messageDataForSocket = array(
                "id" => (string) $data['_id']->{'$id'},
                "sender_id" => (string) $data['sender_id']->{'$id'},
                "receiver_id" => (string) $data['receiver_id']->{'$id'},
                "message" => urlencode($data['message']),
                "is_active" => (int) $data['is_active'],
                "is_read" => (int) $data['is_read'],
                "created_date" => (int) $data['created_date'],
                "unreadMessage" => (int) $messagesCount
            );

            $messageDataApi = array(
                "id" => (string) $data['_id']->{'$id'},
                "sender_id" => (string) $data['sender_id']->{'$id'},
                "receiver_id" => (string) $data['receiver_id']->{'$id'},
                "message" => (string) $data['message'],
                "is_active" => (int) $data['is_active'],
                "is_read" => (int) $data['is_read'],
                "created_date" => (int) $data['created_date'],
                "unreadMessage" => (int) $messagesCount
            );

            $getReceiverData = $this->db->user->findOne(array('_id' => new MongoId($receiverId)));
            if (count($getReceiverData) > 0) {
                $isSocket = (isset($getReceiverData['socket'])) ? $getReceiverData['socket'] : 0;
                if ($isSocket == 0) {
                    $this->getGcmData($receiverId);
                } else {
                    $this->httpPost($socket_url, json_encode($messageDataForSocket));
                }
            } else {
                $this->httpPost($socket_url, json_encode($messageDataForSocket));
            }
            // Mark all the message to READ
            $this->markedReadMessage($senderId, $receiverId);
            return $error = array("message" => 'Message has been sent successfully.', 'data' => $messageDataApi);
        } else {
            return $error = $this->comman_model->senderror('EE037');
        }
    }

    /**
     * Function to send a message
     * @param type $senderId
     */
    function listMessages($senderId) {
        $collection = $this->db->message;
        $collectionUsers = $this->db->message_users;
        $messageArray = array();
        $usersArray = array();

        if (isset($senderId)) {
            $users = $collectionUsers->find(array('$or' => array(
                    array('sender' => new MongoId($senderId)),
                    array('receiver' => new MongoId($senderId)))));
//print_r(iterator_to_array($users));
            foreach ($users as $user) {
                if ($senderId != $user['receiver']) {
                    $receiverId = $user['receiver'];
                    $getUserData = $this->db->user->find(array("_id" => $user['receiver']));
                } else {
                    $receiverId = $user['sender'];
                    $getUserData = $this->db->user->find(array("_id" => $user['sender']));
                }
                $messageList = array();
                foreach ($getUserData as $userDetail) {
                    if (!in_array($userDetail['_id']->{'$id'}, $usersArray)) {
                        $usersArray[] = $userDetail['_id']->{'$id'};
                        // Get last message of User
                        $getMessageResult = $this->db->message->find(array('$or' => array(
                                                array("sender_id" => new MongoId($senderId), "receiver_id" => new MongoId($receiverId)),
                                                array("sender_id" => new MongoId($receiverId), "receiver_id" => new MongoId($senderId)))))
                                        ->sort(array('created_date' => -1))->limit(1);

                        foreach ($getMessageResult as $lastMessage) {
                            $messageList['lastMessage'] = $lastMessage['message'];
                            $messageList['lastMessageTime'] = $lastMessage['created_date'];
                        }

                        // Get unread message count.
                        $getUnreadMessage = $this->db->message->count(array("sender_id" => new MongoId($receiverId),
                            "receiver_id" => new MongoId($senderId), "is_read" => 0));
                        $messageList['unreadCount'] = $getUnreadMessage;
                        $messageList['profileImage'] = $userDetail['pimg'];
                        $messageList['userId'] = $userDetail['_id']->{'$id'};
                        $messageList['userName'] = (isset($userDetail['nickname'])) ? $userDetail['nickname'] : $userDetail['un'];

                        $messageArray[] = $messageList;
                    }
                }
            }
            //print_r($messageArray);
            $list = $this->array_sort($messageArray, 'lastMessageTime', SORT_DESC);
//print_r($list);
            return $error = array("messages" => $list);
        } else {
            return $error = $this->comman_model->senderror('EE037');
        }
    }

    /**
     * Function to send a message
     * @param type $senderId
     * @param type $userId
     * @param type $direction
     * @param type $timestamp
     */
    function getConversation($senderId, $userId, $direction, $timestamp, $pageCount) {
        $limit = 10;
        $skip = 0;
        $conversationArray = array();
        $messageDetails = array();
        /* if ($page) {
          $skip = $page * $limit - $limit;
          } */
        $collection = $this->db->message;
        if (isset($senderId) && $senderId != "") {
            if (isset($timestamp) && $timestamp != "") {
                if ($direction == 'down') {
//echo 'downif';
                    if ($pageCount == "") {
                        $conversations = $collection->find(array('$or' => array(
                                        array('sender_id' => new MongoId($senderId), 'receiver_id' => new MongoId($userId)),
                                        array('sender_id' => new MongoId($userId), 'receiver_id' => new MongoId($senderId))
                                    ), "created_date" => array('$lt' => $timestamp)))->sort(array('created_date' => -1))->limit(100);
                    } elseif (isset($pageCount) && $pageCount == -1) {
                        $conversations = $collection->find(array('$or' => array(
                                        array('sender_id' => new MongoId($senderId), 'receiver_id' => new MongoId($userId)),
                                        array('sender_id' => new MongoId($userId), 'receiver_id' => new MongoId($senderId))
                                    ), "created_date" => array('$lt' => $timestamp)))->sort(array('created_date' => -1));
                    } else {
                        $conversations = $collection->find(array('$or' => array(
                                        array('sender_id' => new MongoId($senderId), 'receiver_id' => new MongoId($userId)),
                                        array('sender_id' => new MongoId($userId), 'receiver_id' => new MongoId($senderId))
                                    ), "created_date" => array('$lt' => $timestamp)))->sort(array('created_date' => -1))->limit($pageCount);
                    }
                } else {
//echo 'upelse';              
                    $conversations = $collection->find(array('$or' => array(
                            array('sender_id' => new MongoId($senderId), 'receiver_id' => new MongoId($userId)),
                            array('sender_id' => new MongoId($userId), 'receiver_id' => new MongoId($senderId))
                        ), 'created_date' => array('$gt' => $timestamp)));
                }
            } else {
//echo 'elsetimestamp';  
                if ($pageCount == "") {
                    $conversations = $collection->find(array('$or' => array(
                                    array('sender_id' => new MongoId($senderId), 'receiver_id' => new MongoId($userId)),
                                    array('sender_id' => new MongoId($userId), 'receiver_id' => new MongoId($senderId))
                        )))->sort(array('created_date' => -1))->limit(100);
                } elseif (isset($pageCount) && $pageCount == -1) {
                    $conversations = $collection->find(array('$or' => array(
                                    array('sender_id' => new MongoId($senderId), 'receiver_id' => new MongoId($userId)),
                                    array('sender_id' => new MongoId($userId), 'receiver_id' => new MongoId($senderId))
                        )))->sort(array('created_date' => -1));
                } else {
                    $conversations = $collection->find(array('$or' => array(
                                    array('sender_id' => new MongoId($senderId), 'receiver_id' => new MongoId($userId)),
                                    array('sender_id' => new MongoId($userId), 'receiver_id' => new MongoId($senderId))
                        )))->sort(array('created_date' => -1))->limit($pageCount);
                }
            }
            foreach ($conversations as $conversation) {
                $messageDetails['id'] = $conversation['_id']->{'$id'};
                $messageDetails['sender_id'] = $conversation['sender_id']->{'$id'};
                $messageDetails['receiver_id'] = $conversation['receiver_id']->{'$id'};
                $messageDetails['message'] = $conversation['message'];
                $messageDetails['is_active'] = $conversation['is_active'];
                $messageDetails['is_read'] = $conversation['is_read'];
                $messageDetails['created_date'] = $conversation['created_date'];

                $conversationArray[] = $messageDetails;
            }

            // Mark all the message to READ
            $this->markedReadMessage($senderId, $userId);
            $list = $this->array_sort($conversationArray, 'created_date', SORT_ASC);
            return $error = array("messages" => $list);
        } else {
            return $error = $this->comman_model->senderror('EE037');
        }
    }

    /**
     * Function to count unread message
     * @param type $senderId   
     */
    function countUnreadMessage($senderId) {
        $collection = $this->db->message;
        if (isset($senderId)) {
            $messagesCount = $collection->count(array('receiver_id' => new MongoId($senderId),
                'is_read' => 0));
            return $error = array("messageCount" => $messagesCount);
        } else {
            return $error = $this->comman_model->senderror('EE037');
        }
    }

    /**
     * Function to count unread message
     * @param type $senderId   
     * @param type $receiverId 
     */
    function countUnreadMessageOfUser($senderId, $receiverId) {
        $collection = $this->db->message;
        if (isset($senderId)) {
            $messagesCount = $collection->count(array('sender_id' => new MongoId($senderId), 'receiver_id' => new MongoId($receiverId),
                'is_read' => 0));
            return $error = array("messageCount" => $messagesCount);
        } else {
            return $error = $this->comman_model->senderror('EE037');
        }
    }

    /**
     * Function to marked message as read
     * @param type $senderId   
     * @param type $userId 
     */
    function markedReadMessage($senderId, $userId) {
        $collection = $this->db->message;
        if (isset($senderId)) {
            $collection->update(array("sender_id" => new MongoId($userId),
                "receiver_id" => new MongoId($senderId)), array('$set' => array("is_read" => 1)), array('multiple' => true));
            return $error = array("message" => 'Message has been updated successfully.');
        } else {
            return $error = $this->comman_model->senderror('EE037');
        }
    }

    /**
     * Function to change user status
     * @param type $senderId   
     * @param type $status 
     */
    function userSocketStatus($senderId, $status) {
        //$socket_url = $this->socketurl . 'createroom';
        //$userData['id'] = $senderId;
        $collection = $this->db->user;
        if (isset($senderId)) {
            //$this->httpPost($socket_url, json_encode($userData));
            $collection->update(array("_id" => new MongoId($senderId)), array('$set' => array("socket" => $status)));
            return $error = array("message" => 'User status has been updated successfully.');
        } else {
            return $error = $this->comman_model->senderror('EE037');
        }
    }

    /**
     * Function to count unread message
     * @param type $senderId   
     * @param type $receiverId   
     */
    function checkUserInFriendList($senderId, $receiverId) {
        $collection = $this->db->message_users;
        if (isset($senderId)) {
            $userCount = $collection->count(array('sender' => new MongoId($senderId), 'receiver' => new MongoId($receiverId)));
            if ($userCount == 0) {
                $collection->insert(array('sender' => new MongoId($senderId), 'receiver' => new MongoId($receiverId)));
            }
        }
    }

    function httpPost($url, $params) {
        $params = json_decode($params);
        ;
        $postData = '';
        //create name value pairs seperated by &
        foreach ($params as $k => $v) {
            $postData .= $k . '=' . $v . '&';
        }
        $postData = rtrim($postData, '&');

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, count($postData));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

        $output = curl_exec($ch);
        curl_close($ch);
        //return $output;
    }

    /**
     * Function to send push when socket is not connect    
     * @param type $receiverId    
     */
    function getGcmData($receiverId) {
        $getUserRecord = $this->db->user->findOne(array("_id" => new MongoId($receiverId)));
        $gcmData = $iosData = array();
        if (count($getUserRecord) > 0) {
            $getMessageData = $this->countUnreadMessage($receiverId);
            $messageCount = (isset($getMessageData['messageCount']) && $getMessageData['messageCount'] != 0) ? $getMessageData['messageCount'] : 0;
            $sendPush = $getUserRecord['getpush'];
            $getDeviceInfo = $this->db->userdeviceinfo->findOne(array("uid" => new MongoId($receiverId)));
            if (count($getDeviceInfo) > 0 && $sendPush == 1) {
                if (isset($getDeviceInfo['dvcs']) && count($getDeviceInfo['dvcs']) > 0 && $messageCount != 0) {
                    foreach ($getDeviceInfo['dvcs'] as $val) {
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
            } else {
                return false;
            }
            $message = str_replace("<total>", $messageCount, $this->invitationMessage['message']);
            if ((int) $messageCount > 1) {
                $sendMessage = str_replace("<mes>", 'messages', $message);
            } else {
                $sendMessage = str_replace("<mes>", 'message', $message);
            }

            if (count($gcmData) > 0) {
                $this->pushnotification_model->pushAndroidNotification($gcmData, $sendMessage, "message", $messageCount);
            }
            if (count($iosData) > 0) {
                $this->pushnotification_model->pushIosNotification($iosData, $sendMessage, "message", $messageCount);
            }
            return true;
        } else {
            return false;
        }
    }

    function array_sort($array, $on, $order = SORT_ASC) {
        $new_array = array();
        $sortable_array = array();

        if (count($array) > 0) {
            foreach ($array as $k => $v) {
                if (is_array($v)) {
                    foreach ($v as $k2 => $v2) {
                        if ($k2 == $on) {
                            $sortable_array[$k] = $v2;
                        }
                    }
                } else {
                    $sortable_array[$k] = $v;
                }
            }

            switch ($order) {
                case SORT_ASC:
                    asort($sortable_array);
                    break;
                case SORT_DESC:
                    arsort($sortable_array);
                    break;
            }

            foreach ($sortable_array as $k => $v) {
                $new_array[] = $array[$k];
            }
        }

        return $new_array;
    }

}

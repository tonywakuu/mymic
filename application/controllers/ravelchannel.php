<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class ravelchannel extends CI_Controller {

    private $ipaddress = 'localhost';

    function ravelchannel() {
        parent::__construct();
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, token");
        header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
        $this->load->helper('string');
        $this->load->helper('form');
        $this->load->helper('url');
        $this->load->helper('text');
        $this->load->model('ravelchannel_model');
        $this->load->model('comman_model');
    }

    /**
     * Function to get a all channel category
     */
    function getCategory() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {
                $docPerPage = $this->input->post('docPerPage', TRUE);
                $result = $this->ravelchannel_model->getCategory($docPerPage);
                if (isset($result['errors'])) {
                    $this->checkResult($result);
                } else {
                    $resultFinal['catagories'] = $result;
                    $this->checkResult($resultFinal);
                }
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /**
     * Function to create a channel 
     */
    function createChannel() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {
                $channelName = trim($this->input->post('channelName', TRUE));
                $description = trim($this->input->post('description', TRUE));
                $channelCategoryId = trim($this->input->post('channelCategoryId', TRUE));
                $imageUpload = trim($this->input->post('isImageUpload', TRUE));
                $videoUpload = trim($this->input->post('isVideoUpload', TRUE));
                $angle = trim($this->input->post('angle', TRUE));
                if ($angle == '') {
                    $angle = 0;
                }
                if (!empty($channelName) && !empty($description) && !empty($channelCategoryId)) {
                    $data = array();
                    $data['channelName'] = $channelName;
                    $data['description'] = $description;
                    $data['channelCategoryId'] = $channelCategoryId;
                    $data['imageUpload'] = $imageUpload;
                    $data['videoUpload'] = $videoUpload;
                    $data['accessToken'] = $accessToken;
                    $data['userId'] = $verifyuserID;
                    $data['angle'] = (int) $angle;
                    $result = $this->ravelchannel_model->createChannel($data);
                    $this->checkResult($result);
                } else {
                    $this->checkResult($this->comman_model->senderror('EE037'));
                }
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /**
     * Function to edit user channel
     */
    function editChannel() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {
                $channelDetails = array();
                $channelId = $this->input->post('channelId', TRUE);
                $channelName = $this->input->post('channelName', TRUE);
                $description = $this->input->post('description', TRUE);
                $channelCategoryId = $this->input->post('channelCategoryId', TRUE);
                $imageUpload = $this->input->post('isImageUpload', TRUE);
                $videoUpload = $this->input->post('isVideoUpload', TRUE);
                $planId = $this->input->post('planId', TRUE);
                $angle = trim($this->input->post('angle', TRUE));
                if ($angle == '') {
                    $angle = 0;
                }
                if (!empty($channelName) && !empty($description) && !empty($channelCategoryId) && !empty($channelId)) {
                    $data = array();
                    $data['channelId'] = $channelId;
                    $data['channelName'] = $channelName;
                    $data['description'] = $description;
                    $data['channelCategoryId'] = $channelCategoryId;
                    $data['imageUpload'] = $imageUpload;
                    $data['videoUpload'] = $videoUpload;
                    $data['planId'] = $planId;
                    $data['accessToken'] = $accessToken;
                    $data['userId'] = $verifyuserID;
                    $data['angle'] = (int) $angle;
                    $result = $this->ravelchannel_model->editChannel($data);
                    $this->checkResult($result);
                } else {
                    $this->checkResult($this->comman_model->senderror('EE037'));
                }
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /**
     * Function to delete a channel
     * @return array
     */
    function deleteChannel() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {
                $channelId = $this->input->post('channelId', TRUE);
                $userId = $verifyuserID;
                if (!empty($channelId)) {
                    $result = $this->ravelchannel_model->deleteChannel($channelId, $userId, $accessToken);
                    $this->checkResult($result);
                } else {
                    $this->checkResult($this->comman_model->senderror('EE037'));
                }
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /**
     * Function to get accesstoken from header 
     */
    function getHeader() {
        $accessToken = '';
        foreach (getallheaders() as $name => $value) {
            if ($name == 'token') {
                return $accessToken = $value;
            }
        }
        return $accessToken;
    }

    /**
     * Function to create a favorite channel 
     */
    function createFavoriteChannel() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {
                $userId = $verifyuserID;
                $channelId = $this->input->post('channelId', TRUE);
                $isFavorite = $this->input->post('isFavorite', TRUE);
                if (!empty($channelId) && $isFavorite != '') {
                    $result = $this->ravelchannel_model->createFavoriteChannel($userId, $channelId, $isFavorite);
                    $this->checkResult($result);
                } else {
                    $this->checkResult($this->comman_model->senderror('EE037'));
                }
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /**
     * Function to get a favorite channel 
     */
    function getFavoriteChannel() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {
                $result = $this->ravelchannel_model->getFavoriteChannel($verifyuserID);
                if (isset($result['errors'])) {
                    $this->checkResult($result);
                } else {
                    $resultFinal['favorite'] = $result;
                    $this->checkResult($resultFinal);
                }
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /**
     * Function to get channel for map 
     */
    function getMapChannel() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {
                $userId = $verifyuserID;
                $searchParams = $this->input->post('searchParams', TRUE);
                if (!empty($searchParams)) {
                    $result = $this->ravelchannel_model->findChannels($searchParams, $userId, true);
                    if (isset($result['errors'])) {
                        $this->checkResult($result);
                    } else {
                        $resultFinal['channel'] = $result;
                        $this->checkResult($resultFinal);
                    }
                } else {
                    $this->checkResult($this->comman_model->senderror('EE037'));
                }
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /**
     * Function to get channel for map 
     */ /* {
      "mapMarkerUser": {
      "location": {
      "lat": 454564556,
      "lng": 546546465
      },
      "radius": "30",
      "count": "10"
      }
      } */
    function mapMarkerUser() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {
                $userId = $verifyuserID;
                $request = (file_get_contents('php://input'));
                $getData = json_decode($request);
                $radius = (isset($getData->mapMarkerUser->radius) && $getData->mapMarkerUser->radius != '') ? $getData->mapMarkerUser->radius : '0.10';
                $lat = (isset($getData->mapMarkerUser->location->lat) && $getData->mapMarkerUser->location->lat != '') ? $getData->mapMarkerUser->location->lat : '34.052235';
                $long = (isset($getData->mapMarkerUser->location->lng) && $getData->mapMarkerUser->location->lng != '') ? $getData->mapMarkerUser->location->lng : '-118.243683';
                if ((float) $long > 180 || (float) $long < -180) {
                    $long = -118.243683;
                }
                if ((float) $lat > 90 || (float) $lat < -90) {
                    $lat = 34.052235;
                }
                $result = $this->ravelchannel_model->mapMarkerUser($userId, $radius, $lat, $long);
                $this->checkResult($result);
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /**
     * Function to initiate the search for channels
     */
    function searchChannel() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {
                $userId = $verifyuserID;
                $searchParams = $this->input->post('searchParams', TRUE);
                if (!empty($searchParams)) {
                    $result = $this->ravelchannel_model->findChannels($searchParams, $userId, false);
                    if (isset($result['errors'])) {
                        $this->checkResult($result);
                    } else {
                        $resultFinal['channel'] = $result;
                        $this->checkResult($resultFinal);
                    }
                } else {
                    $this->checkResult($this->comman_model->senderror('EE037'));
                }
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /**
     * Function to create a content(broadcast) of channel     
     * @return array
     */
    function createContent() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {
                $request = (file_get_contents('php://input'));
                $getData = json_decode($request);
                $channelId = (isset($getData->createContent->channelId)) ? $getData->createContent->channelId : '';
                $userId = $verifyuserID;
                $contentName = (isset($getData->createContent->name)) ? $getData->createContent->name : 'No title';
                $description = (isset($getData->createContent->description)) ? $getData->createContent->description : '';
                $startTime = (isset($getData->createContent->startTime)) ? $getData->createContent->startTime : '';
                $endTime = (isset($getData->createContent->endTime)) ? $getData->createContent->endTime : '';
                $accessType = (isset($getData->createContent->accessType)) ? $getData->createContent->accessType : '';
                $rating = (isset($getData->createContent->rating)) ? $getData->createContent->rating : '';
                $contactInvites = (isset($getData->createContent->contactInvites)) ? $getData->createContent->contactInvites : '';
                $deviceType = (isset($getData->createContent->deviceType)) ? $getData->createContent->deviceType : '';
                if ($channelId != '' && $startTime != '') {
                    $broadcastData['channelId'] = $channelId;
                    $broadcastData['userId'] = $userId;
                    $broadcastData['contentName'] = $contentName;
                    $broadcastData['description'] = $description;
                    $broadcastData['startTime'] = (int) $startTime;
                    $broadcastData['endTime'] = (int) $endTime;
                    $broadcastData['accessType'] = $accessType;
                    $broadcastData['rating'] = $rating;
                    $broadcastData['contactInvites'] = $contactInvites;
                    $broadcastData['deviceType'] = $deviceType;
                    $result = $this->ravelchannel_model->createContent($broadcastData, $accessToken);
                    $this->checkResult($result);
                } else {
                    $this->checkResult($this->comman_model->senderror('EE037'));
                }
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /**
     * Function to create a content(broadcast) of channel and start now    
     * @return array
     */
    function createContentStartNow() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {
                $request = (file_get_contents('php://input'));
                $getData = json_decode($request);
                $channelId = (isset($getData->createContent->channelId)) ? $getData->createContent->channelId : '';
                $userId = $verifyuserID;
                $contentName = (isset($getData->createContent->name)) ? $getData->createContent->name : 'No title';
                $description = (isset($getData->createContent->description)) ? $getData->createContent->description : '';
                $startTime = (isset($getData->createContent->startTime)) ? $getData->createContent->startTime : '';
                $endTime = (isset($getData->createContent->endTime)) ? $getData->createContent->endTime : '';
                $accessType = (isset($getData->createContent->accessType)) ? $getData->createContent->accessType : '';
                $rating = (isset($getData->createContent->rating)) ? $getData->createContent->rating : '';
                $contactInvites = (isset($getData->createContent->contactInvites)) ? $getData->createContent->contactInvites : '';
                $deviceType = (isset($getData->createContent->deviceType)) ? $getData->createContent->deviceType : '';
                if ($channelId != '') {
                    $broadcastData['channelId'] = $channelId;
                    $broadcastData['userId'] = $userId;
                    $broadcastData['contentName'] = $contentName;
                    $broadcastData['description'] = $description;
                    $broadcastData['endTime'] = (int) $endTime;
                    $broadcastData['accessType'] = $accessType;
                    $broadcastData['rating'] = $rating;
                    $broadcastData['contactInvites'] = $contactInvites;
                    $broadcastData['deviceType'] = $deviceType;
                    $result = $this->ravelchannel_model->createContentStartNow($broadcastData, $accessToken);
                    $this->checkResult($result);
                } else {
                    $this->checkResult($this->comman_model->senderror('EE037'));
                }
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /**
     * Function to get a predefined rating data
     */
    function getRating() {
        if ($this->input->server('REQUEST_METHOD') == 'GET') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {
                $result = $this->ravelchannel_model->getRatingType();
                if (isset($result['errors'])) {
                    $this->checkResult($result);
                } else {
                    $resultFinal['ratings'] = $result;
                    $this->checkResult($resultFinal);
                }
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /**
     * Function to get report msg
     */
    function getReportMsg() {
        if ($this->input->server('REQUEST_METHOD') == 'GET') {
            $result = $this->ravelchannel_model->getReportMsg();
            $resultFinal['reportMsg'] = $result;
            $this->checkResult($resultFinal);
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /**
     * Function to get a all channel plan
     */
    function getChannelPlan() {
        if ($this->input->server('REQUEST_METHOD') == 'GET') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {
                $result = $this->ravelchannel_model->getChannelPlan($verifyuserID);
                //if (isset($result['errors'])) {
                $this->checkResult($result);
                //} else {
                //   $resultFinal['plans'] = $result;
                // $this->checkResult($resultFinal);
                //}
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /**
     * Function to update a channel plan
     */
    function updateChannelPlan() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {
                $userId = $verifyuserID;
                $PlanId = $this->input->post('planId', TRUE);
                if ($PlanId != "") {
                    $result = $this->ravelchannel_model->updateChannelPlan($userId, $PlanId);
                    $this->checkResult($result);
                } else {
                    $this->checkResult($this->comman_model->senderror('EE037'));
                }
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /**
     * Function to get a send invitation
     */
    function getSendInvitation() {
        if ($this->input->server('REQUEST_METHOD') == 'GET') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {
                $userId = $verifyuserID;
                $result = $this->ravelchannel_model->getSendInvitation($userId);
                $this->checkResult($result);
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /**
     * Function to get a update status of invitation
     */
    function updateInvitation() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {
                $userId = $verifyuserID;
                $contentId = $this->input->post('contentId', TRUE);
                $status = $this->input->post('status', TRUE);
                if ($contentId != "") {
                    $result = $this->ravelchannel_model->updateInvitationStatus($userId, $contentId, $status);
                    $this->checkResult($result);
                } else {
                    $this->checkResult($this->comman_model->senderror('EE037'));
                }
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /**
     * Function to verify the result
     * @param type $result
     */
    function checkResult($result) {
        echo $this->returnSuccess($result);
        return;
    }

    /**
     * Function used for return a json data
     * @param type $result
     */
    function returnSuccess($result) {

        $return_value = array();
        if (isset($result['errors'])) {
            if ($result['errors'][0]['errorCode'] == 'EE006' || $result['errors'][0]['errorCode'] == 'EE063') {
                $status = 2;
            } else {
                $status = 0;
            }
        } else {
            $status = 1;
        }
        $return_value['status'] = $status;
        $return_value['response'] = $result;
        echo json_encode($return_value);
    }

    /**
     * Function to invite contact
     * @return array
     */
    function inviteContact() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {

                $request = (file_get_contents('php://input'));
                $getData = json_decode($request);
                $channelId = (isset($getData->inviteContent->channelId)) ? $getData->inviteContent->channelId : '';
                $contentId = (isset($getData->inviteContent->contentId)) ? $getData->inviteContent->contentId : '';
                $userId = $verifyuserID;
                $accessType = (isset($getData->inviteContent->accessType)) ? $getData->inviteContent->accessType : '';
                $contactInvites = (isset($getData->inviteContent->contactInvites)) ? $getData->inviteContent->contactInvites : '';
                if (!empty($userId) && !empty($channelId) && !empty($contentId)) {
                    $result = $this->ravelchannel_model->inviteContact($userId, $contentId, $channelId, $contactInvites, $accessType);
                    if ($result) {
                        $result = array("message" => 'Successfully send invitation');
                        $this->checkResult($result);
                    } else {
                        $result = $this->comman_model->senderror('EE039');
                    }
                } else {
                    $error = $this->comman_model->senderror('EE039');
                    $this->checkResult($error);
                }
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /**
     * Function to create weekly viewers
     * @return array
     */
    function createWeeklyViewers() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {
                $request = (file_get_contents('php://input'));
                $getData = json_decode($request);
                $channelId = (isset($getData->weeklyViewers->channelId)) ? $getData->weeklyViewers->channelId : '';
                $userId = $verifyuserID;
                if (!empty($userId) && !empty($channelId)) {
                    $result = $this->ravelchannel_model->createWeeklyViewers($userId, $channelId);
                    $this->checkResult($result);
                } else {
                    $error = $this->comman_model->senderror('EE039');
                    $this->checkResult($error);
                }
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }
    function testPush(){
        $this->channel_model->testPush();
    }

}

/* End of file ravelchannel.php */
/* Location: ./application/controllers/ravelchannel.php */

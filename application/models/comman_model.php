<?php

require_once realpath(__DIR__ . '/../..') . '/application/third_party/aws/aws-autoloader.php';
require_once realpath(__DIR__ . '/../..') . '/application/third_party/Google/autoload.php';
if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class comman_model extends CI_Model {

    private $db, $mongo, $timezone;

    function comman_model() {
        parent::__construct();
        $this->load->helper('string');
        $this->load->helper('form');
        $this->load->helper('url');
        $this->load->helper('text');
        $this->load->library('rest_client');
        $this->load->library('countrycode');
        $this->load->helper('date');
        $this->mongo = $this->config->config['mongo'];
        $this->openFireUrl = $this->config->config['openfire_url'];
        $dbName = $this->config->config['mongoDb'];
        $this->db = $this->mongo->$dbName;
        $timezone = $this->config->config['timezone'];
        $this->awsS3 = $this->config->config['awsS3'];
        $this->invitationMessage = $this->config->config['invitationMessage'];
        $this->googleProjectP12 = $this->config->config['googleProjectP12'];
        $this->clientEmailGoogleProject = $this->config->config['clientEmailGoogleProject'];
        $this->paymentAppleId = $this->config->config['paymentAppleId'];
        $this->sandboxItunes = $this->config->config['sandboxItunes'];
    }

    /**
     * Function to used to upload on s3
     * @param type $file       
     */
    function s3FileUpload($file, $image = NULL, $angle = NULL) {
        $bucket = $this->awsS3['bucket'];
        $name = $file['name'];
        $tmp = $file['tmp_name'];
        $type = $file['type'];
        $typeArray = explode('/', $type);
        $mediaType = $typeArray[0];
        $dir = 'images';
        if ($mediaType == 'video')
            $dir = 'videos';
        $keyMediaType = '';
        if ($image) {
            $keyTypeArray = explode('.', $image);
            $keyMediaType = $keyTypeArray[count($keyTypeArray) - 1];
        }
        $ext = $this->getExtension($name);
        try {
            // Fires an exception if there is no data sent
            if (!isset($file)) {
                throw new Exception("File not uploaded", 1);
            }
            // Creates a client object, informing AWS credentials
            $clientS3 = $this->getS3Client();
            if (!$image) {
                $actual_image_name = rand() . time() . "." . $ext;
                $image = $actual_image_name;
            }
            $actual_image_name = rand() . time() . "." . $ext;
            if ($ext != $keyMediaType) {
                $fileKey = $dir . '/' . $actual_image_name;
                $imageKey = $actual_image_name;
            } else {
                $fileKey = $dir . '/' . $image;
                $imageKey = $image;
            }
            $this->deleteObject($image);
            if ($type == '') {
                $type = "application/octet-stream";
            }
            $response = $clientS3->putObject(array(
                'Bucket' => $bucket,
                'Key' => $fileKey,
                'SourceFile' => $tmp,
                'ACL' => 'public-read',
                'ContentType' => $type
            ));
            $thumbnailUrl = '';
            if ($mediaType == 'video') {
                $thumbnailPath = $this->createVideoThumbnailViaFfmpeg($response['ObjectURL'], $imageKey, $angle);
                $thumbnailArr = explode('/', $thumbnailPath);
                $responseThumbnail = $this->uploadThumbnail($thumbnailPath, $thumbnailArr[count($thumbnailArr) - 1]);
                $thumbnailUrl = $responseThumbnail['ObjectURL'];
                unlink($thumbnailPath);
            }
            return array('type' => $mediaType, 'url' => $response['ObjectURL'], 'image' => $imageKey, 'thumbnail' => $thumbnailUrl);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get Object Url     *
     * Retreives object url.     *
     * @param string $keyName The keyName.
     * @return string $objectUrl.
     */
    function getObjectUrl($keyName) {
        $clientS3 = $this->getS3Client();
        $bucket = $this->awsS3['bucket'];
        $objectUrl = $clientS3->getObjectUrl($bucket, $keyName);
        return $objectUrl;
    }

    /**
     * Delete Object     *
     * Deletes an object.     *
     * @param string $key The key.
     */
    function deleteObject($key) {
        $typeArray = explode('/', $key);
        $mediaType = $typeArray[count($typeArray) - 1];
        $dir = 'images';
        if ($mediaType == 'video')
            $dir = 'videos';
        $bucket = $this->awsS3['bucket'];
        $clientS3 = $this->getS3Client();
        $result = $clientS3->deleteObject(array(
            'Bucket' => $bucket,
            'Key' => $dir . "/" . $key,
        ));
    }

    /**
     * Get S3 Client     *
     * gets s3 client.     *
     * @return S3Client $s3Client.
     */
    function getS3Client() {
        $bucket = $this->awsS3['bucket'];
        $accessKey = $this->awsS3['accessKey'];
        $secretKey = $this->awsS3['secretKey'];
        $region = $this->awsS3['region'];
        $s3Client = Aws\S3\S3Client::factory(array(
                    'credentials' => array(
                        'key' => $accessKey,
                        'secret' => $secretKey
                    ),
                    'region' => $region,
                    'version' => 'latest'
        ));
        return $s3Client;
    }

    /**
     * Get Extension     *
     * Gets extension of file.     *
     * @param string $str The file name.
     * @return string $ext.
     */
    function getExtension($str) {
        $i = strrpos($str, ".");
        if (!$i) {
            return "";
        }
        $l = strlen($str) - $i;
        $ext = substr($str, $i + 1, $l);
        return $ext;
    }

    /**
     * Create Video Thumbnail   *
     * Creates video thumbnail.   *
     * @param string $key The key.
     * @return string $image.
     */
    function createVideoThumbnailViaFfmpeg($video, $thumbnail, $angle = NULL) {
        // Where ffmpeg is located.  
        $ffmpeg = '/usr/bin/ffmpeg';
        $thumbArr = explode('.', $thumbnail);
        // Where to save the image.  
        $image = getcwd() . '/assets/thumbnail/' . $thumbArr[0] . ".jpeg";
        // Time to take screenshot at.  
        $interval = 1;
        // Screenshot size.  
        $size = '600x600';
        // Ffmpeg command.  
        //$ffmpeg . ' -i ' . $output_file_full . ' -metadata:s:v:0 rotate=0 -vf "transpose=1" ' . $output_file_full . ".rot.mp4 2>&1"
        $cmd = "$ffmpeg -i $video -deinterlace -an -ss $interval -f mjpeg -t 1 -r 1 -y -s $size $image 2>&1";
        // Execute command.
        shell_exec($cmd);
        chmod($image, 0777);
        if ($angle != NULL) {
            $source = imagecreatefromjpeg($image);
            $rotate = imagerotate($source, $angle, 0);
            imagejpeg($rotate, $image);
        }
        return $image;
    }

    /**
     * Upload Video Thumbnail     *
     * Uploads video thumbnail to s3.     *
     * @param string $source The source.
     * @param string $key The key.
     * @return string $image.
     */
    public function uploadThumbnail($source, $key) {
        $bucket = $this->awsS3['bucket'];
        $clientS3 = $this->getS3Client();
        $response = $clientS3->putObject(array(
            'Bucket' => $bucket,
            'Key' => "thumbnail/" . $key,
            'SourceFile' => $source,
            'ACL' => 'public-read-write',
        ));
        return $response;
    }

    /**
     * Function to send a error  
     * @param type $errorNumber     
     */
    function senderror($errorNumber, $msg = null) {

        $errordata = array();
        $mongouserresult['errors'] = array();
        switch ($errorNumber) {
            case 'EE001':
                $errormsg = "We don't recognize this email or password";
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
                $errormsg = 'Invalid verification code.';
                break;
            case 'EE011':
                $errormsg = 'Username cannot be empty';
                break;
            case 'EE012':
                $errormsg = 'Password cannot be empty';
                break;
            case 'EE013':
                $errormsg = 'Please enter a valid email address.';
                break;
            case 'EE014':
                $errormsg = 'Please enter valid country code and mobile number.';
                break;
            case 'EE015':
                $errormsg = 'Channelname already exists';
                break;
            case 'EE016':
                $errormsg = "We don't recognize this email or password.";
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
            case 'EE022':
                $errormsg = 'Failed to retrieve map channel list.';
                break;
            case 'EE023':
                $errormsg = 'Channel Id does not exist in db.';
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
                $errormsg = 'You have reached the channel creation limit. Please upgrade your plan to create more channels.';
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
                $errormsg = "We don't recognize the old password.";
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
            case 'EE046':
                $errormsg = 'There is no active plan for this user';
                break;
            case 'EE047':
                $errormsg = 'Start time must be greater than present time.';
                break;
            case 'EE050':
                $errormsg = 'Invalid request type.';
                break;
            case 'EE051':
                $errormsg = 'User Phone number not verified.';
                break;
            case 'EE052':
                $errormsg = 'Phone Number already exists';
                break;
            case 'EE053':
                $errormsg = 'As per your plan you can create broadcast for ' . $msg . ' seconds';
                break;
            case 'EE054':
                $errormsg = 'You have already rated this broadcast';
                break;
            case 'EE055':
                $errormsg = 'You have successfully rated this broadcast';
                break;
            case 'EE056':
                $errormsg = 'You have already reported this broadcast';
                break;
            case 'EE057':
                $errormsg = 'You have successfully reported this broadcast';
                break;
            case 'EE058':
                $errormsg = 'It appears broadcast has not started yet.';
                break;
            case 'EE059':
                $errormsg = 'Can not update user location';
                break;
            case 'EE060':
                $errormsg = 'Nickname already exist.';
                break;
            case 'EE061':
                $errormsg = 'User have not able to get push notification.';
                break;
            case 'EE062':
                $errormsg = 'According to your plan you can not save broadcast.';
                break;
            case 'EE063':
                $errormsg = 'Your account has been suspended by admin please contact to admin for more information.';
                break;
            case 'EE064':
                $errormsg = '@ special character are not allowed.';
                break;
            case 'EE065':
                $errormsg = 'You are already using the same plan.';
                break;
            case 'EE066':
                $errormsg = 'As per new plan you can have ' . $msg . ' channel.';
                break;
            case 'EE067':
                $errormsg = 'Please choose right plan for upgrade.';
                break;
            case 'EE068':
                $errormsg = 'Please choose right plan for downgrade.';
                break;
            case 'EE069':
                $errormsg = 'Please provide valid plan ids.';
                break;
            case 'EE070':
                $errormsg = "Please upload a valid demo reel of length less than or equals to " . $msg . " secs.";
                break;
            case 'EE071':
                $errormsg = "Invalid Payment";
                break;
            case 'EE072':
                $errormsg = $msg . " is already using this account for payment.Please login in ravel app using " . $msg . " credentials.";
                break;
            case 'EE073':
                $errormsg = "Password must be 5 or more characters long";
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

    /**
     * Function to verify if a user already login or not
     * @param type $data
     */
    function oAuth($data, $logout = NULL) {
        $result = 0;
        $token = base64_decode($data);
        $getResult = explode('##', $token);
        if (count($getResult) > 0 && (isset($getResult[1])) && (isset($getResult[3]))) {
            $collection = $this->db->userdeviceinfo;
            $userId = $getResult[0];
            $userCollection = $this->db->user;
            $user = $userCollection->findOne(array("st" => 0, "_id" => new MongoId($userId)));
            if ($logout) {
                $logoutResult = $collection->update(array("uid" => new MongoId($userId), "dvcs.tid" => (string) $data), array('$set' => array("mat" => time(), "dvcs.$.st" => 0)));
                if (count($logoutResult)) {
                    $userCollection->update(array("_id" => new MongoId($userId)), array('$set' => array("socket" => 0)));
                    return $result = array("message" => 'Successfully logout');
                }
            } elseif (count($user) > 0) {
                return $result = $this->senderror('EE063');
            } else {
                //db.userdeviceinfo.find({uid:ObjectId("56cc2e176be4e66d10bc8579")}, 
                //{dvcs:{$elemMatch:{"tid":"NTZjYzJlMTc2YmU0ZTY2ZDEwYmM4NTc5IyNNR1ExTURrIyMxIyNKb2N4MQ=="}}}).pretty();
                $getData = $collection->findOne(array('uid' => new MongoId($userId)), array("dvcs" => array('$elemMatch' => array("tid" => (string) $data, "st" => 1))));

                if ((count($getData) > 0) && (isset($getData['dvcs'])) /* && ($getData['dvcs'][0]['tet'] > time()) */) {
                    $getResult = $collection->update(array("uid" => new MongoId($userId), "dvcs.tid" => (string) $data), array('$set' => array("mat" => time(), 'dvcs.$.tet' => time() + 3 * 3600, "dvcs.$.st" => 1)));
                    $result = $userId;
                } else {
                    $getResult = $collection->update(array("uid" => new MongoId($userId), "dvcs.tid" => (string) $data), array('$set' => array("mat" => time(), "dvcs.$.st" => 0)));
                    $result = $this->senderror('EE006');
                }
            }
        } else {
            $result = $this->senderror('EE006');
        }
        return $result;
    }

    /**
     * Function to used to genrate comman user response
     * @param type $result
     * @param type $accessToken    
     * @param type $verified      
     */
    function generateResponseUser($result, $accessToken, $verified) {
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
        if ($verified == 1) {
            $phoneNumber = (isset($result['pno']) && $result['pno'] != 'false' && $result['pno'] != '') ? $result['pno'] : '';
        } else {
            $phoneNumber = "";
        }
        $imgDefault = base_url() . 'assets/profilepic/default_user_image.png';
        $userResult = array();
        $userResult['id'] = (string) $result['_id'];
        $userResult['userName'] = $result['un'];
        $userResult['moneyEarned'] = number_format((isset($result['uem'])) ? $result['uem'] : 0, 2);
        $userResult['cashIn'] = number_format((isset($result['cin'])) ? $result['cin'] : 0, 2);
        $userResult['nickName'] = (isset($result['nickname'])) ? $result['nickname'] : "";
        $userResult['name'] = (isset($result['name'])) ? $result['name'] : "";
        $userResult['firstName'] = (isset($result['fname']) && $result['fname'] != 'false' && $result['fname'] != '') ? $result['fname'] : '';
        $userResult['lastName'] = (isset($result['lname']) && $result['lname'] != 'false' && $result['lname'] != '') ? $result['lname'] : '';
        $userResult['accessToken'] = $accessToken;
        $userResult['createdDate'] = $result['cat'];
        $userResult['type'] = $typeLogin;
        $userResult['email'] = (isset($result['email']) && $result['email'] != 'false' && $result['email'] != '') ? $result['email'] : '';
        $userResult['phoneNumber'] = $phoneNumber;
        $userResult['countryCode'] = (isset($result['ccod']) && $result['ccod'] != 'false' && $result['ccod'] != '') ? $result['ccod'] : '';
        $userResult['profileImage'] = (isset($result['pimg']) && $result['pimg'] != 'false' && $result['pimg'] != '') ? $result['pimg'] : $imgDefault;
        $userResult['isVerified'] = $verified;
        $userResult['planId'] = $result['pid'];
        $planDetails = $this->getPlanDetails($userResult['planId']);
        if ($planDetails != 0) {
            $userResult['planName'] = (isset($planDetails['name'])) ? $planDetails['name'] : '';
            $userResult['planPrice'] = (isset($planDetails['price'])) ? $planDetails['price'] : '';
            $userResult['planBroadcastLength'] = (isset($planDetails['blength'])) ? $planDetails['blength'] : '';
            $userResult['planBroadcastNumber'] = (isset($planDetails['bnum'])) ? $planDetails['bnum'] : '';
            $userResult['planChannelNumber'] = (isset($planDetails['cnum'])) ? $planDetails['cnum'] : '';
            $userResult['planDemorealLength'] = (isset($planDetails['ldreel'])) ? $planDetails['ldreel'] : '';
        }
        //$userResult['planName'] = $this->getPlanName($userResult['planId']);
        $userResult['saveBroadcast'] = (isset($result['savebcast'])) ? $result['savebcast'] : 0;
        $userResult['isPush'] = (isset($result['getpush'])) ? $result['getpush'] : 1;
        $userResult['isLoc'] = (isset($result['uploc'])) ? $result['uploc'] : 1;
        $userResult['openfirePassword'] = (isset($result['oppwd']) && $result['oppwd'] != 'false' && $result['oppwd'] != '') ? $result['oppwd'] : '';
        $userResult['unreadMessageCount'] = $this->countUnreadMessage($userResult['id']);
        $channelData = $this->getChannelList((string) $result['_id'], (string) $result['_id']);
        $userResult['channel'] = $channelData;
        return $userResult;
    }

    /**
     * Function to get plan name
     * @param type $planId         
     */
    function getPlanName($planId) {
        $planName = '';
        $planData = $this->db->plan->findOne(array("_id" => new MongoId($planId)));
        if (count($planData) > 0) {
            $planName = (isset($planData['name'])) ? $planData['name'] : '';
        }
        return $planName;
    }

    /**
     * Function to get plan name
     * @param type $planId         
     */
    function getPlanDetails($planId) {
        $plandetails = 0;
        $planData = $this->db->plan->findOne(array("_id" => new MongoId($planId)));
        if (count($planData) > 0) {
            $plandetails = $planData;
        }
        return $plandetails;
    }

    /**
     * Function to get channel list
     * @param type $userId
     * @param type $loginUserId
     * @return  array
     */
    function getChannelList($userId, $loginUserId) {

        $result = array();
        $channelArray = array();
        $responsechannel = array();
        $channelresult = $this->db->channel->find(array("uid" => new MongoId($userId), 'isactive' => 1));
        $channelContent = $this->db->channelcontent->find(array(), array('cid' => true, 'etime' => true, 'stime' => true));
        $broadcastLog = $this->db->userbroadcastlog->find();
        if (count(iterator_to_array($channelresult)) > 0) {
            foreach ($channelresult as $document) {
                $channelArray[] = (string) $document['_id'];
                $channelArrayKey[(string) $document['_id']] = $this->generateChannelDetail($document, $loginUserId, $channelContent, $broadcastLog);
            }
            if (count($channelArray) > 0) {
                $getliveContent = $this->getLiveContentResult($channelArray);
                $getUpcommingContent = $this->getUpcommingContentResult($channelArray);
                if ($getliveContent != 0) {
                    foreach ($getliveContent as $contentData) {
                        if (!isset($responsechannel[$contentData['cid']])) {
                            $responsechannel[$contentData['cid']] = $channelArrayKey[$contentData['cid']];
                        }
                        if ($contentData['actype'] == 1) {
                            $responsechannel[$contentData['cid']]['liveEvents'][] = $this->getUpcomingContent($contentData);
                        } else {
                            try {
                                $getInvite = $this->db->invitecontact->findOne(array(
                                    "conid" => (string) $contentData['_id'],
                                    '$or' => array(array("sid" => new MongoId($userId)),
                                        array("recid" => new MongoId($userId)))));
                                if (count($getInvite) > 0 || (string) $loginUserId == (string) $contentData['uid']) {
                                    $responsechannel[$contentData['cid']]['liveEvents'][] = $this->getUpcomingContent($contentData);
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
                                $responsechannel[$contentData['cid']]['upcomingEvents'][] = $this->getUpcomingContent($contentData);
                            } else {
                                try {
                                    $getInvite = $this->db->invitecontact->findOne(array(
                                        "conid" => (string) $contentData['_id'],
                                        '$or' => array(array("sid" => new MongoId($userId)),
                                            array("recid" => new MongoId($userId)))));
                                    if (count($getInvite) > 0 || (string) $loginUserId == (string) $contentData['uid']) {
                                        $responsechannel[$contentData['cid']]['upcomingEvents'][] = $this->getUpcomingContent($contentData);
                                    }
                                } catch (Exception $exc) {
                                    continue;
                                }
                            }
                        } else {
                            $responsechannel[$contentData['cid']] = $channelArrayKey[$contentData['cid']];
                            if ($contentData['actype'] == 1) {
                                $responsechannel[$contentData['cid']]['upcomingEvents'][] = $this->getUpcomingContent($contentData);
                            } else {
                                try {
                                    $getInvite = $this->db->invitecontact->findOne(array(
                                        "conid" => (string) $contentData['_id'],
                                        '$or' => array(array("sid" => new MongoId($userId)),
                                            array("recid" => new MongoId($userId)))));
                                    if (count($getInvite) > 0 || (string) $loginUserId == (string) $contentData['uid']) {
                                        $responsechannel[$contentData['cid']]['upcomingEvents'][] = $this->getUpcomingContent($contentData);
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
        return $result;
    }

    /**
     * Function to get rating detail according to rating id
     * @param type $ratingId     
     */
    function getratingDetail($ratingId) {
        $ratingresult = array();
        $ratingresult = $this->db->ratingmaster->findOne(array("_id" => new MongoId($ratingId)));
        return $ratingresult;
    }

    /**
     * Function to get weekly viewers 
     * @param type $channelId      
     */
    function getWeeklyViewers($channelId) {
        $startTime = strtotime('Last Monday', time());
        $endTime = strtotime('Monday', time()) + 3600 * 24;
        $collection = $this->db->weeklyviewer;
        $dateRange = $collection->find(array("cat" => array('$gt' => $startTime, '$lt' => $endTime), "cid" => $channelId));
        $getData = count(iterator_to_array($dateRange));
        return $getData;
    }

    /**
     * Function to get content name
     * @param type $contentId     
     */
    function getContentName($contentId) {
        $contentCollection = $this->db->channelcontent;
        $contentName = "";
        $contentData = $contentCollection->findOne(array("_id" => new MongoId($contentId)));
        if (count($contentData) > 0) {
            $contentName = $contentData['name'];
        }
        return $contentName;
    }

    /**
     * Function to get content name
     * @param type $contentId     
     */
    function getChannelName($contentId) {
        $contentCollection = $this->db->channelcontent;
        $channelName = "";
        $contentData = $contentCollection->findOne(array("_id" => new MongoId($contentId)));
        if (count($contentData) > 0) {
            $channelId = $contentData['cid'];
            $getChannelDetail = $this->db->channel->findOne(array("_id" => new MongoId($channelId)));
            $channelName = (isset($getChannelDetail['cn'])) ? $getChannelDetail['cn'] : "";
        }
        return $channelName;
    }

    /**
     * Function to get upcoming content data
     * @param type $content   
     */
    function getUpcomingContent($content) {
        $ratingName = '';
        $ratingId = (isset($content['cratid'])) ? $content['cratid'] : '';
        if ($ratingId != '') {
            $ratingresult = $this->getratingDetail($ratingId);
            $ratingName = $ratingresult['name'];
        }
        $contentDetail['contentId'] = $content['_id']->{'$id'};
        $contentDetail['contentName'] = $content['name'];
        $contentDetail['description'] = $content['des'];
        $contentDetail['broadcastId'] = (isset($content['ofrmid'])) ? $content['ofrmid'] : '';
        $contentDetail['broadcastPwd'] = (isset($content['ofpwd'])) ? $content['ofpwd'] : '';
        $contentDetail['startTime'] = $content['stime'];
        $contentDetail['endTime'] = $content['etime'];
        $contentDetail['ratingId'] = $ratingId;
        $contentDetail['ratingName'] = (isset($ratingName)) ? $ratingName : '';
        $contentDetail['accessType'] = $content['actype'];
        return $contentDetail;
    }

    /**
     * Function to get live content data
     * @param type $content    
     */
    function getLiveContent($content) {
        $ratingName = '';
        $ratingId = (isset($content['cratid'])) ? $content['cratid'] : '';
        if ($ratingId != '') {
            $ratingresult = $this->getratingDetail($ratingId);
            $ratingName = $ratingresult['name'];
        }
        $liveDetail['contentId'] = $content['_id']->{'$id'};
        $liveDetail['contentName'] = $content['name'];
        $liveDetail['description'] = $content['des'];
        $liveDetail['broadcastId'] = (isset($content['ofrmid'])) ? $content['ofrmid'] : '';
        $liveDetail['broadcastPwd'] = (isset($content['ofpwd'])) ? $content['ofpwd'] : '';
        $liveDetail['startTime'] = $content['stime'];
        $liveDetail['endTime'] = $content['etime'];
        $liveDetail['ratingId'] = $ratingId;
        $liveDetail['ratingName'] = (isset($ratingName)) ? $ratingName : '';
        $liveDetail['accessType'] = $content['actype'];
        return $liveDetail;
    }

    /**
     * Function to get live content data
     * @param type $content    
     */
    function createOpenfireRoom($type, $loginUserId, $params, $channel = false, $roomType = NULL, $contentId = NULL) {

        // Code snippet to create user on Open fire server.        
        $method = 'POST';
        if ($type == 1) {
            $uri = $this->openFireUrl . 'server/createusr';
            $response = $this->rest_client->send($uri, $method, $params);
            $openFireData = json_decode($response->body);
            $openfirePassword = '';
            if (isset($openFireData->status) && $openFireData->status == 1) {
                $openfirePassword = (isset($openFireData->result_set)) ? $openFireData->result_set : '';
                $this->db->user->update(array("_id" => new MongoId($loginUserId)), array('$set' => array('oppwd' => $openfirePassword)));
            }
            return $openfirePassword;
        }
        // Code snippet to create channel on Open fire server.
        if ($type == 2) {
            $openfirePassword = "";

            if ($channel) {
                $uri = $this->openFireUrl . 'server/createchannel?chid=' . $params['chid'] . '&uid=' . $params['uid'];
                $response = $this->rest_client->send($uri, $method, $params);
            } else {
                $uri = $this->openFireUrl . 'server/createchannelwithuser?chid=' . $params['chid'] . '&uid=' . $params['uid'];
                $response = $this->rest_client->send($uri, $method, $params);
                $openFireData = json_decode($response->body);

                if (isset($openFireData->status) && $openFireData->status == 1) {
                    if (isset($openFireData->result_set)) {
                        $openfirePassword = (isset($openFireData->result_set->password)) ? $openFireData->result_set->password : '';
                        //update user collection
                        $this->db->user->update(array("_id" => new MongoId($loginUserId)), array('$set' => array('oppwd' => $openfirePassword)));
                    }
                }
            }
            return true;
        }
        // Code snippet to create room on Open fire server.
        if ($type == 3) {
            if ($roomType == 1) {
                $uri = $this->openFireUrl . 'chatgrp/createrm';
            } else {
                $uri = $this->openFireUrl . 'chatgrp/creatermpwd';
            }
            try {
                $response = $this->rest_client->send($uri, $method, $params);
                $openFireData = json_decode($response->body);
                if (isset($openFireData->status) && $openFireData->status == 1 && $roomType == 1) {
                    $this->db->channelcontent->update(array("_id" => new MongoId($contentId)), array('$set' => array("ofrmid" => $openFireData->result_set, "ofpwd" => "")));
                }
                if (isset($openFireData->status) && $openFireData->status == 1 && $roomType == 0) {
                    $roomId = (isset($openFireData->result_set->rmid)) ? $openFireData->result_set->rmid : '';
                    $roomPassword = (isset($openFireData->result_set->pwd)) ? $openFireData->result_set->pwd : '';
                    $this->db->channelcontent->update(array("_id" => new MongoId($contentId)), array('$set' => array("ofrmid" => $roomId, "ofpwd" => $roomPassword)));
                }
                return true;
            } catch (Exception $ex) {
                return false;
            }
        }
    }

    /**
     * Function to count unread message
     * @param type $receiverId  
     */
    function countUnreadMessage($receiverId) {
        $collection = $this->db->message;
        $messagesCount = 0;
        if (isset($receiverId)) {
            $messagesCount = $collection->count(array('receiver_id' => new MongoId($receiverId),
                'is_read' => 0));
            return $messagesCount;
        }
    }

    /**
     * Function to send push notification to channel owner
     * @param type $channelOwnerId
     * @param type $userName
     * @param type $channelName
     */
    function sendPushToFavorite($channelOwnerId, $userName, $channelName) {

        $collection = $this->db->user;
        $gcmData = $IosData = array();
        $getOwnereDetails = $collection->findOne(array("_id" => new MongoId($channelOwnerId)));
        $sendPush = (isset($getOwnereDetails['getpush'])) ? $getOwnereDetails['getpush'] : 0;
        $pushNotificationData = $this->sendPushNotification($channelOwnerId, $sendPush, $userName, $channelName);
        return true;
    }

    /**
     * Function to send push when broadcast will be start.
     * @param type $channelOwnerId
     * @param type $sendPush
     * @param type $channelName
     * @param type $channelName
     */
    function sendPushNotification($channelOwnerId, $sendPush, $userName, $channelName) {
        $gcmData = $IosData = array();
        try {
            $getDeviceInfo = $this->db->userdeviceinfo->findOne(array("uid" => new MongoId($channelOwnerId)));
            if (count($getDeviceInfo) > 0 && $sendPush == 1) {
                if (isset($getDeviceInfo['dvcs']) && count($getDeviceInfo['dvcs']) > 0) {
                    foreach ($getDeviceInfo['dvcs'] as $val) {
                        if ($val['st'] == 1 && !empty($val['dregid'])) {
                            if ((int) $val['dtype'] == 1) {
                                $gcmData[] = $val['dregid'];
                            } else if ((int) $val['dtype'] == 2) {
                                $IosData[] = $val['dregid'];
                            }
                        }
                    }
                }
            }
            $message = str_replace("<un>", $userName, $this->invitationMessage['favorite']);
            $sendMessage = str_replace("<cn>", $channelName, $message);
            if (count($gcmData) > 0) {
                $this->pushnotification_model->pushAndroidNotification($gcmData, $sendMessage, "favorite");
            } else if (count($IosData) > 0) {
                $this->pushnotification_model->pushIosNotification($IosData, $sendMessage, "favorite");
            }
        } catch (Exception $ex) {
            return false;
        }
        return true;
    }

    /**
     * Function to get nick name of user
     * @param type $userId     
     */
    function getNickName($userId) {
        $collection = $this->db->user;
        $nickName = "";
        $userData = $collection->findOne(array("_id" => new MongoId($userId)));
        if (count($userData) > 0) {
            $nickName = $userData['nickname'];
        }
        return $nickName;
    }

    /**
     * Function to get channel details
     * @param type $document    
     * @param type $userId   
     */
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
        $data = $this->getChannelDurationRatingAndViewers($document, $channelContent, $broadcastLog);
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
        if (!empty($channelResult['channelId'])) {
            $channelResult['weeklyUser'] = $this->comman_model->getWeeklyViewers($channelResult['channelId']);
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
     * Function to check content is live or not
     * @param type $channelIds    
     */
    function getLiveContentResult($channelIds) {
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

    /**
     * Function to check content is upcoming or not
     * @param type $channelIds     
     */
    function getUpcommingContentResult($channelIds) {
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

    function currentWeek() {
        $d = strtotime("today");
        $date = array();
        $date['startDate'] = strtotime("last sunday midnight", $d);
        $date['endDate'] = strtotime("next saturday", $d);
        return $date;
        //$start = date("Y-m-d",$start_week);
        //$end = date("Y-m-d",$end_week);
    }

    function previousWeek() {
        $date = array();
        $previous_week = strtotime("-1 week +1 day");
        $date['startDate'] = strtotime("last sunday midnight", $previous_week);
        $date['endDate'] = strtotime("next saturday", $date['startDate']);
        //$start_week = date("Y-m-d",$start_week);
        //$end_week = date("Y-m-d",$end_week);
        return $date;
    }

    function currentMonth() {
        $date = array();
        $dt = time();
        $date['startDate'] = strtotime('first day of this month', $dt);
        $date['endDate'] = strtotime('last day of this month', $dt);
        return $date;
    }

    function previousMonth() {
        $date = array();
        $date['startDate'] = strtotime('first day of last month');
        $date['endDate'] = strtotime('last day of last month');
        return $date;
    }

    function getChannelDurationRatingAndViewers($channel, $content, $broadcastLog) {
        $data = array();
        $sum = 0;
        $count = 0;
        $reportCount = 0;
        $noContent = 0;
        $rating = 0;
        $viewers = 0;
        $channelDuration = 0;
        $channelId = isset($channel['_id']) ? (string) $channel['_id'] : $channel;
        foreach ($content as $channelContent) {
            if ($channelContent['cid'] == (string) $channelId) {
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
            }
        }
        $data['rating'] = $rating;
        $data['viewers'] = $viewers;
        $data['noContent'] = $noContent;
        $data['reportCount'] = $reportCount;
        $data['channelDuration'] = $channelDuration;
        $data['sum'] = $sum;
        return $data;
    }

    /**
     * Function to get country code        
     */
    function getCountryCode() {
        $ipaddress = $_SERVER["REMOTE_ADDR"];
        $details = $this->ip_details($ipaddress);
        $countryName = (isset($details->country) && $details->country != "") ? $details->country : "";
        if (!empty($countryName)) {
            $result = $this->countrycode->getCountry($details->country);
            return $result;
        } else {
            return false;
        }
    }

    /**
     * Function to get ip info
     * @param type $ip    
     */
    function ip_details($ip) {
        $json = file_get_contents("http://ipinfo.io/$ip");
        $details = json_decode($json);
        return $details;
    }

    /* This function is used to get access token from Google OAuth */

    function getAccessTokenGoogle() {
        $accessToken = 0;
        $client_email = $this->clientEmailGoogleProject;
        $private_key = file_get_contents($this->googleProjectP12);
        $scopes = array('https://www.googleapis.com/auth/sqlservice.admin', 'https://www.googleapis.com/auth/androidpublisher');
        $credentials = new Google_Auth_AssertionCredentials($client_email, $scopes, $private_key);
        $client = new Google_Client();
        $client->setAssertionCredentials($credentials);
        if ($client->getAuth()->isAccessTokenExpired()) {
            $client->getAuth()->refreshTokenWithAssertion();
            $accessToken = $client->getAccessToken();
        }
        return $accessToken;
    }

    /* This function is used to revoke permission on google play store */

    function subscriptionsRevokeGoogle($packageName, $subscriptionId, $token) {
        $response = FALSE;
        $getAccessToken = json_decode($this->getAccessTokenGoogle());
        if (isset($getAccessToken->access_token)) {
            $accessToken = $getAccessToken->access_token;
            $revokeUrl = 'https://www.googleapis.com/androidpublisher/v2/applications/' . $packageName . '/purchases/subscriptions/' . $subscriptionId . '/tokens/' . $token . ':revoke?access_token=' . $accessToken;
            $result = file_get_contents($revokeUrl);
            //log entry start
            $postLog = array();
            $postLog['token'] = $postLog;
            $postLog['response'] = $result;
            $this->createLog(json_encode($postLog));
            //log entry end
            $decode = json_decode($result);
            if (empty($decode)) {
                $response = TRUE;
            }
        }
        return $response;
    }

    function verifyPaymentOnPlayStore($packageName, $subscriptionId, $token) {
        $response = FALSE;
        $getAccessToken = json_decode($this->getAccessTokenGoogle());
        if (isset($getAccessToken->access_token)) {
            $accessToken = $getAccessToken->access_token;
            $revokeUrl = 'https://www.googleapis.com/androidpublisher/v2/applications/' . $packageName . '/purchases/subscriptions/' . $subscriptionId . '/tokens/' . $token . '?access_token=' . $accessToken;
            $result = file_get_contents($revokeUrl);
            //log entry start
            $postLog = array();
            $postLog['token'] = $postLog;
            $postLog['response'] = $result;
            $this->createLog(json_encode($postLog));
            //log entry end
            $decode = json_decode($result);
            if (!empty($decode) && $decode->paymentState == 1) {
                $response = TRUE;
            }
        }
        return $response;
    }

    function verifyPaymentOnItunes($receipt_data) {
        $response = FALSE;
        if ($this->sandboxItunes == TRUE) {
            $url = "https://sandbox.itunes.apple.com/verifyReceipt/";
        } else {
            $url = "https://buy.itunes.apple.com/verifyReceipt";
        }
        $ch = curl_init($url);
        $data_string = json_encode(array(
            'receipt-data' => $receipt_data,
            'password' => $this->paymentAppleId,
        ));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string))
        );
        $output = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (200 != $httpCode) {
            return $httpCode;
        }
        $decoded = json_decode($output, TRUE);
        if (isset($decoded['status']) && $decoded['status'] != 0) {
            $response = FALSE;
        } else if (isset($decoded['status']) == FALSE || isset($decoded['receipt']) == FALSE) {
            //receipt
            $response = FALSE;
        } else {
            $response = TRUE;
        }
        return $response;
    }

    function createLog($post) {
        $file = getcwd() . '/application/logs/paymentlog' . date("Y-m-d") . 'txt';
        $current = '';
        if (file_exists($file)) {
            $file = getcwd() . '/application/logs/paymentlog' . date("Y-m-d") . 'txt';
        } else {
            $file = fopen($file, 'w');
            chmod($file, 0777);
        }
// Open the file to get existing content
        $current = file_get_contents($file);
// Append a new person to the file
        $current .= date("Y-m-d H:i:s") . $post . "\n\n\n\n";
// Write the contents back to the file
        file_put_contents($file, $current);
    }

}

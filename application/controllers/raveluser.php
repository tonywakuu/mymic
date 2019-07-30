<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class raveluser extends CI_Controller {

    function raveluser() {
        parent::__construct();
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, token");
        header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
        $this->load->helper('string');
        $this->load->helper('form');
        $this->load->helper('url');
        $this->load->helper('text');
        $this->load->model('raveluser_model');
        $this->load->model('admin_model');
        $this->load->model('comman_model');
        //$this->webId = $this->config->config['webId'];
    }

    /**
     * Function to sign up with email    
     */
    function register() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $firstName = $this->input->post('firstName', TRUE);
            $userName = $this->input->post('userName', TRUE);
            $password = $this->input->post('password', TRUE);
            $lastName = $this->input->post('lastName', TRUE);
            $email = $this->input->post('email', TRUE);
            $phoneNumber = $this->input->post('phoneNumber', TRUE);
            $deviceId = $this->input->post('deviceId', TRUE);
            $registrationId = $this->input->post('registrationId', TRUE);
            $deviceType = $this->input->post('deviceType', TRUE);
            $fileUpload = $this->input->post('isUpload');

            if (!empty($userName) || !empty($password) || !empty($email) || !empty($deviceId) || !empty($deviceType) || !empty($fileUpload) || !empty($registrationId)) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = $this->comman_model->senderror('EE013');
                    $this->checkResult($error);
                }if (strpos($userName, '@') !== false) {
                    $error = $this->comman_model->senderror('EE064');
                    $this->checkResult($error);
                }
                if (strlen($password) < 5) {
                    $error = $this->comman_model->senderror('EE073');
                    $this->checkResult($error);
                }
                $data = array();
                $data['firstName'] = $firstName;
                $data['userName'] = $userName;
                $data['password'] = $password;
                $data['lastName'] = $lastName;
                $data['email'] = $email;
                $data['phoneNumber'] = $phoneNumber;
                $data['deviceId'] = $deviceId;
                $data['registrationId'] = $registrationId;
                $data['deviceType'] = $deviceType;
                $data['upload'] = $fileUpload;
                $result = $this->raveluser_model->register($data);
                $this->checkResult($result);
            } else {
                $this->checkResult($this->comman_model->senderror('EE037'));
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /**
     * Function to login with email and token     
     */
    function login() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $email = $this->input->post('email', TRUE);
            $password = $this->input->post('password', TRUE);
            $deviceId = $this->input->post('deviceId', TRUE);
            $deviceType = $this->input->post('deviceType', TRUE);
            $registrationId = $this->input->post('registrationId', TRUE);
            $loginType = $this->input->post('loginType', TRUE);

            if (isset($loginType) && !empty($loginType) && trim($loginType) == 'email') {
                if (!empty(trim($email)) && !empty($password) && !empty($deviceId) && !empty($deviceType)) {
                    if (!filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
                        $error = $this->comman_model->senderror('EE013');
                        $this->checkResult($error);
                    }
                    $this->checkResult($this->raveluser_model->loginEmail(trim($email), trim($password), trim($deviceId), trim($deviceType), trim($registrationId)));
                } else {
                    $this->checkResult($this->comman_model->senderror('EE037'));
                }
            } else if (isset($loginType) && !empty($loginType) && trim($loginType) == 'fb') {
                //$password means assecc token facebook
                if (!empty($password)) {
                    $result = $this->raveluser_model->loginFB(trim($password), trim($deviceId), trim($deviceType), trim($registrationId));
                    $this->checkResult($result);
                } else {
                    $this->checkResult($this->comman_model->senderror('EE037'));
                }
            } else if (isset($loginType) && !empty($loginType) && trim($loginType) == 'tw') {

                //$password means assecc token twitter
                if (!empty($password)) {
                    $result = $this->raveluser_model->loginTW(trim($password), trim($deviceId), trim($deviceType), trim($registrationId));
                    $this->checkResult($result);
                } else {
                    $this->checkResult($this->comman_model->senderror('EE037'));
                }
            } else if (isset($loginType) && !empty($loginType) && trim($loginType) == 'in') {
                //$password means assecc token twitter
                if (!empty($password)) {
                    $result = $this->raveluser_model->loginIN(trim($password), trim($deviceId), trim($deviceType), trim($registrationId));
                    $this->checkResult($result);
                } else {
                    $this->checkResult($this->comman_model->senderror('EE037'));
                }
            } else {
                $this->checkResult($this->comman_model->senderror('EE037'));
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /**
     * Function to logout a user
     */
    function logout() {
        if ($this->input->server('REQUEST_METHOD') == 'GET') {
            $accessToken = $this->getHeader();
            if (!empty($accessToken)) {
                $verifyuserID = $this->comman_model->oAuth($accessToken, '');
                if (isset($verifyuserID['errors'])) {
                    $this->checkResult($verifyuserID);
                } else {
                    $result = $this->comman_model->oAuth($accessToken, 'logout');
                    $this->checkResult($result);
                }
            } else {
                $this->checkResult($this->comman_model->senderror('EE037'));
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /**
     * Function to send a reset password link 
     */
    function forgotPassword() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $email = $this->input->post('email', TRUE);
            if ((!filter_var(trim($email), FILTER_VALIDATE_EMAIL)) && empty($email)) {
                $error = $this->comman_model->senderror('EE013');
                $this->checkResult($error);
            } else {
                $result = $this->raveluser_model->forgotPassword($email);
            }
            $this->checkResult($result);
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /**
     * Function to update user password
     */
    function resetPassword() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {
                $userId = $verifyuserID;
                $oldPassword = $this->input->post('oldPassword', TRUE);
                $newPassword = $this->input->post('newPassword', TRUE);
                if (!empty($oldPassword) && !empty($newPassword)) {
                    $result = $this->raveluser_model->changePassword($userId, $oldPassword, $newPassword, $accessToken);
                } else {
                    $result = $this->checkResult($this->comman_model->senderror('EE037'));
                }
                $this->checkResult($result);
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /**
     * Function to send otp to mobile 
     */
    function sendOtp() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            if (!empty($accessToken)) {
                $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            } else {
                $this->checkResult($this->comman_model->senderror('EE037'));
            }
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {
                $phoneNumber = $this->input->post('phoneNumber', TRUE);
                $countryCode = $this->input->post('countryCode', TRUE);
                if (!empty($phoneNumber) && !empty($countryCode)) {
                    $data = $this->raveluser_model->verifyNumber($phoneNumber, $countryCode);
                    if ($data) {
                        $result = $this->raveluser_model->sendOtp($phoneNumber, $countryCode, $verifyuserID);
                        $this->checkResult($result);
                    } else {
                        $this->checkResult($this->comman_model->senderror('EE052'));
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
     * Function to verify otp to mobile 
     */
    function verifyOtp() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            if (!empty($accessToken)) {
                $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            } else {
                $this->checkResult($this->comman_model->senderror('EE037'));
            }
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {
                $otp = $this->input->post('otp', TRUE);
                if (!empty($otp)) {
                    $result = $this->raveluser_model->verifyOtp($otp, $verifyuserID, $accessToken);
                } else {
                    $this->checkResult($this->comman_model->senderror('EE037'));
                }
                $this->checkResult($result);
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /**
     * Function to edit user profile
     */
    function editProfile() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {
                $userDetails = array();
                $userDetails['firstName'] = $this->input->post('firstName', TRUE);
                $userDetails['lastName'] = $this->input->post('lastName', TRUE);
                $userDetails['email'] = $this->input->post('email', TRUE);
                $userDetails['isUpload'] = $this->input->post('isUpload', TRUE);
                $userDetails['userId'] = $verifyuserID;
                $userDetails['accessToken'] = $accessToken;
                if ((!filter_var($userDetails['email'], FILTER_VALIDATE_EMAIL)) && !empty($userDetails['email'])) {
                    $result = $this->comman_model->senderror('EE013');
                } else {
                    $result = $this->raveluser_model->editProfile($userDetails);
                }
                $this->checkResult($result);
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    function getRevenue() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {
                $userId = $verifyuserID;
                $startIndex = $this->input->post('startIndex', TRUE);
                $limit = $this->input->post('limit', TRUE);
                $startDate = $this->input->post('startDate', TRUE);
                $endDate = $this->input->post('endDate', TRUE);
                $result = $this->admin_model->getRevenueHistory($startIndex, $limit, $userId, $startDate, $endDate);
                if (isset($result['errors'])) {
                    $resultData = $result;
                } else {
                    $resultData['revenueHistory'] = $result;
                }
                $this->checkResult($result);
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }    
    /**
     * Function to retrieve application configuration 
     */
    function getConfig() {
        $accessToken = $this->getHeader();
        $verifyuserID = $this->comman_model->oAuth($accessToken, '');
        if (isset($verifyuserID['errors'])) {
            $this->checkResult($verifyuserID);
        } else {
            $result['config'] = $this->raveluser_model->getAppConfig();
            $this->checkResult($result);
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
     * Function to get user details based on user id 
     */
    function userProfile() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {
                $userId = $this->input->post('userId', TRUE);
                $channelId = $this->input->post('channelId', TRUE);
                $result = $this->raveluser_model->getUserDetails($userId, $accessToken, $channelId, $verifyuserID);
                $this->checkResult($result);
            }
        } else {
            $this->checkResult($this->comman_model->senderror('EE050'));
        }
    }

    /**
     * Function to update user location 
     */
    function updateLocation() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {
                $latitude = $this->input->post('latitude', TRUE); //means y axis
                $longitude = $this->input->post('longitude', TRUE); //for x axis
                if (!empty($latitude) && !empty($longitude)) {
                    $userId = $verifyuserID;
                    $result = $this->raveluser_model->updateUserLocation($userId, $latitude, $longitude);
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
     * Function to get user profile
     */
    function myProfile() {
        $accessToken = $this->getHeader();
        $verifyuserID = $this->comman_model->oAuth($accessToken, '');
        $token = base64_decode($accessToken);
        if (isset($verifyuserID['errors'])) {
            $this->checkResult($verifyuserID);
        } else {

            $userId = $verifyuserID;
            $result = $this->raveluser_model->myProfile($userId);
            if (isset($result['errors'])) {
                $this->checkResult($result);
            } else {
                $resultFinal['myProfile'] = $result;
                $this->checkResult($resultFinal);
            }
        }
    }

    /**
     * Function to update user settings
     */
    function updateSettings() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {
                $request = (file_get_contents('php://input'));
                $getData = json_decode($request);
                $type = (isset($getData->updateSetting->type)) ? $getData->updateSetting->type : '';
                $status = (isset($getData->updateSetting->status)) ? $getData->updateSetting->status : '';
                $userId = $verifyuserID;
                if (!empty($type)) {
                    $result = $this->raveluser_model->updateSettings($userId, $status, $type);
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

    /**
     * Function to add nick name
     */
    function addNickName() {
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $accessToken = $this->getHeader();
            $verifyuserID = $this->comman_model->oAuth($accessToken, '');
            if (isset($verifyuserID['errors'])) {
                $this->checkResult($verifyuserID);
            } else {
                $request = (file_get_contents('php://input'));
                $getData = json_decode($request);
                $nickName = (isset($getData->addNickName->nickName)) ? $getData->addNickName->nickName : '';
                $isSave = (isset($getData->addNickName->isSave)) ? $getData->addNickName->isSave : 0;
                $userId = $verifyuserID;
                if (!empty($nickName)) {
                    if (strpos($nickName, '@') !== false) {
                        $error = $this->comman_model->senderror('EE064');
                        $this->checkResult($error);
                    }
                    $result = $this->raveluser_model->addNickName($userId, $nickName, $isSave);
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

}

/* End of file welcome.php */
/* Location: ./application/controllers/welcome.php */

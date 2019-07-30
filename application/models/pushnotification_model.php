<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

require_once realpath(__DIR__ . '/../..') . '/assets/twilio/Services/Twilio.php';
//Path to iOS certificate
define('IOS_CERTIFICATE_PATH', realpath(__DIR__ . '/..') . '/config/pushcert.pem');

/**
 * Class pushnotification to send the push notifications to the registration ids
 */
class pushnotification_model extends CI_Model {

    function pushnotification_model() {
        parent::__construct();

        $this->load->helper('string');
        $this->load->helper('url');
        $this->load->helper('text');
        $this->sid = $this->config->config['sid'];
        $this->token = $this->config->config['token'];
        $this->twilioNumber = $this->config->config['twilioNumber'];
        $this->ANDROID_API_ACCESS_KEY = $this->config->config['ANDROID_API_ACCESS_KEY'];
        $this->IOS_PASS_PHRASE = $this->config->config['IOS_PASS_PHRASE'];
        $this->apnsHost = $this->config->config['apnsHost'];
        $this->IOS_CERTIFICATE_PATH = $this->config->config['IOS_CERTIFICATE_PATH'];
        $this->mongo = $this->config->config['mongo'];
        $dbName = $this->config->config['mongoDb'];
        $this->db = $this->mongo->$dbName;
    }

    /**
     * Function to send twilio message
     * @param type $phoneNumber
     * @param type $sendMessage
     * @return boolean
     */
    public function sendTwilioMessage($phoneNumber, $sendMessage) {
        $sid = $this->sid; // Your Account SID from www.twilio.com/user/account
        $token = $this->token; // Your Auth Token from www.twilio.com/user/account
        $twilioNumber = $this->twilioNumber; // From a valid Twilio number
        $client = new Services_Twilio($sid, $token);
        if (count($phoneNumber) > 0) {
            foreach ($phoneNumber as $val) {
                try {
                    $message = $client->account->messages->sendMessage(
                            $twilioNumber, // From a valid Twilio number
                            $val, $sendMessage
                    );
                } catch (Exception $e) {
                    continue;
                }
            }
        }
        return true;
    }

    /**
     * Function to send Push notifications to the android devices
     * @param type $registrationIds
     * @param type $msg
     * @return boolean
     */
    public function pushAndroidNotification($registrationIds, $message, $type, $count = NULL, $channelId = NULL, $userId = NULL) {

        // prepare the bundle
        $msg = array
            (
            'message' => $message,
            'type' => $type,
            'count' => isset($count) ? $count : 0,
            'sound' => 1,
            'userId' => isset($userId) ? $userId : "",
            'channelId' => isset($channelId) ? $channelId : "",
            'largeIcon' => 'large_icon',
            'smallIcon' => 'small_icon'
        );
        $fields = array
            (
            'registration_ids' => $registrationIds,
            'data' => $msg
        );

        $headers = array
            (
            'Authorization: key=' . $this->ANDROID_API_ACCESS_KEY,
            'Content-Type: application/json'
        );
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://android.googleapis.com/gcm/send');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
            $result = curl_exec($ch);
            curl_close($ch);
            $getData = json_decode($result);
            if (isset($getData->success) && $getData->success == 1) {
                $this->updateCanonicalIds($registrationIds, $getData);
                return json_decode($result);
            } else {
                return false;
            }
        } catch (Exception $e) {
            return FALSE;
        }
    }

    /*
     * function to update canonical ids
     * @param type $registrationIds
     * @param type $response
     */

    function updateCanonicalIds($registrationId, $response) {

        if (isset($response->canonical_ids) && $response->canonical_ids >= 0) {
            $x = 0;
            foreach ($response->results as $data) {
                try {
                    if (isset($data->registration_id)) {
                        $getRegId = $registrationId[$x];
                        $updateArray = array(
                            "mat" => time(),
                            "dvcs.$.dregid" => (string) $data->registration_id,
                        );
                        $this->db->userdeviceinfo->update(array("dvcs.dregid" => (string) $getRegId), array('$set' => $updateArray));
                    }
                } catch (Exception $ex) {
                    continue;
                }
                $x++;
            }
        }
        return true;
    }

    function pushIosNotification($deviceToken, $message, $type, $count = NULL, $channelId = NULL, $userId = NULL) {
//$deviceToken="fda51aee8594c64bcad7f9a22995c0805b2f3bfcf0b12766a57d1f21b0899b3b";
        foreach ($deviceToken as $val) {
            try {
                $badge = 0;
                $apnsHost = $this->apnsHost;
//        $apnsHost = 'gateway.sandbox.push.apple.com';
                $apnsPort = 2195;
                $apnsCert = $this->IOS_CERTIFICATE_PATH;
                $streamContext = stream_context_create();
                stream_context_set_option($streamContext, 'ssl', 'local_cert', $apnsCert);
                $apns = @stream_socket_client('ssl://' . $apnsHost . ':' . $apnsPort, $error, $errorString, 5, STREAM_CLIENT_CONNECT, $streamContext);
                @stream_set_blocking($apns, 0);
                if ($apns === false) {
                    return 'error';
                } else {
                    $payload = array();
                    $aps = array(
                        'alert' => $message,
                        'type' => $type,
                        'count' => isset($count) ? $count : 0,
                        'sound' => 1,
                        'badge' => $badge,
                        'userId' => isset($userId) ? $userId : "",
                        'channelId' => isset($channelId) ? $channelId : ""
                    );
                    $return = array(
                        'type' => '1',
                        'message' => $message
                    );
                    $payload = array(
                        'aps' => $aps,
                    );

                    $payload = json_encode($payload);
                    @$apnsMessage = chr(0) . chr(0) . chr(32) . pack('H*', str_replace(' ', '', $val)) . chr(0) . chr(strlen($payload)) . $payload;
                    $result = fwrite($apns, $apnsMessage);
                    //  $response = $this->checkAppleErrorResponse($apns);
                }
            } catch (Exception $e) {
                continue;
            }
            fclose($apns);
        }
        return true;
    }

    function checkAppleErrorResponse($fp) {

        $apple_error_response = fread($fp, /* 38 */ 6); //byte1=always 8, byte2=StatusCode, bytes3,4,5,6=identifier(rowID). Should return nothing if OK.
        //NOTE: Make sure you set stream_set_blocking($fp, 0) or else fread will pause your script and wait forever when there is no response to be sent.

        if ($apple_error_response) {

            $error_response = unpack('Ccommand/Cstatus_code/Nidentifier', $apple_error_response); //unpack the error response (first byte 'command" should always be 8)

            if ($error_response['status_code'] == '0') {
//                $error_response['status_code'] = '0-No errors encountered';
            } else if ($error_response['status_code'] == '1') {
                $error_response['status_code'] = '1-Processing error';
            } else if ($error_response['status_code'] == '2') {
                $error_response['status_code'] = '2-Missing device token';
            } else if ($error_response['status_code'] == '3') {
                $error_response['status_code'] = '3-Missing topic';
            } else if ($error_response['status_code'] == '4') {
                $error_response['status_code'] = '4-Missing payload';
            } else if ($error_response['status_code'] == '5') {
                $error_response['status_code'] = '5-Invalid token size';
            } else if ($error_response['status_code'] == '6') {
                $error_response['status_code'] = '6-Invalid topic size';
            } else if ($error_response['status_code'] == '7') {
                $error_response['status_code'] = '7-Invalid payload size';
            } else if ($error_response['status_code'] == '8') {
                $error_response['status_code'] = '8-Invalid token';
            } else if ($error_response['status_code'] == '255') {
                $error_response['status_code'] = '255-None (unknown)';
            } else {
                $error_response['status_code'] = $error_response['status_code'] . '-Not listed';
            }

            return $error_response['status_code'];
        }
        return 'no response';
    }

}

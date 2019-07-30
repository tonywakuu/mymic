<?php

// API access key from Google API's Console
//define('ANDROID_API_ACCESS_KEY', 'AIzaSyAM-cXnfG5bZTWF4CI-w1VKs211negC_7A');
//IOS pass phrase
//define('IOS_PASS_PHRASE', '');
//Path to iOS certificate
//define('IOS_CERTIFICATE_PATH', realpath(__DIR__ . '/..') . '/config/CertificatesDeveloperRevel.pem');

/**
 * Class notification to send the push notifications to the registration ids
 */
class pushNotifications {

    /**
     * Function to send Push notifications to the android devices
     * @param type $registrationIds
     * @param type $msg
     * @return boolean
     */
    public function pushAndroidNotification($registrationIds, $message) {
//        $registrationIds = array('APA91bG0nOBP_IO_MNr3X-6z00XM27HIrARqndzvrIKPmrRyaDlX889TtuwvqGeZ_T82RAsHlwF5yEKhvsQXAM_9as_imp5JEifzICPX8nREWvo4XvtECIrk7XtrkFBl9N5YA8paS3t6');
        // prepare the bundle
        $msg = array
            (
            'message' => $message,
            'title' => 'test title',
            'subtitle' => 'test subtitle',
            'tickerText' => 'Ticker text',
            'vibrate' => 1,
            'sound' => 1,
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
            'Authorization: key=' . ANDROID_API_ACCESS_KEY,
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

            /* $response->StatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
              $response->ErrorCode = curl_errno($ch); // http://curl.haxx.se/libcurl/c/libcurl-errors.html
              $response->Error = curl_error($ch);
              $response->CurlHttpInfo = curl_getinfo($ch); */
            curl_close($ch);
            $getData = json_decode($result);
            if (isset($getData->success) && $getData->success == 1) {
                return json_decode($result);
            } else {
                return false;
            }
        } catch (Exception $e) {
            return FALSE;
        }
    }

    /**
     * Function to send Push notifications to the iOS devices
     * @param string $deviceToken
     * @param type $message
     * @param type $badge
     */
    public function pushIosNotification($deviceToken, $message, $badge = NULL) {
        // My device token here (without spaces):
//        $deviceToken = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
        // My private key's passphrase here:
        $passphrase = IOS_PASS_PHRASE;

        // My alert message here:
        //        $message = 'New Push Notification!';
        //badge
        $badge = isset($badge1) ? $badge : 1;

        $ctx = stream_context_create();
        stream_context_set_option($ctx, 'ssl', 'local_cert', IOS_CERTIFICATE_PATH);
        stream_context_set_option($ctx, 'ssl', 'passphrase', $passphrase);

        // Open a connection to the APNS server
        try {
            $fp = stream_socket_client(
                    'ssl://gateway.sandbox.push.apple.com:2195', $err, $errstr, 60, STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT, $ctx);

            if (!$fp)
                exit("Failed to connect: $err $errstr" . PHP_EOL);

            // Create the payload body
            $body['aps'] = array(
                'alert' => $message,
                'badge' => $badge,
                'sound' => ''
            );

            // Encode the payload as JSON
            $payload = json_encode($body);

            // Build the binary notification
            $msg = chr(0) . pack('n', 32) . pack('H*', str_replace(' ', '', sprintf('%u', CRC32($deviceToken[0])))) . pack('n', strlen($payload)) . $payload;

            // Send it to the server
            $result = fwrite($fp, $msg, strlen($msg));
            return $result;
        } catch (Exception $e) {
            return FALSE;
        }
        // Close the connection to the server
        fclose($fp);
    }

}

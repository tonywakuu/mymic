<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/**
 * Rest Client Class
 *
 * For full filling the REST requests
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Rest Client
 */
class CI_Rest_client {

    // --------------------------------------------------------------------

    function send($uri, $method, $args, $headers = array(), $body = null) {
        if (empty($uri)) {
            return false;
        }
        $response = new stdClass();

        if ($method == 'GET' || $method == 'DELETE') {
            $uri = $uri . ( strpos($uri, '?') ? '&' : '?' ) . http_build_query($args);
        }

        if (($method == 'POST' || $method == 'PUT') && !isset($headers['Content-type'])) {
            $headers['Content-type'] = 'Content-type: application/json';
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $curl_opts = array(
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => 0, // its your call now
            CURLOPT_USERAGENT => "",
        );

        if (isset($headers) && $headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($args));
        }

        if ($method == 'PUT') {
            curl_setopt($ch, CURLOPT_PUT, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($args));
        }

        if ($method == 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response->body = curl_exec($ch);
//         Then, after your curl_exec call:
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response->body, 0, $headerSize);
        $body = substr($response->body, $headerSize);
        $response->headers = $header;
        $response->body = $body;
        $response->statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $response->errorCode = curl_errno($ch); // http://curl.haxx.se/libcurl/c/libcurl-errors.html
        $response->errorMsg = curl_error($ch);
        $response->curlHttpInfo = curl_getinfo($ch);
        curl_close($ch);

        return $response;
    }

    /**
     * Function to authenticate Open fire user
     */
    function authenticateOpenFire() {
        $CI = & get_instance();
        $openFireUrl = $CI->config->config['openfire_url'];
        $openFireAdmin = $CI->config->config['uid'];
        $openFirePwd = $CI->config->config['pwd'];
        $customToken = '';
        $uri = $openFireUrl . 'login';
        $params['uid'] = $openFireAdmin;
        $params['pwd'] = $openFirePwd;
        $method = 'POST';
        $response = $this->send($uri, $method, $params);
        $data = json_decode($response->body);
        if (isset($data->status) && $data->status == 1) {
            preg_match_all('/^Set-Cookie:\s*([^\r\n]*)/mi', $response->headers, $ms);
            $cookies = array();
            foreach ($ms[1] as $m) {
                list($name, $value) = explode('=', $m, 2);
                $cookies[$name] = $value;
            }
            $customToken = (isset($cookies['X-CustomToken'])) ? $cookies['X-CustomToken'] : '';
        }
        return $customToken;
    }

}

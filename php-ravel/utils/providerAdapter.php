<?php

require_once realpath(__DIR__ . '/..') . '/config/providerConfig.php';
require_once realpath(__DIR__ . '/..') . '/utils/twitteroauth/autoload.php';

use Abraham\TwitterOAuth\TwitterOAuth;

/**
 * Provider Adapter class
 */
class providerAdapter {

    /**
     * Variable to hold the Request class object
     * @var type 
     */
    protected $request;

    /**
     * Variable to hold the configuration of Id provider's consumer key and secret
     * @var type 
     */
    protected $config;

    /**
     * Initiate Provider Adapter
     */
    function __construct() {
        $this->request = new \Hybridauth\Http\Request();
        $this->config = providerConfig::getConfig();
    }

    /**
     * Get User Profile from facebook using access token
     * @param type $token
     */
    public function FacebookProfile($token) {
        $uri = $this->config['FacebookUri'];
        $param['access_token'] = $token;
        $param['fields'] = 'id,name,email,first_name,gender,last_name,link,locale,timezone,birthday,picture.width(500).height(500)';
        $response = $this->request->send($uri, 'GET', $param);
        $result = json_decode($response->getBody(), TRUE);        
        $dataArray = array();
        if (!isset($result['error'])) {
            $data['username'] = $result['id'];
            $data['id'] = $result['id'];
            $data['name'] = $result['first_name'] . ' ' . $result['last_name'];
            $data['email'] = '';
            $data['type'] = 'fb';
            $data['pimg'] = (isset($result['picture']['data']['url'])) ? $result['picture']['data']['url'] : '';
            $dataArray[] = $data;
        }
        return $dataArray;
    }

    /**
     * Get User Profile from twitter using access token
     * @param type $token
     */
    public function TwitterProfile($token) {
        $tokenArray = explode('@', $token);
        $twitterConfig = $this->config['providers']['Twitter'];
        if ($twitterConfig['enabled']) {
            $connection = new TwitterOAuth($twitterConfig['keys']['key'], $twitterConfig['keys']['secret'], $tokenArray[0], $tokenArray[1]);
            $response = $connection->get("account/verify_credentials");
            
            $dataArray = array();
            if (!isset($response->errors)) {
                $data['username'] = $response->screen_name;
                $data['id'] = $response->id;
                $data['name'] = $response->name;
                $data['email'] = "";
                $data['type'] = 'tw';
                $data['pimg'] = (isset($response->profile_image_url)) ? str_replace('_normal', '', $response->profile_image_url) : '';
                $dataArray[] = $data;
            }            
            return $dataArray;
        } else {
            return FALSE;
        }
    }

    /**
     * Get User Profile from Instagram using access token
     * @param type $token
     */
    public function InstagramProfile($token) {
        $uri = $this->config['InstagramUri'] . '/self';
        $param['access_token'] = $token;
        $response = $this->request->send($uri, 'GET', $param);
        $result = json_decode($response->getBody(), TRUE);

        $dataArray = array();
        if ($result['meta']['code'] == '200') {
            $data['username'] = $result['data']['username'];
            $data['id'] = $result['data']['id'];
            $data['name'] = $result['data']['username'];
            $data['email'] = "";
            $data['type'] = 'in';
            $data['pimg'] = (isset($result['data']['profile_picture'])) ? $result['data']['profile_picture'] : '';
            $dataArray[] = $data;
        }
        return $dataArray;
    }

    /**
     * Get User Profile from Google using access token
     * @param type $token
     */
    public function GoogleProfile($token) {
        $uri = $this->config['GoogleUri'];
        $param['access_token'] = $token;
        $response = $this->request->send($uri, 'GET', $param);
        $result = json_decode($response->getBody(), TRUE);        
        $dataArray = array();
        if (!isset($result['error'])) {
            $data['username'] = $result['email'];
            $data['id'] = $result['id'];
            $data['name'] = $result['name'];
            $data['email'] = $result['email'];
            $data['type'] = 'go';
            $dataArray[] = $data;
        }
        return $dataArray;
    }

    /**
     * Function to fetch the contact of user from facebook
     * @param type $token
     * @param type $identifier - username
     */
    public function FacebookContacts($token, $identifier) {
        $uri = $this->config['FacebookUri'] . '/friends';
        $param['access_token'] = $token;
        $param['limit'] = 5000;
        $response = $this->request->send($uri, 'GET', $param);
        $result = json_decode($response->getBody());
        return $result->data;
    }

    /**
     * Function to fetch the contact of user from Twitter
     * @param type $token
     * @param type $identifier - username
     */
    public function TwitterContacts($token, $identifier) {
        $tokenArray = explode('@', $token);
        $twitterConfig = $this->config['providers']['Twitter'];
        $userList = array();
        if ($twitterConfig['enabled']) {
            $connection = new TwitterOAuth($twitterConfig['keys']['key'], $twitterConfig['keys']['secret'], $tokenArray[0], $tokenArray[1]);
            $response = $connection->get('friends/list');
            if (!isset($response->errors)) {
                foreach ($response->users as $result) {
                    $userList[]->name = $result->screen_name;
                }
            } else {
                return FALSE;
            }
            return $dataArray;
        } else {
            return FALSE;
        }
    }

    /**
     * Function to fetch the contact of user from Instagram
     * @param type $token
     * @param type $identifier - username
     */
    public function InstagramContacts($token, $identifier) {
        $uri = $this->config['InstagramUri'] . '/' . $identifier . '/follows';
        $param['access_token'] = $token;
        $response = $this->request->send($uri, 'GET', $param);
    }

    /**
     * Function to fetch the contact of user from Google
     * @param type $token
     * @param type $identifier
     */
    public function GoogleContacts($token, $identifier) {
        $response = new stdClass();
        $response->data = '';
        $uri = $this->config['GoogleContact'] . '/full?max-results=5000&access_token=' . $token;
        $xmlresponse = $this->curl_file_get_contents($uri);
        if ((strlen(stristr($xmlresponse, 'Authorization required')) > 0) && (strlen(stristr($xmlresponse, 'Error ')) > 0)) {
            $error[] = "<h2>OOPS !! Something went wrong. Please try reloading the page.</h2>";
            return $response->data;
        }
        $xml = new SimpleXMLElement($xmlresponse);
        $xml->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005/Atom');

        $result = $xml->xpath('//gd:email');

        foreach ($result as $title) {
            $arr[] = $title->attributes()->address;
        }
        $response_array = json_decode(json_encode($arr), true);
        foreach ($response_array as $value2) {
            $email_list[]->name = $value2[0];
        }
        return $email_list;
    }

    /**
     * Post status on facebook
     * @param type $token
     * @return boolean
     */
    public function FacebookPost($token) {
        $uri = $this->config['FacebookUri'] . '/feed';
        $param['access_token'] = $token;
        $param['message'] = 'Testing';
        $response = $this->request->send($uri, 'POST', $param);
        $result = json_decode($response->getBody());
        if (isset($result->id)) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * Post Tweet on Twitter
     * @param type $token
     * @return boolean
     */
    public function TwitterPost($token) {
        $tokenArray = explode('@', $token);
        $twitterConfig = $this->config['providers']['Twitter'];
        if ($twitterConfig['enabled']) {
            $connection = new TwitterOAuth($twitterConfig['keys']['key'], $twitterConfig['keys']['secret'], $tokenArray[0], $tokenArray[1]);
            $response = $connection->post('statuses/update', array('status' => 'testing'));
            if (!isset($response->errors)) {
                return TRUE;
            } else {
                return FALSE;
            }
        } else {
            return FALSE;
        }
    }

    function curl_file_get_contents($url) {
        $curl = curl_init();
        $userAgent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)';

        curl_setopt($curl, CURLOPT_URL, $url); //The URL to fetch. This can also be set when initializing a session with curl_init().
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE); //TRUE to return the transfer as a string of the return value of curl_exec() instead of outputting it out directly.
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5); //The number of seconds to wait while trying to connect.

        curl_setopt($curl, CURLOPT_USERAGENT, $userAgent); //The contents of the "User-Agent: " header to be used in a HTTP request.
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE); //To follow any "Location: " header that the server sends as part of the HTTP header.
        curl_setopt($curl, CURLOPT_AUTOREFERER, TRUE); //To automatically set the Referer: field in requests where it follows a Location: redirect.
        curl_setopt($curl, CURLOPT_TIMEOUT, 10); //The maximum number of seconds to allow cURL functions to execute.
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); //To stop cURL from verifying the peer's certificate.
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);

        $contents = curl_exec($curl);
        curl_close($curl);
        return $contents;
    }

}

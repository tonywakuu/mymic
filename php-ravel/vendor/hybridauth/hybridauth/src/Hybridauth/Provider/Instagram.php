<?php

/* !
 * This file is part of the HybridAuth PHP Library (hybridauth.sourceforge.net | github.com/hybridauth/hybridauth)
 *
 * This branch contains work in progress toward the next HybridAuth 3 release and may be unstable.
 */

namespace Hybridauth\Provider;

use Hybridauth\Exception;
use Hybridauth\Http\Request;
use Hybridauth\Adapter\Template\OAuth2\OAuth2Template;

class Instagram extends OAuth2Template {

    // default permissions   
    public $scope = "basic";

    /**
     * IDp wrappers initializer 
     */
    function initialize() {
        parent::initialize();
        $this->letApplicationId($this->getAdapterConfig('keys', 'id'));
        $this->letApplicationSecret($this->getAdapterConfig('keys', 'secret'));

        // @ todo create a way to track scope & request addtl scope as needed
        $scope = $this->getAdapterConfig('scope') ? $this->getAdapterConfig('scope') : 'basic likes comments';

        $this->letApplicationScope($scope);
        // Provider api end-points
        $this->api->api_base_url = "https://api.instagram.com/v1/";
        $this->api->authorize_url = "https://api.instagram.com/oauth/authorize/";
        $this->api->token_url = "https://api.instagram.com/oauth/access_token";

        $this->letEndpointRedirectUri($this->getHybridauthEndpointUri());
        $this->letEndpointBaseUri('https://api.instagram.com/v1/');
        $this->letEndpointAuthorizeUri('https://api.instagram.com/oauth/authorize/');
        $this->letEndpointRequestTokenUri('https://api.instagram.com/oauth/access_token');

        $this->letEndpointAuthorizeUriAdditionalParameters( array( 'display' => 'page' ));
    }

    /**
     * load the user profile from the IDp api client
     */
    function getUserProfile() {
//        $data = $this->api->api("users/self/");
        $response = $this->signedRequest( 'users/self' );
        $data = json_decode($response);
        if ($data->meta->code != 200) {
            throw new Exception("User profile request failed! Instagram returned an invalid response.", 6);
        }

        $this->user->profile->identifier = $data->data->id;
        $this->user->profile->displayName = $data->data->full_name ? $data->data->full_name : $data->data->username;
        $this->user->profile->description = $data->data->bio;
        $this->user->profile->photoURL = $data->data->profile_picture;

        $this->user->profile->webSiteURL = $data->data->website;

        $this->user->profile->username = $data->data->username;

        return $this->user->profile;
    }
    
    function signedRequest($uri, $method = Request::GET, $parameters = array()) {
        if (!isset($parameters['access_token']) && !empty($this->use_access_token)) {
            $parameters['access_token'] = $this->use_access_token;
        }
        $parameters['fields'] = 'id,name,email,first_name,gender,last_name,link,locale,timezone,birthday';
        return parent::signedRequest($uri, $method, $parameters);
    }
    
    /**
     * load the user profile from the IDp api client
     */
    function getUserProfileByToken($tokens) {
        $response = $this->signedRequest( 'users/self','GET',array('access_token'=>$tokens) );
        $data = json_decode($response);
        if ($data->meta->code != 200) {
            throw new Exception("User profile request failed! Instagram returned an invalid response.", 6);
        }

        $this->user->profile->identifier = $data->data->id;
        $this->user->profile->displayName = $data->data->full_name ? $data->data->full_name : $data->data->username;
        $this->user->profile->description = $data->data->bio;
        $this->user->profile->photoURL = $data->data->profile_picture;

        $this->user->profile->webSiteURL = $data->data->website;

        $this->user->profile->username = $data->data->username;

        return $this->user->profile;
    }

    /**
     *
     */
    function getUserContacts() {
        // refresh tokens if needed
        $this->refreshToken();

        //
        $response = array();
        $contacts = array();
        $profile = ( ( isset($this->user->profile->identifier) ) ? ( $this->user->profile ) : ( $this->getUserProfile() ) );
        try {
            $response = $this->api->api("users/{$this->user->profile->identifier}/follows");
        } catch (LinkedInException $e) {
            throw new Exception("User contacts request failed! {$this->providerId} returned an error: $e");
        }
        //

        if (isset($response) && $response->meta->code == 200) {
            foreach ($response->data as $contact) {
                try {
                    $contactInfo = $this->api->api("users/" . $contact->id);
                } catch (LinkedInException $e) {
                    throw new Exception("Contact info request failed for user {$contact->username}! {$this->providerId} returned an error: $e");
                }
                //
                $uc = new Hybrid_User_Contact();
                //
                $uc->identifier = $contact->id;
                $uc->profileURL = "https://instagram.com/{$contact->username}";
                $uc->webSiteURL = @$contactInfo->data->website;
                $uc->photoURL = @$contact->profile_picture;
                $uc->displayName = @$contact->full_name;
                $uc->description = @$contactInfo->data->bio;
                //$uc->email          = ;
                //
				$contacts[] = $uc;
            }
        }
        return $contacts;
    }

}

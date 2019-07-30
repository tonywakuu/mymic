<?php

require_once __DIR__ . '/controller/user.php';
require_once __DIR__ . '/controller/search.php';
require_once __DIR__ . '/model/pushNotifications.php';

$userObj = new user();
$searchObj = new search();
$pushObj = new pushNotifications();
//$token = 'ya29.WQKzhW4yLqi0cibD9nyGrVDZ_6iRDpeajfTkqAcMOoWemQI46Ghr46c2l4vawYobsl2b';
//$token = '498256852-U05axlgB2QcQns31avCUofEOMRgGYP9vFHrV7M5q@gC6dvyuM3gQZ09EeEOjnPMNycujaCSh54hlYGhjT7XNgW';
//$idetifier = '1165675750127415';
//$userProfile = $userObj->signUp('Twitter',$token);
//$userContacts = $userObj->providerContacts('Google', $token, $identifier);
//$searchData = array(
//    'entity' => 'user',
//    'searchMethod' => '',
//    'searchParameters' => array(array("location" => array("coordinate" => array(28.6298081, 77.4251121), 'radius' => 30)), array("username" => "tpro")),
//    'sortParameters' => array("username" => -1, "createdAt" => 1),
//    'filter' => array('name' => 1, 'email' => 1, '_id' => 1),
//    'page' => '1',
//    'docPerPage' => '',
//);
//$searchResult = $searchObj->search($searchData);
$pushResponse = $pushObj->pushAndroidNotification(array('fL7MIYLYQrw:APA91bHlfv3oI_cts-K3pFE-cxcFuPNheoCPskN_loKperZRIbhzTydJh7oAYZxVdD79qoohW7jqrcAID3cMdsgcAhNkFTyHZQQWSnPpA8GX3MmC7i-hCW5rgS1RWLwv3BcJ-CmruuP4'),'test');
echo "<pre />";
print_r($pushResponse);
die;

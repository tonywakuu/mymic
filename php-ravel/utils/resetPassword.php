<?php

require_once realpath(__DIR__ . '/..') . '/model/userModel.php';
require_once realpath(__DIR__ . '/..') . '/config/providerConfig.php';
$configObj = new providerConfig();
$config = $configObj->getConfig();
$userModel = new userModel();
$validHash = $userModel->searchHash($_POST['hash']);
if (!empty($validHash)) {
    $pass = hash("sha256", $_POST['password'] . 'ravel@');
    $result = $userModel->resetPassword($_POST['email'], $pass);
    $flag = 'changed';
} else {
    $flag = 'expired';
}
header("Location: ../success.phtml?success=$flag");

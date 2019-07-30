<?php
class Apputil
{
    /**
     * get current timestamp
     * @return int
     */
    function getCurrentTimeStamp() {
        return time();
    }
    /**
     * @param $id : mongoId
     * @return bool|MongoId
     */
    function validateMongoId($id) {
        try {
            $id = new MongoId($id);
            return $id;
        } catch(MongoException $exp) {
            $id = new MongoId();
            return false;
        }
    }
    function getSha256($password) {
        return hash("sha256", $password . 'ravel@');
    }
    function validateEmail($email) {
        if ((!filter_var(trim($email), FILTER_VALIDATE_EMAIL)) && empty($email)) {
            return false;
        }
        return true;
    }
}
?>
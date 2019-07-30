<?php

/**
 * Class searchConfig to provide search configuration details
 */
class searchConfig {

    /**
     * Variable to hold the value of search config object
     * @var type 
     */
    protected $searchConfig;

    public function __construct() {
        $this->searchConfig = $this->getSearchEntities();
        return $this->searchConfig;
    }

    /**
     * Function to obtain search entities object
     * @format : entityName[[data]=>['type'=>'attribute',....]]
     */
    public function getSearchEntities() {
        $entityArr = array(
            array(
                "available_entities" => array(
                    'user',
                    'channel',
                    'channelcontent',
                    'plan'
                ),
                "distance_config" => array(
                    "miles" => 3963.2,
                    "kilometer" => 6378.1,
                    "used_unit" => "kilometer"
                ),
            ),
            array(
                "entity" => "user",
                "data" => array(
                    array(
                        "type" => "number",
                        "value" => "id"
                    ),
                    array(
                        "type" => "string",
                        "value" => "un"
                    ),
                    array(
                        "type" => "string",
                        "value" => "name"
                    ),
                    array(
                        "type" => "string",
                        "value" => "email"
                    ),
                    array(
                        "type" => "string",
                        "value" => "firstname"
                    ),
                    array(
                        "type" => "string",
                        "value" => "subscription"
                    ),
                    array(
                        "type" => "string",
                        "value" => "lastname"
                    ),
                    array(
                        "type" => "number",
                        "value" => "phonenumber"
                    ),
                    array(
                        "type" => "array",
                        "value" => array('location' => array(
                                "coordinate" => array(
                                    'long',
                                    'lat',
                                ),
                            ),
                        ),
                    ),
                    array(
                        "type" => "string",
                        "value" => "type"
                    ),
                    array(
                        "type" => "datetime",
                        "value" => "created_date"
                    ),
                    array(
                        "type" => "datetime",
                        "value" => "modified_date"
                    ),
                )
            ),
            array(
                "entity" => "channel",
                "data" => array(
                    array(
                        "type" => "number",
                        "value" => "id"
                    ),
                    array(
                        "type" => "string",
                        "value" => "username"
                    ),
                    array(
                        "type" => "string",
                        "value" => "cn"
                    ),
                    array(
                        "type" => "string",
                        "value" => "des"
                    ),
                    array(
                        "type" => "number",
                        "value" => "isactive"
                    ),
                    array(
                        "type" => "datetime",
                        "value" => "created_date"
                    ),
                    array(
                        "type" => "datetime",
                        "value" => "modified_date"
                    ),
                )
            ),
            array(
                "entity" => "channelcontent",
                "data" => array(
                    array(
                        "type" => "number",
                        "value" => "id"
                    ),
                    array(
                        "type" => "string",
                        "value" => "des"
                    ),
                    array(
                        "type" => "string",
                        "value" => "name"
                    ),
                    array(
                        "type" => "datetime",
                        "value" => "created_date"
                    ),
                    array(
                        "type" => "datetime",
                        "value" => "modified_date"
                    ),
                )
            ),
            array(
                "entity" => "plan",
                "data" => array(
                    array(
                        "type" => "number",
                        "value" => "id"
                    ),
                    array(
                        "type" => "string",
                        "value" => "name"
                    ),
                    array(
                        "type" => "string",
                        "value" => "pdesc"
                    ),
                    array(
                        "type" => "datetime",
                        "value" => "created_date"
                    ),
                    array(
                        "type" => "datetime",
                        "value" => "modified_date"
                    ),
                )
            ),
        );
        return $entityArr;
    }

}

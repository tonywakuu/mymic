<?php

/**
 * Include Required utilities and libraries
 */
require_once realpath(__DIR__ . '/..') . '/config/searchConfig.php';
require_once realpath(__DIR__ . '/..') . '/config/dbConfig.php';

/**
 * Class searchModel to provide user enquiry connectivity with business logic
 */
class searchModel {

    /**
     * Variable to hold the object of search handler
     * @var type 
     */
    protected $searchConfig;

    /**
     * Variable to hold the DB configuration
     * @var type 
     */
    protected $dbConfig;

    /**
     * Variable is a handler to the connected Database
     * @var type 
     */
    protected $dbHandler;

    /**
     * Variable to hold the instance of Database collection for mongo DB
     * @var type 
     */
    protected $db;

    /**
     * Initiate searchModel
     */
    public function __construct() {
        $dbConfigObj = new dbConfig();
        $this->dbConfig = $dbConfigObj->configureParams();
        // Connect to the database with defined constants
        if ($this->dbConfig['type'] != 'mongodb') {
            $this->dbHandler = new dbConfig(PDO_DSN, PDO_USER, PDO_PASSWORD);
        } else {
            $this->dbHandler = new MongoClient('mongodb://localhost/php_auth');
            $dbName = $this->dbConfig['dbname'];
            $this->db = $this->dbHandler->$dbName;
        }
        $searchConfigObj = new searchConfig();
        $this->searchConfig = $searchConfigObj->getSearchEntities();
    }

    
    /**
     * Function to initialize the search module
     * @param type $searchObj
     * @return boolean
     */
    public function initializeSearch($searchObj, $map) {
        if (!empty($this->searchConfig)) {
            if (isset($searchObj['entity']) && trim($searchObj['entity']) != '') {
                if (in_array($searchObj['entity'], $this->searchConfig[0]['available_entities'])) {
                    $entityIndex = array_search($searchObj['entity'], $this->searchConfig[0]['available_entities']) + 1;
                    if (isset($this->searchConfig[$entityIndex])) {
                        $searchList = $this->searchEntity($searchObj, $this->searchConfig[$entityIndex], $map);
                        if ($searchList) {
                            return $searchList;
                        } else {
                            return FALSE;
                        }
                    } else {
                        return FALSE;
                    }
                } else {
                    return FALSE;
                }
            } else {
                return FALSE;
            }
        } else {
            return FALSE;
        }
    }

    /**
     * method to get search results based on the search criteria
     * @param type $searchObj
     * @param type $entityConfig
     */
    public function searchEntity($searchObj, $entityConfig, $map) {
        $distanceUnit = $this->searchConfig[0]['distance_config'][$this->searchConfig[0]['distance_config']['used_unit']];
        if (isset($entityConfig['entity']) && $entityConfig['entity'] != '') {
            $collection = $this->db->$entityConfig['entity'];
            $attribute = array();
            $queryString = '';
            $sortArray = array();
            $filterFields = array();
            $loc = 0;
            //Operator incorporation
            if (isset($searchObj['searchMethod'])) {
                $operator = strtolower($searchObj['searchMethod']) == 'and' ? '$and' : '$or';
            }
            //TODO : Create Indexes for all searchable fields
            if (isset($searchObj['searchParameters']) && !empty($searchObj['searchParameters']) && is_array($searchObj['searchParameters'])) {
                foreach ($searchObj['searchParameters'] as $attr) {
                    foreach ($attr as $key => $attrValue) {
                        if (trim($key) == 'loc') {
                            $loc = 1;
                            //Create 2d index for co-ordinate fields
                            $collection->ensureIndex(array('loc.coordinate' => '2d'));
                            //Location Based Search
                            $lat = floatval($attrValue['coordinate'][1]);
                            $long = floatval($attrValue['coordinate'][0]);
                            $radius = floatval($attrValue['radius']);
                            /* if ($radius >= 1) {
                              $attribute[]['loc.coordinate'] = array(
                              '$within' => array(
                              '$centerSphere' => array(
                              array(floatval($long), floatval($lat)), $radius / floatval($distanceUnit)//avg radius of earth in km/miles
                              )
                              )
                              );
                              } else { */
                            $radius = 1000;
                            $attribute[]['loc.coordinate'] = array(
                                '$within' => array(
                                    '$centerSphere' => array(
                                        array(floatval($long), floatval($lat)), $radius / floatval($distanceUnit)//avg radius of earth in km/miles
                                    )
                                )
                            );
                            // }
                        } else if (trim($key) == 'cname') {
                            //Create 2d index for co-ordinate fields
                            $collection->ensureIndex(array('category.cname' => 1));
                            $attribute[]['category.cname'] = array('$regex' => new MongoRegex(trim("/" . $attrValue . "/i")));
                        } else if (trim($attrValue) != '') {
                            //Create index for searchable fields fields
                            $collection->ensureIndex(array($key => 1));
                            $attribute[][trim($key)] = array('$regex' => new MongoRegex(trim("/" . $attrValue . "/i")));
                        }
                    }
                }
            }
            if($map == "admin"){
                if (!empty($attribute) && $loc == 0) {
                    $queryString = array($operator => $attribute);
                }
            }else {
                if (!empty($attribute) && $loc == 0) {
                    $queryString = array($operator => $attribute, "isactive" => 1);
                } elseif (!empty($attribute) && $map) {
                    $attribute[]['isactive'] = 1;
                    $queryString = array('$and' => $attribute);
                } else {
                    $queryString = array("isactive" => 1);
                }
            }
            if (!empty($queryString) && count($queryString) >= 1) {
                $cursor = $collection->find($queryString);
            } else {
                $cursor = $collection->find();
            }

            //Sorting of data based on provided criteria
            if (isset($searchObj['sortParameters']) && !empty($searchObj['sortParameters'])) {
                $i = 0;
                foreach ($searchObj['sortParameters'] as $sortField => $order) {
                    $sortArray[$sortField] = $order == 1 ? 1 : -1;
                    $i++;
                }
                $cursor->sort($sortArray);
            }

            //Pagination of data fetched from DB
            $page = isset($searchObj['page']) && $searchObj['page'] != '' ? (int) $searchObj['page'] : 1;
            $limit = isset($searchObj['docPerPage']) && $searchObj['docPerPage'] != '' ? (int) $searchObj['docPerPage'] : 0;

            if ($limit != 0) {
                $skip = ($page - 1) * $limit;
            } else {
                $skip = 0;
            }
            $cursor->skip($skip)->limit($limit);

            //Filter mongo collection based on filter input
            if (isset($searchObj['filter']) && !empty($searchObj['filter'])) {
                foreach ($searchObj['filter'] as $filter => $status) {
                    $filterFields[$filter] = $status;
                }
                if (!empty($filterFields)) {
                    $cursor->fields($filterFields);
                }
            }

            //preparation of final data object
//            if($entityConfig['entity'] == "channel") {
//                $finalList['data'] = array();
//                if (isset($cursor)) {
//                    foreach ($cursor as $document) {
//                        $finalList['data'][] = $document;
//                    }
//                }
//            } else {
//                $finalList = $cursor;
//            }
            $finalList = array('total' => $cursor->count());
            $finalList = $cursor;
            return $finalList;
        } else {
            return FALSE;
        }
    }

}

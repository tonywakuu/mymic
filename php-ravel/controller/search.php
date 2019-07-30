<?php

/**
 * Include Required utilities and libraries
 */
require_once realpath(__DIR__ . '/..') . '/model/searchModel.php';

/**
 * SEARCH Controller class to handle all the activities related to search
 */
class search {

    /**
     * Variable to hold the instance of search model
     * @var type 
     */
    protected $searchModel;

    /**
     * Construct to initiate the search class
     */
    public function __construct() {
        $this->searchModel = new searchModel();
        //TODO: Create index for searchable fields in DB
    }

    /**
     * Function to initiate search
     * @param type $searchObj
     */
    public function search($searchObj,$map) {
        $searchResult = $this->searchModel->initializeSearch($searchObj,$map);
        return $searchResult;
    }

}

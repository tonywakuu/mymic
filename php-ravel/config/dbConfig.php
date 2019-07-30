<?php

/**
 * dbConfig Class holds the information about DB type and configuration params
 */
class dbConfig extends PDO {

    public function __construct($dsn = null, $username = null, $password = null, array $options = null) {
        return;
    }

    /**
     * Function to establish a connection with database
     * @param type $dsn
     * @param type $username
     * @param type $password
     * @param type $driver_options
     */
    public function connect($dsn, $username = '', $password = '', $driver_options = array()) {

        // Temporarily change the PHP exception handler while we . . .
        set_exception_handler(array(__CLASS__, 'exception_handler'));

        // . . . create a PDO object
        parent::__construct($dsn, $username, $password, $driver_options);

        // Change the exception handler back to whatever it was before
        restore_exception_handler();
    }

    /**
     * Function to get the DB configuration parameters
     * @return Array
     */
    public function configureParams() {
        $config = array(
            "type" => "mongodb",
            // "host" => "mongodb://172.30.3.181",
            "host" => "mongodb://localhost:27017",
            "dbname" => "db_ravel",
            "username" => "ravelprod",
            "password" => "ravelprod",
        );

        return $config;
    }

    /**
     * Throw new exception
     * @param type $exception
     */
    public static function exception_handler($exception) {
        // Output the exception details
        die('Uncaught exception: ' . $exception->getMessage());
    }

}

<?php
function getDBConnection(){
    $config = parse_ini_file('/var/www/private/db-config.ini');
    if (!$config) {
        return null; // Return null if config file fails
    }

    $conn = new mysqli($config['servername'], $config['username'], $config['password'], $config['dbname']);

    return ($conn->connect_error) ? null : $conn; // Return null if connection fails
}

?>

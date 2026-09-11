<?php
// Copy to config.php on the server. Do not commit real credentials.
$db_hostname = 'localhost';
$db_username = 'root';
$db_password = '';
$db_name = 'sik';

function db_connect() {
    global $db_hostname, $db_username, $db_password, $db_name;
    $db = mysqli_connect($db_hostname, $db_username, $db_password, $db_name);
    if (!$db) {
        http_response_code(500);
        exit('Database unavailable');
    }
    mysqli_set_charset($db, 'utf8mb4');
    return $db;
}

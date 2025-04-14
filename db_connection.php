<?php
class class_db
{
    public $connection_database;

    public function __construct()
    {

        $site_on_local = "localhost";
        $site_on_domain = "";

        $url_domain = "$_SERVER[HTTP_HOST]";

        if (empty(defined('SITE'))) {
            define("SITE", "$_SERVER[HTTP_HOST]");
        }

        if (SITE == $site_on_local) {
            $servername = "localhost";
            $username_db = "root";
            $password_db = "";
            $dbname = "davani_db";
        } elseif (SITE == $site_on_domain) {
            $username_db = "";
            $password_db = "";
            $dbname = "";
        }

// Create connection
        $this->connection_database = new mysqli($servername, $username_db, $password_db, $dbname);
        // تنظیم مجموعه کاراکترها به utf8mb4
        if (!$this->connection_database ->set_charset("utf8mb4")) {
            die("Error loading character set utf8mb4: " . $this->connection_database ->error);
        }
// Check connection
        if ($this->connection_database->connect_error) {
            die("Connection failed: " . $this->connection_database->connect_error);
        }
    }
}
?>
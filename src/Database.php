<?php

require_once __DIR__ . "/config.php";
class Database{

    private static ?self $instance = null;
    private PDO $conn;

    private function __construct()
    {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            ];

            try{

                $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
                
                } catch(PDOException $e){
                    throw new RuntimeException('Errore di connessione al database');
                    }
                }
                public static function getInstance() : self
                {
                    if(self::$instance ===null) {
                        self::$instance = new self();
                    }

                    return self::$instance;
                }
                public function getConnection(){
                    return $this->conn;
                }

                private function __clone()
                {
                    throw new RuntimeException('Non implementato');
                }

                public function __wakeup()
                {
                    throw new RuntimeException('Not implemented');
                }
}
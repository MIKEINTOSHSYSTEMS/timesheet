<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'merq_timesheet';
    private $username = 'merq_timesheet';
    private $password = 'merq_timesheet';
    private $port = '3307'; // Default MySQL port is 3306, change if necessary
    private $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name, 
                $this->username, 
                $this->password


            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8mb4");
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            throw new Exception("Database connection failed. Please try again later.");
        }

        return $this->conn;
    }
    
    public function closeConnection() {
        $this->conn = null;
    }
}


//$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/*
<td><?= e($u['first_name'] . ' ' . e($u['last_name']) ?></td>
class Database
{
    private $host;
    private $user;
    private $pass;
    private $name;
    private $charset;
    private $pdo;

    public function __construct()
    {
        $this->host = DB_HOST;
        $this->user = DB_USER;
        $this->pass = DB_PASS;
        $this->name = DB_NAME;
        $this->charset = DB_CHARSET;

        $this->connect();
    }

    private function connect()
    {
        $dsn = "mysql:host={$this->host};dbname={$this->name};charset={$this->charset}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $this->user, $this->pass, $options);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public function getConnection()
    {
        return $this->pdo;
    }

    // Other database helper methods...
}
*/
<?php
class Database {
    private $host;
    private $name;
    private $user;
    private $pass;
    private $connection;

    public function __construct() {
        $config = require __DIR__ . '/../config/config.php';
        $this->host = $config['db_host'];
        $this->name = $config['db_name'];
        $this->user = $config['db_user'];
        $this->pass = $config['db_pass'];
    }

    public function connect() {
        try {
            $this->connection = new PDO("mysql:host={$this->host};dbname={$this->name}", $this->user, $this->pass);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $this->connection;
        } catch (PDOException $e) {
            throw new Exception("Erreur de connexion : " . $e->getMessage());
        }
    }
}
?>


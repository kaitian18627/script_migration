<?php
class DatabaseSetup {
    private $connection;
    private $oldDBs;
    private $newDBs;
    private $config;

    public function __construct() {
        // Charger la configuration depuis le fichier config
        $this->config = require __DIR__ . '/../config/config.php';

        $this->newDBs = ['new_analysis'];

        try {
            // Initialiser la connexion à MySQL sans spécifier la base de données
            $this->connection = new PDO(
                "mysql:host={$this->config['db_host']}",
                $this->config['db_user'],
                $this->config['db_pass']
            );
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Vérifier si la base de données existe, sinon la créer
            foreach ($this->newDBs as $newDBName) {
                $this->connection->exec("CREATE DATABASE IF NOT EXISTS `$newDBName`");
            }

            // Se connecter à la base de données
            // $this->connection->exec("USE `$dbName`");
            echo "Connexion réussie à la base de données.";
        } catch (PDOException $e) {
            die("Erreur de connexion : " . $e->getMessage());
        }
    }

    public function getOldDBNames () {
        $this->oldDBs = $this->connection->query("SHOW DATABASES LIKE 'analysis_%'")->fetchAll(PDO::FETCH_COLUMN);
    }

    // Méthode de base pour exécuter des requêtes SQL
    public function executeQuery($sql) {
        try {
            $this->connection->exec($sql);
            echo "Requête exécutée avec succès.";
        } catch (PDOException $e) {
            echo "Erreur lors de l'exécution de la requête : " . $e->getMessage();
        }
    }

    // Méthode pour la création de la base et des tables initiales
    public function createDatabaseNewSystem() {
        try {
            $this->getOldDBNames();
    
            foreach ($this->oldDBs as $oldDBName) {
                $bddKey = $this->extractBddKey($oldDBName);
                $oldDB = $this->getDatabaseConnection($oldDBName);
    
                $oldTables = $this->getTables($oldDB);
    
                foreach ($this->newDBs as $newDBName) {
                    if ($this->isCompatibleDatabase($newDBName, $oldDBName)) {
                        $newDB = $this->getDatabaseConnection($newDBName);
                        
                        foreach ($oldTables as $oldTable) {
                            $tableStructure = $this->getModifiedTableStructure($oldDB, $oldTable);
                            $this->createNewTableIfNotExists($newDB, $tableStructure, $oldTable);
                            $this->migrateTableData($newDB, $newDBName, $oldDBName, $oldTable, $bddKey);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error in database migration: " . $e->getMessage());
        }
    }
    
    private function extractBddKey($dbName) {
        return str_replace('analysis_', '', $dbName);
    }
    
    private function getDatabaseConnection($dbName) {
        $dsn = "mysql:host={$this->config['db_host']};dbname={$dbName}";
        $db = new PDO($dsn, $this->config['db_user'], $this->config['db_pass']);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    }
    
    private function getTables($db) {
        return $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    }
    
    private function getModifiedTableStructure($db, $table) {
        $tableStructure = $db->query("SHOW CREATE TABLE $table")->fetchColumn(1);
        return str_replace(") ENGINE=", ", `bdd_key` VARCHAR(255)) ENGINE=", $tableStructure);
    }
    
    private function isCompatibleDatabase($newDBName, $oldDBName) {
        return (strpos($newDBName, 'user') !== false && strpos($oldDBName, 'user') !== false) ||
               (strpos($newDBName, 'analysis') !== false && strpos($oldDBName, 'analysis') !== false);
    }
    
    private function createNewTableIfNotExists($db, $tableStructure, $tableName) {
        // Check if table already exists
        $tableExists = $db->query("SHOW TABLES LIKE '$tableName'")->fetchColumn();
        
        if (!$tableExists) {
            $db->exec("SET foreign_key_checks = 0");
            
            $tableStructure = str_replace("CREATE TABLE `$tableName`", "CREATE TABLE IF NOT EXISTS `$tableName`", $tableStructure);
            $db->exec($tableStructure);
    
            $db->exec("SET foreign_key_checks = 1");
        }
    }
    
    private function migrateTableData($db, $newDBName, $oldDBName, $table, $bddKey) {
        $insertQuery = "INSERT IGNORE INTO $newDBName.$table SELECT *, '$bddKey' AS bdd_key FROM $oldDBName.$table";
        $db->exec($insertQuery);
    }    

    // Méthode de déconnexion
    public function disconnect() {
        $this->connection = null;
        echo "Déconnexion réussie.";
    }
}


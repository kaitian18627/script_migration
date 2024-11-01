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
    
            $offset = 0;

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
                            $this->migrateTableData($newDB, $newDBName, $oldDBName, $oldTable, $bddKey, $offset);
                        }
                    }
                }
            }

            $offset += 1000;
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
        // Query to select only the tables from the current database
        $query = "SELECT TABLE_NAME 
                  FROM information_schema.TABLES 
                  WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_TYPE = 'BASE TABLE'";
        
        return $db->query($query)->fetchAll(PDO::FETCH_COLUMN);
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
    
    // private function migrateTableData($db, $newDBName, $oldDBName, $table, $bddKey) {
    //     // Get columns for the new table
    //     $newColumns = $this->getTableColumns($db, $newDBName, $table);
        
    //     // Fetch data from the old table
    //     $oldData = $db->query("SELECT * FROM $oldDBName.$table")->fetchAll(PDO::FETCH_ASSOC);
        
    //     // Prepare the new data with matched columns
    //     $insertData = [];
        
    //     foreach ($oldData as $row) {
    //         // Create an array to hold the new row data
    //         $newRow = [];
            
    //         foreach ($newColumns as $column) {
    //             // Check if the old row has this column
    //             if (array_key_exists($column, $row)) {
    //                 $newRow[$column] = $row[$column];
    //             }
    //         }
            
    //         // Add the bdd_key to the new row
    //         $newRow['bdd_key'] = $bddKey;
    //         $insertData[] = $newRow;
    //     }
        
    //     // Prepare insert statement
    //     if (!empty($insertData)) {
    //         // Getting the column list for the insert statement
    //         $columnList = implode(", ", array_keys($insertData[0]));
            
    //         // Create a values placeholder string
    //         $placeholders = rtrim(str_repeat('(?' . str_repeat(', ?', count($insertData[0]) - 1) . '), ', count($insertData)), ', ');
    
    //         $insertQuery = "INSERT IGNORE INTO $newDBName.$table ($columnList) VALUES $placeholders";
            
    //         // Prepare the statement
    //         $stmt = $db->prepare($insertQuery);
            
    //         // Bind values
    //         $flatValues = [];
    //         foreach ($insertData as $row) {
    //             $flatValues = array_merge($flatValues, array_values($row));
    //         }
            
    //         // Execute the statement with the values
    //         $stmt->execute($flatValues);
    //     }
    // }

    private function migrateTableData($db, $newDBName, $oldDBName, $table, $bddKey, $offset) {
        // Get columns for the new table
        $newColumns = $this->getTableColumns($db, $newDBName, $table);
    
        // Fetch data from the old table
        $oldData = $db->query("SELECT * FROM $oldDBName.$table")->fetchAll(PDO::FETCH_ASSOC);
    
        // Prepare the new data with matched columns and offset ids
        $insertData = [];
    
        foreach ($oldData as $row) {
            $newRow = [];
    
            foreach ($newColumns as $column) {
                if (array_key_exists($column, $row)) {
                    // Apply offset to 'id' and any foreign key fields
                    if ($column == 'id' || $this->isForeignKeyColumn($column, $table)) {
                        $newRow[$column] = $row[$column] + $offset;
                    } else {
                        $newRow[$column] = $row[$column];
                    }
                }
            }
    
            // Add the bdd_key to the new row
            $newRow['bdd_key'] = $bddKey;
            $insertData[] = $newRow;
        }
    
        // Prepare insert statement
        if (!empty($insertData)) {
            $columnList = implode(", ", array_keys($insertData[0]));
            $placeholders = rtrim(str_repeat('(?' . str_repeat(', ?', count($insertData[0]) - 1) . '), ', count($insertData)), ', ');
    
            $insertQuery = "INSERT IGNORE INTO $newDBName.$table ($columnList) VALUES $placeholders";
    
            $stmt = $db->prepare($insertQuery);
    
            $flatValues = [];
            foreach ($insertData as $row) {
                $flatValues = array_merge($flatValues, array_values($row));
            }
    
            $stmt->execute($flatValues);
        }
    }
    
    private function isForeignKeyColumn($columnName, $tableName) {
        // Define a method to check if the column is a foreign key reference
        // This could be achieved by checking the schema or known foreign key naming patterns
    
        // Example naming pattern for foreign keys
        return strpos($columnName, '_id') !== false;
    }
    
    private function getTableColumns($db, $dbName, $tableName) {
        // Query to get the column names from the specified table
        $query = "SELECT COLUMN_NAME 
                  FROM information_schema.COLUMNS 
                  WHERE TABLE_SCHEMA = '$dbName' 
                  AND TABLE_NAME = '$tableName'";
        
        return $db->query($query)->fetchAll(PDO::FETCH_COLUMN);
    }

    // Méthode de déconnexion
    public function disconnect() {
        $this->connection = null;
        echo "Déconnexion réussie.";
    }
}


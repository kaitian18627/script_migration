<?php

class DatabaseTest {
    private $connection;
    private $newDBName = 'new_analysis';
    private $config;

    public function __construct() {
        // Charger la configuration depuis le fichier de configuration
        $this->config = require __DIR__ . '/../config/config.php';

        try {
            // Connexion sans spécifier la base de données pour accéder à toutes les bases
            $this->connection = new PDO(
                "mysql:host={$this->config['db_host']}",
                $this->config['db_user'],
                $this->config['db_pass']
            );
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            

            echo "Connexion réussie à la base de données.\n";
        } catch (PDOException $e) {
            die("Erreur de connexion : " . $e->getMessage());
        }
    }

    // Obtenir la liste des tables d'une base de données donnée, sans les vues
    private function getTablesInDatabase($dbName) {
        $query = "SELECT TABLE_NAME 
                  FROM information_schema.TABLES 
                  WHERE TABLE_SCHEMA = :dbName 
                  AND TABLE_TYPE = 'BASE TABLE'";
        $stmt = $this->connection->prepare($query);
        $stmt->execute(['dbName' => $dbName]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Vérifier que toutes les tables des anciennes bases de données existent dans la nouvelle base
    public function checkTablesFromOldDBsInNewDB() {
        $oldDBNames = $this->getOldDatabaseNames();
        $missingTables = [];

        foreach ($oldDBNames as $oldDBName) {
            $oldTables = $this->getTablesInDatabase($oldDBName);
            $newTables = $this->getTablesInDatabase($this->newDBName);

            // Vérifier si chaque table de l'ancienne base de données existe dans la nouvelle
            foreach ($oldTables as $table) {
                if (!in_array($table, $newTables)) {
                    $missingTables[] = "Table `$table` de la base `$oldDBName` est manquante dans `$this->newDBName`";
                }
            }
        }

        if (empty($missingTables)) {
            echo "Toutes les tables des anciennes bases de données sont présentes dans `$this->newDBName`.\n";
        } else {
            echo "Tables manquantes dans `$this->newDBName` :\n" . implode("\n", $missingTables) . "\n";
        }
    }

    // Obtenir la liste des bases de données à partir du préfixe 'analysis_'
    private function getOldDatabaseNames() {
        $query = "SHOW DATABASES LIKE 'analysis_%'";
        return $this->connection->query($query)->fetchAll(PDO::FETCH_COLUMN);
    }

    // Vérifier que toutes les clés étrangères dans la nouvelle base de données sont valides
    public function checkForeignKeys() {
        $tables = $this->getTablesInDatabase($this->newDBName);
        $errors = [];

        foreach ($tables as $table) {
            $foreignKeys = $this->getForeignKeys($table);
            
            foreach ($foreignKeys as $fk) {
                // Vérifier si la clé étrangère est correcte
                if (!$this->foreignKeyIsValid($fk)) {
                    $errors[] = "Erreur de clé étrangère dans la table $table pour la contrainte {$fk['CONSTRAINT_NAME']}";
                }
            }
        }

        if (empty($errors)) {
            echo "Toutes les clés étrangères sont correctement définies dans `$this->newDBName`.\n";
        } else {
            echo "Des erreurs de clés étrangères ont été trouvées :\n" . implode("\n", $errors) . "\n";
        }
    }

    // Obtenir les informations sur les clés étrangères pour une table
    private function getForeignKeys($table) {
        $query = "SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
                  FROM information_schema.KEY_COLUMN_USAGE 
                  WHERE TABLE_SCHEMA = :dbName AND TABLE_NAME = :table AND REFERENCED_TABLE_NAME IS NOT NULL";
        
        $stmt = $this->connection->prepare($query);
        $stmt->execute(['dbName' => $this->newDBName, 'table' => $table]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Vérifier si la clé étrangère pointe vers une table et une colonne existantes
    private function foreignKeyIsValid($foreignKey) {
        try {
            $refTable = $foreignKey['REFERENCED_TABLE_NAME'];
            $refColumn = $foreignKey['REFERENCED_COLUMN_NAME'];

            $this->connection->exec("USE {$this->newDBName}");

            // Vérifier si la table référencée existe
            $tableExists = $this->connection->query("SHOW TABLES LIKE '$refTable'")->fetchAll(PDO::FETCH_COLUMN);
            if (!$tableExists) {
                return false;
            }

            // Vérifier si la colonne référencée existe dans la table
            $columnExists = $this->connection->query("SHOW COLUMNS FROM `$refTable` LIKE '$refColumn'")->fetchAll(PDO::FETCH_COLUMN);
            return $columnExists !== false;
        } catch (PDOException $e) {
            // Handle the exception gracefully
            echo "Error occurred: " . $e->getMessage();
        }
    }

    // Méthode pour fermer la connexion à la base de données
    public function disconnect() {
        $this->connection = null;
        echo "Déconnexion réussie.\n";
    }
}
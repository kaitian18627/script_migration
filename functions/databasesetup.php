<?php
class DatabaseSetup {
    private $connection;
    private $oldDBs;
    private $newDBs;
    private $config;

    public function __construct() {
        // Charger la configuration depuis le fichier de configuration
        $this->config = require __DIR__ . '/../config/config.php';

        $this->newDBs = ['new_analysis'];

        try {
            // Initialiser la connexion MySQL sans spécifier la base de données
            $this->connection = new PDO(
                "mysql:host={$this->config['db_host']}",
                $this->config['db_user'],
                $this->config['db_pass']
            );
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Vérifier si la base de données existe ; sinon, la créer
            foreach ($this->newDBs as $newDBName) {
                $this->connection->exec("CREATE DATABASE IF NOT EXISTS `$newDBName`");
            }

            echo "Connexion réussie à la base de données.\n";
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
            echo "Requête exécutée avec succès.\n";
        } catch (PDOException $e) {
            echo "Erreur lors de l'exécution de la requête : " . $e->getMessage(\n);
        }
    }

    public function deleteRowFromSteppersOfUsers() {
        // Créer une nouvelle connexion PDO
        try {
            // Créer une nouvelle connexion PDO
            $db = $this->getDatabaseConnection('users');
        
            // Requête SQL pour supprimer les lignes où la colonne méthode est COMPLIANCE
            $sql = "DELETE FROM steppers WHERE method = 'COMPLIANCE'";
        
            // Exécuter la requête
            $stmt = $db->prepare($sql);
            $stmt->execute();
        } catch (PDOException $e) {
            echo "Erreur : " . $e->getMessage(\n);
        } finally {
            echo "Lignes supprimées de la table steppers de la base de données utilisateurs\n";
            echo "Veuillez tester et supprimer les anciennes bases de données\n";
        }
        
        // Fermer la connexion
        $db = null;
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
                $offset += 10000;
            }
        } catch (Exception $e) {
            error_log("Erreur dans la migration de la base de données : " . $e->getMessage());
        } finally {
            echo "Système de nouvelle base de données créé\n";
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
        // Requête pour sélectionner uniquement les tables de la base de données actuelle
        $query = "SELECT TABLE_NAME 
                  FROM information_schema.TABLES 
                  WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_TYPE = 'BASE TABLE'";
        
        return $db->query($query)->fetchAll(PDO::FETCH_COLUMN);
    }
    
    
    private function getModifiedTableStructure($db, $table) {
        // Récupérer la structure de la table en utilisant SHOW CREATE TABLE
        $tableStructure = $db->query("SHOW CREATE TABLE $table")->fetchColumn(1);
        
        // Utiliser une expression régulière pour remplacer SMALLINT par INT, en excluant 'id', les clés étrangères finissant par '_id' et 'root'
        $modifiedStructure = preg_replace_callback(
            '/`(\w+)`\s+smallint\(\d+\)/',
            function ($matches) {
                // Vérifier si le nom de la colonne est 'ref', 'id', une clé étrangère ou 'root'
                $columnName = $matches[1];
                if ($columnName === 'ref' || $columnName === 'id' || $this->isForeignKeyColumn($columnName) || $columnName === 'root') {
                    return preg_replace('/smallint\(\d+\)/', 'int(11)', $matches[0]); // Remplacer SMALLINT par INT
                }
                return $matches[0]; // Laisser la définition inchangée si ce n'est pas une colonne concernée
            },
            $tableStructure
        );
    
        // Ajouter la modification pour la colonne `bdd_key`
        $modifiedStructure = str_replace(") ENGINE=", ", `bdd_key` VARCHAR(255)) ENGINE=", $modifiedStructure);
    
        return $modifiedStructure;
    }
    
    
    private function isCompatibleDatabase($newDBName, $oldDBName) {
        return (strpos($newDBName, 'user') !== false && strpos($oldDBName, 'user') !== false) ||
               (strpos($newDBName, 'analysis') !== false && strpos($oldDBName, 'analysis') !== false);
    }
    
    private function createNewTableIfNotExists($db, $tableStructure, $tableName) {
        try {
            $db->exec("SET foreign_key_checks = 0"); // Désactiver les vérifications de clé étrangère
            
            // Vérifier si la table existe
            $tableExists = $db->query("SHOW TABLES LIKE '$tableName'")->fetchColumn();
            
            if (!$tableExists) {
                // Modifier la structure de la table pour ajouter "IF NOT EXISTS"
                $tableStructure = str_replace("CREATE TABLE `$tableName`", "CREATE TABLE IF NOT EXISTS `$tableName`", $tableStructure);
                $db->exec($tableStructure);
            }
        } catch (PDOException $e) {
            echo "Erreur lors de la création de la table : " . $e->getMessage();
        } finally {
            $db->exec("SET foreign_key_checks = 1"); // Réactiver les vérifications de clé étrangère
        }
    }

    private function migrateTableData($db, $newDBName, $oldDBName, $table, $bddKey, $offset) {
        try {
            $db->exec("SET foreign_key_checks = 0"); // Désactiver temporairement les vérifications de clé étrangère
    
            // Obtenir les colonnes pour la nouvelle table
            $newColumns = $this->getTableColumns($db, $newDBName, $table);
            
            // Récupérer les données de l'ancienne table
            $oldData = $db->query("SELECT * FROM $oldDBName.$table")->fetchAll(PDO::FETCH_ASSOC);
            
            // Préparer les données avec le mappage des colonnes et l'ajustement de l'offset des ID
            $insertData = [];
            
            foreach ($oldData as $row) {
                $newRow = [];
                foreach ($newColumns as $column) {
                    if (array_key_exists($column, $row)) {
                        // Appliquer un offset aux champs 'id' et clés étrangères
                        if ($column === 'id' || $this->isForeignKeyColumn($column) || $column === 'root') {
                            $newRow[$column] = ($row[$column] !== null && is_int($row[$column])) ? $row[$column] + $offset : $row[$column];
                        } else {
                            $newRow[$column] = $row[$column];
                        }
                    }
                }
                // Ajouter la clé bdd_key à chaque ligne
                $newRow['bdd_key'] = $bddKey;
                $insertData[] = $newRow;
            }
            
            // Construire et exécuter la requête d'insertion
            if (!empty($insertData)) {
                $columnList = implode(", ", array_keys($insertData[0]));
                $placeholders = rtrim(str_repeat('(' . str_repeat('?, ', count($insertData[0]) - 1) . '?), ', count($insertData)), ', ');
                
                $insertQuery = "INSERT IGNORE INTO $newDBName.$table ($columnList) VALUES $placeholders";
                $stmt = $db->prepare($insertQuery);
                
                // Aplatir le tableau de données pour les paramètres liés
                $flatValues = array_merge(...array_map('array_values', $insertData));
                $stmt->execute($flatValues);
            }
        } catch (PDOException $e) {
            echo "Erreur lors de la migration des données : " . $e->getMessage();
        } finally {
            $db->exec("SET foreign_key_checks = 1"); // Réactiver les vérifications de clé étrangère
        }
    }    
    
    private function isForeignKeyColumn($columnName) {
        // Définir une méthode pour vérifier si la colonne est une référence de clé étrangère
        // Cela pourrait être fait en vérifiant le schéma ou des modèles de nommage de clés étrangères connus
    
        // Exemple de modèle de nommage pour les clés étrangères
        return strpos($columnName, '_id') !== false || strpos($columnName, 'id_') !== false;
    }
    
    private function getTableColumns($db, $dbName, $tableName) {
        // Requête pour obtenir les noms de colonnes de la table spécifiée
        $query = "SELECT COLUMN_NAME 
                  FROM information_schema.COLUMNS 
                  WHERE TABLE_SCHEMA = '$dbName' 
                  AND TABLE_NAME = '$tableName'";
        
        return $db->query($query)->fetchAll(PDO::FETCH_COLUMN);
    }

    // Méthode de déconnexion
    public function disconnect() {
        $this->connection = null;
        echo "Déconnexion réussie.\n";
    }
}

<?php
class DatabaseSetup {
    private $connection;
    private $sourceDBs;
    private $targetDBs;

    public function __construct() {
        // Charger la configuration depuis le fichier config
        $config = require __DIR__ . '/../config/config.php';

        $this->targetDBs = ['new_analysis'];

        try {
            // Initialiser la connexion à MySQL sans spécifier la base de données
            $this->connection = new PDO(
                "mysql:host={$config['db_host']}",
                $config['db_user'],
                $config['db_pass']
            );
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Vérifier si la base de données existe, sinon la créer
            foreach ($this->targetDBs as $dbName) {
                $this->connection->exec("CREATE DATABASE IF NOT EXISTS `$dbName`");
            }

            // Se connecter à la base de données
            // $this->connection->exec("USE `$dbName`");
            echo "Connexion réussie à la base de données.";
        } catch (PDOException $e) {
            die("Erreur de connexion : " . $e->getMessage());
        }
    }

    public function getDBNamesOfOldSystem () {
        $this->sourceDBs = $this->connection->query("SHOW DATABASES LIKE 'analysis_%'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($this->sourceDBs as $dbName) {
            echo $dbName . PHP_EOL; // Outputs each database name on a new line
        }
        return $this->sourceDBs;  
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
        // Exemple de requête pour créer une table de test
        // Ton développeur pourra ajouter les requêtes nécessaires ici
        $this->getDBNamesOfOldSystem();

    }

    // Méthode de déconnexion
    public function disconnect() {
        $this->connection = null;
        echo "Déconnexion réussie.";
    }
}


<?php
class DatabaseSetup {
    private $connection;

    public function __construct() {
        // Charger la configuration depuis le fichier config
        $config = require __DIR__ . '/../config/config.php';

        try {
            // Initialiser la connexion à MySQL sans spécifier la base de données
            $this->connection = new PDO(
                "mysql:host={$config['db_host']}",
                $config['db_user'],
                $config['db_pass']
            );
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Vérifier si la base de données existe, sinon la créer
            $dbName = $config['db_name'];
            $this->connection->exec("CREATE DATABASE IF NOT EXISTS `$dbName`");

            // Se connecter à la base de données
            $this->connection->exec("USE `$dbName`");
            echo "Connexion réussie à la base de données.";
        } catch (PDOException $e) {
            die("Erreur de connexion : " . $e->getMessage());
        }
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
        $sql = "CREATE TABLE IF NOT EXISTS example_table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE
        )";
        $this->executeQuery($sql);
    }

    // Méthode de déconnexion
    public function disconnect() {
        $this->connection = null;
        echo "Déconnexion réussie.";
    }
}


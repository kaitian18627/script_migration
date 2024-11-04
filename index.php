<?php
require_once 'config/config.php';
require_once 'functions/db.php';
require_once 'functions/databasesetup.php';
require_once 'functions/logger.php';

// Connexion à la base de données
$db = new Database();
try {
	// $connection = $db->connect();
	$dbSetup = new DatabaseSetup();
	$dbSetup->createDatabaseNewSystem();
	echo "Système de nouvelle base de données créé\n";
	$dbSetup->deleteRowFromSteppersOfUsers();
	echo "Lignes supprimées de la table steppers de la base de données utilisateurs\n";
	echo "Veuillez tester et supprimer les anciennes bases de données\n";
	// Déconnexion
	$dbSetup->disconnect();
    // Exécutez vos requêtes ici
	echo "connexion réussie\n";
} catch (Exception $e) {
    Logger::log($e->getMessage());
}
?>

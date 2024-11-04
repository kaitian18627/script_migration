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
	$dbSetup->deleteRowFromSteppersOfUsers();
	// Déconnexion
	$dbSetup->disconnect();
    // Exécutez vos requêtes ici
echo "connexion success";
} catch (Exception $e) {
    Logger::log($e->getMessage());
}
?>


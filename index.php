<?php
require_once 'config/config.php';
require_once 'functions/db.php';
require_once 'functions/databasesetup.php';
require_once 'functions/logger.php';
require_once 'functions/test.php';

$db = new Database();
try {
	$dbSetup = new DatabaseSetup();
	$dbSetup->createDatabaseNewSystem();
	$dbSetup->deleteRowFromSteppersOfUsers();
	$dbSetup->disconnect();

	$test = new DatabaseTest();
	$test->checkTablesFromOldDBsInNewDB();
	$test->checkForeignKeys();
	$test->disconnect();
} catch (Exception $e) {
    Logger::log($e->getMessage());
}
?>

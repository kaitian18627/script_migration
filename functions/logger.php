<?php
class Logger {
    public static function log($message) {
        // Define the log directory and file path
        $logDir = __DIR__ . '/../log/';
        $logFile = $logDir . 'errors.log';
    
        // Check if the log directory exists, if not, create it
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
    
        // Create the formatted message with a timestamp
        $timeStamp = date('Y-m-d H:i:s');
        $formattedMessage = "[{$timeStamp}] - {$message}\n";
    
        // Write the message to the log file
        file_put_contents($logFile, $formattedMessage, FILE_APPEND);
    }    
}
?>

